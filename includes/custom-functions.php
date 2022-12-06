<?php
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
<!--      <label>-->
<!--        Limit-->
<!--        <input type="text" id="product_limit" class="api-key-input" name="api-limit" value="1" />-->
<!--      </label>-->
      <input type="submit" class="button button-custom-import" name="insert" value="Import" />
      <p class="hide-message">Create cron task</p>
      <p class="hide-message-cron">Cron start</p>
      <p class="hide-message-error">Import completed with error</p>
      <span class="spinner"></span>
      <div id="myProgress">
        <div id="myBar">0%</div>
      </div>
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
    if ($product->get_name() !== $value['typePrefix'] . " " . $value['vendor']['name'] . " " . $value['model']) {
        $product->set_name($value['typePrefix'] . " " . $value['vendor']['name'] . " " . $value['model']);
    }
    if ($get_sale_flag) {
        if ($product->get_price() !== $value['price']) {
            $product->set_price($value['price']);
        }
        if ($product->get_regular_price() !== $value['price']) {
            $product->set_regular_price($value['price']);
        }

        if (isset($value['supplierInfo']['isWholesalePrice']) && isset($value['priceOld']) && $value['supplierInfo']['isWholesalePrice'] == true) {
          if ($product->get_price() !== $value['priceOld']) {
              $product->set_price($value['priceOld']);
          }
          if ($product->get_price() !== $value['priceOld']) {
              $product->set_regular_price($value['priceOld']);
          }
          if ($product->get_sale_price() !== $value['price']) {
              $product->set_sale_price($value['price']);
          }
        }
    }

    setProductStatus($product, $value);
    $oldImg = wp_get_attachment_url($product->get_image_id());
    if (!$oldImg ||
        !str_contains($oldImg, $value['id'] .  "_" . $value['typePrefix'] . "_" . $value['vendor']['name'] . "_" . $value['model'] . "_" )) {
      $imageId = uploadImage($value);
    }

    if (is_int($imageId) || is_float($imageId)) {
        $product->set_image_id( isset( $imageId ) ? $imageId : "" );
    }
    $product->save();
}

/**
 * @throws Exception
 */
function uploadImage($product, $singleImg = true, $number = 0): int
{
    if ($singleImg) {
        $imageUrl = $product['image'];
    } else {
        $imageUrl = $product['images'][$number];
    }
    $image_url = $imageUrl . ".jpeg";
    $upload_dir = wp_upload_dir();
    $image_data = file_get_contents($image_url);
    $result = $product['id'] . "_" . $product['typePrefix'] . "_" . $product['vendor']['name'] . "_" . $product['model'] . "_";
    $filename = $result . basename($image_url);
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
}

function setNewAttribute($product, $dataArray)
{
    $data = [];
    foreach ($dataArray as $item) {
        $rus_name = $item['name'];
        $taxonomy_name = generateSlug($item['name']);
        wc_create_attribute(array(
            'name' => $rus_name,
            'type' => 'text',
            'slug' => $taxonomy_name
        ));
        register_taxonomy( 'pa_' . $taxonomy_name, array( 'product' ), array() );
        $product_id = $product->get_id();
        foreach ($item['values'] as $value) {
            $terms = $value['name'];
            wp_set_object_terms($product_id, $terms, 'pa_' . $taxonomy_name);
            $data['pa_' . $taxonomy_name] =
                [
                    'name' => 'pa_' . $taxonomy_name,
                    'value' => '',
                    'is_visible' => '0',
                    'is_taxonomy' => '1'
                ];
        }
    }
    update_post_meta($product_id, '_product_attributes', $data);
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
function makeRequest($url, $callDetails = false)
{
    // Set handle
    $ch = curl_init($url);

    // Set options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute curl handle add results to data return array.
    $result = curl_exec($ch);
//    $returnGroup = ['curlResult' => $result,];

    // If details of curl execution are asked for add them to return group.
    if ($callDetails) {
        $returnGroup['info'] = curl_getinfo($ch);
        $returnGroup['errno'] = curl_errno($ch);
        $returnGroup['error'] = curl_error($ch);
    }

    // Close cURL and return response.
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
    if ($data['status'] === 3) {
        $product->set_stock_status('outofstock');
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
  $results = $wpdb->get_results("SELECT meta_value, count(*) AS total FROM {$wpdb->get_blog_prefix()}postmeta WHERE meta_key = '_sku' GROUP BY meta_value HAVING total > 1");
//  $products = wc_get_product_id_by_sku( $results[0]->meta_value );
//    global $wpdb;

    $product_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s'", $results[0]->meta_value ) );

    var_dump(1);
}
// 3203 product API