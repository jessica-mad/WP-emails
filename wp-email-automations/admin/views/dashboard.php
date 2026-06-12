<?php
defined( 'ABSPATH' ) || exit;

global $wpdb;

$total_automations = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wea_automations" );
$active_automations = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wea_automations WHERE status='active'" );
$total_templates   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wea_templates" );
$emails_sent_today = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wea_logs WHERE status='sent' AND DATE(created_at) = %s", current_time('Y-m-d') ) );
$emails_sent_month = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wea_logs WHERE status='sent' AND YEAR(created_at)=%d AND MONTH(created_at)=%d", current_time('Y'), current_time('n') ) );
$api_key           = get_option( 'wea_api_key', '–' );
$endpoint          = rest_url( 'wea/v1/event' );
?>
<div class="wrap wea-wrap">
    <h1><?php esc_html_e( 'Email Automations — Dashboard', 'wp-email-automations' ); ?></h1>

    <div class="wea-stats-grid">
        <div class="wea-stat-card">
            <span class="wea-stat-number"><?php echo esc_html( $active_automations ); ?> / <?php echo esc_html( $total_automations ); ?></span>
            <span class="wea-stat-label"><?php esc_html_e( 'Active Automations', 'wp-email-automations' ); ?></span>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wea-automations' ) ); ?>"><?php esc_html_e( 'Manage', 'wp-email-automations' ); ?></a>
        </div>
        <div class="wea-stat-card">
            <span class="wea-stat-number"><?php echo esc_html( $total_templates ); ?></span>
            <span class="wea-stat-label"><?php esc_html_e( 'Email Templates', 'wp-email-automations' ); ?></span>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wea-templates' ) ); ?>"><?php esc_html_e( 'Manage', 'wp-email-automations' ); ?></a>
        </div>
        <div class="wea-stat-card">
            <span class="wea-stat-number"><?php echo esc_html( $emails_sent_today ); ?></span>
            <span class="wea-stat-label"><?php esc_html_e( 'Emails Sent Today', 'wp-email-automations' ); ?></span>
        </div>
        <div class="wea-stat-card">
            <span class="wea-stat-number"><?php echo esc_html( $emails_sent_month ); ?></span>
            <span class="wea-stat-label"><?php esc_html_e( 'Emails This Month', 'wp-email-automations' ); ?></span>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wea-logs' ) ); ?>"><?php esc_html_e( 'View Logs', 'wp-email-automations' ); ?></a>
        </div>
    </div>

    <div class="wea-panel">
        <h2><?php esc_html_e( 'Event API', 'wp-email-automations' ); ?></h2>
        <p><?php esc_html_e( 'Send events from your external repositories to this endpoint:', 'wp-email-automations' ); ?></p>
        <table class="widefat" style="max-width:700px">
            <tr>
                <th><?php esc_html_e( 'Endpoint', 'wp-email-automations' ); ?></th>
                <td><code><?php echo esc_html( $endpoint ); ?></code></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'API Key header', 'wp-email-automations' ); ?></th>
                <td><code>X-WEA-Key: <?php echo esc_html( $api_key ); ?></code></td>
            </tr>
        </table>
        <h3><?php esc_html_e( 'Example cURL', 'wp-email-automations' ); ?></h3>
        <pre class="wea-code">curl -X POST "<?php echo esc_url( $endpoint ); ?>" \
  -H "Content-Type: application/json" \
  -H "X-WEA-Key: <?php echo esc_html( $api_key ); ?>" \
  -d '{
    "trigger": "order.completed",
    "data": {
      "email": "user@example.com",
      "name": "Jane Doe",
      "order_id": 42
    }
  }'</pre>

        <h3><?php esc_html_e( 'PHP SDK (for your other repository)', 'wp-email-automations' ); ?></h3>
        <pre class="wea-code">$response = wp_remote_post( '<?php echo esc_url( $endpoint ); ?>', [
    'headers' => [
        'Content-Type' => 'application/json',
        'X-WEA-Key'    => '<?php echo esc_html( $api_key ); ?>',
    ],
    'body' => wp_json_encode( [
        'trigger' => 'order.completed',
        'data'    => [ 'email' => $user_email, 'name' => $user_name ],
    ] ),
] );</pre>
    </div>
</div>
