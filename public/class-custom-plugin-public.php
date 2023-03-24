<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Custom_Plugin_Public
{

    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function enqueue_styles(): void
    {
        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/main-public.css', array(), $this->version, 'all' );
    }

    public function enqueue_scripts(): void
    {
        wp_enqueue_media();
        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url( __FILE__ ) . 'js/main-public.js',
            array( 'jquery' ),
            $this->version,
            false
        );
    }
//
//    public function cmk_additional_button() {
//        $product = wc_get_product();
//
//        if ($product->get_stock_status() === 'onbackorder') {
//            echo "<button>Test</button>";
//        }
//
//    }
//
    public function remove_product_description_add_cart_button() {
        $product = wc_get_product();

        if ($product->get_stock_status() === 'onbackorder') {
            remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
        }
    }

//    public function ts_replace_add_to_cart_button( $button, $product ) {
//        if (is_product_category() || is_shop()) {
//            if ($product->get_stock_status() === 'onbackorder') {
//                $button = "<button>Test</button>";
//            }
//        }
//        return $button;
//    }



}