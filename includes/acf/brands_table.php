<?php

if( function_exists('acf_add_local_field_group') ):

    acf_add_local_field_group(array(
        'key' => 'group_63aac97d4ceed',
        'title' => 'Brand Group',
        'fields' => array(
            array(
                'key' => 'field_63aac97d84f36',
                'label' => 'Brand Table',
                'name' => 'brand_table',
                'type' => 'repeater',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'layout' => 'table',
                'pagination' => 0,
                'min' => 0,
                'max' => 0,
                'collapsed' => '',
                'button_label' => 'Add Row',
                'rows_per_page' => 20,
                'sub_fields' => array(
                    array(
                        'key' => 'field_63aac9ec84f37',
                        'label' => 'WP brand id',
                        'name' => 'wp_brand_id',
                        'type' => 'number',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array(
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ),
                        'default_value' => '',
                        'min' => '',
                        'max' => '',
                        'placeholder' => '',
                        'step' => '',
                        'prepend' => '',
                        'append' => '',
                        'parent_repeater' => 'field_63aac97d84f36',
                    ),
                    array(
                        'key' => 'field_63aaca1184f38',
                        'label' => 'Zomos brand id',
                        'name' => 'zomos_brand_id',
                        'type' => 'number',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array(
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ),
                        'default_value' => '',
                        'min' => '',
                        'max' => '',
                        'placeholder' => '',
                        'step' => '',
                        'prepend' => '',
                        'append' => '',
                        'parent_repeater' => 'field_63aac97d84f36',
                    ),
                ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'options_page',
                    'operator' => '==',
                    'value' => 'options-brand',
                ),
            ),
        ),
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen' => '',
        'active' => true,
        'description' => '',
        'show_in_rest' => 0,
    ));

endif;