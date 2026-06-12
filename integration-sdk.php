<?php
/**
 * WP Email Automations — Integration SDK for App-painting
 *
 * Copy this file into your App-painting repository and call the helpers
 * at the appropriate moments in your application flow.
 *
 * Quick start:
 *   define('WEA_SITE_URL', 'https://tu-web.com');
 *   define('WEA_API_KEY',  'tu_api_key_aqui');
 *
 * ─────────────────────────────────────────────────────────────────────────
 * EVENTOS DISPONIBLES Y SUS PAYLOADS
 * ─────────────────────────────────────────────────────────────────────────
 *
 * USUARIOS
 *   user.registered        → email, name, username, registered_at, avatar_url
 *   user.password_reset    → email, name, username
 *
 * TRAGAPERRAS
 *   slot.played            → email, name, username, result, prize_name,
 *                            prize_value, symbols, total_coins, spins_today, played_at
 *   slot.big_win           → email, name, prize_name, prize_value, symbols, multiplier
 *   slot.jackpot           → email, name, jackpot_amount, symbols
 *
 * RETOS / RACHAS
 *   challenge.completed    → email, name, challenge_title, challenge_id, category,
 *                            difficulty, points_earned, streak_days, artwork_url,
 *                            completed_at, next_challenge_url
 *   challenge.started      → email, name, challenge_title, challenge_id, category,
 *                            deadline, challenge_url
 *   challenge.streak_active   → email, name, streak_days, streak_record,
 *                               last_challenge, next_challenge_url, motivational_rank
 *   challenge.streak_at_risk  → email, name, streak_days, hours_left, challenge_url
 *   challenge.streak_broken   → email, name, streak_days, broken_at, restart_url
 *   challenge.streak_paused   → email, name, streak_days, paused_until, resume_url
 *
 * NOTIFICACIONES SOCIALES
 *   social.new_follower    → email, name, follower_name, follower_username,
 *                            follower_avatar, follower_profile, total_followers
 *   social.me_inspiras     → email, name, from_name, from_username, from_avatar,
 *                            artwork_title, artwork_url, artwork_image, total_me_inspiras
 *   social.comment         → email, name, commenter_name, comment_text,
 *                            artwork_title, artwork_url
 *   social.artwork_liked   → email, name, liker_name, artwork_title,
 *                            artwork_url, total_likes
 *   social.mention         → email, name, mentioned_by, context, link
 * ─────────────────────────────────────────────────────────────────────────
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
// Helpers específicos para App-painting
// -----------------------------------------------------------------------

/**
 * Llama esto cuando un usuario se registre.
 */
function wea_user_registered( WEA_Client $wea, array $user ): void {
    $wea->trigger( 'user.registered', [
        'email'         => $user['email']         ?? '',
        'name'          => $user['name']           ?? $user['display_name'] ?? '',
        'username'      => $user['username']       ?? $user['user_login'] ?? '',
        'registered_at' => $user['registered_at']  ?? date('Y-m-d H:i:s'),
        'avatar_url'    => $user['avatar_url']     ?? '',
    ] );
}

/**
 * Llama esto después de cada tirada de tragaperras.
 */
function wea_slot_played( WEA_Client $wea, array $user, array $spin_result ): void {
    $is_jackpot  = ( $spin_result['result'] ?? '' ) === 'jackpot';
    $is_big_win  = ( $spin_result['result'] ?? '' ) === 'big_win';

    $payload = [
        'email'       => $user['email']                   ?? '',
        'name'        => $user['name']                    ?? '',
        'username'    => $user['username']                ?? '',
        'result'      => $spin_result['result']           ?? 'lose',
        'prize_name'  => $spin_result['prize_name']       ?? '',
        'prize_value' => $spin_result['prize_value']      ?? 0,
        'symbols'     => $spin_result['symbols']          ?? '',
        'total_coins' => $spin_result['total_coins']      ?? 0,
        'spins_today' => $spin_result['spins_today']      ?? 1,
        'multiplier'  => $spin_result['multiplier']       ?? 1,
        'played_at'   => date( 'Y-m-d H:i:s' ),
    ];

    $wea->trigger( 'slot.played', $payload );

    if ( $is_jackpot ) {
        $wea->trigger( 'slot.jackpot', array_merge( $payload, [
            'jackpot_amount' => $spin_result['prize_value'] ?? 0,
        ] ) );
    } elseif ( $is_big_win ) {
        $wea->trigger( 'slot.big_win', $payload );
    }
}

/**
 * Llama esto cuando un usuario completa un reto.
 */
function wea_challenge_completed( WEA_Client $wea, array $user, array $challenge, int $streak_days ): void {
    $wea->trigger( 'challenge.completed', [
        'email'              => $user['email']             ?? '',
        'name'               => $user['name']              ?? '',
        'challenge_title'    => $challenge['title']        ?? '',
        'challenge_id'       => $challenge['id']           ?? '',
        'category'           => $challenge['category']     ?? '',
        'difficulty'         => $challenge['difficulty']   ?? '',
        'points_earned'      => $challenge['points']       ?? 0,
        'streak_days'        => $streak_days,
        'artwork_url'        => $challenge['artwork_url']  ?? '',
        'completed_at'       => date( 'Y-m-d H:i:s' ),
        'next_challenge_url' => $challenge['next_url']     ?? '',
    ] );
}

/**
 * Llama esto cada día para usuarios con racha activa.
 * Programa esto vía cron en tu app.
 */
function wea_streak_reminder( WEA_Client $wea, array $user, int $streak_days, bool $at_risk = false ): void {
    $trigger = $at_risk ? 'challenge.streak_at_risk' : 'challenge.streak_active';
    $wea->trigger( $trigger, [
        'email'              => $user['email']          ?? '',
        'name'               => $user['name']           ?? '',
        'streak_days'        => $streak_days,
        'streak_record'      => $user['streak_record']  ?? $streak_days,
        'hours_left'         => $user['hours_left']     ?? 24,
        'next_challenge_url' => $user['challenge_url']  ?? '',
        'challenge_url'      => $user['challenge_url']  ?? '',
        'motivational_rank'  => $user['streak_rank']    ?? '',
    ] );
}

/**
 * Llama esto cuando alguien recibe un nuevo seguidor.
 */
function wea_new_follower( WEA_Client $wea, array $recipient, array $follower, int $total_followers ): void {
    $wea->trigger( 'social.new_follower', [
        'email'             => $recipient['email']      ?? '',
        'name'              => $recipient['name']       ?? '',
        'follower_name'     => $follower['name']        ?? '',
        'follower_username' => $follower['username']    ?? '',
        'follower_avatar'   => $follower['avatar_url']  ?? '',
        'follower_profile'  => $follower['profile_url'] ?? '',
        'total_followers'   => $total_followers,
    ] );
}

/**
 * Llama esto cuando alguien da "Me Inspiras" a una obra.
 */
function wea_me_inspiras( WEA_Client $wea, array $recipient, array $from_user, array $artwork, int $total ): void {
    $wea->trigger( 'social.me_inspiras', [
        'email'             => $recipient['email']      ?? '',
        'name'              => $recipient['name']       ?? '',
        'from_name'         => $from_user['name']       ?? '',
        'from_username'     => $from_user['username']   ?? '',
        'from_avatar'       => $from_user['avatar_url'] ?? '',
        'artwork_title'     => $artwork['title']        ?? '',
        'artwork_url'       => $artwork['url']          ?? '',
        'artwork_image'     => $artwork['image_url']    ?? '',
        'total_me_inspiras' => $total,
    ] );
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
