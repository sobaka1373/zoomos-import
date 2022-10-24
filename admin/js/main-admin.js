
(function( $) {
    'use strict';
    $(document).ready(function(){
        $('.button-custom-import').click(function(){
            console.log(3);
            $.ajax({
                url: '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    'action': 'start_import',
                },
                success:function(data) {
                    console.log(data);
                    $('.hide-message ').show();
                },
                error: function(errorThrown){
                    console.log(errorThrown);
                }
            });
        });
    });
})(jQuery);