<?php
namespace WEA;

defined( 'ABSPATH' ) || exit;

class CampaignManager {

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    public static function save( array $data ): int|false {
        global $wpdb;
        $table = $wpdb->prefix . 'wea_campaigns';

        $fields = [
            'name'          => sanitize_text_field( $data['name']          ?? '' ),
            'subject'       => sanitize_text_field( $data['subject']       ?? '' ),
            'from_name'     => sanitize_text_field( $data['from_name']     ?? '' ),
            'from_email'    => sanitize_email(      $data['from_email']    ?? '' ),
            'template_id'   => isset( $data['template_id'] ) && $data['template_id'] ? (int) $data['template_id'] : null,
            'body_html'     => $data['body_html']     ?? '',
            'filter_tags'   => sanitize_text_field( $data['filter_tags']   ?? '' ),
            'filter_status' => sanitize_key(         $data['filter_status'] ?? 'subscribed' ),
            'status'        => sanitize_key(         $data['status']        ?? 'draft' ),
            'scheduled_at'  => $data['scheduled_at'] ?? null,
        ];

        $formats = [ '%s','%s','%s','%s','%d','%s','%s','%s','%s','%s' ];
        if ( $fields['template_id'] === null ) {
            $formats[4] = null; // handled below
        }

        $id = absint( $data['id'] ?? 0 );

        if ( $id ) {
            $result = $wpdb->update( $table, $fields, [ 'id' => $id ], null, [ '%d' ] );
            return $result !== false ? $id : false;
        } else {
            $result = $wpdb->insert( $table, $fields );
            return $result ? (int) $wpdb->insert_id : false;
        }
    }

    public static function get( int $id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wea_campaigns WHERE id = %d", $id ),
            ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * @return array{items: array, total: int, pages: int}
     */
    public static function get_all( array $args = [] ): array {
        global $wpdb;
        $per_page = max( 1, (int) ( $args['per_page'] ?? 20 ) );
        $page     = max( 1, (int) ( $args['page']     ?? 1  ) );
        $offset   = ( $page - 1 ) * $per_page;
        $status   = sanitize_key( $args['status'] ?? '' );

        $where  = '';
        $params = [];
        if ( $status ) {
            $where    = 'WHERE status = %s';
            $params[] = $status;
        }

        $total = (int) $wpdb->get_var(
            $params
                ? $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wea_campaigns $where", ...$params )
                : "SELECT COUNT(*) FROM {$wpdb->prefix}wea_campaigns"
        );

        $query_params   = array_merge( $params, [ $per_page, $offset ] );
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wea_campaigns $where ORDER BY id DESC LIMIT %d OFFSET %d",
                ...$query_params
            ),
            ARRAY_A
        );

