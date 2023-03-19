<?php

use entity\ProductHelper;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function admin_page_open()
{
    ob_start();
    ?>
    <div class="container">
      <p>Import options</p>
      <label>
        API key
        <input type="text" id="api-key-input" class="api-key-input" name="api-key" value="<?php echo get_option('zoomos_api_key'); ?>" />
      </label>
      <input type="submit" class="button button-custom-import" name="insert" value="Import" />
      <p class="hide-message">Create cron task</p>
      <p class="hide-message-cron">Cron start</p>
      <p class="hide-message-404">Import already working</p>
      <p class="hide-message-error">Import completed with error</p>
      <span class="spinner"></span>
      <div id="myProgress">
        <div class="container">
          <span id="offset"></span>
          /
          <span id="total"></span>
        </div>
      </div>
<!--      <input type="button" class="button button-custom-import" id="clear_products" value="Clear" />-->
      <br>
      <input type="button" class="button button-update-single-product" id="single_product" value="Update Price" />
      <input type="button" class="button button-update-single-product-price" id="single_product_price" value="Update Status" />
      <input type="button" class="button button-update-single-product-gallery" id="single_product_gallery" value="Update Price and Status" />
    </div>
    <?php
    $output = ob_get_clean();
    echo $output;
}

function getDigits($str)
{
    $strWithoutChars = preg_replace('/[^0-9]/', '', $str);
    return (int)$strWithoutChars;
}

