
(function( $) {
    'use strict';
    $(document).ready(function(){
        $('.button-custom-import').click(function(){
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
                    'api-limit': $('#product_limit').val(),
                    'action': 'start_import',
                },
                success:function(data) {
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
    });
})(jQuery);