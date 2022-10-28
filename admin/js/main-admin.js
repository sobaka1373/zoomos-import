
(function( $) {
    'use strict';
    $(document).ready(function(){
        $('.button-custom-import').click(function(){
            $('.spinner').addClass('is-active');
            document.getElementById('api-key-input').readOnly = true;
            $.ajax({
                url: '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    'api-key': $('#api-key-input').val(),
                    'action': 'start_import',
                },
                success:function(data) {
                    $('.spinner').removeClass('is-active');
                    document.getElementById('api-key-input').readOnly = false;
                    $('.hide-message ').show();
                },
                error: function(errorThrown){
                    $('.spinner').removeClass('is-active');
                    document.getElementById('api-key-input').readOnly = false;
                    console.log(errorThrown);
                }
            });
        });
    });
})(jQuery);