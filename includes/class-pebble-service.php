<?php
/**
 * WooCommerce Pebble.by Integration.
 *
 * @package  Pebble_Service
 * @category Integration
 * @author   pebble.by
 */

if (!defined('PEBBLE_BY_API_BASE_URL')) {
    die('Direct access is prohibited.');
}

class Pebble_Service
{
    public static function validate_app_key($app_id, $secret_key)
    {
        $endpoint = join('/', [PEBBLE_BY_API_BASE_URL, 'integrations/wordpress/validate-key']);

        if (!empty($secret_key) && !empty($app_id)) {
            $data = [
                "app_id" => $app_id,
                "app_secret" => $secret_key,
                "url" => get_site_url()
            ];

            $response_body = self::_fetch($endpoint, $data, 'POST');

            return $response_body->success || false;
        }
    }

    public static function disconnect($app_id, $secret_key)
    {
        $endpoint = join('/', [PEBBLE_BY_API_BASE_URL, 'integrations/wordpress/disconnect']);

        if (!empty($secret_key) && !empty($app_id)) {
            $data = [
                "app_id" => $app_id,
                "app_secret" => $secret_key,
                "url" => get_site_url()
            ];

            $response_body = self::_fetch($endpoint, $data, 'POST');

            return $response_body->success || false;
        }
    }

    public static function widgetEnabled($app_id)
    {
        $endpoint = join('/', [PEBBLE_BY_API_BASE_URL, 'community/details/'.$app_id.'?bare=1']);
        $response_body = self::_fetch($endpoint);

        print_r($response_body);
    }

    private static function _fetch($endpoint, $data = null, $requestType = 'GET') {
        if (!empty($data))
            $params = [
                "body" => $data
            ];

        if($requestType == "POST")
            $response = wp_safe_remote_post($endpoint, $params);
        else
            $response = wp_safe_remote_get($endpoint);
        
        if (is_wp_error($response)) {
            return error_log(print_r($response, TRUE));
        }

        $response_body = json_decode($response['body']);

        return $response_body;
    }
}