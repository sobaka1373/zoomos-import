<?php

namespace entity;

use WC_Product_Attribute;

class ProductHelper
{
    public CONST IMPORT_CAPACITY = 500;

    public static function getTotalProductFromDb(): int
    {
        $count_posts = wp_count_posts('product');
        return (int)$count_posts->draft + (int)$count_posts->publish;
    }

    public static function generateProductName($typePrefix, $name, $model): string
    {
        return $typePrefix . " " . $name . " " . $model;
    }

    public static function findAndSetPrice($json, &$product): void
    {
        if (isset($json['supplierInfo']->isWholesalePrice) && isset($json['priceOld']) && $json['supplierInfo']->isWholesalePrice == true) {
            if ($product->get_price() !== $json['priceOld']) {
                $product->set_price($json['priceOld']);
            }
            if ($product->get_price() !== $json['priceOld']) {
                $product->set_regular_price($json['priceOld']);
            }
            if ($product->get_sale_price() !== $json['price']) {
                $product->set_sale_price($json['price']);
            }
        }
        $product->save();
    }

    public static function setProductData($json, &$product): void
    {
        $product->set_manage_stock(true);
        if ($json['status'] === 3) {
            $product->set_stock_status('outofstock');
        }
        if ($json['status'] === 2) {
            $product->set_stock_status('outofstock');
            $product->set_backorders('yes');
        }
        if ($json['status'] === 1) {
            if (!isset($data['supplierInfo']->quantity) ||  getDigits($data['supplierInfo']->quantity) === 0 || empty($data['supplierInfo']->quantity)) {
                $product->set_stock_quantity(10);
            } else {
                $product->set_stock_quantity(getDigits($data['supplierInfo']->quantity));
            }
        }
        $product->save();
    }

    public static function uploadImage($product): int
    {
        $imageUrl = $product['image'];
        $image_url = $imageUrl . ".jpeg";
        $upload_dir = wp_upload_dir();
        $image_data = file_get_contents($image_url);
        $result = $product['id'] . "_" . $product['typePrefix'] . "_" . $product['vendor']->name . "_";
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

    public static function deleteEmptyProducts(): void
    {
        global $wpdb;
        $results = $wpdb->get_results(
            "SELECT p.ID 
                    FROM  {$wpdb->get_blog_prefix()}posts p 
                    WHERE NOT EXISTS (SELECT * FROM  {$wpdb->get_blog_prefix()}postmeta pm
                    WHERE p.id = pm.post_id) AND p.post_type = 'product'"
        );
        foreach ($results as $product) {
            wp_delete_post( $product->ID, true );
        }
    }

}