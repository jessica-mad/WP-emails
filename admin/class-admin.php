<?php
namespace WEA;

defined( 'ABSPATH' ) || exit;

class Admin {

    public static function init(): void {
        add_action( 'admin_menu',            [ __CLASS__, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'admin_post_wea_save_template',   [ __CLASS__, 'save_template' ] );
        add_action( 'admin_post_wea_delete_template', [ __CLASS__, 'delete_template' ] );
        add_action( 'admin_post_wea_save_automation', [ __CLASS__, 'save_automation' ] );
        add_action( 'admin_post_wea_delete_automation', [ __CLASS__, 'delete_automation' ] );
        add_action( 'admin_post_wea_toggle_automation', [ __CLASS__, 'toggle_automation' ] );

        // AJAX: save template from builder
        add_action( 'wp_ajax_wea_save_template',     [ __CLASS__, 'ajax_save_template' ] );
        add_action( 'wp_ajax_wea_load_template',     [ __CLASS__, 'ajax_load_template' ] );
        add_action( 'wp_ajax_wea_test_email',        [ __CLASS__, 'ajax_test_email' ] );
        add_action( 'wp_ajax_wea_save_automation',   [ __CLASS__, 'ajax_save_automation' ] );
        add_action( 'wp_ajax_wea_load_automation',   [ __CLASS__, 'ajax_load_automation' ] );

        // Contacts AJAX
        add_action( 'wp_ajax_wea_save_contact',   [ __CLASS__, 'ajax_save_contact' ] );
        add_action( 'wp_ajax_wea_delete_contact', [ __CLASS__, 'ajax_delete_contact' ] );
        add_action( 'wp_ajax_wea_contact_tag',    [ __CLASS__, 'ajax_contact_tag' ] );
        add_action( 'wp_ajax_wea_save_tag',       [ __CLASS__, 'ajax_save_tag' ] );
        add_action( 'wp_ajax_wea_delete_tag',     [ __CLASS__, 'ajax_delete_tag' ] );
        add_action( 'wp_ajax_wea_sync_wp_users',  [ __CLASS__, 'ajax_sync_wp_users' ] );
        add_action( 'wp_ajax_wea_import_csv',     [ __CLASS__, 'ajax_import_csv' ] );
        add_action( 'wp_ajax_wea_bulk_tag',       [ __CLASS__, 'ajax_bulk_tag' ] );

        // Campaigns AJAX
        add_action( 'wp_ajax_wea_save_campaign',       [ __CLASS__, 'ajax_save_campaign' ] );
        add_action( 'wp_ajax_wea_delete_campaign',     [ __CLASS__, 'ajax_delete_campaign' ] );
        add_action( 'wp_ajax_wea_send_campaign',       [ __CLASS__, 'ajax_send_campaign' ] );
        add_action( 'wp_ajax_wea_schedule_campaign',   [ __CLASS__, 'ajax_schedule_campaign' ] );
        add_action( 'wp_ajax_wea_cancel_campaign',     [ __CLASS__, 'ajax_cancel_campaign' ] );
        add_action( 'wp_ajax_wea_campaign_stats',      [ __CLASS__, 'ajax_campaign_stats' ] );
        add_action( 'wp_ajax_wea_preview_recipients',  [ __CLASS__, 'ajax_preview_recipients' ] );

        // Cron hook for processing campaigns
        add_action( 'wea_process_campaign', [ 'WEA\\CampaignManager', 'process' ] );
    }

    // -------------------------------------------------------------------------
    // Menus
    // -------------------------------------------------------------------------

    public static function register_menus(): void {
        add_menu_page(
            __( 'Email Automations', 'wp-email-automations' ),
            __( 'Email Auto',        'wp-email-automations' ),
            'manage_options',
            'wea-dashboard',
            [ __CLASS__, 'page_dashboard' ],
            'dashicons-email-alt2',
            56
        );

        add_submenu_page( 'wea-dashboard', __( 'Dashboard',    'wp-email-automations' ), __( 'Dashboard',    'wp-email-automations' ), 'manage_options', 'wea-dashboard',   [ __CLASS__, 'page_dashboard' ] );
        add_submenu_page( 'wea-dashboard', __( 'Automations',  'wp-email-automations' ), __( 'Automations',  'wp-email-automations' ), 'manage_options', 'wea-automations', [ __CLASS__, 'page_automations' ] );
        add_submenu_page( 'wea-dashboard', __( 'Templates',    'wp-email-automations' ), __( 'Templates',    'wp-email-automations' ), 'manage_options', 'wea-templates',   [ __CLASS__, 'page_templates' ] );
        add_submenu_page( 'wea-dashboard', __( 'Campaigns',    'wp-email-automations' ), __( 'Campaigns',    'wp-email-automations' ), 'manage_options', 'wea-campaigns',   [ __CLASS__, 'page_campaigns' ] );
        add_submenu_page( 'wea-dashboard', __( 'Contacts',     'wp-email-automations' ), __( 'Contacts',     'wp-email-automations' ), 'manage_options', 'wea-contacts',    [ __CLASS__, 'page_contacts' ] );
        add_submenu_page( 'wea-dashboard', __( 'Logs',         'wp-email-automations' ), __( 'Logs',         'wp-email-automations' ), 'manage_options', 'wea-logs',        [ __CLASS__, 'page_logs' ] );
        add_submenu_page( 'wea-dashboard', __( 'Settings',     'wp-email-automations' ), __( 'Settings',     'wp-email-automations' ), 'manage_options', 'wea-settings',    [ __CLASS__, 'page_settings' ] );
    }

    // -------------------------------------------------------------------------
    // Assets
    // -------------------------------------------------------------------------

    public static function enqueue_assets( string $hook ): void {
        if ( strpos( $hook, 'wea-' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'wea-admin',
            WEA_PLUGIN_URL . 'admin/assets/css/admin.css',
            [],
            WEA_VERSION
        );

        wp_enqueue_script(
            'wea-admin',
            WEA_PLUGIN_URL . 'admin/assets/js/admin.js',
            [ 'jquery' ],
            WEA_VERSION,
            true
        );

        wp_localize_script( 'wea-admin', 'weaAdmin', [
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'wea_admin' ),
            'pluginUrl'  => WEA_PLUGIN_URL,
            'operators'  => ConditionEvaluator::operators(),
            'triggers'   => Automation::available_triggers(),
            'i18n'       => [
                'confirmDelete' => __( 'Are you sure you want to delete this?', 'wp-email-automations' ),
                'saved'         => __( 'Saved!', 'wp-email-automations' ),
                'error'         => __( 'An error occurred.', 'wp-email-automations' ),
            ],
        ] );

        // Block editor — only on template editor page
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'wea-templates' && isset( $_GET['action'] ) && in_array( $_GET['action'], [ 'edit', 'new' ], true ) ) {
            wp_enqueue_media();
            wp_enqueue_style(  'wea-builder', WEA_PLUGIN_URL . 'admin/assets/css/builder.css', [], WEA_VERSION );
            wp_enqueue_script( 'wea-builder', WEA_PLUGIN_URL . 'admin/assets/js/builder.js',  [], WEA_VERSION, true );
        }
    }

