<?php
/**
 * WP Email Automations — Integration helper
 *
 * Copy this file into your other WordPress repository (or any PHP project)
 * to send events to the automation engine.
 *
 * Usage:
 *   $wea = new WEA_Client('https://your-site.com', 'YOUR_API_KEY');
 *   $wea->trigger('order.completed', ['email' => $email, 'name' => $name, 'order_id' => $id]);
 */

class WEA_Client {

    private string $base_url;
    private string $api_key;
    private int    $timeout;

    public function __construct( string $base_url, string $api_key, int $timeout = 10 ) {
        $this->base_url = rtrim( $base_url, '/' );
        $this->api_key  = $api_key;
        $this->timeout  = $timeout;
    }

    /**
     * Fire a single event.
     *
     * @param string $trigger  Event key, e.g. "order.completed"
     * @param array  $data     Arbitrary key-value payload
     * @return array{success: bool, automations_queued: int, error?: string}
     */
    public function trigger( string $trigger, array $data = [] ): array {
        return $this->post( '/wp-json/wea/v1/event', [
            'trigger' => $trigger,
            'data'    => $data,
        ] );
    }

    /**
     * Fire multiple events in one HTTP request.
     *
     * @param array $events  Array of ['trigger' => string, 'data' => array]
     */
    public function batch( array $events ): array {
        return $this->post( '/wp-json/wea/v1/events/batch', [ 'events' => $events ] );
    }

    /**
     * Check connectivity.
     */
    public function ping(): bool {
        $ch = curl_init( $this->base_url . '/wp-json/wea/v1/ping' );
        curl_setopt_array( $ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => [ 'X-WEA-Key: ' . $this->api_key ],
        ] );
        $body = curl_exec( $ch );
        $code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        curl_close( $ch );
        return $code === 200;
    }

    private function post( string $path, array $payload ): array {
        $url  = $this->base_url . $path;
        $json = json_encode( $payload );

        $ch = curl_init( $url );
        curl_setopt_array( $ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen( $json ),
                'X-WEA-Key: ' . $this->api_key,
            ],
        ] );

        $body  = curl_exec( $ch );
        $code  = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $error = curl_error( $ch );
        curl_close( $ch );

        if ( $error ) {
            return [ 'success' => false, 'error' => $error ];
        }

        $decoded = json_decode( $body, true );
        if ( $code !== 200 || ! is_array( $decoded ) ) {
            return [ 'success' => false, 'error' => "HTTP $code: $body" ];
        }

        return $decoded;
    }
}

// -----------------------------------------------------------------------
// WordPress-native version (for use inside another WP site/plugin)
// -----------------------------------------------------------------------
if ( ! function_exists( 'wea_trigger' ) ) {
    /**
     * @param string $site_url  The WP Email Automations site URL
     * @param string $api_key
     * @param string $trigger
     * @param array  $data
     */
    function wea_trigger( string $site_url, string $api_key, string $trigger, array $data = [] ): bool {
        $response = wp_remote_post(
            trailingslashit( $site_url ) . 'wp-json/wea/v1/event',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-WEA-Key'    => $api_key,
                ],
                'body'    => wp_json_encode( [ 'trigger' => $trigger, 'data' => $data ] ),
                'timeout' => 10,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return ! empty( $body['success'] );
    }
}
