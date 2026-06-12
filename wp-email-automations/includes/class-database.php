<?php
namespace WEA;

defined( 'ABSPATH' ) || exit;

class Database {

    public static function create_tables(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Email templates
        dbDelta( "CREATE TABLE {$wpdb->prefix}wea_templates (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name        VARCHAR(191)    NOT NULL,
            subject     VARCHAR(500)    NOT NULL DEFAULT '',
            mjml_json   LONGTEXT        NOT NULL,
            html        LONGTEXT        NOT NULL,
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;" );

        // Automations
        dbDelta( "CREATE TABLE {$wpdb->prefix}wea_automations (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name        VARCHAR(191)    NOT NULL,
            trigger_key VARCHAR(100)    NOT NULL,
            conditions  LONGTEXT        NOT NULL DEFAULT '[]',
            actions     LONGTEXT        NOT NULL DEFAULT '[]',
            status      ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY trigger_key (trigger_key),
            KEY status (status)
        ) $charset;" );

        // Queue — pending jobs (delayed sends, etc.)
        dbDelta( "CREATE TABLE {$wpdb->prefix}wea_queue (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            automation_id   BIGINT UNSIGNED NOT NULL,
            action_index    TINYINT UNSIGNED NOT NULL DEFAULT 0,
            event_data      LONGTEXT        NOT NULL DEFAULT '{}',
            scheduled_at    DATETIME        NOT NULL,
            status          ENUM('pending','processing','done','failed') NOT NULL DEFAULT 'pending',
            attempts        TINYINT UNSIGNED NOT NULL DEFAULT 0,
            error           TEXT,
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY scheduled_at (scheduled_at),
            KEY status (status)
        ) $charset;" );

        // Logs
        dbDelta( "CREATE TABLE {$wpdb->prefix}wea_logs (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            automation_id   BIGINT UNSIGNED,
            queue_id        BIGINT UNSIGNED,
            event_key       VARCHAR(100),
            to_email        VARCHAR(191),
            subject         VARCHAR(500),
            status          ENUM('sent','failed','skipped') NOT NULL,
            message         TEXT,
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY automation_id (automation_id),
            KEY created_at (created_at)
        ) $charset;" );

        update_option( 'wea_db_version', WEA_DB_VERSION );
    }

    /**
     * Add a cron interval for every-minute processing.
     */
    public static function add_cron_intervals( array $schedules ): array {
        $schedules['every_minute'] = [
            'interval' => 60,
            'display'  => __( 'Every Minute', 'wp-email-automations' ),
        ];
        return $schedules;
    }
}

add_filter( 'cron_schedules', [ 'WEA\\Database', 'add_cron_intervals' ] );
