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

        wp_clear_scheduled_hook( 'my_hourly_event' );
        wp_unschedule_hook( 'my_hourly_event');
        wp_clear_scheduled_hook( 'custom_product_update' );
        wp_unschedule_hook( 'custom_product_update');
        if (isset($_POST['api-key']) && !empty($_POST['api-key'])) {
            update_option( 'zoomos_api_key', $_POST['api-key']);
            $all_products_prices = ApiRequest::getAllProductPriceRequest($_POST['api-key']);
            update_option('zoomos_total_product', count($all_products_prices));
        }

        $arg1 = get_option('zoomos_api_key');
        $arg2 = '';
        update_option('zoomos_offset', 0);


//        do_action('my_hourly_event', $arg1, $arg2);
//        do_action('custom_product_update');
//        do_action('custom_single_product_update',$arg1, 1982184);


        if (!wp_next_scheduled('custom_product_update')) {
            wp_schedule_event(time(), 'every_minute', 'custom_product_update');
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

    public function do_this_hourly($arg1, $arg2): void
    {
        $api_key = get_option('zoomos_api_key');
        $offset = get_option('zoomos_offset');

        if (!empty($api_key)) {
            $obj = ApiRequest::priceRequest($api_key, $offset, ProductHelper::IMPORT_CAPACITY);
            if (!empty($obj)) {
                foreach ($obj as $value) {
                    $value = (array) $value;
                    if (!ProductChecker::issetProduct($value['id'])) {
                        $product = new WC_Product_Simple();
                        $product->set_name(ProductHelper::generateProductName($value['typePrefix'], $value['vendor']->name, $value['model']));
                        $product->set_sku($value['id']);
                        $product->set_status( 'draft' );
                        $product->set_catalog_visibility( 'visible' );
                        ProductHelper::findAndSetPrice($value,$product);
                        $product->set_image_id(ProductHelper::uploadImage($value));
                        $product->update_meta_data('zoomos_id', $value['id']);
                        $product->update_meta_data('zoomos_category', $value['category']->id);
                        $product->save();
                        ProductHelper::setProductData($value, $product);
                        $product->save();
                        wp_schedule_single_event( time(), 'custom_single_product_update',  array($api_key, $value['id']));
                    } else {
                        continue;
                    }
                    $my_offset = get_option('zoomos_offset');
                    $my_offset++;
                    update_option('zoomos_offset', $my_offset);
                }
            } else {
                wp_clear_scheduled_hook( 'my_hourly_event' );
                wp_unschedule_hook( 'my_hourly_event');
                wp_clear_scheduled_hook( 'custom_product_update' );
                wp_unschedule_hook( 'custom_product_update');
                if (!wp_next_scheduled('custom_product_update')) {
                    wp_schedule_event(time(), 'every_minute', 'custom_product_update');
                }

            }
        }
    }
    public function update_products_every_day(): void
    {
        $tasks = _get_cron_array();
        foreach ($tasks as $task) {
            if (array_key_exists('custom_single_product_update', $task)) {
                return;
            }
        }
        if (wp_next_scheduled('custom_single_product_update')) {
            return;
        }
        $capacity = 300;
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
                            'posts_per_page' => -1,
                            'sku' => $value['id'],
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
                        }
                        $get_sale_flag = get_field('sale_from_zoomoz', $products[0]->id, false);
                        if ($get_sale_flag === null || $get_sale_flag === 'Yes') {
                            setMinData($product, $value);
                        } else {
                            setMinData($product, $value, false);
                        }
                        wp_schedule_single_event( time(), 'custom_single_product_update',  array($api_key, $value['id']));
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
//                    wp_die();
                }
            } else {
                ProductHelper::deleteEmptyProducts();
                update_option('zoomos_offset', 0);
                wp_unschedule_hook('custom_product_update');
            }
        }
    }

    public function update_single_product($api, $product_id)
    {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'sku' => $product_id,
        );
        $products = wc_get_products($args);
        $product = $products[0];

        $productLink = "https://api.zoomos.by/item/$product_id?key=$api";
        $json = makeRequest($productLink);
        $obj = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $product->set_name($obj['typePrefix'] . " " . $obj['vendor']['name'] . " " . $obj['model']);
        setNewAttribute($product, $obj['filters']);

        if ($product->get_short_description() !== $obj['shortDescriptionHTML']) {
            $product->set_short_description($obj['shortDescriptionHTML']);
        }

        $productDescription = $product->get_description();
        if (empty($productDescription)) {
            if ($product->get_description() !== $obj['fullDescriptionHTML'] . '<br><br>' . $obj['warrantyInfoHTML']) {
                $product->set_description($obj['fullDescriptionHTML'] . '<br><br>' . $obj['warrantyInfoHTML']);
            }
        } elseif (str_contains($productDescription, "class='supplier'")) {
            $warrantyInfoHTML = $obj['warrantyInfoHTML'];
            $pos = mb_stripos($warrantyInfoHTML, "\n");
            $warrantyInfoHTML = mb_substr($warrantyInfoHTML, $pos, strlen($warrantyInfoHTML));
            $productDescription = $obj['fullDescriptionHTML'] . '<br><br>' . $productDescription . $warrantyInfoHTML;
            if ($product->get_description() !== $productDescription) {
                $product->set_description($productDescription);
            }
        } else {
            $product->set_description($obj['fullDescriptionHTML'] . '<br><br>' . $obj['warrantyInfoHTML']);
        }
        $product->save();

        $productGalleryIds = $product->get_gallery_image_ids();
        $productGalleryCount = count($productGalleryIds);


        if (!empty($productGalleryIds)) {
            for ($i = 0; $i < $productGalleryCount; $i++)
            {
                $oldImg = get_the_title($productGalleryIds[$i]);
                $api_img_name = $obj['id'] .  "_" . $obj['typePrefix'] . "_" . $obj['vendor']['name'] . "_" . basename($obj['images'][$i]);
                if (str_contains($oldImg, $api_img_name)) {
                    continue;
                } else {
                    wp_delete_attachment($productGalleryIds[$i], true);
                    unset($productGalleryIds[$i]);
                }
            }
        }

        $product->set_gallery_image_ids($productGalleryIds);
        $product->save();

        $productGalleryIds = $product->get_gallery_image_ids();
        $productGalleryCount = count($productGalleryIds);

        if (empty($productGalleryIds) || $productGalleryCount < count($obj['images'])) {
            $gallery = $product->get_gallery_image_ids();
            for ($i = $productGalleryCount, $iMax = count($obj['images']); $i < $iMax; $i++) {
                $gallery[] = uploadImage($obj, false, $i);
            }
            $product->set_gallery_image_ids($gallery);
        }
        $product->save();
        return null;
    }

    public function update_product_image($product_id, $obj, $index)
    {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'sku' => $product_id,
        );
        $products = wc_get_products($args);
        $product = wc_get_product($products[0]->id);
        $productGalleryIds = $product->get_gallery_image_ids();
        $attach_id = uploadImage($obj, false, $index);
        $productGalleryIds[] = $attach_id;
        $product->set_gallery_image_ids($productGalleryIds);
        $product->save();
    }
}