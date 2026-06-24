<?php
namespace WEA;
defined('ABSPATH') || exit;

class TagManager {

    public static function get_all(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT t.*, COUNT(ct.contact_id) as contact_count
             FROM {$wpdb->prefix}wea_tags t
             LEFT JOIN {$wpdb->prefix}wea_contact_tags ct ON ct.tag_id = t.id
             GROUP BY t.id ORDER BY t.name", ARRAY_A
        ) ?: [];
    }

    public static function get(int $id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wea_tags WHERE id = %d", $id
        ), ARRAY_A);
        return $row ?: null;
    }

    public static function save(array $data): int|false {
        global $wpdb;
        $name  = sanitize_text_field($data['name'] ?? '');
        $slug  = sanitize_title($name);
        $color = sanitize_hex_color($data['color'] ?? '#6366f1') ?: '#6366f1';
        if (!$name) return false;

        if (!empty($data['id'])) {
            $wpdb->update($wpdb->prefix . 'wea_tags', compact('name', 'slug', 'color'), ['id' => (int)$data['id']]);
            return (int)$data['id'];
        }
        $wpdb->insert($wpdb->prefix . 'wea_tags', compact('name', 'slug', 'color'));
        return $wpdb->insert_id ?: false;
    }

    public static function delete(int $id): bool {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'wea_contact_tags', ['tag_id' => $id]);
        return (bool)$wpdb->delete($wpdb->prefix . 'wea_tags', ['id' => $id]);
    }
}
