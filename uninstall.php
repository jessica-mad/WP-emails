<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wea_logs" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wea_queue" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wea_automations" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wea_templates" );

delete_option( 'wea_api_key' );
delete_option( 'wea_db_version' );

$timestamp = wp_next_scheduled( 'wea_process_queue' );
if ( $timestamp ) {
    wp_unschedule_event( $timestamp, 'wea_process_queue' );
}
