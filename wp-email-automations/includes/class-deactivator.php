<?php
namespace WEA;

defined( 'ABSPATH' ) || exit;

class Deactivator {

    public static function deactivate(): void {
        $timestamp = wp_next_scheduled( 'wea_process_queue' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'wea_process_queue' );
        }
        flush_rewrite_rules();
    }
}
