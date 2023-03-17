<?php

use entity\ApiRequest;
use entity\ProductHelper;
use entity\ProductChecker;
use entity\Product;

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

    public function enqueue_styles(): void
    {
        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/main-admin.css', array(), $this->version, 'all' );
    }

    public function enqueue_scripts(): void
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

    public function register_admin_page(): void
    {
        add_menu_page(
            'custom plugin', 'Custom plugin title', 'manage_options',
            'custom-plugin-admin', 'admin_page_open', '', 6
        );
        add_option('zoomos_api_key', '');
        add_option('zoomos_offset', 0);
        add_option('zoomos_total_product', 0);
    }

    /**
     * @throws JsonException
     */
    public function start_import(): void
    {

        clearCronJobs();
        if (isset($_POST['api-key']) && !empty($_POST['api-key'])) {
            update_option( 'zoomos_api_key', $_POST['api-key']);
            $all_products_prices = ApiRequest::getAllProductPriceRequest($_POST['api-key']);
            update_option('zoomos_total_product', count($all_products_prices));
        }

        update_option('zoomos_offset', 0);

        if (!wp_next_scheduled('custom_product_update')) {
            wp_schedule_event(time(), 'one_min', 'custom_product_update');
        }

        wp_die();
    }

    public function get_product_count()
    {
        $import_progress = ['zoomos_total_product' =>  get_option('zoomos_total_product'), 'zoomos_offset' => get_option('zoomos_offset')];
        $import_progress = json_encode($import_progress, JSON_THROW_ON_ERROR);
        echo $import_progress;
        wp_die();
    }

    public function start_cron()
    {
        echo 1;
        wp_die();
    }

    /**
     * @throws JsonException
     * @throws WC_Data_Exception
     */
    public function update_products_every_day(): void
    {
        if (!configCronJobs()) {
            return;
        }

        $api_key = get_option('zoomos_api_key');
        $offset = get_option('zoomos_offset');

        if (empty($api_key)) {
            return;
        }

        $obj = ApiRequest::priceRequest($api_key, $offset, 300);

        if (empty($obj)) {
            finishImportProducts();
            return;
        }

        foreach ($obj as $value) {
            $my_offset = get_option('zoomos_offset');
            $my_offset++;
            update_option('zoomos_offset', $my_offset);


            $products = findProduct($value);

            if (empty($products)) {
                $product = createProduct($value);
            } else {
                $product = wc_get_product($products[0]->id);
            }

            $get_sale_from_api = checkSales($products[0]->id);

            setMinData($product, $value, $get_sale_from_api);
            $product->update_meta_data('zoomos_updated', 'Yes');
            $product->save();
            wp_schedule_single_event( time(), 'custom_single_product_update',  array($api_key, $value['id']));
        }
    }

    /**
     * @throws JsonException
     */
    public function update_single_product($api, $product_id)
    {
        $product = getProduct($product_id);

        $obj = ApiRequest::productRequest($api, $product_id);

        setTypePrefix($product, $obj);

        setNewAttribute($product, $obj['filters']);

        setMetaAttribute($product, $obj['filters']);

        setShortDescription($product, $obj);

        setDescription($product, $obj);

        setUpdated($product);

        setGallery($product, $obj);

        return null;
    }

    public function remove_meta_date()
    {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => 200,
            'meta_key'         => 'zoomos_updated',
            'meta_value'       => 'Yes',
        );

        $products = get_posts($args);
        if (!empty($products)) {
            foreach ($products as $product) {
                $product = wc_get_product($product->ID);
                $product->delete_meta_data('zoomos_updated');
                $product->save();
            }
        } else {
            wp_clear_scheduled_hook( 'custom_remove_meta_date' );
            wp_unschedule_hook( 'custom_remove_meta_date');
        }
    }

    public function single_product_func() {

        clearCronJobs();

        $all_products_prices = ApiRequest::getAllProductPriceRequest(get_option('zoomos_api_key'));
        update_option('zoomos_total_product', count($all_products_prices));

        update_option('zoomos_offset', 0);

        if (!wp_next_scheduled('my_hourly_event')) {
            wp_schedule_event(time(), 'five_min', 'my_hourly_event', array(true, false));
        }
        wp_die();

    }

    public function single_product_price_func() {

        clearCronJobs();

        $all_products_prices = ApiRequest::getAllProductPriceRequest(get_option('zoomos_api_key'));
        update_option('zoomos_total_product', count($all_products_prices));

        update_option('zoomos_offset', 0);

        if (!wp_next_scheduled('my_hourly_event')) {
            wp_schedule_event(time(), 'five_min', 'my_hourly_event', array(false, true));
        }
        wp_die();
    }

    public function single_product_gallery_func() {

        clearCronJobs();

        $all_products_prices = ApiRequest::getAllProductPriceRequest(get_option('zoomos_api_key'));
        update_option('zoomos_total_product', count($all_products_prices));

        update_option('zoomos_offset', 0);

        if (!wp_next_scheduled('my_hourly_event')) {
            wp_schedule_event(time(), 'five_min', 'my_hourly_event', array(true, true));
        }
        wp_die();
    }

    public function do_this_hourly($arg1, $arg2): void
    {
        $api_key = get_option('zoomos_api_key');
        $offset = get_option('zoomos_offset');

        if (empty($api_key)) {
            return;
        }

        $obj = ApiRequest::priceRequest($api_key, $offset, 300);

        if (empty($obj)) {
            update_option('zoomos_offset', 0);
            setBackordersAfterImport();
            wp_unschedule_hook('my_hourly_event');
            return;
        }

        foreach ($obj as $value) {
            $my_offset = get_option('zoomos_offset');
            $my_offset++;
            update_option('zoomos_offset', $my_offset);


            $products = findProduct($value);

            if (empty($products)) {
                continue;
            }

            $product = wc_get_product($products[0]->id);

            if ($arg1) {
                $get_sale_from_api = checkSales($products[0]->id);

                setPrice($product, $value, $get_sale_from_api);
            }

            if ($arg2) {
                $product->set_manage_stock(true);
                if ($value['status'] === 3 || $value['status'] === 0) {
                    $product->set_stock_status('outofstock');
                    $product->set_backorders('no');
                }
                if ($value['status'] === 2) {
                    $product->set_stock_status('outofstock');
                    $product->set_backorders('yes');
                }
                if ($value['status'] === 1) {
                    if (!isset($value['supplierInfo']['quantity']) ||  getDigits($value['supplierInfo']['quantity']) === 0 || empty($value['supplierInfo']['quantity'])) {
                        $product->set_stock_quantity(10);
                    } else {
                        $product->set_stock_quantity(getDigits($value['supplierInfo']['quantity']));
                    }
                }
            }

            $product->update_meta_data('zoomos_updated', 'Yes');
            $product->save();
        }
    }

    public function cron_add_five_min( $schedules ) {
        $schedules['five_min'] = array(
            'interval' => 60 * 2,
            'display' => 'Раз в 5 минут'
        );
        return $schedules;
    }

    public function cron_add_one_min( $schedules ) {
        $schedules['one_min'] = array(
            'interval' => 60,
            'display' => 'Раз в минуту'
        );
        return $schedules;
    }

}
