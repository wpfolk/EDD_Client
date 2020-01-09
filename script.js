(function( $ ) {
    $(document).ready( function() {

        $('.edd-client-cred-link').on('click',function () {
            $(this).closest('tr').next('.edd-client-row').toggle('slow', 'linear');
            $(this).closest('p').next('.edd-client-row').toggle('slow', 'linear');
        });

        $('.edd-client-button').on('click', function (event) {
            event.preventDefault();

            $('.dashicons', this).removeClass( 'dashicons-yes-alt' ).addClass('dashicons-update');
            $('.dashicons', this).addClass( 'spin' );

            $.ajax({
                context: this,
                url: '/wp-admin/admin-ajax.php',
                type: 'post',
                dataType: 'json',
                data: {
                    nonce : $(this).attr('data-nonce'),
                    action : $(this).attr('data-action'),
                    operation: $(this).attr('data-operation'),
                    license: $(this).prev('.edd-client-license-key').val(),
                },
                success: function (response) {
                    $('.edd-client-msg').remove();
                    $(this).closest('.edd-client-row').append('<div class="edd-client-msg">'+response.data+'</div>');

                    if( response.success === true ){
                        $('.dashicons', this).removeClass( 'dashicons-update spin' ).addClass('dashicons-yes-alt');
                        if(response.data === 'License deactivated for this site' || response.data === 'License successfully activated'){
                            location.reload(false);
                        }
                    }else{
                        $('.dashicons', this).removeClass( 'dashicons-update spin' ).addClass('dashicons-dismiss');
                    }
                }

            });
        });
    });
})(jQuery);