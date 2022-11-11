<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Custom_Plugin_Admin
{

    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function enqueue_styles() {
        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/main-admin.css', array(), $this->version, 'all' );
    }

    public function enqueue_scripts() {
        wp_enqueue_media();
        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url( __FILE__ ) . 'js/main-admin.js',
            array( 'jquery' ),
            $this->version,
            false
        );
    }

    public function register_admin_page() {
        add_menu_page(
            'custom plugin', 'Custom plugin title', 'manage_options',
            'custom-plugin-admin', 'admin_page_open', '', 6
        );
        add_option('zoomos_api_key', '');
//        add_filter( 'option_page_capability_'.'custom-plugin-admin', 'my_page_capability' );
    }

    /**
     * @throws JsonException
     */
    public function start_import() {

        wp_clear_scheduled_hook( 'my_hourly_event' );
        $arg1 = $_POST['api-key'];
        $arg2 = (int)$_POST['api-limit'];

        wp_schedule_single_event( time(), 'my_hourly_event',  array($arg1, $arg2));
//        do_action('my_hourly_event', $arg1, $arg2);

    }

    public function start_cron() {
        echo 1;
        wp_die();
    }

    /**
     * @throws JsonException
     * @throws WC_Data_Exception
     */
    public function do_this_hourly($arg1, $arg2)
    {
        $priceListLink = "https://api.zoomos.by/pricelist?key=";
        $priceLimit = "&limit=";
        $productLink = "https://api.zoomos.by/item/{zoomos_product_id}?key=";
        $postLimit = 1;
        if (isset($_POST['api-limit'])) {
            $postLimit = (int)$_POST['api-limit'];
        }
        elseif (isset($arg2)) {
            $postLimit = (int)$arg2;
        }

        if (isset($_POST['api-key']) && !empty($_POST['api-key'])) {
            update_option( 'zoomos_api_key', $_POST['api-key']);
        }
        elseif (isset($arg1)) {
            update_option( 'zoomos_api_key', $arg1);
        }
        $api_key = get_option('zoomos_api_key');

        if (!empty($api_key)) {

            $json = file_get_contents($priceListLink . $api_key . $priceLimit . $postLimit);
            $obj = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            foreach ($obj as $key => $value) {
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

                    productSetData($product, $value, $api_key);
                } else {
                    $productLink = "https://api.zoomos.by/item/{zoomos_product_id}?key=";
                    $productLink = str_replace('{zoomos_product_id}', $products[0]->sku, $productLink);
                    $json = file_get_contents($productLink . $api_key );
                    $obj_additional_data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                    $product = wc_get_product( $products[0]->id );

                    wp_update_post(array(
                        'ID' => $products[0]->id,
                        'post_title' => $value['typePrefix'] . " " . $value['vendor']['name'] . " " . $value['model'],
                    ));

                    $product->set_regular_price($value['price']);
                    $product->set_price($value['price']);
                    $product->set_sale_price($product->get_sale_price());
                    if ($value['status'] === 3) {
                        $product->set_stock_status('outofstock');
                    }
                    if ($value['status'] === 2) {
                        $product->set_stock_status('outofstock');
                        $product->set_backorders('yes');
                    }
                    if ($value['status'] === 1) {
                        $product->set_stock_quantity($value['supplierInfo']['quantity']);
                    }
                    $product->set_short_description( $obj_additional_data['shortDescriptionHTML']);
                    $product->set_description($obj_additional_data['fullDescriptionHTML'] . '<br>' . $obj_additional_data['warrantyInfoHTML']);
                    $gallery = [];
                    foreach ($obj_additional_data['images'] as $item) {
                        $gallery[] = uploadImage($item);
                    }
                    $product->set_gallery_image_ids($gallery);
                    if (empty($product->get_image_id())) {
                        $imageId = uploadImage($value['image']);
                        $product->set_image_id($imageId);
                    }

                    $product->save();
                }
            }

        }
    }

}