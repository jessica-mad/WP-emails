<?php
/**
 * Plugin Name:       WP Email Automations
 * Plugin URI:        https://github.com/jessica-mad/wp-emails
 * Description:       Email automation platform with visual builder (GrapesJS/MJML), event-driven triggers, and full automation workflows.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            jessica-mad
 * License:           GPL-2.0-or-later
 * Text Domain:       wp-email-automations
 */

defined( 'ABSPATH' ) || exit;

define( 'WEA_VERSION',     '1.0.0' );
define( 'WEA_PLUGIN_FILE', __FILE__ );
define( 'WEA_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'WEA_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'WEA_DB_VERSION',  '1.0' );

// Autoload classes
spl_autoload_register( function ( $class ) {
    $prefix = 'WEA\\';
    if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
        return;
    }
    $relative = substr( $class, strlen( $prefix ) );
    $file      = WEA_PLUGIN_DIR . 'includes/class-' . strtolower( str_replace( '\\', '-', $relative ) ) . '.php';
    if ( file_exists( $file ) ) {
        require $file;
    }
} );

register_activation_hook(   __FILE__, [ 'WEA\\Activator',   'activate'   ] );
register_deactivation_hook( __FILE__, [ 'WEA\\Deactivator', 'deactivate' ] );

function wea_init(): void {
    require_once WEA_PLUGIN_DIR . 'includes/class-activator.php';
    require_once WEA_PLUGIN_DIR . 'includes/class-deactivator.php';
    require_once WEA_PLUGIN_DIR . 'includes/class-database.php';
    require_once WEA_PLUGIN_DIR . 'includes/class-template-manager.php';
    require_once WEA_PLUGIN_DIR . 'includes/class-automation.php';
    require_once WEA_PLUGIN_DIR . 'includes/class-condition-evaluator.php';
    require_once WEA_PLUGIN_DIR . 'includes/class-email-sender.php';
    require_once WEA_PLUGIN_DIR . 'includes/class-event-receiver.php';
    require_once WEA_PLUGIN_DIR . 'admin/class-admin.php';

    WEA\EventReceiver::init();
    WEA\Automation::init();

    if ( is_admin() ) {
        WEA\Admin::init();
    }
}
add_action( 'plugins_loaded', 'wea_init' );
