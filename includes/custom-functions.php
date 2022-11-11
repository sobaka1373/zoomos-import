<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function admin_page_open() {
    ob_start();
    ?>
    <div class="container">
      <p>Import options</p>
      <label>
        API key
        <input type="text" id="api-key-input" class="api-key-input" name="api-key" value="<?php echo get_option('zoomos_api_key'); ?>" />
      </label>
      <label>
        Limit
        <input type="text" id="product_limit" class="api-key-input" name="api-limit" value="1" />
      </label>
      <input type="submit" class="button button-custom-import" name="insert" value="Import" />
      <p class="hide-message">Create cron task</p>
      <p class="hide-message-cron">Cron start</p>
      <p class="hide-message-error">Import completed with error</p>
      <span class="spinner"></span>
    </div>
    <?php
    $output = ob_get_clean();
//    phpinfo();
    echo $output;
}

function getDigits($str)
{
    $strWithoutChars = preg_replace('/[^0-9]/', '', $str);
    return (int)$strWithoutChars;
}

function productSetData($product, $value, $api_key)
{
    $product->set_price($value['price']);
    $product->set_regular_price($value['price']);
    $product->set_manage_stock(true);
    if ($value['status'] === 3) {
        $product->set_stock_status('outofstock');
    }
    if ($value['status'] === 2) {
        $product->set_stock_status('outofstock');
        $product->set_backorders('yes');
    }
    if ($value['status'] === 1) {
        $product->set_stock_quantity(getDigits($value['supplierInfo']['quantity']));
    }
    $imageId = uploadImage($value['image']);
    $product->set_image_id($imageId);
    $product->update_meta_data('zoomos_id', $value['id']);
    $product->update_meta_data('zoomos_category', $value['category']['id']);

    $productLink = "https://api.zoomos.by/item/{zoomos_product_id}?key=";
    $productLink = str_replace('{zoomos_product_id}', $value['id'], $productLink);
    $json = file_get_contents($productLink . $api_key );
    $obj_additional_data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

    setAttribute($product, $obj_additional_data['filters']);
    $gallery = [];
    foreach ($obj_additional_data['images'] as $item) {
        $gallery[] = uploadImage($item);
    }
    $product->set_gallery_image_ids($gallery);
    $product->set_short_description($obj_additional_data['shortDescriptionHTML']);
    $product->set_description($obj_additional_data['fullDescriptionHTML'] . '<br>' . $obj_additional_data['warrantyInfoHTML']);

    $product->save();
}

function uploadImage($imageUrl)
{
    $image_url = $imageUrl . ".jpeg";

    $upload_dir = wp_upload_dir();

    $image_data = file_get_contents( $image_url );

    $random_hex = bin2hex(random_bytes(18));

    $filename = $random_hex . basename( $image_url );

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

function setAttribute($product, $dataArray) {

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