        return [
            'items' => $items ?: [],
            'total' => $total,
            'pages' => (int) ceil( $total / $per_page ),
        ];
    }

    public static function delete( int $id ): bool {
        global $wpdb;
        $campaign = self::get( $id );
        if ( ! $campaign || $campaign['status'] !== 'draft' ) {
            return false;
        }
        return (bool) $wpdb->delete( $wpdb->prefix . 'wea_campaigns', [ 'id' => $id ], [ '%d' ] );
    }

    // -------------------------------------------------------------------------
    // Recipients
    // -------------------------------------------------------------------------

    public static function get_recipients( int $campaign_id ): array {
        global $wpdb;
        $campaign = self::get( $campaign_id );
        if ( ! $campaign ) {
            return [];
        }

        $filter_status = $campaign['filter_status'] ?: 'subscribed';
        $filter_tags   = array_filter( array_map( 'intval', explode( ',', $campaign['filter_tags'] ) ) );

        if ( ! empty( $filter_tags ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $filter_tags ), '%d' ) );
            $params       = array_merge( [ $filter_status ], $filter_tags );
            $rows         = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT c.* FROM {$wpdb->prefix}wea_contacts c
                     INNER JOIN {$wpdb->prefix}wea_contact_tags ct ON ct.contact_id = c.id
                     WHERE c.status = %s AND ct.tag_id IN ($placeholders)",
                    ...$params
                ),
                ARRAY_A
            );
        } else {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}wea_contacts WHERE status = %s",
                    $filter_status
                ),
                ARRAY_A
            );
        }

        return $rows ?: [];
    }

    // -------------------------------------------------------------------------
    // Dispatch / Schedule
    // -------------------------------------------------------------------------

    public static function dispatch( int $campaign_id ): bool {
        global $wpdb;
        $campaign = self::get( $campaign_id );
        if ( ! $campaign ) {
            return false;
        }

        // Prepare recipients + send rows
        $recipients = self::get_recipients( $campaign_id );
        if ( empty( $recipients ) ) {
            return false;
        }

        $wpdb->update(
            $wpdb->prefix . 'wea_campaigns',
            [ 'status' => 'sending', 'total_recipients' => count( $recipients ) ],
            [ 'id' => $campaign_id ],
            [ '%s', '%d' ],
            [ '%d' ]
        );

        self::_create_send_rows( $campaign_id, $recipients );

        wp_schedule_single_event( time(), 'wea_process_campaign', [ $campaign_id ] );

        // Trigger cron via loopback so it runs immediately without waiting for next visit
        spawn_cron();
        return true;
    }

    public static function schedule( int $campaign_id, string $datetime ): bool {
        global $wpdb;
        $campaign = self::get( $campaign_id );
        if ( ! $campaign ) {
            return false;
        }

        $timestamp = strtotime( $datetime );
        if ( ! $timestamp || $timestamp <= time() ) {
            return false;
        }

        $recipients = self::get_recipients( $campaign_id );

        $wpdb->update(
            $wpdb->prefix . 'wea_campaigns',
            [
                'status'           => 'scheduled',
                'scheduled_at'     => gmdate( 'Y-m-d H:i:s', $timestamp ),
                'total_recipients' => count( $recipients ),
            ],
            [ 'id' => $campaign_id ],
            [ '%s', '%s', '%d' ],
            [ '%d' ]
        );

        self::_create_send_rows( $campaign_id, $recipients );

        wp_schedule_single_event( $timestamp, 'wea_process_campaign', [ $campaign_id ] );
        return true;
    }

    private static function _create_send_rows( int $campaign_id, array $recipients ): void {
        global $wpdb;
        foreach ( $recipients as $contact ) {
            $token = self::generate_token( $campaign_id, (int) $contact['id'] );
            $wpdb->replace(
                $wpdb->prefix . 'wea_campaign_sends',
                [
                    'campaign_id' => $campaign_id,
                    'contact_id'  => (int) $contact['id'],
                    'email'       => $contact['email'],
                    'token'       => $token,
                    'status'      => 'pending',
                ],
                [ '%d', '%d', '%s', '%s', '%s' ]
            );
        }
    }

    // -------------------------------------------------------------------------
    // Process (cron)
    // -------------------------------------------------------------------------

    public static function process( int $campaign_id ): void {
        global $wpdb;
        $campaign = self::get( $campaign_id );
        if ( ! $campaign || ! in_array( $campaign['status'], [ 'sending', 'scheduled' ], true ) ) {
            return;
        }

        // Mark as sending
        $wpdb->update(
            $wpdb->prefix . 'wea_campaigns',
            [ 'status' => 'sending' ],
            [ 'id' => $campaign_id ],
            [ '%s' ],
            [ '%d' ]
        );

        $pending = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wea_campaign_sends
                 WHERE campaign_id = %d AND status = 'pending'",
                $campaign_id
            ),
            ARRAY_A
        );

        // Resolve body HTML from template at send time
        $base_html = $campaign['body_html'] ?? '';
        if ( $campaign['template_id'] ) {
            $template = TemplateManager::get( (int) $campaign['template_id'] );
            if ( $template ) {
                $mjml        = $template['mjml_content'] ?? '';
                $stored_html = $template['html'] ?? '';

                if ( ! empty( trim( $stored_html ) ) && ! self::_is_mjml( $stored_html ) && ! self::_is_block_json( $stored_html ) ) {
                    $base_html = $stored_html;
                } elseif ( self::_is_block_json( $mjml ) ) {
                    $base_html = BlockRenderer::to_html( $mjml );
                } elseif ( ! empty( trim( $mjml ) ) ) {
                    $base_html = EmailSender::compile_mjml( $mjml ) ?: $stored_html;
                }

                if ( $base_html ) {
                    $wpdb->update(
                        $wpdb->prefix . 'wea_campaigns',
                        [ 'body_html' => $base_html ],
                        [ 'id' => $campaign_id ],
                        [ '%s' ],
                        [ '%d' ]
                    );
                }
            }
        }
        $campaign['body_html'] = $base_html;

        if ( empty( trim( $base_html ) ) ) {
            // Nothing to send — log and abort
            $wpdb->insert( $wpdb->prefix . 'wea_logs', [
                'automation_id' => null,
                'queue_id'      => null,
                'event_key'     => 'campaign',
                'to_email'      => '',
                'subject'       => $campaign['subject'] ?? '',
                'status'        => 'failed',
                'message'       => "Campaign {$campaign_id}: template HTML is empty — check the template has content.",
            ] );
            $wpdb->update( $wpdb->prefix . 'wea_campaigns', [ 'status' => 'draft' ], [ 'id' => $campaign_id ], [ '%s' ], [ '%d' ] );
            return;
        }

        $from_name  = $campaign['from_name']  ?: get_option( 'blogname' );
        $from_email = $campaign['from_email'] ?: get_option( 'admin_email' );
        $headers    = [
            'Content-Type: text/html; charset=UTF-8',
            "From: {$from_name} <{$from_email}>",
        ];

        $total_sent = 0;

        foreach ( $pending as $send ) {
            $send_id    = (int) $send['id'];
            $contact_id = (int) $send['contact_id'];
            $email      = $send['email'];
            $token      = $send['token'];

            $html = self::_build_email_html( $campaign, $send_id, $token );

            $sent = wp_mail( $email, $campaign['subject'], $html, $headers );

            if ( $sent ) {
                $wpdb->update(
                    $wpdb->prefix . 'wea_campaign_sends',
                    [ 'status' => 'sent', 'sent_at' => current_time( 'mysql', true ) ],
                    [ 'id' => $send_id ],
                    [ '%s', '%s' ],
                    [ '%d' ]
                );
                $total_sent++;
            } else {
                $wpdb->update(
                    $wpdb->prefix . 'wea_campaign_sends',
                    [ 'status' => 'failed' ],
                    [ 'id' => $send_id ],
                    [ '%s' ],
                    [ '%d' ]
                );
            }

            $wpdb->insert( $wpdb->prefix . 'wea_logs', [
                'automation_id' => null,
                'queue_id'      => null,
                'event_key'     => 'campaign',
                'to_email'      => $email,
                'subject'       => $campaign['subject'],
                'status'        => $sent ? 'sent' : 'failed',
                'message'       => "Campaign ID {$campaign_id}",
            ] );
        }

        $wpdb->update(
            $wpdb->prefix . 'wea_campaigns',
            [
                'status'     => 'sent',
                'sent_at'    => current_time( 'mysql', true ),
                'total_sent' => (int) $campaign['total_sent'] + $total_sent,
            ],
            [ 'id' => $campaign_id ],
            [ '%s', '%s', '%d' ],
            [ '%d' ]
        );
    }

    private static function _build_email_html( array $campaign, int $send_id, string $token ): string {
        $html = $campaign['body_html'];

        // Wrap links with click tracking
        $click_base = rest_url( 'wea/v1/track/click' );
        $html = preg_replace_callback(
            '/<a\s([^>]*?)href=["\']([^"\']+)["\']([^>]*?)>/i',
            function ( $matches ) use ( $send_id, $token, $click_base ) {
                $url = $matches[2];
                // Skip anchor links and mailto
                if ( str_starts_with( $url, '#' ) || str_starts_with( $url, 'mailto:' ) ) {
                    return $matches[0];
                }
                $tracked = add_query_arg( [
                    'sid'   => $send_id,
                    'token' => $token,
                    'url'   => rawurlencode( $url ),
                ], $click_base );
                return "<a {$matches[1]}href=\"" . esc_url( $tracked ) . "\"{$matches[3]}>";
            },
            $html
        );

        // Add open tracking pixel at the end
        $open_url = add_query_arg( [
            'sid'   => $send_id,
            'token' => $token,
        ], rest_url( 'wea/v1/track/open' ) );

        $html .= '<img src="' . esc_url( $open_url ) . '" width="1" height="1" style="display:none" alt="">';

        return $html;
    }

    // -------------------------------------------------------------------------
    // Token
    // -------------------------------------------------------------------------

    public static function generate_token( int $campaign_id, int $contact_id ): string {
        $secret = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'wea_fallback_secret';
        return hash( 'sha256', "wea_{$campaign_id}_{$contact_id}_{$secret}" );
    }

    // -------------------------------------------------------------------------
    // Tracking
    // -------------------------------------------------------------------------

    public static function record_open( int $send_id ): void {
        global $wpdb;
        $send = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wea_campaign_sends WHERE id = %d", $send_id ),
            ARRAY_A
        );
        if ( ! $send ) {
            return;
        }

        $update = [ 'open_count' => (int) $send['open_count'] + 1 ];
        $format = [ '%d' ];

        if ( ! $send['first_opened_at'] ) {
            $update['first_opened_at'] = current_time( 'mysql', true );
            $format[]                  = '%s';
        }

        $wpdb->update( $wpdb->prefix . 'wea_campaign_sends', $update, [ 'id' => $send_id ], $format, [ '%d' ] );

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}wea_campaigns SET total_opens = total_opens + 1 WHERE id = %d",
                (int) $send['campaign_id']
            )
        );
    }

    public static function record_click( int $send_id, string $url ): void {
        global $wpdb;
        $send = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wea_campaign_sends WHERE id = %d", $send_id ),
            ARRAY_A
        );
        if ( ! $send ) {
            return;
        }

        $wpdb->insert(
            $wpdb->prefix . 'wea_campaign_clicks',
            [
                'send_id'     => $send_id,
                'campaign_id' => (int) $send['campaign_id'],
                'contact_id'  => (int) $send['contact_id'],
                'url'         => $url,
                'clicked_at'  => current_time( 'mysql', true ),
            ],
            [ '%d', '%d', '%d', '%s', '%s' ]
        );

        $update = [ 'click_count' => (int) $send['click_count'] + 1 ];
        $format = [ '%d' ];

        if ( ! $send['first_clicked_at'] ) {
            $update['first_clicked_at'] = current_time( 'mysql', true );
            $format[]                   = '%s';
        }

        $wpdb->update( $wpdb->prefix . 'wea_campaign_sends', $update, [ 'id' => $send_id ], $format, [ '%d' ] );

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}wea_campaigns SET total_clicks = total_clicks + 1 WHERE id = %d",
                (int) $send['campaign_id']
            )
        );
    }

    // -------------------------------------------------------------------------
    // Stats
    // -------------------------------------------------------------------------

    public static function get_send_stats( int $campaign_id ): array {
        global $wpdb;

        $sends = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.*, c.first_name, c.last_name
                 FROM {$wpdb->prefix}wea_campaign_sends s
                 LEFT JOIN {$wpdb->prefix}wea_contacts c ON c.id = s.contact_id
                 WHERE s.campaign_id = %d
                 ORDER BY s.id ASC",
                $campaign_id
            ),
            ARRAY_A
        );

        if ( ! $sends ) {
            return [];
        }

        $send_ids    = array_column( $sends, 'id' );
        $placeholders = implode( ',', array_fill( 0, count( $send_ids ), '%d' ) );

        $clicks_raw = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT send_id, url, clicked_at FROM {$wpdb->prefix}wea_campaign_clicks WHERE send_id IN ($placeholders) ORDER BY clicked_at ASC",
                ...$send_ids
            ),
            ARRAY_A
        );

        $clicks_by_send = [];
        foreach ( $clicks_raw as $click ) {
            $clicks_by_send[ (int) $click['send_id'] ][] = [
                'url'        => $click['url'],
                'clicked_at' => $click['clicked_at'],
            ];
        }

        $result = [];
        foreach ( $sends as $send ) {
            $sid      = (int) $send['id'];
            $result[] = [
                'name'            => trim( ( $send['first_name'] ?? '' ) . ' ' . ( $send['last_name'] ?? '' ) ),
                'email'           => $send['email'],
                'sent_at'         => $send['sent_at'],
                'open_count'      => (int) $send['open_count'],
                'first_opened_at' => $send['first_opened_at'],
                'click_count'     => (int) $send['click_count'],
                'first_clicked_at'=> $send['first_clicked_at'],
                'clicks'          => $clicks_by_send[ $sid ] ?? [],
            ];
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Cancel
    // -------------------------------------------------------------------------

    public static function cancel( int $campaign_id ): bool {
        global $wpdb;
        $campaign = self::get( $campaign_id );
        if ( ! $campaign || ! in_array( $campaign['status'], [ 'scheduled', 'sending' ], true ) ) {
            return false;
        }
        return (bool) $wpdb->update(
            $wpdb->prefix . 'wea_campaigns',
            [ 'status' => 'cancelled' ],
            [ 'id' => $campaign_id ],
            [ '%s' ],
            [ '%d' ]
        );
    }

    private static function _is_mjml( string $s ): bool {
        $t = ltrim( $s );
        return str_starts_with( $t, '<mjml' ) || str_starts_with( $t, '<mj-' );
    }

    private static function _is_block_json( string $s ): bool {
        $t = ltrim( $s );
        if ( ! str_starts_with( $t, '{' ) ) return false;
        try {
            $d = json_decode( $t, true, 512, JSON_THROW_ON_ERROR );
            return is_array( $d ) && isset( $d['version'] ) && $d['version'] === 1;
        } catch ( \JsonException $e ) {
            return false;
        }
    }
}
