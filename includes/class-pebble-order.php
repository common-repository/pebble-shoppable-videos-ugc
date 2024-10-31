<?php
/**
 * WooCommerce Pebble.by Integration.
 *
 * @package  Pebble_Order
 * @category Integration
 * @author   pebble.by
 */

if (!defined('PEBBLE_BY_API_BASE_URL')) {
    die('Direct access is prohibited.');
}

class Pebble_Order
{
    private $order;
    private $secret_key;

    public $wc_pre_30 = false;
    public $app_id;
    public $customer_id;
    public $total;
    public $currency;
    public $order_id;
    public $browser_ip;
    public $user_agent;

    public function __construct($wc_order_id, WC_Pebble_Integration $integration)
    {
        $this->wc_pre_30 = version_compare(WC_VERSION, '3.0.0', '<');
        $this->order = new WC_Order($wc_order_id);

        if ($this->wc_pre_30) {
            $this->customer_id = $this->order->customer_id;
            $this->total = $this->order->get_total();
            $this->currency = $this->order->get_order_currency();
            $this->order_id = $wc_order_id;
            $this->browser_ip = $this->order->customer_ip_address;
            $this->user_agent = $this->order->customer_user_agent;
        } else {
            $order_data = $this->order->get_data();

            $this->customer_id = $order_data['customer_id'];
            $this->total = $order_data['total'];
            $this->currency = $order_data['currency'];
            $this->order_id = $wc_order_id;
            $this->browser_ip = $order_data['customer_ip_address'];
            $this->user_agent = $order_data['customer_user_agent'];
        }

        $this->app_id = $integration->app_id;
    }

    private function generate_post_fields($specific_keys = [], $additional_keys = [])
    {
        $post_fields = [
            'app_id' => $this->app_id,
            'customer_id' => $this->customer_id,
            'browser_ip' => $this->browser_ip,
            'user_agent' => $this->user_agent,
            'invoice_amount' => $this->total,
            'currency_code' => $this->currency,
            'order_id' => $this->order_id,
            'timestamp' => time(),
        ];

        // check if we need only specific post fields from the default
        if ($specific_keys != null && count($specific_keys) > 0) {
            $new_post_fields = [];
            foreach ($post_fields as $field => $value) {
                if (in_array($field, $specific_keys)) {
                    $new_post_fields[$field] = $value;
                }
            }

            // only overwrite post fields if at least one key is retreived
            if ($new_post_fields != null && count($new_post_fields) > 0) {
                $post_fields = $new_post_fields;
            }
        }

        // check if there are additional keys we want to add to the payload
        if ($additional_keys != null && count($additional_keys) > 0) {
            $post_fields = array_merge($post_fields, $additional_keys);
        }

        // sort keys
        ksort($post_fields);

        return $post_fields;
    }

    // created this function because PHP's http_build_query function converts 'timestamp' to 'xstamp'
    private function prepParams(array $params)
    {
        $preppedParams = '';
        foreach ($params as $key => $value) {
            $preppedParams .= "$key=$value";
        }

        return $preppedParams;
    }

    private function generate_request_body($post_fields)
    {
        $params = [
            'body' => $post_fields
        ];

        if (!empty($this->api_id)) {
            $params['body']['api_id'] = $this->api_id;
        }

        if (!empty($this->secret_key) && !empty($this->api_id)) {
            $params['body']['signature'] = md5($this->secret_key . $this->prepParams($post_fields));
        }

        return $params;
    }

    public function generate_tracker_js_data()
    {
        return '
            window.pebbleConversion = {
                group: "'.$this->app_id.'",
                orderId: "'.$this->order_id.'",
                currency: "'.$this->currency.'",
                totalPrice: "'.$this->total.'",
                customerId: "'.$this->customer_id.'",
                source: "wordpress"
            };
        ';
    }

    // https://www.referralcandy.com/api#purchase
    public function submit_purchase()
    {
        $endpoint = join('/', [PEBBLE_BY_API_BASE_URL, 'purchase.json']);

        if (!empty($this->secret_key) && !empty($this->api_id)) {
            $params = $this->generate_request_body($this->generate_post_fields());
            $response = wp_safe_remote_post($endpoint, $params);

            if (is_wp_error($response)) {
                return error_log(print_r($response, TRUE));
            }

            $response_body = json_decode($response['body']);

            if ($response_body->message == 'Success' && !empty($response_body->referralcorner_url)) {
                $this->order->add_order_note('Order sent to Pebble');
            }
        }
    }
}