
(function( $) {
    'use strict';
    $(document).ready(function(){
        $('.button-custom-import').click(function(){

            $(':button').prop('disabled', true);
            $('.button-custom-import').prop('disabled', true);

            $('.spinner').addClass('is-active');
            $('.hide-message ').hide();
            $('.hide-message-error').hide();
            $('.hide-message-cron').hide();
            document.getElementById('api-key-input').readOnly = true;
            $.ajax({
                url: '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    'api-key': $('#api-key-input').val(),
                    // 'api-limit': $('#product_limit').val(),
                    'action': 'start_import',
                },
                success:function(data) {
                    if (data === '404') {
                        $('.hide-message-404').show();
                        $('.spinner').removeClass('is-active');
                        return;
                    }
                    $('.spinner').removeClass('is-active');
                    document.getElementById('api-key-input').readOnly = false;
                    $('.hide-message ').show();
                    $.ajax({
                        url: '/wp-admin/admin-ajax.php',
                        type: 'POST',
                        data: {
                            'action': 'start_cron',
                        },
                        success:function() {
                            $('.hide-message-cron').show();
                        }
                    });
                },
                error: function(errorThrown){
                    $('.spinner').removeClass('is-active');
                    document.getElementById('api-key-input').readOnly = false;
                    console.log(errorThrown);
                    $('.hide-message-error').show();
                }
            });
        });

        if ($('#myProgress').length !== 0) {
            $.ajax({
                url: '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    'action': 'get_product_count',
                },
                success:function(data) {
                    var json = JSON.parse(data);
                    document.getElementById("total").innerHTML = json['zoomos_total_product'];
                    document.getElementById("offset").innerHTML = json['zoomos_offset'];
                },
                error: function(errorThrown){
                    console.log(errorThrown);
                }
            });
        }

        $('#single_product').click(function(){

            $(':button').prop('disabled', true);
            $('.button-custom-import').prop('disabled', true);

            $.ajax({
                url: '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    'action': 'single_product_func',
                },
                success:function() {
                    if (data === '404') {
                        $('.hide-message-404').show();
                        return;
                    }
                    $('.spinner').removeClass('is-active');
                    $('.hide-message-cron').show();
                },
                error: function(errorThrown){
                    console.log(errorThrown);
                }
            });
        });

        $('#single_product_price').click(function(){

            $(':button').prop('disabled', true);
            $('.button-custom-import').prop('disabled', true);

            $.ajax({
                url: '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    'action': 'single_product_price_func',
                },
                success:function() {

                    if (data === '404') {
                        $('.hide-message-404').show();
                        return;
                    }
                    $('.spinner').removeClass('is-active');
                    $('.hide-message-cron').show();
                },
                error: function(errorThrown){
                    console.log(errorThrown);
                }
            });
        });

        $('#single_product_gallery').click(function(){

            $(':button').prop('disabled', true);
            $('.button-custom-import').prop('disabled', true);

            $.ajax({
                url: '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    'action': 'single_product_gallery_func',
                },
                success:function(data) {

                    if (data === '404') {
                        $('.hide-message-404').show();
                        return;
                    }
                    $('.spinner').removeClass('is-active');
                    $('.hide-message-cron').show();
                },
                error: function(errorThrown){
                    console.log(errorThrown);
                }
            });
        });
    });
})(jQuery);