function productSetData($product, $value, $api_key, $get_sale_flag = true)
{
   if ($product->get_name() !== $value['typePrefix'] . " " . $value['vendor']['name'] . " " . $value['model']) {
    $product->set_name($value['typePrefix'] . " " . $value['vendor']['name'] . " " . $value['model']);
   }

    if ($get_sale_flag) {
        $product->set_price($value['price']);
        $product->set_regular_price($value['price']);
        if (isset($value['supplierInfo']['isWholesalePrice']) && isset($value['priceOld']) && $value['supplierInfo']['isWholesalePrice'] == true) {
            $product->set_price($value['priceOld']);
            $product->set_regular_price($value['priceOld']);
            $product->set_sale_price($value['price']);
        }
    }

    $product->set_manage_stock(true);
    if ($value['status'] === 3) {
        $product->set_stock_status('outofstock');
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
    $imageId = uploadImage($value['image']);
    $product->set_image_id($imageId);
    $product->update_meta_data('zoomos_id', $value['id']);
    $product->update_meta_data('zoomos_category', $value['category']['id']);

    $productLink = "https://api.zoomos.by/item/{zoomos_product_id}?key=";
    $productLink = str_replace('{zoomos_product_id}', $value['id'], $productLink);
    $json = file_get_contents($productLink . $api_key );
    $obj_additional_data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

    setNewAttribute($product, $obj_additional_data['filters']);
    $gallery = [];
    foreach ($obj_additional_data['images'] as $item) {
        $gallery[] = uploadImage($item);
    }
    $product->set_gallery_image_ids($gallery);
    $product->set_short_description($obj_additional_data['shortDescriptionHTML']);
    $product->set_description($obj_additional_data['fullDescriptionHTML'] . '<br>' . $obj_additional_data['warrantyInfoHTML']);

    $product->save();
}

function setMinData($product, $value, $get_sale_flag = true)
{
    $product_name = $value['typePrefix'] . " " . $value['vendor']['name'] . " " . $value['model'];
    if (isset($value['modelCode']) && !empty($value['modelCode'])) {
        $product_name .= " " . $value['modelCode'];
    }

    if ($product->get_name() !== $product_name) {
        $product->set_name($product_name);
    }

    setPrice($product, $value, $get_sale_flag);

    setWarranty($product, $value);

    $product->save();

    try {
        setBrand($product, $value);
    } catch (Exception $exception) {}

    setWholeSalePrice($product, $value);

    setProductStatus($product, $value);
    $oldImg = wp_get_attachment_url($product->get_image_id());
    $filename = $value['id'] .  "_" . $value['typePrefix'] . "_" . $value['vendor']['name'] . "_";
    $filename = str_replace(array(" ", "&", "/"), "_", $filename);
    if (!$oldImg ||
        !str_contains($oldImg, $filename)) {
        deleteOldMedias($product->get_id());
        $imageId = uploadImage($value);
    }

    $attachment = wp_get_attachment_by_post_name( sanitize_file_name( $oldImg ) );
    if (!$attachment) {
        $imageId = uploadImage($value);
    }

    if (is_int($imageId) || is_float($imageId)) {
        $product->set_image_id( isset( $imageId ) ? $imageId : "" );
    }
    $product->save();
}

function setPrice($product, $value, $get_sale_flag)
{
    if ($get_sale_flag) {
        if ($product->get_price() !== $value['price']) {
            $product->set_price($value['price']);
            $product->set_regular_price($value['price']);
        }

        if (isset($value['supplierInfo']['isWholesalePrice'], $value['priceOld']) && $value['supplierInfo']['isWholesalePrice']) {
            if ($product->get_price() !== $value['priceOld']) {
                $product_price = (float)$value['priceOld'];
                $product->set_price($product_price);
                $product->set_regular_price($product_price);
            }
            if ($product->get_sale_price() !== $value['price']) {
                $product_sale_price = (float)$value['price'];
                $product->set_sale_price($product_sale_price);
            }
        } else {
            $product->set_sale_price('');
        }
    } else if (isset($value['priceOld']) ) {
        if($product->get_sale_price() !== null) {
            $product->set_price($value['priceOld']);
            $product->set_regular_price($value['priceOld']);
        }
    } elseif ($product->get_sale_price() !== null) {
        $product->set_price($value['price']);
        $product->set_regular_price($value['price']);
    }
}

function setWarranty($product, $value)
{
    if (isset($value['supplierInfo']['warrantyMonth'])) {
        $statusId = (int)$value['supplierInfo']['warrantyMonth'];
        if ($statusId === 1) {
            update_post_meta($product->get_id(), 'warranty_month', "Гарантия: " . $value['supplierInfo']['warrantyMonth'] . " месяц");
        } elseif ($statusId >= 1 && $statusId <= 5) {
            update_post_meta($product->get_id(), 'warranty_month', "Гарантия: " . $value['supplierInfo']['warrantyMonth'] . " месяца");
        } elseif ($statusId >= 5) {
            update_post_meta($product->get_id(), 'warranty_month', "Гарантия: " . $value['supplierInfo']['warrantyMonth'] . " месяцев");
        }
    }
}


function setWholeSalePrice($product, $value)
{
    $supplierInfoId = $value['supplierInfo']['id'] ?? null;

    $wholeSalePrice = null;
    if ($supplierInfoId !== null && isset($value['otherSuppliers'])) {
      foreach ($value['otherSuppliers'] as $supplier) {
        if (($supplier['id'] === $supplierInfoId) && isset($supplier['price'])) {
            $wholeSalePrice = $supplier['price'];
        }
      }
    }

    if ($wholeSalePrice !== null) {
        update_post_meta($product->get_id(), 'wholesale_customer_wholesale_price', $wholeSalePrice);
    }
}

/**
 * @throws Exception
 */
function
uploadImage($product, $singleImg = true, $number = 0): int
{
    if ($singleImg) {
        $imageUrl = $product['image'];
    } else {
        $imageUrl = $product['images'][$number];
    }
    $image_url = $imageUrl . ".png";
    $upload_dir = wp_upload_dir();
    $image_data = file_get_contents($image_url);
    $result = $product['id'] . "_" . $product['typePrefix'] . "_" . $product['vendor']['name'] . "_";

    $filename = $result . basename($image_url);
    $filename = str_replace(array(" ", "&", "/"), "_", $filename);
    $attachment = wp_get_attachment_by_post_name( sanitize_file_name( $filename ) );
    if ($attachment) {
        if (wp_get_attachment_url($attachment->ID)) {
            return $attachment->ID;
        }
        wp_delete_attachment($attachment->ID, true);
    }

    if ( wp_mkdir_p( $upload_dir['path'] ) ) {
        $file = $upload_dir['path'] . '/' . $filename;
    }
    else {
        $file = $upload_dir['basedir'] . '/' . $filename;
    }

    file_put_contents( $file, $image_data );
    $wp_filetype = wp_check_filetype( $filename, null );
    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => sanitize_file_name( $filename ),
        'post_content' => '',
        'post_status' => 'inherit'
    );

    $attach_id = wp_insert_attachment( $attachment, $file );
    require_once( ABSPATH . 'wp-admin/includes/image.php' );
    $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
    wp_update_attachment_metadata( $attach_id, $attach_data );
    return $attach_id;
}

