<?php

/**
 * WooCommerce Pebble.by Integration.
 *
 * @package  Pebble_API
 * @category Integration
 * @author   pebble.by
 */

if (!defined('PEBBLE_BY_API_BASE_URL')) {
    die('Direct access is prohibited.');
}

class Pebble_Api
{
    protected $namespace = 'pebble/v1/wc';
    protected $wc_products_controller;

    public function __construct()
    {
        $this->wc_products_controller = new WC_REST_Products_Controller();

        add_filter('woocommerce_rest_check_permissions', [$this, "allow_read_products"], 10, 4);
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/currency', array(
            'methods' => 'GET',
            'callback' => array($this, "currency")
        ));

        register_rest_route($this->namespace, '/products', array(
            'methods' => 'GET',
            'callback' => array($this, "products"),
            'args' => $this->wc_products_controller->get_collection_params(),
        ));
    }

    public function currency()
    {
        return rest_ensure_response(["currency" => get_woocommerce_currency()]);
    }

    public function products($request)
    {
        return $this->wc_products_controller->get_items($request);
    }

    public function allow_read_products($permission, $context, $object_id, $post_type)
    {
        if ($context == "read" && $post_type == "product")
            return true;

        return $permission;
    }
}
