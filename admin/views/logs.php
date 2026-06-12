<?php
defined( 'ABSPATH' ) || exit;

global $wpdb;
$per_page = 50;
$page_num = max( 1, absint( $_GET['paged'] ?? 1 ) );
$offset   = ( $page_num - 1 ) * $per_page;
$status   = sanitize_key( $_GET['status_filter'] ?? '' );

$where = $status ? $wpdb->prepare( "WHERE l.status = %s", $status ) : '';
$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wea_logs l $where" );
$logs  = $wpdb->get_results(
    "SELECT l.*, a.name as automation_name
     FROM {$wpdb->prefix}wea_logs l
     LEFT JOIN {$wpdb->prefix}wea_automations a ON a.id = l.automation_id
     $where
     ORDER BY l.created_at DESC
     LIMIT $per_page OFFSET $offset",
    ARRAY_A
) ?: [];
?>
<div class="wrap wea-wrap">
    <h1><?php esc_html_e( 'Email Logs', 'wp-email-automations' ); ?></h1>

    <form method="get" style="margin-bottom:1em">
        <input type="hidden" name="page" value="wea-logs">
        <select name="status_filter">
            <option value=""><?php esc_html_e( 'All statuses', 'wp-email-automations' ); ?></option>
            <option value="sent"    <?php selected( $status, 'sent' );    ?>><?php esc_html_e( 'Sent',    'wp-email-automations' ); ?></option>
            <option value="failed"  <?php selected( $status, 'failed' );  ?>><?php esc_html_e( 'Failed',  'wp-email-automations' ); ?></option>
            <option value="skipped" <?php selected( $status, 'skipped' ); ?>><?php esc_html_e( 'Skipped', 'wp-email-automations' ); ?></option>
        </select>
        <button type="submit" class="button"><?php esc_html_e( 'Filter', 'wp-email-automations' ); ?></button>
    </form>

    <?php if ( empty( $logs ) ) : ?>
        <p><?php esc_html_e( 'No log entries found.', 'wp-email-automations' ); ?></p>
    <?php else : ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:160px"><?php esc_html_e( 'Date', 'wp-email-automations' ); ?></th>
                <th><?php esc_html_e( 'Automation', 'wp-email-automations' ); ?></th>
                <th><?php esc_html_e( 'To', 'wp-email-automations' ); ?></th>
                <th><?php esc_html_e( 'Subject', 'wp-email-automations' ); ?></th>
                <th><?php esc_html_e( 'Event', 'wp-email-automations' ); ?></th>
                <th style="width:80px"><?php esc_html_e( 'Status', 'wp-email-automations' ); ?></th>
                <th><?php esc_html_e( 'Message', 'wp-email-automations' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $logs as $log ) : ?>
            <tr>
                <td><?php echo esc_html( $log['created_at'] ); ?></td>
                <td><?php echo esc_html( $log['automation_name'] ?? '—' ); ?></td>
                <td><?php echo esc_html( $log['to_email'] ); ?></td>
                <td><?php echo esc_html( $log['subject'] ); ?></td>
                <td><code><?php echo esc_html( $log['event_key'] ); ?></code></td>
                <td><span class="wea-badge wea-badge--<?php echo esc_attr( $log['status'] ); ?>"><?php echo esc_html( ucfirst( $log['status'] ) ); ?></span></td>
                <td><?php echo esc_html( $log['message'] ); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php
    $pages = ceil( $total / $per_page );
    if ( $pages > 1 ) {
        echo '<div class="tablenav"><div class="tablenav-pages">';
        echo paginate_links( [
            'base'    => add_query_arg( 'paged', '%#%' ),
            'format'  => '',
            'current' => $page_num,
            'total'   => $pages,
        ] );
        echo '</div></div>';
    }
    ?>
    <?php endif; ?>
</div>
