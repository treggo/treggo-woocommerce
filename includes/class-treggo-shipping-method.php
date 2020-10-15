<?php

class Treggo_Shipping_Method extends WC_Shipping_Method
{

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

    public function get_endpoint()
    {
        $woocommerce_country = strtolower(get_option('woocommerce_default_country'));
        $country = strpos($woocommerce_country, ':') !== false ? explode(':', $woocommerce_country)[0] : $woocommerce_country;
        return 'https://api.' . $country . '.treggo.co/1/integrations/woocommerce';
    }

    public function init()
    {
        $this->init_form_fields();
        $this->init_settings();
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'warning' => array(
                'title' => __('No podrás utilizar este método de envío hasta que haya un acuerdo comercial sobre las coberturas.', 'treggo'),
                'type' => 'title',
                'class' => 'treggo-warning dashicons-before dashicons-warning'
            ),
            'info' => array(
                'title' => __('Recordá agregar este método de envío dentros de las zonas de envío para que esté disponible.', 'treggo'),
                'type' => 'title',
                'class' => 'treggo-info dashicons-before dashicons-info'
            ),
            'warning-2' => array(
                'title' => __('El multiplicador dejó de ser un porcentaje. ¡Revisá su valor!', 'treggo'),
                'type' => 'title',
                'class' => 'treggo-info dashicons-before dashicons-warning'
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
                'type' => 'price',
                'description' => __('Afectar la cotización para agregar o quitar comisiones multiplicando el precio por este valor.', 'treggo'), 'default' => 1
            ),
            'multiplicador-help' => array(
                'title' => '',
                'type' => 'title',
                'description' => __('<b>Ejemplos:</b>', 'treggo'),
                'class' => 'multiplier-help'
            ),
            'mutliplicador-help-1' => array(
                'title' => '',
                'type' => 'title',
                'description' => __('• <b>0.5</b> = 50% del total', 'treggo'),
                'class' => 'multiplier-help-item'
            ),
            'mutliplicador-help-2' => array(
                'title' => '',
                'type' => 'title',
                'description' => __('• <b>1.21</b> = 21% de sobrecargo', 'treggo'),
                'class' => 'multiplier-help-item'
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
            'tags_type_zebra' => array(
                'title' => __('Zebra PDF', 'treggo'),
                'label' => __('Habilitado', 'treggo'),
                'type' => 'checkbox',
                'default' => 'yes'
            )
        );
    }

    public function calculate_shipping($package = array())
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
                $response = wp_remote_post($this->get_endpoint() . '/rate', $args);
                $body = wp_remote_retrieve_body($response);

                $rates = json_decode($body, true);

                if (is_array($rates)) {
                    foreach ($rates as $rate) {
                        if (isset($rate['total_price']) && is_numeric($rate['total_price'])) {
                            $this->add_rate(array(
                                'id' => $this->id . '-' . $rate['code'],
                                'label' => $this->settings['title'] . (strlen($rate['service']) > 0 ? ' - ' . $rate['service'] : ''),
                                'cost' => $rate['total_price'] * $this->settings['multiplicador'],
                                'calc_tax' => 'per_item',
                                'meta_data' => $rate
                            ));
                        }
                    }
                }
            } catch (\Exception $e) {
                throw new \Exception('Error al comunicar con el servidor de Treggo: ' . $e->getMessage());
            }
        }

        if ($this->settings['manual'] == 'yes') {
            $this->add_rate(array(
                'id' => $this->id . '-manual',
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
                $response = wp_remote_post($this->get_endpoint() . '/notifications', $args);
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
        // Shipping information
        $shipments = [];
        $isTreggo = false;
        foreach ($order->get_items('shipping') as $item) {
            if (strpos($item['method_id'], $this->id) !== false) {
                $isTreggo = true;
            }
            $data = (array) $item->get_data();

            $code = null;
            $service = null;
            $name = null;

            if (is_array($data['meta_data'])) {
                foreach ($data['meta_data'] as $meta_data) {
                    $meta = $meta_data->get_data();
                    switch ($meta['key']) {
                        case 'name':
                            $name = $meta['value'];
                            break;
                        case 'service':
                            $service = $meta['value'];
                            break;
                        case 'code':
                            $code = $meta['value'];
                            break;
                        default:
                            break;
                    }
                }
            }

            array_push($shipments, array(
                'id' => $data['id'],
                'order_id' => $data['order_id'],
                'method_id' => $data['method_id'],
                'total' => $data['total'],
                'code' => $code,
                'service' => $service,
                'name' => $name
            ));
        }

        if (!$isTreggo) {
            return false;
        }

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
            'shipping' => $order->get_address('shipping'),
            'date' => strval($order->get_date_created())
        );
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
                    ->countries[WC()
                    ->countries
                    ->get_base_country()]
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
            wp_remote_post($this->get_endpoint() . '/signup', $args);
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
                $response = wp_remote_post($this->get_endpoint() . '/tags', $args);
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
