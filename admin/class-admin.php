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

        // GrapesJS + MJML builder — only on template editor page
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'wea-templates' && isset( $_GET['action'] ) && in_array( $_GET['action'], [ 'edit', 'new' ], true ) ) {
            // Archivos locales — sin depender de CDN externos
            wp_enqueue_style(  'grapesjs',      WEA_PLUGIN_URL . 'assets/vendor/grapes.min.css',      [], WEA_VERSION );
            wp_enqueue_script( 'grapesjs',      WEA_PLUGIN_URL . 'assets/vendor/grapes.min.js',       [], WEA_VERSION, false );
            wp_enqueue_script( 'grapesjs-mjml', WEA_PLUGIN_URL . 'assets/vendor/grapesjs-mjml.min.js',[], WEA_VERSION, false );
            // Biblioteca de medios de WP (para el selector de imágenes)
            wp_enqueue_media();
            wp_enqueue_script(
                'wea-builder',
                WEA_PLUGIN_URL . 'admin/assets/js/builder.js',
                [ 'grapesjs', 'grapesjs-mjml' ],
                WEA_VERSION,
                true
            );
        }
    }

    // -------------------------------------------------------------------------
    // Pages
    // -------------------------------------------------------------------------

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

        $to   = sanitize_email( $_POST['to']          ?? get_option( 'admin_email' ) );
        $mjml = stripslashes( $_POST['html']           ?? '' );
        $subj = sanitize_text_field( $_POST['subject'] ?? __( 'Test Email', 'wp-email-automations' ) );

        // Compile MJML → HTML server-side
        $html = EmailSender::compile_mjml( $mjml ) ?: $mjml;

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
}
