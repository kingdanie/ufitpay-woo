<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_UfitPay extends WC_Payment_Gateway
{

    public function __construct()
    {
        $this->id                 = 'ufitpay';
        $this->icon               = 'https://ufitpay.com/logo.png?v=100'; // URL of the icon that will be displayed on checkout page near your gateway name
        $this->has_fields         = false;
        $this->method_title       = __('UfitPay', 'ufitpay');
        $this->method_description = __('Pay with Airtime via the UfitPay WooCommerce Payment Gateway', 'ufitpay');

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user setting variables.
        $this->title        = $this->get_option('title');
        $this->description  = $this->get_option('description');
        $this->api_key      = $this->get_option('api_key');

        // Actions.
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

        // Payment listener/API hook.
        add_action('woocommerce_api_wc_ufitpay_gateway', array($this, 'verify_ufitpay_transaction'));

        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'ufitpay'),
                'type'    => 'checkbox',
                'label'   => __('Enable UfitPay Payment Gateway', 'ufitpay'),
                'default' => 'no'
            ),
            'title' => array(
                'title'       => __('Title', 'ufitpay'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'ufitpay'),
                'default'     => __('UfitPay', 'ufitpay'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'ufitpay'),
                'type'        => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'ufitpay'),
                'default'     => __('Pay with UfitPay', 'ufitpay'),
            ),
            'api_key' => array(
                'title'       => __('API Key', 'ufitpay'),
                'type'        => 'text',
                'description' => __('Enter your UfitPay API key.', 'ufitpay'),
            ),
            'callback_url' => array(
                'title'       => __('Callback URL', 'ufitpay'),
                'type'        => 'text',
                'description' => __('This is the URL you should use for the callback in your payment gateway settings.', 'ufitpay'),
                'default'     => esc_url(home_url('/wc-api/wc_ufitpay_gateway')),
                'desc_tip'    => true,
            ),
        );
    }

    // public function payment_fields() {
    //     echo '<button id="place_order">Pay with Airtime</button>';
    // }

    public function payment_fields()
    {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }
    }

    public function payment_scripts()
    {
        if (!is_checkout() || !$this->enabled) {
            return;
        }

        // Enqueue UfitPay script
        wp_enqueue_script('ufitpay_inline_js', 'https://embed.ufitpay.com/' . $this->api_key, array(), null, true);


        // Enqueue UfitPay local custom js script
        wp_enqueue_script('ufitpay_js', plugins_url('../assets/js/ufitpay.js', __FILE__), array('jquery'), '1.0', true);

        $order_key = urldecode($_GET['key']);
        $order_id  = absint(get_query_var('order-pay'));

        $order = wc_get_order($order_id);


        if (is_checkout_pay_page() && get_query_var('order-pay')) {
            $current_user = wp_get_current_user();

            $email = method_exists($order, 'get_billing_email') ? $order->get_billing_email() : $order->billing_email;

            $amount = (int) $order->get_total();

            $txnref = $order_id . '_' . time();

            $the_order_id  = method_exists($order, 'get_id') ? $order->get_id() : $order->id;
            $the_order_key = method_exists($order, 'get_order_key') ? $order->get_order_key() : $order->order_key;

            $billing_phone = method_exists($order, 'get_billing_phone') ? $order->get_billing_phone() : $order->billing_phone;
            $callback_url =  WC()->api_request_url('wc_payinvert_gateway');

            if ($the_order_id == $order_id && $the_order_key == $order_key) {

                $ufitpay_data['orderId']    = $the_order_id;
                $ufitpay_data['name'] = $order->billing_first_name . ' ' . $order->billing_last_name;
                $ufitpay_data['email']    = $email;
                $ufitpay_data['amount']   = $amount;
                $ufitpay_data['description']   = 'Payment for order ' . $order_id;
                $ufitpay_data['reference']   = $txnref;
                $ufitpay_data['callback_url']   = $callback_url;
            }



            update_post_meta($order_id, '_ufitpay_airtime_woo_txn_ref', $txnref);
            $order->save();
        }

        wp_localize_script('ufitpay_js', 'ufitpay_params', $ufitpay_data);

        // wp_localize_script('ufitpay_js', 'ufitpay_params', array(
        //     'api_key' => $this->api_key,
        //     'callback_url' => wc_get_checkout_url(),
        // ));
    }

    /** 
     * Redirect the User to the Payment page 
     * when they click of the checkout order button 
     * */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        // // Mark as on-hold (we're awaiting the payment)
        // $order->update_status('on-hold', __('Awaiting UfitPay payment', 'ufitpay'));

        // Reduce stock levels
        //wc_reduce_stock_levels($order_id);

        //// Remove cart
        //WC()->cart->empty_cart();

        // Return thank you page redirect
        return array(
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url(true),
        );
    }

    /**
     * Displays the payment page.
     *
     * @param $order_id
     */
    public function receipt_page($order_id)
    {

        $order = wc_get_order($order_id);

        echo '<div id="place_order-form">';

        echo '<p>' . __('Thank you for your order, please click the button below to pay using Airtime with Ufitpay.', 'ufitpay') . '</p>';

        echo '<div id="ufit_form"><form id="order_review" method="post" action="' . WC()->api_request_url('wc_ufitpay_gateway')  . '"></form><button class="button" id="place_order">' . __('Pay Now', 'ufitpay') . '</button></div>';

        echo '</div>';
    }


    /**
     * Verify Ufitpay payment.
     */
    public function verify_ufitpay_transaction()
    {

        $response = sanitize_text_field($_POST['response']);

        //Convert json response from the rtequest into an object
        $response_object = json_decode($response, true);

        // Check if the response is valid
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_redirect(wc_get_page_permalink('cart'));
            exit;
        }

        //Get the status of the API request
        $request_status = $response_object['status'];

        //Check if request was successful
        if ($request_status == "success") {
            $response_data = $response_object['data'];
            $transaction_status = $response_data['payment_status'];
            if ($transaction_status == "completed") {
                $returned_token = $response_data['token'];
                $reference = $response_data['reference'];


                $order_details = explode('_', $reference);
                $order_id      = (int) $order_details[0];
                $order         = wc_get_order($order_id);

                if (in_array($order->get_status(), ['processing', 'completed', 'on-hold'])) {

                    wp_redirect($this->get_return_url($order));

                    exit;
                }

                $order_total      = $order->get_total();
                $amount_paid      = $response_data->amount / 100;
                $currency_symbol  = $order->get_currency();

                // check if the amount paid is equal to the order amount.
                if ($amount_paid < absint($order_total)) {

                    $order->update_status('on-hold', '');

                    $order->add_meta_data('_transaction_id', $reference, true);

                    $notice      = sprintf(__('Thank you for shopping with us.%1$sYour payment transaction was successful, but the amount paid is not the same as the total order amount.%2$sYour order is currently on hold.%3$sKindly contact us for more information regarding your order and payment status.', 'ufitpay'), '<br />', '<br />', '<br />');
                    $notice_type = 'notice';

                    // Add Customer Order Note
                    $order->add_order_note($notice, 1);

                    // Add Admin Order Note
                    $admin_order_note = sprintf(__('<strong>Look into this order</strong>%1$sThis order is currently on hold.%2$sReason: Amount paid is less than the total order amount.%3$sAmount Paid was <strong>%4$s (%5$s)</strong> while the total order amount is <strong>%6$s (%7$s)</strong>%8$s<strong>Ufitpay Transaction Reference:</strong> %9$s', 'ufitpay'), '<br />', '<br />', '<br />', $currency_symbol, $amount_paid, $currency_symbol, $order_total, '<br />', $reference);
                    $order->add_order_note($admin_order_note);

                    function_exists('wc_reduce_stock_levels') ? wc_reduce_stock_levels($order_id) : $order->reduce_order_stock();

                    wc_add_notice($notice, $notice_type);
                } else {

                    $order->payment_complete($reference);
                    $order->add_order_note(sprintf(__('(Transaction Reference: %s) Successful using Ufipay with Airtime', 'ufitpay'), $reference));
                    $order->update_status('completed');
                }

                $order->save();
                WC()->cart->empty_cart();
            } else {

                //this is the error message from the API request
                $error = $response_object['message'];
                $order_details = explode('_', $response_object['data']['reference']);

                $order_id = (int) $order_details[0];

                $order = wc_get_order($order_id);

                $order->update_status('failed', __('Ufipay Declined Payment. Reason:' . $error, 'ufitpay'));
            }

            wp_redirect($this->get_return_url($order));
            exit;
        }


        wp_redirect(wc_get_page_permalink('cart'));
        exit;
    }
}
