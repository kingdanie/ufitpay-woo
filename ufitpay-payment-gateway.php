<?php
/*
Plugin Name: UfitPay Payment Gateway
Description: Adds a UfitPay payment gateway to WooCommerce.
Version: 1.0
Author: Danie D'mola
*/

if (!defined('ABSPATH')) {
    exit;
}

// Include our Gateway Class and Register Payment Gateway with WooCommerce

// function ufitpay_init() {
//     if (class_exists('WC_Payment_Gateway')) {
//         include_once 'includes/class-wc-gateway-ufitpay.php';
//     }
//     new WC_Gateway_UfitPay();
// }
// add_action('plugins_loaded', 'ufitpay_init');


function ufitpay_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', 'wc_ufitpay_wc_missing_notice');
        return;
    }

    require_once('includes/class-wc-gateway-ufitpay.php');

    add_filter('woocommerce_payment_gateways', 'add_to_woo_ufitpay_gateway');

    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ufitpay_action_links');
}

add_action('plugins_loaded', 'ufitpay_init');


// Add the Gateway to WooCommerce
function add_to_woo_ufitpay_gateway($gateways)
{
    $gateways[] = 'WC_Gateway_UfitPay';
    return $gateways;
}


// add_filter('woocommerce_payment_gateways', 'add_to_woo_ufitpay_gateway');

// Add custom action links
function ufitpay_action_links($links)
{
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=ufitpay') . '">' . __('Settings', 'ufitpay') . '</a>',
    );

    return array_merge($plugin_links, $links);
}
//add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ufitpay_action_links');

// Add hidden fields to checkout
function ufitpay_add_hidden_fields()
{
?>
    <input type="hidden" id="order_id" name="order_id" value="<?php echo esc_attr(WC()->session->get('order_awaiting_payment')); ?>" />
    <input type="hidden" name="amount" value="<?php echo esc_attr(WC()->cart->total); ?>" />
<?php
}
add_action('woocommerce_review_order_before_payment', 'ufitpay_add_hidden_fields');


/**
 * Display a notice if WooCommerce is not installed
 */
function wc_ufitpay_wc_missing_notice()
{
    echo '<div class="error"><p><strong>' . sprintf(__('Oops!, this plugin requires WooCommerce to be installed and active. Click %s to install WooCommerce.', 'ufitpay'), '<a href="' . admin_url('plugin-install.php?tab=plugin-information&plugin=woocommerce&TB_iframe=true&width=772&height=539') . '" class="thickbox open-plugin-details-modal">here</a>') . '</strong></p></div>';
}
