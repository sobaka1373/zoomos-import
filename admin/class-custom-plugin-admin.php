<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Custom_Plugin_Admin
{

    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version )
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function enqueue_styles()
    {
        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/main-admin.css', array(), $this->version, 'all' );
    }

    public function enqueue_scripts()
    {
        wp_enqueue_media();
        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url( __FILE__ ) . 'js/main-admin.js',
            array( 'jquery' ),
            $this->version,
            false
        );
    }

    public function register_admin_page()
    {
        add_menu_page(
            'custom plugin', 'Custom plugin title', 'manage_options',
            'custom-plugin-admin', 'admin_page_open', '', 6
        );
        add_option('zoomos_api_key', '');
        add_option('zoomos_offset', 0);
    }

    /**
     * @throws JsonException
     */
    public function start_import()
    {

        wp_clear_scheduled_hook( 'my_hourly_event' );
        wp_unschedule_hook( 'custom_product_update');
        if (isset($_POST['api-key']) && !empty($_POST['api-key'])) {
            update_option( 'zoomos_api_key', $_POST['api-key']);
        }
//        elseif (isset($arg1)) {
//            update_option( 'zoomos_api_key', $arg1);
//        }
        $arg1 = get_option('zoomos_api_key');
        $arg2 = '';
        update_option('zoomos_offset', 0);
//        wp_schedule_single_event( time(), 'my_hourly_event',  array($arg1, $arg2));
//        do_action('my_hourly_event', $arg1, $arg2);

//        do_action('custom_product_update');

        wp_schedule_event( time(), 'five_min', 'my_hourly_event',  array($arg1, $arg2));
    }

    public function get_product_count()
    {
        $priceListLink = "https://api.zoomos.by/pricelist?key=";
        $api_key = get_option('zoomos_api_key');
        $priceLimit = '&supplierInfo=0&warrantyInfo=0&competitorInfo=0&deliveryInfo=0';
        $json = file_get_contents($priceListLink . $api_key . $priceLimit );
        $obj = json_decode($json);
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
        );
        $products = wc_get_products($args);
        $total_product = count($products) / count($obj) * 100;
        echo (int)$total_product;
        wp_die();
    }

    public function start_cron()
    {
        echo 1;
        wp_die();
    }

    /**
     * @throws WC_Data_Exception
     */
    public function do_this_hourly($arg1, $arg2)
    {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
        );
        $capacity = 25;
        $products = wc_get_products($args);
        $total_product = count($products);

        if ($total_product < 100) {
//            $offset = ($total_product / $capacity) * $capacity;
            $priceListLink = "https://api.zoomos.by/pricelist?key=";
            $priceLimit = "&offset=";
            $priceLimit .= $total_product . "&limit=$capacity";
            $productLink = "https://api.zoomos.by/item/{zoomos_product_id}?key=";


            $api_key = get_option('zoomos_api_key');

            if (!empty($api_key)) {

                $json = file_get_contents($priceListLink . $api_key . $priceLimit );
                $obj = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

                foreach ($obj as  $value) {
                    $args = array(
                        'post_type' => 'product',
                        'meta_key' => 'zoomos_id',
                        'meta_value' => $value['id'],
                        'meta_compare' => '='
                    );
                    $products = wc_get_products($args);

                    if (empty($products)) {
                        $post_id = wp_insert_post(array(
                            'post_title' => $value['typePrefix'] . " " . $value['vendor']['name'] . " " . $value['model'],
                            'post_type' => 'product',
                            'post_status' => 'draft',
                            'post_content' => '',
                        ));
                        $product = wc_get_product($post_id);
                        $product->set_sku($value['id']);

                    } else {
                        $product = wc_get_product( $products[0]->id );
                        $old_main_img = $product->get_image_id();
                        wp_delete_attachment( $old_main_img );
                        wp_update_post(array(
                            'ID' => $products[0]->id,
                            'post_title' => $value['typePrefix'] . " " . $value['vendor']['name'] . " " . $value['model'],
                        ));
                        deleteOldMedias($products[0]->id);
                    }
                    productSetData($product, $value, $api_key);
                }

            }
        } else {
            wp_unschedule_hook( 'my_hourly_event' );
            wp_schedule_event( time(), 'five_min', 'custom_product_update');
        }


    }

    public function update_products_every_day()
    {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
        );
        $capacity = 25;
        $products = wc_get_products($args);
        $total_product = count($products);
        $my_offset = get_option('zoomos_offset');

        if ($my_offset < 100) {
            $priceListLink = "https://api.zoomos.by/pricelist?key=";
            $priceLimit = "&offset=";
            $priceLimit .= $my_offset . "&limit=$capacity";
            $productLink = "https://api.zoomos.by/item/{zoomos_product_id}?key=";

            $api_key = get_option('zoomos_api_key');

            if (!empty($api_key)) {

                $json = file_get_contents($priceListLink . $api_key . $priceLimit );
                $obj = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

                foreach ($obj as  $value) {
                    $args = array(
                        'post_type' => 'product',
                        'meta_key' => 'zoomos_id',
                        'meta_value' => $value['id'],
                        'meta_compare' => '='
                    );
                    $products = wc_get_products($args);

                    if (empty($products)) {
                        $post_id = wp_insert_post(array(
                            'post_title' => $value['typePrefix'] . " " . $value['vendor']['name'] . " " . $value['model'],
                            'post_type' => 'product',
                            'post_status' => 'draft',
                            'post_content' => '',
                        ));
                        $product = wc_get_product($post_id);
                        $product->set_sku($value['id']);

                    } else {
                        $product = wc_get_product( $products[0]->id );
                        $old_main_img = $product->get_image_id();
                        $product->set_name($value['typePrefix'] . " " . $value['vendor']['name'] . " " . $value['model']);
                        wp_delete_attachment( $old_main_img );
                        deleteOldMedias($products[0]->id);
                    }
                    productSetData($product, $value, $api_key);
                    $my_offset = get_option('zoomos_offset');
                    $my_offset++;
                    update_option('zoomos_offset', $my_offset);
                }

            }
        } else {
            update_option('zoomos_offset', 0);
            wp_unschedule_hook( 'custom_product_update');
        }

    }
}