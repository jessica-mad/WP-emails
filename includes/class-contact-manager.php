<?php
namespace WEA;
defined('ABSPATH') || exit;

class ContactManager {

    // Upsert: si el email ya existe devuelve el id existente
    public static function upsert(array $data): int|false {
        global $wpdb;
        $table = $wpdb->prefix . 'wea_contacts';
        $email = strtolower(sanitize_email($data['email'] ?? ''));
        if (!is_email($email)) return false;

        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE email = %s", $email));
        if ($existing) {
            // Update editable fields if provided
            $update = [];
            if (!empty($data['first_name'])) $update['first_name'] = sanitize_text_field($data['first_name']);
            if (!empty($data['last_name']))  $update['last_name']  = sanitize_text_field($data['last_name']);
            if (!empty($data['wp_user_id'])) $update['wp_user_id'] = (int)$data['wp_user_id'];
            if (!empty($data['status']))     $update['status']     = sanitize_key($data['status']);
            if ($update) $wpdb->update($table, $update, ['id' => (int)$existing]);
            return (int)$existing;
        }

        $wpdb->insert($table, [
            'email'      => $email,
            'first_name' => sanitize_text_field($data['first_name'] ?? ''),
            'last_name'  => sanitize_text_field($data['last_name']  ?? ''),
            'status'     => sanitize_key($data['status'] ?? 'subscribed'),
            'source'     => sanitize_key($data['source'] ?? 'manual'),
            'wp_user_id' => !empty($data['wp_user_id']) ? (int)$data['wp_user_id'] : null,
            'ip_address' => sanitize_text_field($data['ip_address'] ?? ''),
        ]);
        return $wpdb->insert_id ?: false;
    }

    public static function get(int $id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wea_contacts WHERE id = %d", $id
        ), ARRAY_A);
        return $row ?: null;
    }

    public static function get_by_email(string $email): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wea_contacts WHERE email = %s", strtolower($email)
        ), ARRAY_A);
        return $row ?: null;
    }

    public static function delete(int $id): bool {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'wea_contact_tags', ['contact_id' => $id]);
        return (bool)$wpdb->delete($wpdb->prefix . 'wea_contacts', ['id' => $id]);
    }

    public static function update_status(int $id, string $status): void {
        global $wpdb;
        $update = ['status' => sanitize_key($status)];
        if ($status === 'unsubscribed') $update['unsubscribed_at'] = current_time('mysql');
        $wpdb->update($wpdb->prefix . 'wea_contacts', $update, ['id' => $id]);
    }

    // Listado paginado con filtros
    public static function get_all(array $args = []): array {
        global $wpdb;
        $table = $wpdb->prefix . 'wea_contacts';
        $per_page = max(1, (int)($args['per_page'] ?? 25));
        $page     = max(1, (int)($args['page'] ?? 1));
        $offset   = ($page - 1) * $per_page;
        $status   = $args['status'] ?? '';
        $tag_id   = (int)($args['tag_id'] ?? 0);
        $search   = $args['search'] ?? '';

        $where = ['1=1'];
        $params = [];

        if ($status) { $where[] = 'c.status = %s'; $params[] = $status; }
        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(c.email LIKE %s OR c.first_name LIKE %s OR c.last_name LIKE %s)';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }

        $join = '';
        if ($tag_id) {
            $join = "INNER JOIN {$wpdb->prefix}wea_contact_tags ct ON ct.contact_id = c.id AND ct.tag_id = " . (int)$tag_id;
        }

        $where_sql = implode(' AND ', $where);
        $base_sql  = "FROM $table c $join WHERE $where_sql";
        $count_sql = "SELECT COUNT(DISTINCT c.id) $base_sql";
        $data_sql  = "SELECT DISTINCT c.* $base_sql ORDER BY c.created_at DESC LIMIT %d OFFSET %d";

        $params_count = $params;
        $params_data  = array_merge($params, [$per_page, $offset]);

        $total = $params_count
            ? (int)$wpdb->get_var($wpdb->prepare($count_sql, ...$params_count))
            : (int)$wpdb->get_var($count_sql);

        $rows = $params_data
            ? $wpdb->get_results($wpdb->prepare($data_sql, ...$params_data), ARRAY_A)
            : $wpdb->get_results($data_sql, ARRAY_A);

        return ['items' => $rows ?: [], 'total' => $total, 'pages' => max(1, ceil($total / $per_page))];
    }

    // Tags de un contacto
    public static function get_tags(int $contact_id): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT t.* FROM {$wpdb->prefix}wea_tags t
             INNER JOIN {$wpdb->prefix}wea_contact_tags ct ON ct.tag_id = t.id
             WHERE ct.contact_id = %d ORDER BY t.name", $contact_id
        ), ARRAY_A) ?: [];
    }

    public static function add_tag(int $contact_id, int $tag_id): void {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->prefix}wea_contact_tags (contact_id, tag_id) VALUES (%d, %d)",
            $contact_id, $tag_id
        ));
    }

    public static function remove_tag(int $contact_id, int $tag_id): void {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'wea_contact_tags', ['contact_id' => $contact_id, 'tag_id' => $tag_id]);
    }

    public static function set_tags(int $contact_id, array $tag_ids): void {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'wea_contact_tags', ['contact_id' => $contact_id]);
        foreach (array_unique(array_map('intval', $tag_ids)) as $tid) {
            if ($tid > 0) self::add_tag($contact_id, $tid);
        }
    }

    // Sincronizar todos los usuarios WP como contactos
    public static function sync_wp_users(): int {
        $users = get_users(['fields' => ['ID', 'user_email', 'display_name']]);
        $count = 0;
        foreach ($users as $user) {
            $name  = explode(' ', $user->display_name, 2);
            $id = self::upsert([
                'email'      => $user->user_email,
                'first_name' => $name[0] ?? '',
                'last_name'  => $name[1] ?? '',
                'wp_user_id' => $user->ID,
                'source'     => 'wp_users',
            ]);
            if ($id) $count++;
        }
        return $count;
    }

    // CSV import (string contenido del CSV)
    public static function import_csv(string $csv_content): array {
        $lines   = preg_split('/\r\n|\r|\n/', trim($csv_content));
        $header  = array_map('trim', str_getcsv(array_shift($lines)));
        $header  = array_map('strtolower', $header);
        $results = ['imported' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($lines as $i => $line) {
            if (!trim($line)) continue;
            $row = array_combine($header, array_map('trim', str_getcsv($line)));
            if ($row === false) { $results['errors'][] = 'Línea ' . ($i + 2) . ': columnas inválidas'; continue; }

            $email = $row['email'] ?? $row['correo'] ?? $row['e-mail'] ?? '';
            if (!is_email($email)) { $results['skipped']++; continue; }

            $id = self::upsert([
                'email'      => $email,
                'first_name' => $row['first_name'] ?? $row['nombre'] ?? $row['name'] ?? '',
                'last_name'  => $row['last_name']  ?? $row['apellido'] ?? $row['apellidos'] ?? '',
                'source'     => 'csv_import',
            ]);
            $id ? $results['imported']++ : $results['skipped']++;
        }
        return $results;
    }
}
