<?php
defined( 'ABSPATH' ) || exit;

if ( isset( $_POST['wea_save_settings'] ) ) {
    check_admin_referer( 'wea_settings' );
    if ( current_user_can( 'manage_options' ) ) {
        if ( ! empty( $_POST['regenerate_key'] ) ) {
            update_option( 'wea_api_key', wp_generate_password( 32, false ) );
        }
        add_settings_error( 'wea_settings', 'saved', __( 'Settings saved.', 'wp-email-automations' ), 'success' );
    }
}

$api_key  = get_option( 'wea_api_key', '' );
$endpoint = rest_url( 'wea/v1/event' );
settings_errors( 'wea_settings' );
?>
<div class="wrap wea-wrap">
    <h1><?php esc_html_e( 'Settings', 'wp-email-automations' ); ?></h1>

    <form method="post">
        <?php wp_nonce_field( 'wea_settings' ); ?>

        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'API Endpoint', 'wp-email-automations' ); ?></th>
                <td>
                    <code><?php echo esc_html( $endpoint ); ?></code>
                    <p class="description"><?php esc_html_e( 'Send POST requests to this URL with your API key to trigger automations.', 'wp-email-automations' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'API Key', 'wp-email-automations' ); ?></th>
                <td>
                    <code id="wea-api-key-display"><?php echo esc_html( $api_key ); ?></code>
                    <p><label>
                        <input type="checkbox" name="regenerate_key" value="1">
                        <?php esc_html_e( 'Regenerate API key on save (existing integrations will need updating)', 'wp-email-automations' ); ?>
                    </label></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'SMTP', 'wp-email-automations' ); ?></th>
                <td>
                    <p><?php esc_html_e( 'This plugin uses your site\'s existing wp_mail() configuration. Install a SMTP plugin (e.g. WP Mail SMTP, FluentSMTP) and it will be used automatically.', 'wp-email-automations' ); ?></p>
                    <?php
                    $test_result = get_transient( 'wea_smtp_test' );
                    if ( $test_result ) {
                        echo '<p class="' . ( $test_result === 'ok' ? 'wea-success' : 'wea-error' ) . '">' . esc_html( $test_result === 'ok' ? __( 'Test email sent successfully!', 'wp-email-automations' ) : $test_result ) . '</p>';
                        delete_transient( 'wea_smtp_test' );
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Queue', 'wp-email-automations' ); ?></th>
                <td>
                    <?php
                    global $wpdb;
                    $pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wea_queue WHERE status='pending'" );
                    $failed  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wea_queue WHERE status='failed'" );
                    ?>
                    <p><?php printf( esc_html__( 'Pending jobs: %d | Failed jobs: %d', 'wp-email-automations' ), $pending, $failed ); ?></p>
                    <p><?php esc_html_e( 'The queue processes every minute via WP-Cron.', 'wp-email-automations' ); ?></p>
                    <?php
                    $next = wp_next_scheduled( 'wea_process_queue' );
                    if ( $next ) {
                        echo '<p>' . esc_html( sprintf( __( 'Next cron run: %s', 'wp-email-automations' ), human_time_diff( $next ) . ' ' . __( 'from now', 'wp-email-automations' ) ) ) . '</p>';
                    }
                    ?>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" name="wea_save_settings" class="button button-primary"><?php esc_html_e( 'Save Settings', 'wp-email-automations' ); ?></button>
        </p>
    </form>

    <hr>
    <h2><?php esc_html_e( 'Database', 'wp-email-automations' ); ?></h2>
    <p>
        <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'page' => 'wea-settings', 'wea_action' => 'reinstall_db' ], admin_url( 'admin.php' ) ), 'wea_reinstall_db' ) ); ?>" class="button button-secondary"
           onclick="return confirm('<?php esc_attr_e( 'This will recreate the database tables. No data is deleted. Continue?', 'wp-email-automations' ); ?>')">
            <?php esc_html_e( 'Reinstall DB Tables', 'wp-email-automations' ); ?>
        </a>
    </p>
</div>

<?php
// Handle reinstall
if ( isset( $_GET['wea_action'] ) && $_GET['wea_action'] === 'reinstall_db' ) {
    check_admin_referer( 'wea_reinstall_db' );
    if ( current_user_can( 'manage_options' ) ) {
        WEA\Database::create_tables();
        wp_safe_redirect( add_query_arg( [ 'page' => 'wea-settings', 'db_updated' => 1 ], admin_url( 'admin.php' ) ) );
        exit;
    }
}
?>