function setAttribute($product, $dataArray)
{
    define( 'WC_DELIMITER', '|' );
    $attributes = [];
    foreach ($dataArray as $item) {
        $attribute = new WC_Product_Attribute();
        $attribute->set_id(0);
        $attribute->set_name($item['name']);
        $options_string = '';
        foreach ($item['values'] as $key => $value) {
            if ($key === 0) {
                $options_string .= $value['name'];
            } else {
                $options_string .= " | ";
                $options_string .= $value['name'];
            }
        }
        $attribute->set_options(explode(WC_DELIMITER, $options_string));
        $attribute->set_visible(true);
        $attribute->set_variation(true);
        $attributes[] = $attribute;
    }
    $product->set_attributes($attributes);
    $product->save();
}

function deleteOldMedias($product_id)
{
    $product = wc_get_product( $product_id );
    $old_main_img = $product->get_image_id();
    if ($old_main_img) {
        wp_delete_attachment( $old_main_img );
    }
    $old_gallery = $product->get_gallery_image_ids();
    foreach ($old_gallery as $item) {
        wp_delete_attachment( $item );
    }
    $product->save();
}

function setNewAttribute($product, $dataArray)
{
    $data = [];
    foreach ($dataArray as $item) {
        $rus_name = $item['name'];
        $taxonomy_name = generateSlug($item['name']);
        $taxonomy_name = strtolower($taxonomy_name);
        $taxonomy_name = mb_strimwidth($taxonomy_name, 0, 20, "");
        $taxonomy_name = $item['id'] . "_" . $taxonomy_name;

        wc_create_attribute(array(
            'name' => $rus_name,
            'slug' => $taxonomy_name
        ));
        register_taxonomy( 'pa_' . $taxonomy_name, 'product');
        $product_id = $product->get_id();
        $terms = [];
        foreach ($item['values'] as $value) {
            $terms[] = $value['name'];
            $data['pa_' . $taxonomy_name] =
                [
                    'name' => 'pa_' . $taxonomy_name,
                    'value' => '',
                    'is_visible' => '1',
                    'is_taxonomy' => '1'
                ];
        }
        wp_set_object_terms($product_id, $terms, 'pa_' . $taxonomy_name);
    }
    update_post_meta($product_id, '_product_attributes', $data);
}

function setMetaAttribute($product, $dataArray)
{
    foreach ($dataArray as $item) {
      if ($item['type'] === 'numeric') {
          $name = generateSlug($item['name']);
          $name = strtolower($name);
          $name = mb_strimwidth($name, 0, 20, "");
          $name = $item['id'] . "_" . $name;
        if (isset($item['values'][0]['name']) && !empty($item['values'][0]['name'])) {
            $product->update_meta_data($name, $item['values'][0]['name']);
        }
      }
    }
    $product->save();
}

function generateSlug($string) {

    $rus=array('А','Б','В','Г','Д','Е','Ё','Ж','З','И','Й','К','Л','М','Н','О','П','Р','С','Т','У','Ф','Х','Ц','Ч','Ш','Щ','Ъ','Ы','Ь','Э','Ю','Я','а','б','в','г','д','е','ё','ж','з','и','й','к','л','м','н','о','п','р','с','т','у','ф','х','ц','ч','ш','щ','ъ','ы','ь','э','ю','я',' ');
    $lat=array('a','b','v','g','d','e','e','gh','z','i','y','k','l','m','n','o','p','r','s','t','u','f','h','c','ch','sh','sch','y','y','y','e','yu','ya','a','b','v','g','d','e','e','gh','z','i','y','k','l','m','n','o','p','r','s','t','u','f','h','c','ch','sh','sch','y','y','y','e','yu','ya',' ');

    $string = str_replace(array(...$rus, '-'), array(...$lat, ''), $string);
    return preg_replace('/[^A-Za-z0-9-]+/', '_', $string);
}

/**
 * Handles making a cURL request
 *
 * @param string $url         URL to call out to for information.
 * @param bool   $callDetails Optional condition to allow for extended
 *   information return including error and getinfo details.
 *
 * @return bool|string $returnGroup cURL response and optional details.
 */
