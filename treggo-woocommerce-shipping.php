<?php

/**
 * Plugin Name: Treggo Shipping
 * Plugin URI: https://treggo.co
 * Description: Custom Shipping Method for Treggo for WooCommerce
 * Version: 2.0
 * Author: Treggo.Co
 * Author URI: https://treggo.co
 * License: GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Domain Path: /lang
 * Text Domain: treggo
 */

// Check direct access
if (!defined('ABSPATH') || !defined('WPINC')) {
    exit;
}

// Check WooCommerce enabled
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    exit;
}

class Treggo_WooCommerce_Shipping {

    private $order_id;

    public function __construct()
    {
        add_action('woocommerce_shipping_init', array($this, 'treggo_shipping_init'));
        add_filter('woocommerce_shipping_methods', array($this, 'treggo_shipping_add'));
        add_action('woocommerce_checkout_update_order_meta', array($this, 'treggo_order_created'), 50, 1);
        add_action('woocommerce_process_shop_order_meta', array($this, 'treggo_order_updated_before'), 1, 2);
        add_action('woocommerce_process_shop_order_meta', array($this, 'treggo_order_updated_after'), 60, 2);

        // Sign up
        register_activation_hook(__FILE__, array($this, 'treggo_installed'));
        add_action('admin_init', array($this, 'treggo_signup'));
    }

    function treggo_shipping_init()
    {
        include_once('includes/class-treggo-shipping-method.php');
    }

    function treggo_shipping_add($methods)
    {
        $methods[] = 'Treggo_Shipping_Method';
        return $methods;
    }

    function treggo_order_created($order_id)
    {
        $order = new WC_Order($order_id);
        include_once('includes/class-treggo-shipping-method.php');
        $Treggo_Shipping_Method = new Treggo_Shipping_Method();
        $Treggo_Shipping_Method->init();
        $Treggo_Shipping_Method->treggo_notify($order);
    }

    function treggo_order_updated_before($order_id)
    {
        $order = new WC_Order($order_id);
        $this->order = array(
            'shipping' => $order->get_address('shipping'),
            'status' => $order->status,
            'phone' => $order->get_billing_phone()
        );
    }

    function treggo_order_updated_after($order_id)
    {
        $order = new WC_Order($order_id);
        $updated_order = array(
            'shipping' => $order->get_address('shipping'),
            'status' => $order->status,
            'phone' => $order->get_billing_phone()
        );
        if (json_encode($updated_order) != json_encode($this->order)) {
            include_once('includes/class-treggo-shipping-method.php');
            $Treggo_Shipping_Method = new Treggo_Shipping_Method();
            $Treggo_Shipping_Method->init();
            $Treggo_Shipping_Method->treggo_notify($order);
        }
    }

    function treggo_installed()
    {
        add_option('Treggo_Installed', 'yes');
    }

    function treggo_signup()
    {
        if (is_admin() && get_option('Treggo_Installed') == 'yes')
        {
            delete_option('Treggo_Installed');

            include_once('includes/class-treggo-shipping-method.php');

            $Treggo_Shipping_Method = new Treggo_Shipping_Method();
            $Treggo_Shipping_Method->init();
            $Treggo_Shipping_Method->treggo_signup();
        }
    }

}

new Treggo_WooCommerce_Shipping();
