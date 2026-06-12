<?php
namespace WEA;

defined( 'ABSPATH' ) || exit;

class Activator {

    public static function activate(): void {
        Database::create_tables();

        if ( ! get_option( 'wea_api_key' ) ) {
            update_option( 'wea_api_key', wp_generate_password( 32, false ) );
        }

        if ( ! wp_next_scheduled( 'wea_process_queue' ) ) {
            wp_schedule_event( time(), 'every_minute', 'wea_process_queue' );
        }

        flush_rewrite_rules();
    }
}
