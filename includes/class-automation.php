<?php
namespace WEA;

defined( 'ABSPATH' ) || exit;

/**
 * Automation engine.
 *
 * Action schema (array of objects):
 * [
 *   {
 *     "type": "send_email",
 *     "template_id": 1,
 *     "to": "{{email}}",          // supports placeholders
 *     "subject": "",              // empty = use template default
 *     "delay_minutes": 0
 *   },
 *   {
 *     "type": "webhook",
 *     "url": "https://...",
 *     "method": "POST",
 *     "delay_minutes": 0
 *   },
 *   {
 *     "type": "tag_contact",
 *     "tag": "converted"
 *   }
 * ]
 */
class Automation {

    public static function init(): void {
        add_action( 'wea_process_queue',  [ __CLASS__, 'process_queue' ] );
        add_action( 'wea_trigger_event',  [ __CLASS__, 'handle_event' ], 10, 2 );
    }

    // -------------------------------------------------------------------------
    // Trigger
    // -------------------------------------------------------------------------

    /**
     * Find all active automations that match the trigger key and run them.
     */
    public static function handle_event( string $trigger_key, array $event_data ): void {
        global $wpdb;

        $automations = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wea_automations
                 WHERE trigger_key = %s AND status = 'active'",
                $trigger_key
            ),
            ARRAY_A
        );

        if ( ! $automations ) {
            return;
        }

        foreach ( $automations as $automation ) {
            $conditions = json_decode( $automation['conditions'], true ) ?? [];
            if ( ! ConditionEvaluator::passes( $conditions, $event_data ) ) {
                continue;
            }

            $actions = json_decode( $automation['actions'], true ) ?? [];
            self::enqueue_actions( (int) $automation['id'], $actions, $event_data );
        }
    }

    // -------------------------------------------------------------------------
    // Queue
    // -------------------------------------------------------------------------

    private static function enqueue_actions( int $automation_id, array $actions, array $event_data ): void {
        global $wpdb;
        $delay_offset = 0;

        foreach ( $actions as $index => $action ) {
            $delay_minutes = (int) ( $action['delay_minutes'] ?? 0 );
            $delay_offset += $delay_minutes;
            $scheduled_at  = gmdate( 'Y-m-d H:i:s', time() + $delay_offset * 60 );

            $wpdb->insert(
                "{$wpdb->prefix}wea_queue",
                [
                    'automation_id' => $automation_id,
                    'action_index'  => $index,
                    'event_data'    => wp_json_encode( $event_data ),
                    'scheduled_at'  => $scheduled_at,
                    'status'        => 'pending',
                ]
            );
        }
    }

    public static function process_queue(): void {
        global $wpdb;

        $jobs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT q.*, a.actions FROM {$wpdb->prefix}wea_queue q
                 JOIN {$wpdb->prefix}wea_automations a ON a.id = q.automation_id
                 WHERE q.status = 'pending' AND q.scheduled_at <= %s
                 ORDER BY q.scheduled_at ASC
                 LIMIT 50",
                current_time( 'mysql', true )
            ),
            ARRAY_A
        );

        if ( ! $jobs ) {
            return;
        }

        foreach ( $jobs as $job ) {
            // Mark as processing to prevent duplicate runs
            $updated = $wpdb->update(
                "{$wpdb->prefix}wea_queue",
                [ 'status' => 'processing' ],
                [ 'id' => $job['id'], 'status' => 'pending' ]
            );
            if ( ! $updated ) {
                continue;
            }

            $actions    = json_decode( $job['actions'],    true ) ?? [];
            $event_data = json_decode( $job['event_data'], true ) ?? [];
            $action     = $actions[ $job['action_index'] ] ?? null;

            if ( ! $action ) {
                self::mark_job( (int) $job['id'], 'failed', 'Action index not found.' );
                continue;
            }

            $result = self::execute_action( $action, $event_data, (int) $job['automation_id'], (int) $job['id'] );
            self::mark_job( (int) $job['id'], $result ? 'done' : 'failed' );
        }
    }

    private static function mark_job( int $id, string $status, string $error = '' ): void {
        global $wpdb;
        $wpdb->update(
            "{$wpdb->prefix}wea_queue",
            array_filter( [ 'status' => $status, 'error' => $error ?: null ] ),
            [ 'id' => $id ]
        );
    }

    // -------------------------------------------------------------------------
    // Action executor
    // -------------------------------------------------------------------------

    private static function execute_action( array $action, array $event_data, int $automation_id, int $queue_id ): bool {
        $type   = $action['type'] ?? '';
        $result = false;

        switch ( $type ) {
            case 'send_email':
                $result = self::action_send_email( $action, $event_data, $automation_id, $queue_id );
                break;

            case 'webhook':
                $result = self::action_webhook( $action, $event_data );
                break;

            default:
                do_action( "wea_action_{$type}", $action, $event_data );
                $result = true;
        }

        return $result;
    }

    private static function action_send_email( array $action, array $event_data, int $automation_id, int $queue_id ): bool {
        $template_id = (int) ( $action['template_id'] ?? 0 );
        $to_raw      = $action['to'] ?? '';
        $to          = self::interpolate( $to_raw, $event_data );
        $subject     = self::interpolate( $action['subject'] ?? '', $event_data );

        if ( ! is_email( $to ) ) {
            self::log( $automation_id, $queue_id, $event_data['_trigger'] ?? '', $to, $subject, 'failed', 'Invalid email: ' . $to );
            return false;
        }

        $sent = EmailSender::send_template( $template_id, $to, $subject, $event_data );

        self::log(
            $automation_id,
            $queue_id,
            $event_data['_trigger'] ?? '',
            $to,
            $subject,
            $sent ? 'sent' : 'failed',
            $sent ? '' : 'wp_mail returned false'
        );

        return $sent;
    }

    private static function action_webhook( array $action, array $event_data ): bool {
        $url    = $action['url'] ?? '';
        $method = strtoupper( $action['method'] ?? 'POST' );

        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return false;
        }

        $response = wp_remote_request( $url, [
            'method'  => $method,
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( $event_data ),
            'timeout' => 10,
        ] );

        return ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) < 400;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Replace {{field}} and {{nested.field}} tokens with values from $data.
     */
    public static function interpolate( string $text, array $data ): string {
        return preg_replace_callback(
            '/\{\{([^}]+)\}\}/',
            static function ( $m ) use ( $data ) {
                $parts   = explode( '.', trim( $m[1] ) );
                $current = $data;
                foreach ( $parts as $part ) {
                    if ( is_array( $current ) && isset( $current[ $part ] ) ) {
                        $current = $current[ $part ];
                    } else {
                        return $m[0]; // leave unchanged
                    }
                }
                return is_scalar( $current ) ? (string) $current : $m[0];
            },
            $text
        );
    }

    private static function log(
        int $automation_id,
        int $queue_id,
        string $event_key,
        string $to,
        string $subject,
        string $status,
        string $message = ''
    ): void {
        global $wpdb;
        $wpdb->insert(
            "{$wpdb->prefix}wea_logs",
            compact( 'automation_id', 'queue_id', 'event_key', 'to_email', 'subject', 'status', 'message' )
        );
    }

    // -------------------------------------------------------------------------
    // CRUD helpers used by admin
    // -------------------------------------------------------------------------

    public static function get_all(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}wea_automations ORDER BY updated_at DESC",
            ARRAY_A
        ) ?: [];
    }

    public static function get( int $id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wea_automations WHERE id = %d", $id ),
            ARRAY_A
        );
        return $row ?: null;
    }

    public static function save( array $data ): int|false {
        global $wpdb;
        $now    = current_time( 'mysql' );
        $fields = [
            'name'        => sanitize_text_field( $data['name']        ?? '' ),
            'trigger_key' => sanitize_key( $data['trigger_key']        ?? '' ),
            'conditions'  => wp_json_encode( $data['conditions']       ?? [] ),
            'actions'     => wp_json_encode( $data['actions']          ?? [] ),
            'status'      => in_array( $data['status'] ?? '', [ 'active', 'inactive' ], true )
                                ? $data['status'] : 'inactive',
        ];

        if ( ! empty( $data['id'] ) ) {
            $fields['updated_at'] = $now;
            $wpdb->update( "{$wpdb->prefix}wea_automations", $fields, [ 'id' => (int) $data['id'] ] );
            return (int) $data['id'];
        }

        $fields['created_at'] = $fields['updated_at'] = $now;
        $wpdb->insert( "{$wpdb->prefix}wea_automations", $fields );
        return $wpdb->insert_id ?: false;
    }

    public static function delete( int $id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete( "{$wpdb->prefix}wea_automations", [ 'id' => $id ] );
    }

    public static function toggle_status( int $id ): ?string {
        $auto = self::get( $id );
        if ( ! $auto ) {
            return null;
        }
        $new = $auto['status'] === 'active' ? 'inactive' : 'active';
        global $wpdb;
        $wpdb->update( "{$wpdb->prefix}wea_automations", [ 'status' => $new ], [ 'id' => $id ] );
        return $new;
    }

    public static function available_triggers(): array {
        return apply_filters( 'wea_available_triggers', [

            // ── Usuarios ────────────────────────────────────────────────────
            'user.registered'         => __( '👤 Usuario registrado',                     'wp-email-automations' ),
            'user.password_reset'     => __( '👤 Restablecimiento de contraseña',         'wp-email-automations' ),

            // ── Tragaperras ─────────────────────────────────────────────────
            'slot.played'             => __( '🎰 Tragaperras — partida jugada',            'wp-email-automations' ),
            'slot.big_win'            => __( '🎰 Tragaperras — premio grande',             'wp-email-automations' ),
            'slot.jackpot'            => __( '🎰 Tragaperras — jackpot',                  'wp-email-automations' ),

            // ── Retos / Rachas ───────────────────────────────────────────────
            'challenge.completed'     => __( '🏆 Reto completado',                        'wp-email-automations' ),
            'challenge.started'       => __( '🏆 Reto iniciado',                          'wp-email-automations' ),
            'challenge.streak_active' => __( '🔥 Racha activa — motivación diaria',       'wp-email-automations' ),
            'challenge.streak_at_risk'=> __( '⚠️ Racha en peligro — no la rompas',        'wp-email-automations' ),
            'challenge.streak_broken' => __( '💔 Racha rota',                             'wp-email-automations' ),
            'challenge.streak_paused' => __( '⏸️ Racha pausada',                          'wp-email-automations' ),

            // ── Notificaciones sociales ──────────────────────────────────────
            'social.new_follower'     => __( '👥 Nuevo seguidor',                         'wp-email-automations' ),
            'social.me_inspiras'      => __( '✨ Alguien te ha dado Me Inspiras',          'wp-email-automations' ),
            'social.comment'          => __( '💬 Nuevo comentario en tu obra',             'wp-email-automations' ),
            'social.artwork_liked'    => __( '❤️ Tu obra ha recibido likes',               'wp-email-automations' ),
            'social.mention'          => __( '📣 Te han mencionado',                       'wp-email-automations' ),

            // ── Genéricos ────────────────────────────────────────────────────
            'order.created'           => __( '🛒 Pedido creado',                           'wp-email-automations' ),
            'order.completed'         => __( '🛒 Pedido completado',                       'wp-email-automations' ),
            'subscription.started'    => __( '💳 Suscripción iniciada',                   'wp-email-automations' ),
            'subscription.cancelled'  => __( '💳 Suscripción cancelada',                  'wp-email-automations' ),
            'custom'                  => __( '⚙️ Evento personalizado',                   'wp-email-automations' ),
        ] );
    }

    /**
     * Returns documented payload fields for each trigger.
     * Used in the admin UI to show available {{variables}}.
     */
    public static function trigger_fields(): array {
        return [
            'user.registered' => [
                'email'        => 'Email del usuario',
                'name'         => 'Nombre completo',
                'username'     => 'Nombre de usuario',
                'registered_at'=> 'Fecha de registro',
                'avatar_url'   => 'URL del avatar',
            ],
            'slot.played' => [
                'email'        => 'Email del usuario',
                'name'         => 'Nombre del usuario',
                'username'     => 'Nombre de usuario',
                'result'       => 'Resultado (win / lose / jackpot)',
                'prize_name'   => 'Nombre del premio ganado',
                'prize_value'  => 'Valor del premio (monedas/puntos)',
                'symbols'      => 'Símbolos obtenidos (ej: 🍒🍒🍒)',
                'total_coins'  => 'Monedas totales tras la partida',
                'spins_today'  => 'Partidas jugadas hoy',
                'played_at'    => 'Fecha y hora de la partida',
            ],
            'slot.big_win' => [
                'email'        => 'Email del usuario',
                'name'         => 'Nombre del usuario',
                'prize_name'   => 'Nombre del premio',
                'prize_value'  => 'Valor del premio',
                'symbols'      => 'Símbolos ganadores',
                'multiplier'   => 'Multiplicador aplicado',
            ],
            'slot.jackpot' => [
                'email'        => 'Email del usuario',
                'name'         => 'Nombre del usuario',
                'jackpot_amount' => 'Cantidad del jackpot',
                'symbols'      => 'Símbolos ganadores',
            ],
            'challenge.completed' => [
                'email'            => 'Email del usuario',
                'name'             => 'Nombre del usuario',
                'challenge_title'  => 'Título del reto',
                'challenge_id'     => 'ID del reto',
                'category'         => 'Categoría (acuarela, óleo, digital…)',
                'difficulty'       => 'Dificultad (fácil, medio, difícil)',
                'points_earned'    => 'Puntos ganados',
                'streak_days'      => 'Días de racha actual',
                'artwork_url'      => 'URL de la obra enviada',
                'completed_at'     => 'Fecha de completado',
                'next_challenge_url'=> 'URL del siguiente reto sugerido',
            ],
            'challenge.started' => [
                'email'           => 'Email del usuario',
                'name'            => 'Nombre del usuario',
                'challenge_title' => 'Título del reto',
                'challenge_id'    => 'ID del reto',
                'category'        => 'Categoría',
                'deadline'        => 'Fecha límite',
                'challenge_url'   => 'URL del reto',
            ],
            'challenge.streak_active' => [
                'email'             => 'Email del usuario',
                'name'              => 'Nombre del usuario',
                'streak_days'       => 'Días consecutivos',
                'streak_record'     => 'Récord personal de racha',
                'last_challenge'    => 'Último reto completado',
                'next_challenge_url'=> 'URL del siguiente reto',
                'motivational_rank' => 'Posición en ranking de rachas',
            ],
            'challenge.streak_at_risk' => [
                'email'          => 'Email del usuario',
                'name'           => 'Nombre del usuario',
                'streak_days'    => 'Días de racha en juego',
                'hours_left'     => 'Horas restantes para no romperla',
                'challenge_url'  => 'URL del reto del día',
            ],
            'challenge.streak_broken' => [
                'email'        => 'Email del usuario',
                'name'         => 'Nombre del usuario',
                'streak_days'  => 'Días que duró la racha',
                'broken_at'    => 'Fecha en que se rompió',
                'restart_url'  => 'URL para empezar nueva racha',
            ],
            'challenge.streak_paused' => [
                'email'        => 'Email del usuario',
                'name'         => 'Nombre del usuario',
                'streak_days'  => 'Días de racha antes de pausar',
                'paused_until' => 'Fecha hasta la que está pausada',
                'resume_url'   => 'URL para reanudar',
            ],
            'social.new_follower' => [
                'email'              => 'Email del destinatario',
                'name'               => 'Nombre del destinatario',
                'follower_name'      => 'Nombre del nuevo seguidor',
                'follower_username'  => 'Username del seguidor',
                'follower_avatar'    => 'Avatar del seguidor',
                'follower_profile'   => 'URL del perfil del seguidor',
                'total_followers'    => 'Total de seguidores ahora',
            ],
            'social.me_inspiras' => [
                'email'           => 'Email del destinatario',
                'name'            => 'Nombre del destinatario',
                'from_name'       => 'Nombre de quien inspira',
                'from_username'   => 'Username de quien inspira',
                'from_avatar'     => 'Avatar de quien inspira',
                'artwork_title'   => 'Título de la obra que inspiró',
                'artwork_url'     => 'URL de la obra',
                'artwork_image'   => 'Imagen de la obra',
                'total_me_inspiras' => 'Total de Me Inspiras recibidos',
            ],
            'social.comment' => [
                'email'          => 'Email del destinatario',
                'name'           => 'Nombre del destinatario',
                'commenter_name' => 'Nombre del comentarista',
                'comment_text'   => 'Texto del comentario',
                'artwork_title'  => 'Título de la obra',
                'artwork_url'    => 'URL de la obra',
            ],
            'social.artwork_liked' => [
                'email'         => 'Email del destinatario',
                'name'          => 'Nombre del destinatario',
                'liker_name'    => 'Nombre de quien dio like',
                'artwork_title' => 'Título de la obra',
                'artwork_url'   => 'URL de la obra',
                'total_likes'   => 'Total de likes de la obra',
            ],
            'social.mention' => [
                'email'          => 'Email del destinatario',
                'name'           => 'Nombre del destinatario',
                'mentioned_by'   => 'Nombre de quien menciona',
                'context'        => 'Contexto de la mención',
                'link'           => 'URL donde se mencionó',
            ],
        ];
    }
}
