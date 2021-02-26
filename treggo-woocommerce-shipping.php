<?php

/**
 * Plugin Name: Treggo Shipping
 * Plugin URI: https://treggo.co
 * Description: Custom Shipping Method for Treggo for WooCommerce
 * Version: 2.6
 * Author: Treggo.Co
 * Author URI: https://treggo.co
 * License: GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Domain Path: /lang
 * Text Domain: treggo
 */

// Check direct access
if (!defined('ABSPATH') || !defined('WPINC')) {
    function treggo_no_direct_access() {
      die(__('Se produjo un error al acceder a constantes necesarias para la operabilidad del plugin', 'treggo'));
    }
    register_activation_hook(__FILE__, 'treggo_no_direct_access');
    return;
}

// Check WooCommerce enabled
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    function treggo_no_woocommerce_installed() {
      die(__('No se detectó la instalación del plugin WooCommerce, necesario para la operabilidad de este plugin', 'treggo'));
    }
    register_activation_hook(__FILE__, 'treggo_no_woocommerce_installed');
    return;
}

class Treggo_WooCommerce_Shipping {

    private $order;

    public function __construct()
    {
        add_action('woocommerce_shipping_init', array($this, 'treggo_shipping_init'));
        add_filter('woocommerce_shipping_methods', array($this, 'treggo_shipping_add'));
        add_action('woocommerce_checkout_update_order_meta', array($this, 'treggo_order_created'), 50, 1);
        add_action('woocommerce_process_shop_order_meta', array($this, 'treggo_order_updated_before'), 1, 2);
        add_action('woocommerce_process_shop_order_meta', array($this, 'treggo_order_updated_after'), 60, 2);

        // Single order print action
        add_action('woocommerce_order_actions', array($this, 'treggo_add_print_single_tag_action'));
        add_action('woocommerce_order_action_treggo_print_single_tag_a4', array($this, 'treggo_print_single_tag_a4_action'));
        add_action('woocommerce_order_action_treggo_print_single_tag_zebra', array($this, 'treggo_print_single_tag_zebra_action'));

        // Bulk order print action
        add_filter('bulk_actions-edit-shop_order', array($this, 'treggo_add_print_bulk_tag_action'), 20, 1);
        add_filter('handle_bulk_actions-edit-shop_order', array($this, 'treggo_print_bulk_tag_action'), 10, 3);

        // Sign up
        register_activation_hook(__FILE__, array($this, 'treggo_installed'));
        add_action('admin_init', array($this, 'treggo_signup'));

        add_action('admin_menu', function() {
            add_dashboard_page(
                __('Bienvenido a Treggo ;)', 'treggo'),
                __('Bienvenido a Treggo ;)', 'treggo'),
                'manage_options',
                'treggo-welcome',
                array($this, 'render_welcome_page')
            );
        });

        add_action('admin_head', function() {
            remove_submenu_page('index.php', 'treggo-welcome');
        });

        wp_register_style('treggo', plugins_url('css/styles.css', __FILE__));
        wp_enqueue_style('treggo');
    }

    function treggo_shipping_init()
    {
        include_once('includes/class-treggo-shipping-method.php');
    }

