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
        wp_unschedule_hook( 'my_hourly_event');
        wp_clear_scheduled_hook( 'custom_product_update' );
        wp_unschedule_hook( 'custom_product_update');
        if (isset($_POST['api-key']) && !empty($_POST['api-key'])) {
            update_option( 'zoomos_api_key', $_POST['api-key']);
        }

        $arg1 = get_option('zoomos_api_key');
        $arg2 = '';
        update_option('zoomos_offset', 0);
//        wp_schedule_single_event( time(), 'my_hourly_event',  array($arg1, $arg2));
//        do_action('my_hourly_event', $arg1, $arg2);
        do_action('custom_product_update');


//        wp_schedule_event( time(), 'one_min', 'my_hourly_event',  array($arg1, $arg2));
//        do_action('custom_single_product_update',$arg1, 993097);
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
        $capacity = 500;
        $products = wc_get_products($args);
        $total_product = count($products);

        $priceListLink = "https://api.zoomos.by/pricelist?key=";
        $priceLimit = "&offset=";
        $priceLimit .= $total_product . "&limit=$capacity";

        $api_key = get_option('zoomos_api_key');

        if (!empty($api_key)) {
            $json = makeRequest($priceListLink . $api_key . $priceLimit);
            $obj = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (!empty($obj)) {
                foreach ($obj as $value) {
                    try {
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
                            setMetaData($product, $value);

                        } else {
                            $product = wc_get_product($products[0]->id);
                            $old_main_img = $product->get_image_id();
                            wp_delete_attachment($old_main_img);
//                            wp_update_post(array(
//                                'ID' => $products[0]->id,
//                                'post_title' => $value['typePrefix'] . " " . $value['vendor']['name'] . " " . $value['model'],
//                            ));
                            deleteOldMedias($products[0]->id);
                        }
                        setMinData($product, $value);
                        wp_schedule_single_event( time(), 'custom_single_product_update',  array($api_key, $value['id']));
                    } catch (Exception $exception) {
                        $post_id = wp_insert_post(array(
                            'post_title' => $value['typePrefix'] . " " . $value['vendor']['name'] . " " . $value['model'],
                            'post_type' => 'product',
                            'post_status' => 'draft',
                            'post_content' => $exception->getMessage(),
                        ));
                    }
                }
            } else {
                wp_clear_scheduled_hook( 'my_hourly_event' );
                wp_unschedule_hook( 'my_hourly_event');
                wp_clear_scheduled_hook( 'custom_product_update' );
                wp_unschedule_hook( 'custom_product_update');
                wp_schedule_event(time(), 'one_min', 'custom_product_update');
            }
        }
    }

    public function update_products_every_day()
    {
        $capacity = 500;
        $my_offset = get_option('zoomos_offset');

        $priceListLink = "https://api.zoomos.by/pricelist?key=";
        $priceLimit = "&offset=";
        $priceLimit .= $my_offset . "&limit=$capacity";
        $api_key = get_option('zoomos_api_key');
        if (!empty($api_key)) {
            $json = makeRequest($priceListLink . $api_key . $priceLimit);
            $obj = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (!empty($obj)) {
                foreach ($obj as $value) {
                    try {
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
                            setMetaData($product, $value);
                        } else {
                            $product = wc_get_product($products[0]->id);
                            $old_main_img = $product->get_image_id();
//                            $product->set_name($value['typePrefix'] . " " . $value['vendor']['name'] . " " . $value['model']);
                            wp_delete_attachment($old_main_img);
                            deleteOldMedias($products[0]->id);
                        }
                        $get_sale_flag = get_field('sale_from_zoomoz', $products[0]->id, false);
                        if ($get_sale_flag === null || $get_sale_flag === 'Yes') {
                            setMinData($product, $value);
                            wp_schedule_single_event( time(), 'custom_single_product_update',  array($api_key, $value['id']));
                        } else {
                            setMinData($product, $value, false);
                            wp_schedule_single_event( time(), 'custom_single_product_update',  array($api_key, $value['id']));
                        }
                    } catch (Exception $exception) {
                        $post_id = wp_insert_post(array(
                            'post_title' => $value['typePrefix'] . " " . $value['vendor']['name'] . " " . $value['model'],
                            'post_type' => 'product',
                            'post_status' => 'draft',
                            'post_content' => $exception->getMessage(),
                        ));
                    }

                    $my_offset = get_option('zoomos_offset');
                    $my_offset++;
                    update_option('zoomos_offset', $my_offset);
                }
            } else {
                update_option('zoomos_offset', 0);
                wp_unschedule_hook('custom_product_update');
            }
        }
    }

    public function update_single_product($api, $product_id)
    {
        $args = array(
            'post_type' => 'product',
            'meta_key' => 'zoomos_id',
            'meta_value' => $product_id,
            'meta_compare' => '='
        );
        $products = wc_get_products($args);
        $product = wc_get_product($products[0]->id);
        $productLink = "https://api.zoomos.by/item/$product_id?key=$api";
        $json = makeRequest($productLink);
        $obj = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

//        $product->set_name($obj['typePrefix'] . " " . $obj['vendor']['name'] . " " . $obj['model']);
        setNewAttribute($product, $obj['filters']);
        $gallery = [];
        foreach ($obj['images'] as $item) {
            $gallery[] = uploadImage($item);
        }
        $product->set_gallery_image_ids($gallery);
        if ($product->get_short_description() !== $obj['shortDescriptionHTML']) {
            $product->set_short_description($obj['shortDescriptionHTML']);
        }
        if ($product->get_description() !== $obj['fullDescriptionHTML'] . '<br>' . $obj['warrantyInfoHTML']) {
            $product->set_description($obj['fullDescriptionHTML'] . '<br>' . $obj['warrantyInfoHTML']);
        }
        $product->save();
        return null;
    }

}