<?php

class Treggo_Shipping_Method extends WC_Shipping_Method
{

    private $endpoint = 'https://api.treggo.co/1/integrations/woocommerce';

    public function __construct($instance_id = 0)
    {
        $this->id = 'treggo';
        $this->instance_id = $instance_id;
        $this->method_title = __('Treggo Shipping', 'treggo');
        $this->method_description = __('Método de envío personalizado para Treggo', 'treggo');
        $this->supports = array('shipping-zones', 'settings');

        $this->init();
        $this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';
        $this->title = __('Treggo Shipping', 'treggo');
    }

    function init()
    {
        $this->init_form_fields();
        $this->init_settings();
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    function init_form_fields()
    {
        $this->form_fields = array(
            'info' => array(
                'title' => __('( ! ) No podrás utilizar este método de envío hasta que haya un acuerdo comercial sobre las coberturas.', 'treggo'),
                'type' => 'title',
                'default' => 'yes',
                'class' => 'treggo-info'
            ),
            'enabled' => array(
                'title' => __('Estado', 'treggo'),
                'label' => __('Habilitado', 'treggo'),
                'type' => 'checkbox',
                'description' => __('Habilitar el funcionamiento de Treggo para esta tienda', 'treggo'),
                'default' => 'yes'
            ),
            't1' => array(
                'title' => __('Métodos de envío automáticos', 'treggo'),
                'type' => 'title',
                'description' => __('Agregar los metodos de envios configurados por Treggo para la cuenta', 'treggo'),
                'default' => 'yes'
            ),
            'automatic' => array(
                'title' => __('Habilitado', 'treggo'),
                'type' => 'checkbox',
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Texto del método de envío automático', 'treggo'),
                'type' => 'text',
                'description' => __('Texto que verá el Comprador como método de envío', 'treggo'),
                'default' => __('Envío rápido por Treggo', 'treggo')
            ),
            'multiplicador' => array(
                'title' => __('Multiplicador del importe', 'treggo'),
                'type' => 'number',
                'description' => __('Afectar la cotización para agregar o quitar comisiones multiplicando el precio por este valor', 'treggo'),
                'default' => 1
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
            'manual_title' => array(
                'title' => __('Texto del método de envío manual', 'treggo'),
                'type' => 'text',
                'description' => __('Texto que verá el Comprador como método de envío', 'treggo'),
                'default' => __('Envío rápido por Treggo', 'treggo')
            ),
            'price' => array(
                'title' => __('Precio del envío', 'treggo'),
                'type' => 'price',
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
            ),
            't4' => array(
                'title' => __('Impresión de etiquetas', 'treggo'),
                'type' => 'title',
                'description' => __('Formatos de generación de etiquetas', 'treggo'),
                'default' => 'yes'
            ),
            'tags_type_a4' => array(
                'title' => __('A4 PDF', 'treggo'),
                'label' => __('Habilitado', 'treggo'),
                'type' => 'checkbox',
                'default' => 'yes'
            ),
            'tags_type_cebra' => array(
                'title' => __('Cebra PDF', 'treggo'),
                'label' => __('Habilitado', 'treggo'),
                'type' => 'checkbox',
                'default' => 'yes'
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
                        'label' => $this->settings['title'],
                        'cost' => $rate->total_price * $this->settings['multiplicador'],
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
                'label' => $this->settings['manual_title'],
                'cost' => $this->settings['price'],
                'calc_tax' => 'per_item'
            ));
        }
    }

    public function treggo_notify($order)
    {
        $formattedOrder = $this->format_notification_order($order);

        if ($this->settings['all'] == 'yes' || $formattedOrder !== false) {
            $args = array(
                'body' => json_encode(array(
                    'email' => get_option('admin_email'),
                    'dominio' => get_option('siteurl'),
                    'order' => $formattedOrder
                )),
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
                } else {
                    $order->add_order_note('Estado actualizado en Treggo');
                }
            } catch (\Exception $e) {
                throw new \Exception('Error al comunicar con el servidor de Treggo: ' . $e->getMessage()); 
            }
        }
    }

    private function format_notification_order($order)
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

        if ($isTreggo) {
            return array(
                'status' => $order->get_status(),
                'payment_method' => array(
                    'code' => $order->get_payment_method(),
                    'title' => $order->get_payment_method_title()
                ),
                'items' => $items,
                'shipments' => $shipments,
                'customer_note' => $order->get_customer_note(),
                'phone' => $order->get_billing_phone(),
                'email' => $order->get_billing_email(),
                'shipping' => $order->get_address('shipping')
            );
        } else {
            return false;
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

    public function treggo_print_tags($orders, $type)
    {
        foreach ($orders as $key => $order) {
            $formattedOrder = $this->format_notification_order($order);
            if ($formattedOrder !== false) {
                $orders[$key] = $formattedOrder;
            } else {
                unset($orders[$key]);
            }
        }

        if (count($orders) > 0) {
            $args = array(
                'body' => json_encode(array(
                    'email' => get_option('admin_email'),
                    'dominio' => get_option('siteurl'),
                    'orders' => $orders,
                    'type' => $type
                )),
                'headers' => array(
                    'Content-Type' => 'application/json'
                )
            );

            try {
                $response = wp_remote_post($this->endpoint . '/tags', $args);
                $body = wp_remote_retrieve_body($response);

                $filename = 'treggo-etiquetas-' . date('Ymd') . '.pdf';

                header("Content-type: application/pdf");
                header("Content-Disposition: attachment; filename={$filename}");
                echo $body;
                exit;
            } catch (\Exception $e) {
                throw new \Exception('Error al comunicar con el servidor de Treggo: ' . $e->getMessage()); 
            }
        }
    }

}