    // -------------------------------------------------------------------------
    // Pages
    // -------------------------------------------------------------------------

    public static function page_campaigns(): void  { require WEA_PLUGIN_DIR . 'admin/views/campaigns.php'; }
    public static function page_contacts(): void   { require WEA_PLUGIN_DIR . 'admin/views/contacts.php'; }
    public static function page_dashboard(): void  { require WEA_PLUGIN_DIR . 'admin/views/dashboard.php'; }
    public static function page_automations(): void { require WEA_PLUGIN_DIR . 'admin/views/automations.php'; }
    public static function page_templates(): void  { require WEA_PLUGIN_DIR . 'admin/views/templates.php'; }
    public static function page_logs(): void       { require WEA_PLUGIN_DIR . 'admin/views/logs.php'; }
    public static function page_settings(): void   { require WEA_PLUGIN_DIR . 'admin/views/settings.php'; }

    // -------------------------------------------------------------------------
    // Form handlers
    // -------------------------------------------------------------------------

    public static function save_template(): void {
        check_admin_referer( 'wea_save_template' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );

        $id = TemplateManager::save( [
            'id'           => absint( $_POST['id']           ?? 0 ),
            'name'         => sanitize_text_field( $_POST['name']    ?? '' ),
            'subject'      => sanitize_text_field( $_POST['subject'] ?? '' ),
            'mjml_content' => stripslashes( $_POST['mjml_content']   ?? '' ),
            'html'         => stripslashes( $_POST['html']           ?? '' ),
        ] );

        wp_safe_redirect( add_query_arg( [ 'page' => 'wea-templates', 'saved' => 1, 'id' => $id ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function delete_template(): void {
        check_admin_referer( 'wea_delete_template_' . absint( $_POST['id'] ?? 0 ) );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        TemplateManager::delete( absint( $_POST['id'] ) );
        wp_safe_redirect( add_query_arg( [ 'page' => 'wea-templates', 'deleted' => 1 ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function save_automation(): void {
        check_admin_referer( 'wea_save_automation' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );

        $conditions = json_decode( stripslashes( $_POST['conditions_json'] ?? '[]' ), true ) ?? [];
        $actions    = json_decode( stripslashes( $_POST['actions_json']    ?? '[]' ), true ) ?? [];

        $id = Automation::save( [
            'id'          => absint( $_POST['id'] ?? 0 ),
            'name'        => sanitize_text_field( $_POST['name']        ?? '' ),
            'trigger_key' => sanitize_key( $_POST['trigger_key']        ?? '' ),
            'status'      => sanitize_key( $_POST['status']             ?? 'inactive' ),
            'conditions'  => $conditions,
            'actions'     => $actions,
        ] );

        wp_safe_redirect( add_query_arg( [ 'page' => 'wea-automations', 'saved' => 1, 'id' => $id ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function delete_automation(): void {
        $id = absint( $_POST['id'] ?? 0 );
        check_admin_referer( 'wea_delete_automation_' . $id );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        Automation::delete( $id );
        wp_safe_redirect( add_query_arg( [ 'page' => 'wea-automations', 'deleted' => 1 ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function toggle_automation(): void {
        $id = absint( $_POST['id'] ?? 0 );
        check_admin_referer( 'wea_toggle_automation_' . $id );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        Automation::toggle_status( $id );
        wp_safe_redirect( add_query_arg( [ 'page' => 'wea-automations' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // AJAX
    // -------------------------------------------------------------------------

    public static function ajax_save_template(): void {
        check_ajax_referer( 'wea_admin' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        $id = TemplateManager::save( [
            'id'           => absint( $_POST['id']           ?? 0 ),
            'name'         => sanitize_text_field( $_POST['name']    ?? '' ),
            'subject'      => sanitize_text_field( $_POST['subject'] ?? '' ),
            'mjml_content' => stripslashes( $_POST['mjml_content']   ?? '' ),
            'html'         => stripslashes( $_POST['html']           ?? '' ),
        ] );

        wp_send_json_success( [ 'id' => $id ] );
    }

    public static function ajax_load_template(): void {
        check_ajax_referer( 'wea_admin' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        $template = TemplateManager::get( absint( $_POST['id'] ?? 0 ) );
        if ( ! $template ) {
            wp_send_json_error( 'Not found', 404 );
        }
        wp_send_json_success( $template );
    }

    public static function ajax_test_email(): void {
        check_ajax_referer( 'wea_admin' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        $to      = sanitize_email( $_POST['to']          ?? get_option( 'admin_email' ) );
        $content = stripslashes( $_POST['html']           ?? '' );
        $subj    = sanitize_text_field( $_POST['subject'] ?? __( 'Test Email', 'wp-email-automations' ) );

        // Compile block JSON or MJML → HTML server-side
        $html = BlockRenderer::to_html( $content ) ?: $content;

        $sent = EmailSender::send( $to, $subj, $html );
        $sent ? wp_send_json_success( [ 'to' => $to ] ) : wp_send_json_error( 'wp_mail failed' );
    }

    public static function ajax_save_automation(): void {
        check_ajax_referer( 'wea_admin' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        $conditions = json_decode( stripslashes( $_POST['conditions_json'] ?? '[]' ), true ) ?? [];
        $actions    = json_decode( stripslashes( $_POST['actions_json']    ?? '[]' ), true ) ?? [];

        $id = Automation::save( [
            'id'          => absint( $_POST['id']          ?? 0 ),
            'name'        => sanitize_text_field( $_POST['name']        ?? '' ),
            'trigger_key' => sanitize_key( $_POST['trigger_key']        ?? '' ),
            'status'      => sanitize_key( $_POST['status']             ?? 'inactive' ),
            'conditions'  => $conditions,
            'actions'     => $actions,
        ] );

        wp_send_json_success( [ 'id' => $id ] );
    }

    public static function ajax_load_automation(): void {
        check_ajax_referer( 'wea_admin' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        $auto = Automation::get( absint( $_POST['id'] ?? 0 ) );
        if ( ! $auto ) {
            wp_send_json_error( 'Not found', 404 );
        }
        $auto['conditions'] = json_decode( $auto['conditions'], true );
        $auto['actions']    = json_decode( $auto['actions'],    true );
        wp_send_json_success( $auto );
    }

    // -------------------------------------------------------------------------
    // Contacts AJAX
    // -------------------------------------------------------------------------

    public static function ajax_save_contact(): void {
        check_ajax_referer('wea_admin');
        if (!current_user_can('manage_options')) wp_send_json_error('Forbidden', 403);
        $id = ContactManager::upsert([
            'email'      => sanitize_email($_POST['email'] ?? ''),
            'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
            'last_name'  => sanitize_text_field($_POST['last_name'] ?? ''),
            'status'     => sanitize_key($_POST['status'] ?? 'subscribed'),
            'source'     => 'manual',
        ]);
        $id ? wp_send_json_success(['id' => $id]) : wp_send_json_error('Invalid data');
    }

    public static function ajax_delete_contact(): void {
        check_ajax_referer('wea_admin');
        if (!current_user_can('manage_options')) wp_send_json_error('Forbidden', 403);
        ContactManager::delete(absint($_POST['id'] ?? 0))
            ? wp_send_json_success() : wp_send_json_error('Not found');
    }

    public static function ajax_contact_tag(): void {
        check_ajax_referer('wea_admin');
        if (!current_user_can('manage_options')) wp_send_json_error('Forbidden', 403);
        $contact_id = absint($_POST['contact_id'] ?? 0);
        $tag_ids    = array_map('absint', (array)($_POST['tag_ids'] ?? []));
        ContactManager::set_tags($contact_id, $tag_ids);
        wp_send_json_success();
    }

    public static function ajax_save_tag(): void {
        check_ajax_referer('wea_admin');
        if (!current_user_can('manage_options')) wp_send_json_error('Forbidden', 403);
        $id = TagManager::save([
            'id'    => absint($_POST['id'] ?? 0),
            'name'  => sanitize_text_field($_POST['name'] ?? ''),
            'color' => sanitize_hex_color($_POST['color'] ?? '#6366f1'),
        ]);
        $id ? wp_send_json_success(['id' => $id]) : wp_send_json_error('Invalid data');
    }

    public static function ajax_delete_tag(): void {
        check_ajax_referer('wea_admin');
        if (!current_user_can('manage_options')) wp_send_json_error('Forbidden', 403);
        TagManager::delete(absint($_POST['id'] ?? 0))
            ? wp_send_json_success() : wp_send_json_error('Not found');
    }

    public static function ajax_sync_wp_users(): void {
        check_ajax_referer('wea_admin');
        if (!current_user_can('manage_options')) wp_send_json_error('Forbidden', 403);
        $count = ContactManager::sync_wp_users();
        wp_send_json_success(['count' => $count]);
    }

    public static function ajax_import_csv(): void {
        check_ajax_referer('wea_admin');
        if (!current_user_can('manage_options')) wp_send_json_error('Forbidden', 403);
        if (empty($_FILES['csv']['tmp_name'])) wp_send_json_error('No file');
        $content = file_get_contents($_FILES['csv']['tmp_name']);
        if ($content === false) wp_send_json_error('Cannot read file');
        wp_send_json_success(ContactManager::import_csv($content));
    }

    public static function ajax_bulk_tag(): void {
        check_ajax_referer('wea_admin');
        if (!current_user_can('manage_options')) wp_send_json_error('Forbidden', 403);

        $tag_id = (int)($_POST['tag_id'] ?? 0);
        $mode   = sanitize_key($_POST['mode'] ?? 'add');
        $ids    = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));

        if (!$tag_id || empty($ids) || !in_array($mode, ['add', 'remove'], true)) {
            wp_send_json_error(__('Parámetros inválidos.', 'wp-email-automations'));
        }

        $updated = [];
        foreach ($ids as $contact_id) {
            if ($mode === 'add') {
                ContactManager::add_tag($contact_id, $tag_id);
            } else {
                ContactManager::remove_tag($contact_id, $tag_id);
            }
            $tags = ContactManager::get_tags($contact_id);
            $updated[] = [
                'id'   => $contact_id,
                'tags' => array_map(fn($t) => ['name' => $t['name'], 'color' => $t['color']], $tags),
            ];
        }

        $count = count($ids);
        $msg   = $mode === 'add'
            ? sprintf(_n('Tag añadido a %d contacto.', 'Tag añadido a %d contactos.', $count, 'wp-email-automations'), $count)
            : sprintf(_n('Tag quitado de %d contacto.', 'Tag quitado de %d contactos.', $count, 'wp-email-automations'), $count);

        wp_send_json_success(['message' => $msg, 'updated' => $updated]);
    }

    // -------------------------------------------------------------------------
    // Campaigns AJAX
    // -------------------------------------------------------------------------

    public static function ajax_save_campaign(): void {
        check_ajax_referer('wea_admin');
        if (!current_user_can('manage_options')) wp_send_json_error('Forbidden', 403);

        $id = CampaignManager::save([
            'id'            => absint($_POST['id'] ?? 0),
            'name'          => sanitize_text_field($_POST['name'] ?? ''),
            'subject'       => sanitize_text_field($_POST['subject'] ?? ''),
            'from_name'     => sanitize_text_field($_POST['from_name'] ?? ''),
            'from_email'    => sanitize_email($_POST['from_email'] ?? ''),
            'template_id'   => absint($_POST['template_id'] ?? 0) ?: null,
            'body_html'     => wp_kses_post(stripslashes($_POST['body_html'] ?? '')),
            'filter_tags'   => sanitize_text_field($_POST['filter_tags'] ?? ''),
            'filter_status' => sanitize_key($_POST['filter_status'] ?? 'subscribed'),
            'status'        => sanitize_key($_POST['status'] ?? 'draft'),
        ]);

        $id ? wp_send_json_success(['id' => $id]) : wp_send_json_error('Could not save campaign');
    }

    public static function ajax_delete_campaign(): void {
        check_ajax_referer('wea_admin');
        if (!current_user_can('manage_options')) wp_send_json_error('Forbidden', 403);
        CampaignManager::delete(absint($_POST['id'] ?? 0))
            ? wp_send_json_success() : wp_send_json_error('Cannot delete (not a draft or not found)');
    }

    public static function ajax_send_campaign(): void {
        check_ajax_referer('wea_admin');
        if (!current_user_can('manage_options')) wp_send_json_error('Forbidden', 403);
        $id = absint($_POST['id'] ?? 0);
        CampaignManager::dispatch($id)
            ? wp_send_json_success(['message' => 'Campaign dispatched'])
            : wp_send_json_error('Could not dispatch campaign');
    }

    public static function ajax_schedule_campaign(): void {
        check_ajax_referer('wea_admin');
        if (!current_user_can('manage_options')) wp_send_json_error('Forbidden', 403);
        $id       = absint($_POST['id'] ?? 0);
        $datetime = sanitize_text_field($_POST['scheduled_at'] ?? '');
        CampaignManager::schedule($id, $datetime)
            ? wp_send_json_success(['message' => 'Campaign scheduled'])
            : wp_send_json_error('Could not schedule campaign (check datetime is in the future)');
    }

    public static function ajax_cancel_campaign(): void {
        check_ajax_referer('wea_admin');
        if (!current_user_can('manage_options')) wp_send_json_error('Forbidden', 403);
        CampaignManager::cancel(absint($_POST['id'] ?? 0))
            ? wp_send_json_success() : wp_send_json_error('Cannot cancel');
    }

    public static function ajax_campaign_stats(): void {
        check_ajax_referer('wea_admin');
        if (!current_user_can('manage_options')) wp_send_json_error('Forbidden', 403);
        $id    = absint($_POST['id'] ?? 0);
        $stats = CampaignManager::get_send_stats($id);
        wp_send_json_success(['stats' => $stats]);
    }

    public static function ajax_preview_recipients(): void {
        check_ajax_referer('wea_admin');
        if (!current_user_can('manage_options')) wp_send_json_error('Forbidden', 403);

        // Build a temporary campaign-like array to reuse get_recipients logic
        // We need to temporarily save or just replicate the logic inline
        $filter_tags   = sanitize_text_field($_POST['filter_tags'] ?? '');
        $filter_status = sanitize_key($_POST['filter_status'] ?? 'subscribed');

        // Temporarily insert a draft, get recipients, delete it
        global $wpdb;
        $tag_ids = array_filter(array_map('intval', explode(',', $filter_tags)));

        if (!empty($tag_ids)) {
            $placeholders = implode(',', array_fill(0, count($tag_ids), '%d'));
            $params       = array_merge([$filter_status], $tag_ids);
            $contacts = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT c.email FROM {$wpdb->prefix}wea_contacts c
                     INNER JOIN {$wpdb->prefix}wea_contact_tags ct ON ct.contact_id = c.id
                     WHERE c.status = %s AND ct.tag_id IN ($placeholders)",
                    ...$params
                ),
                ARRAY_A
            );
        } else {
            $contacts = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT email FROM {$wpdb->prefix}wea_contacts WHERE status = %s",
                    $filter_status
                ),
                ARRAY_A
            );
        }

        $emails = array_column($contacts ?? [], 'email');
        $sample = array_slice($emails, 0, 5);

        wp_send_json_success(['count' => count($emails), 'sample' => $sample]);
    }
}
