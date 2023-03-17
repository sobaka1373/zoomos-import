<?php

namespace entity;

class ProductChecker
{
    public static function issetProduct(int $zoomos_id): bool
    {
        $args = array(
            'post_type' => 'product',
            'sku' => '$zoomos_id',
            'meta_compare' => '='
        );
        $products = wc_get_products($args);
        if (empty($products)) {
            return false;
        }

        return true;
    }
}