function makeRequest($url)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

function setMetaData($product, $data)
{
    $product->update_meta_data('zoomos_id', $data['id']);
    $product->update_meta_data('zoomos_category', $data['category']['id']);
    $product->save();
}

function setProductStatus($product, $data)
{
    $product->set_manage_stock(true);
    if ($data['status'] === 3 || $data['status'] === 0) {
        $product->set_stock_status('outofstock');
        $product->set_backorders('no');
    }
    if ($data['status'] === 2) {
        $product->set_stock_status('outofstock');
        $product->set_backorders('yes');
    }
    if ($data['status'] === 1) {
        if (!isset($data['supplierInfo']['quantity']) ||  getDigits($data['supplierInfo']['quantity']) === 0 || empty($data['supplierInfo']['quantity'])) {
            $product->set_stock_quantity(10);
        } else {
            $product->set_stock_quantity(getDigits($data['supplierInfo']['quantity']));
        }
    }
    $product->save();
}

function deleteDuplicateProduct() {
  global $wpdb;
  $results = $wpdb->get_results("SELECT ID FROM {$wpdb->get_blog_prefix()}posts WHERE post_content = '' AND post_type = 'product'");
  foreach ($results as $item) {
      wp_delete_post($item->ID, TRUE);
  }
  $results = $wpdb->get_results("SELECT ID FROM {$wpdb->get_blog_prefix()}posts WHERE post_content = 'Invalid or duplicated SKU.' AND post_type = 'product'");
    foreach ($results as $item) {
        wp_delete_post($item->ID, TRUE);
    }
}

function setBrand($product, $data)
{
  $brands_array = [];
  $product_id = $product->get_id();

    $terms = get_terms(array(
        'taxonomy' => 'yith_product_brand',
        'hide_empty' => false,
    ));

    if (!empty($terms)) {
      foreach ($terms as $term) {
        if (strcasecmp($term->name, $data['vendor']['name']) == 0) {
            $brands_array[] = $term->term_id;
            wp_set_object_terms($product_id, $brands_array, YITH_WCBR::$brands_taxonomy );
        }
      }
    }
}

function setBackordersAfterImport()
{
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => 1000,
        'meta_query' => array(
            array(
                'key' => 'zoomos_updated',
                'compare' => 'NOT EXISTS' // this should work...
            ),
        )
    );
    $products = query_posts($args);
    foreach ($products as $product) {
        $product = get_product($product->ID);
        $product->set_stock_quantity(0);
        $product->set_stock_status('outofstock');
        $product->set_backorders('no');
        $product->set_sale_price('');
        $product->save();
    }
    if (!wp_next_scheduled('custom_remove_meta_date')) {
        wp_schedule_event(time(), 'one_min', 'custom_remove_meta_date');
    }
}

function setShortDescription($product, $obj)
{
    if ($product->get_short_description() !== $obj['shortDescriptionHTML']) {
        $product->set_short_description($obj['shortDescriptionHTML']);
    }
    $product->save();
}

function setDescription($product, $obj)
{
    $meta = get_post_meta($product->get_id(), "warranty_month");

    if (!empty($meta)) {
        $warrantyInfoHTML = $obj['warrantyInfoHTML'];
        $pos = mb_stripos($warrantyInfoHTML, "\n");
        $warrantyInfoHTML = mb_substr($warrantyInfoHTML, $pos, strlen($warrantyInfoHTML));
        $product->set_description($obj['fullDescriptionHTML'] . '<br><br>' . $meta[0] . '<br>' . $warrantyInfoHTML);
    } else {
        $product->set_description($obj['fullDescriptionHTML'] . '<br><br>' . $obj['warrantyInfoHTML']);
    }
    $product->save();
}

function setUpdated($product)
{
    $product->update_meta_data('zoomos_updated', 'Yes');
    $product->save();
}

