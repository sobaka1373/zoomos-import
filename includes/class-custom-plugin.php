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

    private function load_dependencies(): void
    {
        require_once plugin_dir_path(__DIR__) . 'includes/class-custom-plugin-loader.php';
        require_once plugin_dir_path(__DIR__) . 'admin/class-custom-plugin-admin.php';
        require_once plugin_dir_path(__DIR__) . 'public/class-custom-plugin-public.php';
        require_once plugin_dir_path(__DIR__) . 'includes/custom-functions.php';
        require_once plugin_dir_path(__DIR__) . 'includes/acf/sale_checkbox.php';
//        require_once plugin_dir_path(__DIR__) . 'includes/acf/brands_table.php';
//        require_once plugin_dir_path(__DIR__) . 'includes/acf/brand_page.php';

        require_once plugin_dir_path(__DIR__) . 'includes/entity/ApiRequest.php';
        require_once plugin_dir_path(__DIR__) . 'includes/entity/Product.php';
        require_once plugin_dir_path(__DIR__) . 'includes/entity/ProductHelper.php';
        require_once plugin_dir_path(__DIR__) . 'includes/entity/ProductChecker.php';
        $this->loader = new Custom_Plugin_Loader();
    }

    private function define_admin_hooks() {
        $plugin_admin = new Custom_Plugin_Admin( $this->get_plugin_name(), $this->get_version() );
        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
        $this->loader->add_action( 'admin_menu', $plugin_admin, 'register_admin_page');
        $this->loader->add_action( 'wp_ajax_start_import', $plugin_admin , 'start_import' );
        $this->loader->add_action( 'wp_ajax_start_cron', $plugin_admin , 'start_cron' );
        $this->loader->add_action( 'wp_ajax_get_product_count', $plugin_admin , 'get_product_count' );
        $this->loader->add_action( 'my_hourly_event', $plugin_admin, 'do_this_hourly', 10, 2);
        $this->loader->add_action( 'custom_single_product_update', $plugin_admin, 'update_single_product', 10, 2);
        $this->loader->add_action( 'custom_update_product_image', $plugin_admin, 'update_product_image', 10, 3);

        $this->loader->add_action('custom_product_update', $plugin_admin, 'update_products_every_day');
        $this->loader->add_action( 'custom_single_image_product_update', $plugin_admin, 'update_only_image', 10, 2);
        $this->loader->add_action( 'custom_remove_meta_date', $plugin_admin, 'remove_meta_date');
        $this->loader->add_action('wp_ajax_single_product_func', $plugin_admin, 'single_product_func');
        $this->loader->add_action('wp_ajax_single_product_price_func', $plugin_admin, 'single_product_price_func');
        $this->loader->add_action('wp_ajax_single_product_gallery_func', $plugin_admin, 'single_product_gallery_func');
        $this->loader->add_filter( 'cron_schedules',  $plugin_admin, 'cron_add_five_min' );
        $this->loader->add_filter( 'cron_schedules',  $plugin_admin, 'cron_add_one_min' );
    }

    private function define_public_hooks() {
        $plugin_public = new Custom_Plugin_Public( $this->get_plugin_name(), $this->get_version() );
        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_public, 'enqueue_styles' );
        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
//        $this->loader->add_action( 'woocommerce_single_product_summary', $plugin_public, 'cmk_additional_button', 40 );
        $this->loader->add_action( 'woocommerce_single_product_summary', $plugin_public, 'remove_product_description_add_cart_button' );

//        $this->loader->add_filter( 'woocommerce_loop_add_to_cart_link', $plugin_public,'ts_replace_add_to_cart_button', 10, 2 );
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
