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
        $this->method_description = __('UfitPay payment gateway for WooCommerce', 'ufitpay');

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
     * Verify Paystack payment.
     */
    public function verify_ufitpay_transaction()
    {

        // if (isset($_REQUEST['paystack_txnref'])) {
        //     $paystack_txn_ref = sanitize_text_field($_REQUEST['paystack_txnref']);
        // } elseif (isset($_REQUEST['reference'])) {
        //     $paystack_txn_ref = sanitize_text_field($_REQUEST['reference']);
        // } else {
        //     $paystack_txn_ref = false;
        // }

        // @ob_clean();

        // if ($paystack_txn_ref) {

        //     $paystack_response = $this->get_paystack_transaction($paystack_txn_ref);

        //     if (false !== $paystack_response) {

        //         if ('success' == $paystack_response->data->status) {

        //             $order_details = explode('_', $paystack_response->data->reference);
        //             $order_id      = (int) $order_details[0];
        //             $order         = wc_get_order($order_id);

        //             if (in_array($order->get_status(), array('processing', 'completed', 'on-hold'))) {

        //                 wp_redirect($this->get_return_url($order));

        //                 exit;
        //             }

        //             $order_total      = $order->get_total();
        //             $order_currency   = $order->get_currency();
        //             $currency_symbol  = get_woocommerce_currency_symbol($order_currency);
        //             $amount_paid      = $paystack_response->data->amount / 100;
        //             $paystack_ref     = $paystack_response->data->reference;
        //             $payment_currency = strtoupper($paystack_response->data->currency);
        //             $gateway_symbol   = get_woocommerce_currency_symbol($payment_currency);

        //             // check if the amount paid is equal to the order amount.
        //             if ($amount_paid < absint($order_total)) {

        //                 $order->update_status('on-hold', '');

        //                 $order->add_meta_data('_transaction_id', $paystack_ref, true);

        //                 $notice      = sprintf(__('Thank you for shopping with us.%1$sYour payment transaction was successful, but the amount paid is not the same as the total order amount.%2$sYour order is currently on hold.%3$sKindly contact us for more information regarding your order and payment status.', 'woo-paystack'), '<br />', '<br />', '<br />');
        //                 $notice_type = 'notice';

        //                 // Add Customer Order Note
        //                 $order->add_order_note($notice, 1);

        //                 // Add Admin Order Note
        //                 $admin_order_note = sprintf(__('<strong>Look into this order</strong>%1$sThis order is currently on hold.%2$sReason: Amount paid is less than the total order amount.%3$sAmount Paid was <strong>%4$s (%5$s)</strong> while the total order amount is <strong>%6$s (%7$s)</strong>%8$s<strong>Paystack Transaction Reference:</strong> %9$s', 'woo-paystack'), '<br />', '<br />', '<br />', $currency_symbol, $amount_paid, $currency_symbol, $order_total, '<br />', $paystack_ref);
        //                 $order->add_order_note($admin_order_note);

        //                 function_exists('wc_reduce_stock_levels') ? wc_reduce_stock_levels($order_id) : $order->reduce_order_stock();

        //                 wc_add_notice($notice, $notice_type);
        //             } else {

        //                 if ($payment_currency !== $order_currency) {

        //                     $order->update_status('on-hold', '');

        //                     $order->update_meta_data('_transaction_id', $paystack_ref);

        //                     $notice      = sprintf(__('Thank you for shopping with us.%1$sYour payment was successful, but the payment currency is different from the order currency.%2$sYour order is currently on-hold.%3$sKindly contact us for more information regarding your order and payment status.', 'woo-paystack'), '<br />', '<br />', '<br />');
        //                     $notice_type = 'notice';

        //                     // Add Customer Order Note
        //                     $order->add_order_note($notice, 1);

        //                     // Add Admin Order Note
        //                     $admin_order_note = sprintf(__('<strong>Look into this order</strong>%1$sThis order is currently on hold.%2$sReason: Order currency is different from the payment currency.%3$sOrder Currency is <strong>%4$s (%5$s)</strong> while the payment currency is <strong>%6$s (%7$s)</strong>%8$s<strong>Paystack Transaction Reference:</strong> %9$s', 'woo-paystack'), '<br />', '<br />', '<br />', $order_currency, $currency_symbol, $payment_currency, $gateway_symbol, '<br />', $paystack_ref);
        //                     $order->add_order_note($admin_order_note);

        //                     function_exists('wc_reduce_stock_levels') ? wc_reduce_stock_levels($order_id) : $order->reduce_order_stock();

        //                     wc_add_notice($notice, $notice_type);
        //                 } else {

        //                     $order->payment_complete($paystack_ref);
        //                     $order->add_order_note(sprintf(__('Payment via Paystack successful (Transaction Reference: %s)', 'woo-paystack'), $paystack_ref));

        //                     if ($this->is_autocomplete_order_enabled($order)) {
        //                         $order->update_status('completed');
        //                     }
        //                 }
        //             }

        //             $order->save();

        //             $this->save_card_details($paystack_response, $order->get_user_id(), $order_id);

        //             WC()->cart->empty_cart();
        //         } else {

        //             $order_details = explode('_', $_REQUEST['paystack_txnref']);

        //             $order_id = (int) $order_details[0];

        //             $order = wc_get_order($order_id);

        //             $order->update_status('failed', __('Payment was declined by Paystack.', 'woo-paystack'));
        //         }
        //     }

        //     wp_redirect($this->get_return_url($order));

        //     exit;
        // }

        wp_redirect(wc_get_page_permalink('cart'));

        exit;
    }
}
