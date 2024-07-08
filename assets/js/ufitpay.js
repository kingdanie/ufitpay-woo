jQuery(function($) {


    function handleCallback(data) {
        console.log(data);
        console.log("Payment reference: " + data.reference + ", Payment status: " + data.event + ", Payment description: " + data.description + ", Status message: " + data.status);
        if (data.event == "completed") {
            $('#successModal').show();
        } else {
            alert('failed');
            // $('#failureReason').text(data.status);
            // $('#failureModal').show();
        }
    }


    $('#place_order').on('click', function(e) {
        e.preventDefault();
        // alert('got here');

        var orderId = $('form.checkout').find('input[name="order_id"]').val(); // To get the WooCommerce order ID

        const data = {
            resource: "mobileairtime",
            payer_name: ufitpay_params.name,
            payer_email: ufitpay_params.email,
            description: ufitpay_params.description || "Payment for order " + orderId,
            amount: ufitpay_params.amount,
            reference: ufitpay_params.orderId,
            callback_url: ufitpay_params.callback_url,
            return_url: ufitpay_params.callback_url,
            callback_function: "handleCallback"
        };

        console.log(data);
        initializePayWithUfitPay(data);

    });





    $('.close').on('click', function() {
        $(this).closest('.modal').hide();
    });
});
