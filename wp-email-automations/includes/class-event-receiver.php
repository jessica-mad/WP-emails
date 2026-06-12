<?php
namespace WEA;

defined( 'ABSPATH' ) || exit;

/**
 * REST API receiver for external events.
 *
 * Endpoint:  POST /wp-json/wea/v1/event
 * Auth:      Header  X-WEA-Key: <api_key>
 *
 * Body (JSON):
 * {
 *   "trigger": "order.completed",
 *   "data": {
 *     "email": "user@example.com",
 *     "name": "Jane",
 *     "order_id": 123,
 *     ...
 *   }
 * }
 *
 * Response 200:
 * { "success": true, "message": "Event received", "automations_queued": 2 }
 */
class EventReceiver {

    public static function init(): void {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function register_routes(): void {
        register_rest_route( 'wea/v1', '/event', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'handle_event' ],
            'permission_callback' => [ __CLASS__, 'authenticate' ],
            'args'                => [
                'trigger' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_key',
                ],
                'data' => [
                    'required'          => false,
                    'type'              => 'object',
                    'default'           => [],
                ],
            ],
        ] );

        // Batch endpoint
        register_rest_route( 'wea/v1', '/events/batch', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'handle_batch' ],
            'permission_callback' => [ __CLASS__, 'authenticate' ],
        ] );

        // Status / ping
        register_rest_route( 'wea/v1', '/ping', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => static fn() => new \WP_REST_Response( [ 'status' => 'ok', 'version' => WEA_VERSION ] ),
            'permission_callback' => [ __CLASS__, 'authenticate' ],
        ] );
    }

    public static function authenticate( \WP_REST_Request $request ): bool|\WP_Error {
        $api_key      = get_option( 'wea_api_key', '' );
        $provided_key = $request->get_header( 'X-WEA-Key' );

        if ( ! $api_key || ! hash_equals( $api_key, (string) $provided_key ) ) {
            return new \WP_Error(
                'wea_unauthorized',
                __( 'Invalid or missing API key.', 'wp-email-automations' ),
                [ 'status' => 401 ]
            );
        }

        return true;
    }

    public static function handle_event( \WP_REST_Request $request ): \WP_REST_Response {
        $trigger    = $request->get_param( 'trigger' );
        $event_data = (array) $request->get_param( 'data' );
        $event_data['_trigger'] = $trigger;

        $count = self::dispatch( $trigger, $event_data );

        return new \WP_REST_Response( [
            'success'            => true,
            'message'            => 'Event received',
            'automations_queued' => $count,
        ], 200 );
    }

    public static function handle_batch( \WP_REST_Request $request ): \WP_REST_Response {
        $body   = $request->get_json_params();
        $events = $body['events'] ?? [];

        if ( ! is_array( $events ) ) {
            return new \WP_REST_Response( [ 'success' => false, 'message' => 'events must be an array' ], 400 );
        }

        $total = 0;
        foreach ( $events as $event ) {
            $trigger = sanitize_key( $event['trigger'] ?? '' );
            if ( ! $trigger ) {
                continue;
            }
            $data            = (array) ( $event['data'] ?? [] );
            $data['_trigger'] = $trigger;
            $total           += self::dispatch( $trigger, $data );
        }

        return new \WP_REST_Response( [
            'success'            => true,
            'automations_queued' => $total,
        ], 200 );
    }

    /**
     * Fire the WordPress action and return the number of matching automations.
     */
    private static function dispatch( string $trigger, array $event_data ): int {
        global $wpdb;
        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}wea_automations WHERE trigger_key = %s AND status = 'active'",
                $trigger
            )
        );

        do_action( 'wea_trigger_event', $trigger, $event_data );

        return $count;
    }
}
