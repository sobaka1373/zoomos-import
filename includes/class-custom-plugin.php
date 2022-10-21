<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Custom_Plugin {

    protected $loader;
    protected $plugin_name;
    protected $version;

    public function __construct() {
        $this->plugin_name = 'custom-plugin-wp';
        $this->version = '1.0.0';
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    private function load_dependencies() {
        require_once plugin_dir_path(__DIR__) . 'includes/class-custom-plugin-loader.php';
        require_once plugin_dir_path(__DIR__) . 'admin/class-custom-plugin-admin.php';
        require_once plugin_dir_path(__DIR__) . 'public/class-custom-plugin-public.php';
        require_once plugin_dir_path(__DIR__) . 'includes/custom-functions.php';
        $this->loader = new Custom_Plugin_Loader();
    }

    private function define_admin_hooks() {
        $plugin_admin = new Custom_Plugin_Admin( $this->get_plugin_name(), $this->get_version() );
        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
        $this->loader->add_action( 'admin_menu', $plugin_admin, 'register_admin_page');
    }

    private function define_public_hooks() {
        $plugin_public = new Custom_Plugin_Public( $this->get_plugin_name(), $this->get_version() );
        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_public, 'enqueue_styles' );
        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
    }


    public function run() {
        $this->loader->run();
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_loader() {
        return $this->loader;
    }

    public function get_version() {
        return $this->version;
    }
}