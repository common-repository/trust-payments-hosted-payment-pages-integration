(function ($) {
    'use strict';
    // We need to refresh payment request data when total is updated.
    $(document).ready(function () {
        $('#_payment_method').on('change',function () {
            var payment_method = $(this).val();
            if(payment_method == 'securetrading_iframe'){
                $('#securetrading_moto').attr('style','display:block;');
                $('._transaction_id_field').hide();
            }else{
                $('#securetrading_moto').attr('style','display:none;');
                $('._transaction_id_field').show();
            }
        });
    } );
})(jQuery);