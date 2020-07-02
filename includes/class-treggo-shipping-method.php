<?php

class Treggo_Shipping_Method extends WC_Shipping_Method
{

    private $endpoint = 'https://api.treggo.co/1/integrations/woocommerce';

    public function __construct()
    {
        $this->id = 'treggo';
        $this->method_title = __('Treggo Shipping', 'treggo');
        $this->method_description = __('Custom Shipping Method for Treggo', 'treggo');
        $this->availability = 'including';
        $this->countries = array('AR');
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
                'title' => __('Habilitado', 'treggo'),
                'type' => 'checkbox',
                'description' => __('Habilitar el funcionamiento de Treggo para esta tienda', 'treggo'),
                'default' => 'yes'
            ),
            't1' => array(
                'title' => __('Métodos de envío automáticos', 'treggo'),
                'type' => 'title',
                'description' => __('Agregar los metodos de envios configurados por Treggo para la cuenta (Nota: Para setear las zonas por favor contactarse con hola@treggocity.com)', 'treggo'),
                'default' => 'yes'
            ),
            'automatic' => array(
                'title' => __('Habilitado', 'treggo'),
                'type' => 'checkbox',
                'default' => 'yes'
            ),
            'multiplicador' => array(
                'title' => __('Variación del importe', 'treggo'),
                'type' => 'number',
                'description' => __('Afectar la cotización por el procentaje para agregar o quitar comisiones multiplicando el precio por este valor', 'treggo'),
                'default' => 100
            ),
            't2' => array(
                'title' => __('Método de envío manual', 'treggo'),
                'type' => 'title',
                'description' => __('Agregar un método de envío de Treggo manualmente', 'treggo'),
                'default' => 'yes'
            ),
            'manual' => array(
                'title' => __('Habilitado', 'treggo'),
                'type' => 'checkbox',
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Texto del método de envío', 'treggo'),
                'type' => 'text',
                'description' => __('Texto que vera el Comprador como método de envío', 'treggo'),
                'default' => __('envío rápido por Treggo', 'treggo')
            ),
            'price' => array(
                'title' => __('Precio del envío', 'treggo'),
                'type' => 'number',
                'description' => __('Importe a cobrar si el Comprador elige Treggo', 'treggo'),
                'default' => '250'
            ),
            't3' => array(
                'title' => __('Todos los métodos de envío', 'treggo'),
                'type' => 'title',
                'description' => __('Manejar TODAS las compras de la tienda a través de Treggo', 'treggo'),
                'default' => 'yes'
            ),
            'all' => array(
                'title' => __('Habilitado', 'treggo'),
                'type' => 'checkbox',
                'default' => 'no'
            )
        );
    }

    public function calculate_shipping($package = Array())
    {
        if ($this->settings['automatic'] == 'yes') {
            $payload = array(
                'email' => get_option('admin_email'),
                'dominio' => get_option('siteurl'),
                'cp' => $package['destination']['postcode'],
                'locality' => $package['destination']['city']
            );

            $args = array(
                'body' => json_encode($payload),
                'headers' => array(
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                )
            );

            try {
                $response = wp_remote_post($this->endpoint . '/rates', $args);
                $body = wp_remote_retrieve_body($response);

                $rate = json_decode($body);

                if (!isset($rate->message)) {
                    $this->add_rate(array(
                        'id' => $this->id,
                        'label' => $rate->service_name,
                        'cost' => $rate->total_price * ($this->settings['multiplicador'] / 100),
                        'calc_tax' => 'per_item'
                    ));
                }
            } catch (\Exception $e) {
                throw new \Exception('Error al comunicar con el servidor de Treggo: ' . $e->getMessage()); 
            }
        }

        if ($this->settings['manual'] == 'yes') {
            $this->add_rate(array(
                'id' => $this->id . '-z0',
                'label' => $this->settings['title'],
                'cost' => $this->settings['price'],
                'calc_tax' => 'per_item'
            ));
        }
    }

    public function treggo_notify($order)
    {
        $items = [];
        foreach ($order->get_items() as $item) {
            $product = new WC_Product($item['product_id']);
            $product->get_dimensions(false);
            $data = $product->get_data();
            array_push($items, array(
                'id' => $data['id'],
                'name' => $data['name'],
                'slug' => $data['slug'],
                'price' => $data['price'],
                'weight' => $data['weight'],
                'length' => $data['length'],
                'width' => $data['width'],
                'height' => $data['height']
            ));
        }

        // Shipping information
        $shipments = [];
        $isTreggo = false;
        foreach ($order->get_items('shipping') as $item) {
            if ($item['method_id'] == 'treggo') {
                $isTreggo = true;
            }
            $data = (array) $item->get_data();
            array_push($shipments, array(
                'id' => $data['id'],
                'order_id' => $data['order_id'],
                'method_id' => $data['method_id'],
                'total' => $data['total']
            ));
        }

        error_log(json_encode($isTreggo));

        if ($this->settings['all'] == 'yes' || $isTreggo) {
            $payload = array(
                'email' => get_option('admin_email'),
                'dominio' => get_option('siteurl'),
                'order' => array(
                    'payment_method' => array(
                        'code' => $order->get_payment_method(),
                        'title' => $order->get_payment_method_title()
                    ),
                    'items' => $items,
                    'shipments' => $shipments,
                    'customer_note' => $order->get_customer_note(),
                    'phone' => $order->get_billing_phone()
                )
            );

            error_log(print_r($payload, true));

            $args = array(
                'body' => json_encode($payload),
                'headers' => array(
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                )
            );

            try {
                $response = wp_remote_post($this->endpoint . '/notifications', $args);
                $body = wp_remote_retrieve_body($response);

                $response = json_decode($body);
                if (isset($response->message)) {
                    $order->add_order_note($response->message);
                }
                else {
                    $order->add_order_note('Estado actualizado en Treggo');
                }
            } catch (\Exception $e) {
                throw new \Exception('Error al comunicar con el servidor de Treggo: ' . $e->getMessage()); 
            }
        }
    }

    public function treggo_signup()
    {
        $payload = array(
            'email' => get_option('admin_email'),
            'store' => array(
                    'nombre' => get_bloginfo(),
                    'dominio' => get_option('siteurl'),
                    'email' => get_option('admin_email'),
                    'store_address' => get_option('woocommerce_store_address'),
                    'store_address_2' => get_option('woocommerce_store_address_2'),
                    'store_city' => get_option('woocommerce_store_city'),
                    'store_postcode' => get_option('woocommerce_store_postcode'),
                    'store' => wc_get_page_permalink('shop'),
                    'country' => WC()
                        ->countries
                        ->countries[
                            WC()
                            ->countries
                            ->get_base_country()
                        ]
            )
        );

        $args = array(
            'body' => json_encode($payload),
            'headers' => array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            )
        );

        try {
            $response = wp_remote_post($this->endpoint . '/signup', $args);
        } catch (\Exception $e) {
            return;
        }
    }

}