    function treggo_shipping_add($methods)
    {
        $methods['treggo'] = 'Treggo_Shipping_Method';
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
            'status' => $order->get_status(),
            'phone' => $order->get_billing_phone()
        );
    }

    function treggo_order_updated_after($order_id)
    {
        $order = new WC_Order($order_id);
        $updated_order = array(
            'shipping' => $order->get_address('shipping'),
            'status' => $order->get_status(),
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
        add_option('Treggo_New_Installation', 'yes');
    }

    function treggo_signup()
    {
        if (is_admin() && get_option('Treggo_New_Installation') == 'yes')
        {
            delete_option('Treggo_New_Installation');

            include_once('includes/class-treggo-shipping-method.php');

            $Treggo_Shipping_Method = new Treggo_Shipping_Method();
            $Treggo_Shipping_Method->init();
            $Treggo_Shipping_Method->treggo_signup();
            wp_redirect(admin_url('/admin.php?page=treggo-welcome'));
            exit;
        }
    }

    function treggo_add_print_single_tag_action($actions)
    {

        global $theorder;

        $isTreggo = false;
        foreach ($theorder->get_items('shipping') as $item) {
            if ($item['method_id'] == 'treggo') {
                $isTreggo = true;
            }
        }

        if (!$isTreggo) {
            return $actions;
        }

        include_once('includes/class-treggo-shipping-method.php');

        $Treggo_Shipping_Method = new Treggo_Shipping_Method();
        $Treggo_Shipping_Method->init();

        if ($Treggo_Shipping_Method->settings['tags_type_a4']) {
            $actions['treggo_print_single_tag_a4'] = __('Treggo - Imprimir etiqueta A4', 'treggo');
        }
        if ($Treggo_Shipping_Method->settings['tags_type_zebra']) {
            $actions['treggo_print_single_tag_zebra'] = __('Treggo - Imprimir etiqueta Zebra', 'treggo');
        }

        return $actions;
    }

    function treggo_print_single_tag_a4_action($order_id)
    {
        return $this->treggo_print_single_tag_action($order_id, 'a4');
    }

    
    function treggo_print_single_tag_zebra_action($order_id)
    {
        return $this->treggo_print_single_tag_action($order_id, 'zebra');
    }

    function treggo_print_single_tag_action($order_id, $type)
    {
        $order = new WC_Order($order_id);
        include_once('includes/class-treggo-shipping-method.php');
        $Treggo_Shipping_Method = new Treggo_Shipping_Method();
        $Treggo_Shipping_Method->init();
        $Treggo_Shipping_Method->treggo_print_tags([$order], $type);
    }

    function treggo_add_print_bulk_tag_action($bulk_actions)
    {
        include_once('includes/class-treggo-shipping-method.php');

        $Treggo_Shipping_Method = new Treggo_Shipping_Method();
        $Treggo_Shipping_Method->init();

        if ($Treggo_Shipping_Method->settings['tags_type_a4'] === 'yes') {
            $bulk_actions['treggo_print_bulk_tag_a4'] = __('Treggo - Imprimir etiquetas A4', 'treggo');
        }
        if ($Treggo_Shipping_Method->settings['tags_type_zebra'] === 'yes') {
            $bulk_actions['treggo_print_bulk_tag_zebra'] = __('Treggo - Imprimir etiquetas Zebra', 'treggo');
        }

        return $bulk_actions;
    }

    function treggo_print_bulk_tag_action($redirect_to, $action, $post_ids)
    {

        if ($action === 'treggo_print_bulk_tag_a4') {
            $type = 'a4';
        } else if ($action === 'treggo_print_bulk_tag_zebra') {
            $type = 'zebra';
        }

        if (substr($action, 0, strlen('treggo_print_bulk_tag')) === 'treggo_print_bulk_tag') {
            $processed_ids = array();
            $orders = array();

            foreach ($post_ids as $post_id) {
                $order = new WC_Order($post_id);
                $processed_ids[] = $order->get_id();
                $orders[] = $order;
            }

            include_once('includes/class-treggo-shipping-method.php');

            $Treggo_Shipping_Method = new Treggo_Shipping_Method();
            $Treggo_Shipping_Method->init();
            $Treggo_Shipping_Method->treggo_print_tags($orders, $type);
        }
        return $redirect_to;
    }

    function render_welcome_page() {
        $content = file_get_contents(__DIR__ . '/templates/welcome.html');
        $replaces = array(
            '@EMAIL' => get_option('admin_email'),
            '@SETTINGS' => admin_url('/admin.php?page=wc-settings&tab=shipping&section=treggo'),
            '@LOGO' => plugins_url('assets/logo.png', __FILE__),
            '@BAR' => plugins_url('assets/blue-bar.png', __FILE__)
        );
        foreach($replaces as $key => $val){
            $content = str_replace($key, $val, $content);
        }
        echo $content;
    }

}

new Treggo_WooCommerce_Shipping();
