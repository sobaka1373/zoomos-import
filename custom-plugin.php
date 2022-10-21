<?php

/**
 * Custom plugin to Wordpress
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @since             1.0.1
 * @package           Custom_plugin
 *
 * @wordpress-plugin
 * Plugin Name:       Custom plugin
 * Description:       Custom description
 * Version:           1.0.0
 * Author:            Custom author
 * Text Domain:       custom_plugin
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}
define( 'MY_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'MY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

function activate_custom_plugin() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-custom-plugin-activator.php';
    Custom_Plugin_Activator::activate();
}

function deactivate_custom_plugin() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-custom-plugin-deactivator.php';
    Custom_Plugin_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_custom_plugin' );
register_deactivation_hook( __FILE__, 'deactivate_custom_plugin' );

require plugin_dir_path( __FILE__ ) . 'includes/class-custom-plugin.php';

function run_custom_plugin_wp() {
    $plugin = new Custom_Plugin();
    $plugin->run();
}
run_custom_plugin_wp();