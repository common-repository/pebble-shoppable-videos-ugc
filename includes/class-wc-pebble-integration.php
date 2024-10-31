<?php
/**
 * WooCommerce Pebble Integration.
 *
 * @package  WC_Pebble_Integration
 * @category Integration
 * @author   pebble.by
 */
use Automattic\WooCommerce\Utilities\OrderUtil;

if (!defined('ABSPATH')) {
    die('Direct access is prohibited.');
}

if (!class_exists('WC_Pebble_Integration')) {
    class WC_Pebble_Integration extends WC_Integration
    {
        public $app_id;
        public $app_secret;
        public $tracking_page;

        public function __construct()
        {
            global $woocommerce;

            $this->id = 'pebble';
            $this->method_title = __('Pebble', 'pebble-by');
            $this->method_description = 'Welcome to Pebble! Get started with our easy integration process:<br/>'.
            '<div style="background: #fff; border: 1px solid #c3c4c7; width: fit-content;">'.
            '<p style="padding: 1px 6px; margin: 4px;">Note: If you have already completed your account setup on Pebble.by dashboard, please copy your App ID below.</p>'.
            '</div>'.
            '<ol>'.
            '<li><b>Start Your Free Trial:</b> Click the Sign Up button below to begin.</li>'.
            '<a href="https://join.pebble.by/?utm_source=woocommerce-plugin&utm_medium=plugin&utm_campaign=woocommerce-integration-signup" target="_blank" class="button">Sign Up</a>'.
            '<li><b>Integrate with WooCommerce:</b> In your dashboard, go to "Addons" > "WooCommerce".</li>'.
            '<li><b>Enter API Details:</b> Copy your App ID and paste here.</li>'.
            '</ol>'.
            'That\'s it! Your store is now connected. A purchase is required to confirm integration success.<br/><br/>'.
            'Need help with integration? Check out our <a href="https://www.pebble.by/docs" target="_blank">docs</a> for an extensive guide and useful tips.';

            // Load the settings.
            $this->init_form_fields();

            // Define user set variables.
            $this->app_id = $this->get_option('app_id');
            $this->app_secret = $this->get_option('app_secret');
            //$this->status_to = str_replace('wc-', '', $this->get_option('order_status'));
            $this->tracking_page = $this->get_option('tracking_page');

            // Actions.
            add_action('woocommerce_update_options_integration_' . $this->id, [$this, 'process_admin_options']);
            add_action('admin_notices', [$this, 'check_plugin_requirements']);
            add_action('wp_enqueue_scripts', [$this, 'render_tracking_code']);
            add_action('wp_enqueue_scripts', [$this, 'render_widget_code']);
            //add_action('woocommerce_order_status_' . $this->status_to, [$this, 'pebble_submit_purchase'], 10, 1);

            // Filters.
            add_filter('woocommerce_settings_api_sanitized_fields_' . $this->id, [$this, 'sanitize_settings']);
        }

        public function init_form_fields()
        {
            $published_pages = get_pages(['status' => ['publish']]);
            $tracking_page_options = [];
            foreach ($published_pages as $page) {
                $tracking_page_options[$page->post_name] = $page->post_title;
            }

            $this->form_fields = [
                'app_id' => [
                    'title' => __('App ID', 'pebble-by'),
                    'type' => 'text',
                    'desc_tip' => false,
                    'default' => ''
                ],
                'app_secret' => [
                    'title' => __('App Secret', 'pebble-by'),
                    'type' => 'password',
                    'desc_tip' => false,
                    'default' => ''
                ],
                // 'order_status' => [
                //     'title' => __('Process orders with status', 'pebble-by'),
                //     'type' => 'select',
                //     'options' => wc_get_order_statuses(),
                //     'description' => __('Orders with this status are sent to Pebble', 'pebble-by'),
                //     'desc_tip' => true,
                //     'default' => 'wc-completed'
                // ],
                'tracking_page' => [
                    'title' => __('Render tracking code on', 'pebble-by'),
                    'type' => 'select',
                    'options' => $tracking_page_options,
                    'description' => __('Render the tracking code on the selected pages', 'pebble-by'),
                    'desc_tip' => true,
                    'default' => 'checkout'
                ],
            ];
        }

        /**
         * Checks to make sure that the license key is valid.
         *
         * @param string $key The key of the field.
         * @param mixed  $value The value of the field.
         * @return mixed
         * @throws Exception When the license key is invalid.
         */
        public function validate_app_secret_field( $key, $value ) {
            // Trim whitespaces and strip slashes.
		    $app_secret = $this->validate_password_field( $key, $value );
            $validDetails = false;

            if (!empty($app_secret)) {
                // get the App ID as well
                $app_id_post_key = $this->get_field_key('app_id' );
                $post_data = $this->get_post_data();
                $app_id = isset( $post_data[ $app_id_post_key ] ) ? $post_data[ $app_id_post_key ] : null;

                // validate the App ID and Secret Key
                $validDetails = Pebble_Service::validate_app_key($app_id, $app_secret);
            }

            if (!$validDetails) {
                $error_message = __('Your App ID or Secret is incorrect! Please follow the instructions to copy correct values.', 'pebble-by');
                WC_Admin_Settings::add_error( $error_message );
                throw new Exception( esc_html($error_message) );
            }

            return $value;
        }

        public function sanitize_settings($settings)
        {
            return $settings;
        }

        public function clear_settings()
        {
            foreach ($this->form_fields as $key => $field)
                $this->update_option($key, null);
        }

        public function check_plugin_requirements()
        {
            $message = "<strong>Pebble</strong>: Please make sure the following settings are configured for your integration to work properly:";
            $integration_incomplete = false;
            $keys_to_check = [
                'App ID' => $this->get_option('app_id'),
                'App Secret' => $this->get_option('app_secret'),
            ];

            foreach ($keys_to_check as $key => $value) {
                if (empty($value)) {
                    $integration_incomplete = true;
                    $message .= "<br> - $key";
                }
            }

            // $valid_statuses = array_keys(wc_get_order_statuses());
            // if (!in_array($this->get_option('order_status'), $valid_statuses)) {
            //     $integration_incomplete = true;
            //     $message .= "<br> - Please re-select your preferred order status to be sent to us and save your settings";
            // }

            if ($integration_incomplete == true) {
                printf('<div class="notice notice-warning"><p>%s</p></div>', $message);
            }
        }

        // public function pebble_submit_purchase($order_id)
        // {
        //     $rc_order = new Pebble_Order($order_id, $this);
        //     $rc_order->submit_purchase();
        // }

        public function render_tracking_code()
        {
            global $wp;
            if (empty($wp->query_vars['order-received']))
                return;

            $order_id  = absint( $wp->query_vars['order-received'] );

            $rc_order = new Pebble_Order($order_id, $this);
            $shouldRenderTrackingCode = is_order_received_page() || (is_order_received_page() && is_page($this->tracking_page));
            if ($shouldRenderTrackingCode) {
                $tracking_code = '<script async type="text/javascript">
                    !function(d,s) { 
                        '.$rc_order->generate_tracker_js_data().'
                        var rc = "//www.pebble.by/widget/track.js";
                    var js = d.createElement(s); js.src = rc; js.defer = true; var fjs = d.getElementsByTagName(s)[0];
                    fjs.parentNode.insertBefore(js,fjs); }(document,"script"); </script>';
                echo $tracking_code;
            }
        }

        public function render_widget_code()
        {
            // embed this on home page only
            if (!is_home() && !is_front_page())
                return;
            
            $tracking_code = '<script async type="text/javascript">
            fetch("'.PEBBLE_BY_API_BASE_URL.'/community/details/'.$this->app_id.'?bare=1", {
                method: "GET",
                headers: {
                    "Content-Type": "application/json",
                },
            })
                .then(response => response.json())
                .then(data => {
                    if (data.community) {
                        const group = data.community;
                        if (group.widget_conf?.show_widget_in_shop) {
                            if (group.widget_conf.fallback) { 
                                const brandIDScript = document.createElement("script");
                                brandIDScript.innerHTML = `const BrandID = \'${data.real_hashid}\'`;
                                document.getElementsByTagName("head")[0].appendChild(brandIDScript);
                
                                const loaderScript = document.createElement("script");
                                loaderScript.src = "'.PEBBLE_BY_BASE_URL.'/widget/loader.js";
                                document.getElementsByTagName("head")[0].appendChild(loaderScript);
                            } else {
                                const loaderScript = document.createElement("script");
                                loaderScript.src = `'.PEBBLE_BY_API_BASE_URL.'/embed/js/loader?id=${group.widget_conf.page_id}&embedType=widget`;
                                loaderScript.defer = true;
                                document.getElementsByTagName("head")[0].appendChild(loaderScript);
                            }
                        }
                    }
                });
            </script>';
            echo $tracking_code;
        }

        public function disconnect()
        {
            Pebble_Service::disconnect($this->app_id, $this->app_secret);
            $this->clear_settings();
        }
    }
}