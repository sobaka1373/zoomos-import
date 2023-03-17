<?php

if( function_exists('acf_add_options_page') ) {
    $args = array(
        'page_title' => 'Бренды настройка',
        'menu_title' => '',
        'menu_slug' => 'options-brand',
        'post_id' => 'options-brand',
    );
    acf_add_options_page( $args );
}
