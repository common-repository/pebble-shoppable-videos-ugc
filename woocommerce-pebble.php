<?php

/**
 * Plugin Name: Pebble - Shoppable Videos & UGC
 * Plugin URI: 
 * Description: Integrate your Woocommerce store with Pebble.by app
 * Author: pebble.by
 * Author URI: https://www.pebble.by
 * Text Domain: pebble-by
 * Version: 1.2.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('ABSPATH')) {
    die('Direct access is prohibited.');
}

define('PEBBLE_BY_BASE_URL', 'https://pebble.by');
define('PEBBLE_BY_API_BASE_URL', 'https://api.pebble.by/api/v1');

if (preg_grep("/\/woocommerce.php$/", apply_filters('active_plugins', get_option('active_plugins'))) !== null) {
    if (!class_exists('WC_Pebble')) {
        class WC_Pebble
        {
            public function __construct()
            {
                add_action('plugins_loaded', array($this, 'init'));
            }

            public function init()
            {
                if (class_exists('WC_Integration')) {
                    autoload_classes();
                    add_filter('woocommerce_integrations', [$this, 'add_integration']);
                } else {
                    add_action('admin_notices', 'missing_prerequisite_notification');
                }

                register_block_type_from_metadata( __DIR__ . '/build/story' );
                register_block_type( __DIR__ . '/build/carousel' );
            }

            public function add_integration($integrations)
            {
                $integrations[] = 'WC_Pebble_Integration';

                return $integrations;
            }
        }

        $WC_Pebble = new WC_Pebble(__FILE__);
    }

    function autoload_classes()
    {
        $files = scandir(dirname(__FILE__) . '/includes');
        $valid_extensions = ['php'];
        foreach ($files as $index => $file) {
            if (in_array(pathinfo($file)['extension'], $valid_extensions)) {
                require_once('includes/' . pathinfo($file)['basename']);
            }
        }
    }

    function wc_pebble_plugin_activate()
    {
        add_option('wc_pebble_plugin_do_activation_redirect', true);
    }

    function wc_pebble_plugin_deactivate()
    {
        if (class_exists('WC_Pebble_Integration')) {
            $wcpi = new WC_Pebble_Integration();
            $wcpi->disconnect();
        }
    }

    function wc_pebble_plugin_redirect()
    {
        if (get_option('wc_pebble_plugin_do_activation_redirect')) {
            delete_option('wc_pebble_plugin_do_activation_redirect');

            if (!isset($_GET['activate-multi'])) {
                $setup_url = admin_url("admin.php?page=wc-settings&tab=integration&section=pebble");
                wp_redirect($setup_url);

                exit;
            }
        }
    }

    function wc_pebble_register_custom_routes()
    {
        $rest_api = new Pebble_Api();
        $rest_api->register_routes();
    }

    function missing_prerequisite_notification()
    {
        $message = 'Pebble Plugins requires Woocommerce to be installed and activated';
        printf('<div class="notice notice-error"><p>%1$s</p></div>', $message);
    }

    function pebble_plugin_links($links)
    {
        $rc_tab_url = "admin.php?page=wc-settings&tab=integration&section=pebble";
        $settings_link = "<a href='" . esc_url(get_admin_url(null, $rc_tab_url)) . "'>Settings</a>";

        array_unshift($links, $settings_link);

        return $links;
    }

    function wc_pebble_story_tag() {
        $randomNumber = time().rand(100,500);
        
        global $product;

        echo '<div id="pebble-placeholder-default-'.$randomNumber.'" style="opacity: 0;"></div>
        <script defer src="'.PEBBLE_BY_BASE_URL.'/embed/js/loader?id=default-'.$randomNumber.'&productID='.$product->id.'&shop='.get_site_url().'&source=wordpress&designMode='.(strstr($_SERVER['REQUEST_URI'], 'customize.php') ? 'true' : 'false').'&embedType=stories"></script>';
    }

    function wc_pebble_carousel_tag() {
        $randomNumber = time().rand(100,500);
        
        global $product;

        echo '<div id="pebble-placeholder-default-'.$randomNumber.'" style="opacity: 0;"></div>
        <script defer src="'.PEBBLE_BY_BASE_URL.'/embed/js/loader?id=default-'.$randomNumber.'&productID='.$product->id.'&shop='.get_site_url().'&source=wordpress&designMode='.(strstr($_SERVER['REQUEST_URI'], 'customize.php') ? 'true' : 'false').'&embedType=carousel"></script>';
    }

    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'pebble_plugin_links');
    add_action('admin_init', 'wc_pebble_plugin_redirect');

    register_activation_hook(__FILE__, 'wc_pebble_plugin_activate');
    register_deactivation_hook(__FILE__, 'wc_pebble_plugin_deactivate');

    add_action('rest_api_init', 'wc_pebble_register_custom_routes');

    add_action('woocommerce_single_product_summary', 'wc_pebble_story_tag', 6);
    add_action('woocommerce_after_single_product_summary', 'wc_pebble_carousel_tag', 11);
}