function setGallery($product, $obj)
{
    array_shift($obj['images']);
    $productGalleryIds = $product->get_gallery_image_ids();
    foreach ($productGalleryIds as $key => $galleryId) {
        if (!wp_get_attachment_url($galleryId)) {
            unset($productGalleryIds[$key]);
            wp_delete_attachment($galleryId, true);
        }
    }
    $productGalleryCount = count($productGalleryIds);


    if (!empty($productGalleryIds)) {
        for ($i = 0; $i < $productGalleryCount; $i++)
        {
            $oldImg = get_the_title($productGalleryIds[$i]);
            $api_img_name = $obj['id'] .  "_" . $obj['typePrefix'] . "_" . $obj['vendor']['name'] . "_" . basename($obj['images'][$i]);
            $api_img_name = str_replace(array(" ", "&", "/"), "_", $api_img_name);
            $api_img_name = sanitize_title($api_img_name);
            if (str_contains($oldImg, $api_img_name)) {
                continue;
            } else {
                wp_delete_attachment($productGalleryIds[$i], true);
                unset($productGalleryIds[$i]);
            }
        }
    }

    if (empty($productGalleryIds)) {
        $product->set_gallery_image_ids(array());
    }
    $product->save();

    if (empty($productGalleryIds) || $productGalleryCount < count($obj['images'])) {
        $gallery = $productGalleryIds;
        for ($i = $productGalleryCount, $iMax = count($obj['images']); $i < $iMax; $i++) {
            try {
                $gallery[] = uploadImage($obj, false, $i);
                $product->set_gallery_image_ids($gallery);
                $product->save();
            }  catch (Exception $exception) {
                $product->set_gallery_image_ids($gallery);
                $product->save();
            }
        }
//        $product->set_gallery_image_ids($gallery);
    }
    $product->save();
}

function setTypePrefix($product, $obj)
{
    if ($obj['typePrefix']) {
        $product->set_name($obj['typePrefix'] . " " . $obj['vendor']['name'] . " " . $obj['model']);
        $product->save();
    }

}

function getProduct($product_id)
{
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'sku' => $product_id,
    );
    $products = wc_get_products($args);
    return $products[0];
}

function configCronJobs(): bool
{
    $tasks = _get_cron_array();
    foreach ($tasks as $task) {
        if (array_key_exists('custom_single_product_update', $task)) {
            return false;
        }
    }
    if (wp_next_scheduled('custom_single_product_update')) {
        return false;
    }

    return true;
}

function findProduct($value)
{
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'sku' => $value['id'],
    );
    return wc_get_products($args);
}

function createProduct($value)
{
    $post_id = wp_insert_post(array(
        'post_title' => $value['typePrefix'] . " " . $value['vendor']['name'] . " " . $value['model'],
        'post_type' => 'product',
        'post_status' => 'draft',
        'post_content' => '',
    ));
    $product = wc_get_product($post_id);
    $product->set_sku($value['id']);
    setMetaData($product, $value);
    return $product;
}

function finishImportProducts()
{
    update_option('zoomos_offset', 0);
    setBackordersAfterImport();
    ProductHelper::deleteEmptyProducts();
    deleteDuplicateProduct();
    wp_unschedule_hook('custom_product_update');
}

function clearCronJobs()
{
    wp_clear_scheduled_hook( 'my_hourly_event' );
    wp_unschedule_hook( 'my_hourly_event');
    wp_clear_scheduled_hook( 'custom_product_update' );
    wp_unschedule_hook( 'custom_product_update');
}

function wp_get_attachment_by_post_name( $post_name ) {
    $args           = array(
        'posts_per_page' => 1,
        'post_type'      => 'attachment',
        'name'           => trim( $post_name ),
    );

    $get_attachment = new WP_Query( $args );

    if ( ! $get_attachment || ! isset( $get_attachment->posts, $get_attachment->posts[0] ) ) {
        return false;
    }

    return $get_attachment->posts[0];
}

function checkSales($product_id):bool
{
    $get_sale_flag = get_field('sale_from_zoomoz', $product_id, false);
    if ($get_sale_flag === 'Yes') {
        return true;
    } elseif($get_sale_flag === 'No') {
        return false;
    } else {
      update_field('sale_from_zoomoz', 'Yes', $product_id);
      return true;
    }
}

function checkCronTasks():bool
{
    $tasks = _get_cron_array();
    foreach ($tasks as $task) {
        if (array_key_exists('custom_single_product_update', $task)
        || array_key_exists('custom_product_update', $task)
        || array_key_exists('my_hourly_event', $task)
        ) {
            echo 404;
            return false;
        }
    }
    return true;
}