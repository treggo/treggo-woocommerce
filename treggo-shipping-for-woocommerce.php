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

if (!defined('WPINC'))
{
    die;
}

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))))
{
    function treggo_shipping_method()
    {
        if (!class_exists('Treggo_Shipping_Method'))
        {
            class Treggo_Shipping_Method extends WC_Shipping_Method
            {
                public function __construct()
                {
                    $this->id = 'treggo';
                    $this->method_title = __('Treggo Shipping', 'treggo');
                    $this->method_description = __('Custom Shipping Method for Treggo', 'treggo');
                    $this->availability = 'including';
                    $this->countries = array(
                        'AR',
                        'MX',
                        'UY'
                    );
                    $this->init();
                    $this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';
                    $this->title = isset($this->settings['title']) ? $this->settings['title'] : __('Treggo Shipping', 'treggo');
                }

                function init()
                {
                    $this->init_form_fields();
                    $this->init_settings();
                    add_action('woocommerce_update_options_shipping_' . $this->id, array(
                        $this,
                        'process_admin_options'
                    ));
                }

                function init_form_fields()
                {
                    $this->form_fields = array(
                        'enabled' => array(
                            'title' => __('Habilitado', 'treggo') ,
                            'type' => 'checkbox',
                            'description' => __('Habilitar el funcionamiento de Treggo para esta tienda', 'treggo') ,
                            'default' => 'yes'
                        ) ,
                        't1' => array(
                            'title' => __('Métodos de envío automáticos', 'treggo') ,
                            'type' => 'title',
                            'description' => __('Agregar los metodos de envios configurados por Treggo para la cuenta (Nota: Para setear las zonas por favor contactarse con hola@treggocity.com)', 'treggo') ,
                            'default' => 'yes'
                        ) ,
                        'automatic' => array(
                            'title' => __('Habilitado', 'treggo') ,
                            'type' => 'checkbox',
                            'default' => 'yes'
                        ) ,
                        'multiplicador' => array(
                            'title' => __('Variación del importe', 'treggo') ,
                            'type' => 'number',
                            'description' => __('Afectar la cotización por el procentaje para agregar o quitar comisiones multiplicando el precio por este valor', 'treggo') ,
                            'default' => 100
                        ) ,
                        't2' => array(
                            'title' => __('Método de envío manual', 'treggo') ,
                            'type' => 'title',
                            'description' => __('Agregar un método de envío de Treggo manualmente', 'treggo') ,
                            'default' => 'yes'
                        ) ,
                        'manual' => array(
                            'title' => __('Habilitado', 'treggo') ,
                            'type' => 'checkbox',
                            'default' => 'no'
                        ) ,
                        'title' => array(
                            'title' => __('Texto del método de envío', 'treggo') ,
                            'type' => 'text',
                            'description' => __('Texto que vera el Comprador como método de envío', 'treggo') ,
                            'default' => __('envío rápido por Treggo', 'treggo')
                        ) ,
                        'price' => array(
                            'title' => __('Precio del envío', 'treggo') ,
                            'type' => 'number',
                            'description' => __('Importe a cobrar si el Comprador elige Treggo', 'treggo') ,
                            'default' => '250'
                        ) ,
                        't3' => array(
                            'title' => __('Todos los métodos de envío', 'treggo') ,
                            'type' => 'title',
                            'description' => __('Manejar TODAS las compras de la tienda a través de Treggo', 'treggo') ,
                            'default' => 'yes'
                        ) ,
                        'all' => array(
                            'title' => __('Habilitado', 'treggo') ,
                            'type' => 'checkbox',
                            'default' => 'no'
                        )
                    );
                }

                public function calculate_shipping($package = Array())
                {
                    if ($this->settings['automatic'] == "yes")
                    {
                        $curl = curl_init();
                        curl_setopt_array($curl, array(
                            CURLOPT_URL => "http://localhost:4524/1/integrations/woocommerce/pricing",
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => "",
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST => "POST",
                            CURLOPT_HTTPHEADER => array(
                                "Content-Type: application/json"
                            ) ,
                            CURLOPT_POSTFIELDS => json_encode(array(
                                "email" => get_option('admin_email') ,
                                "store" => get_bloginfo()
                            )) ,
                        ));
                        $response = json_decode(curl_exec($curl));
                        foreach ($response as $item) $this->add_rate(array(
                            'id' => $this->id . "-" . $item->id,
                            'label' => $item->label,
                            'cost' => $item->price * ($this->settings['multiplicador'] / 100) ,
                            'calc_tax' => 'per_item'
                        ));
                    }

                    if ($this->settings['manual'] == "yes")
                    {
                        $this->add_rate(array(
                            'id' => $this->id . "-z0",
                            'label' => $this->settings['title'],
                            'cost' => $this->settings['price'],
                            'calc_tax' => 'per_item'
                        ));

                    }

                }
            }
        }
    }
    function add_treggo_shipping_method($package)
    {
        $methods[] = 'Treggo_Shipping_Method';
        return $methods;
    }
    function send_order($order_id)
    {
        treggo_shipping_method();
        $Treggo_Shipping_Method = new Treggo_Shipping_Method();
        $Treggo_Shipping_Method->init();

        $order = new WC_Order($order_id);
        $items = [];
        foreach ($order->get_items() as $item)
        {
            $product = new WC_Product($item['product_id']);
            $product->get_dimensions(false);
            array_push($items, $product->get_data());
        }
        $shipments = [];
        $isTreggo = false;
        foreach ($order->get_items('shipping') as $item)
        {
            if ($item['method_id'] == 'treggo')
            {
                $isTreggo = true;
            }
            array_push($shipments, $item->get_data());
        }

        if ($Treggo_Shipping_Method->settings['all'] == "yes" || $isTreggo)
        {
            $payload = json_encode(array(
                "order" => (Array)$order->get_data() ,
                "items" => $items,
                "shipments" => $shipments,
                "store" => array(
                    "email" => $admin_email = get_option('admin_email') ,
                    "store_address" => get_option('woocommerce_store_address') ,
                    "store_address_2" => get_option('woocommerce_store_address_2') ,
                    "store_city" => get_option('woocommerce_store_city') ,
                    "store_postcode" => get_option('woocommerce_store_postcode') ,
                    "store" => wc_get_page_permalink('shop') ,
                    "country" => WC()
                        ->countries
                        ->countries[WC()
                        ->countries
                        ->get_base_country() ]
                )
            ));
            $url = "http://localhost:4524/1/integrations/woocommerce";
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json"
                ) ,
            ));
            $response = json_decode(curl_exec($curl));
            curl_close($curl);
            if (isset($response->message))
            {
                $order->add_order_note($response->message);
            }
            else
            {
                $order->add_order_note("Estado actualizado en Treggo");
            }
        }
    }

    add_action('woocommerce_order_status_changed', 'send_order', 10, 3);
    add_action('woocommerce_shipping_init', 'treggo_shipping_method');
    add_filter('woocommerce_shipping_methods', 'add_treggo_shipping_method');
}

