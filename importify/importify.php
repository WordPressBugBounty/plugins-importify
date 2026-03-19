<?php

/**
 * @package: importify-plugin
 */

/**
 * Plugin Name: Importify
 * Description: Easily import best-selling products, and automate your entire dropshipping process, all with a single click.
 * Version: 1.0.16
 * Author: Importify
 * Author URI: https://www.importify.com/
 * License: GPLv3 or later
 * Text Domain: importify-plugin
 * Requires PHP: 7.2
 * WC requires at least: 5.0
 */

if (!defined('ABSPATH')) {
  die;
}

define("IMPORTIFY_API_URL", "https://app.importify.net/dashboard");
define('IMPORTIFY_VERSION', '1.0.16');
define('IMPORTIFY_PATH', dirname(__FILE__));
define('IMPORTIFY_FOLDER', basename(IMPORTIFY_PATH));
define('IMPORTIFY_URL', plugins_url() . '/' . IMPORTIFY_FOLDER);
define('IMPORTIFY_API_KEY', get_option('importify_api_key'));
define("IMPORTIFY_DEBUG", false);

register_activation_hook(__FILE__, 'importify_activation_hook');
register_deactivation_hook(__FILE__, 'importify_deactivation_hook');
register_uninstall_hook(__FILE__, 'importify_uninstall_hook');
add_action('admin_enqueue_scripts', 'importify_add_admin_css_js');
add_action('admin_menu', 'importify_admin_menu');
add_filter('wp_kses_allowed_html', 'importify_allow_video_tags', 10, 2);

function importify_allow_video_tags($tags, $context) {
    if ($context === 'post') {
        $tags['video'] = ['width' => true, 'height' => true, 'controls' => true, 'playsinline' => true, 'autoplay' => true, 'muted' => true, 'loop' => true, 'preload' => true, 'src' => true, 'class' => true, 'style' => true];
        $tags['source'] = ['src' => true, 'type' => true];
        $tags['iframe'] = ['src' => true, 'width' => true, 'height' => true, 'frameborder' => true, 'allowfullscreen' => true, 'allow' => true, 'class' => true, 'style' => true];
    }
    return $tags;
}

/**
 * Helper: Create WooCommerce API keys programmatically.
 * Wrapped in class_exists check to prevent duplicate declaration fatal error.
 */
function importify_create_woo_keys($app_name, $user_id, $scope)
{
    if (!class_exists("WC_Auth")) return false;

    if (!class_exists("importify_AuthCustom")) {
        class importify_AuthCustom extends WC_Auth
        {
            public function getKeys($app_name, $user_id, $scope)
            {
                return parent::create_keys($app_name, $user_id, $scope);
            }
        }
    }

    $auth = new importify_AuthCustom();
    return $auth->getKeys($app_name, $user_id, $scope);
}

function importify_activation_hook()
{
    $data = array(
        'store' => get_site_url(),
        'email' => get_option('admin_email'),
        'event' => 'install',
    );

    $response = importify_send_request('/woocomerce/status', $data);

    if ($response)
    {
        if ($response['success'] > 0)
        {
            if (!get_option('importify_api_key'))
            {
                add_option('importify_api_key', $response['api_key']);

                $keys = importify_create_woo_keys($response['app_name'], $response['user_id'], $response['scope']);
                if ($keys)
                {
                    $data = array(
                        'store' => get_site_url(),
                        'keys' => $keys,
                        'user_id' => $response['user_id'],
                        'event' => 'update_keys'
                    );
                    $keys_response = importify_send_request('/woocomerce/status', $data);

                    if ($keys_response && $keys_response['success'] == 0)
                    {
                        update_option('importify_error', 'yes');
                        update_option('importify_error_message', $keys_response['message']);
                    }
                }
            }
            else
            {
                update_option('importify_api_key', $response['api_key']);
            }
        }
        else
        {
            $msg = isset($response['message']) ? $response['message'] : 'Error activation plugin!';
            update_option('importify_error', 'yes');
            update_option('importify_error_message', $msg);
        }
    }
    else
    {
        update_option('importify_error', 'yes');
        update_option('importify_error_message', 'Could not connect to Importify server. Please check your internet connection and try again.');
    }
}

function importify_deactivation_hook()
{
    if (!current_user_can('activate_plugins'))
    {
        return;
    }
    $data = array(
        'store' => get_site_url(),
        'event' => 'deactivated',
    );
    return importify_send_request('/woocomerce/status', $data);
}

function importify_uninstall_hook()
{
    if (!current_user_can('activate_plugins'))
    {
        return;
    }

    delete_option('importify_api_key');
    delete_option('importify_error');
    delete_option('importify_error_message');
    delete_option('importify_check');

    importify_clear_all_caches();

    $data = array(
        'store' => get_site_url(),
        'event' => 'uninstall',
    );
    return importify_send_request('/woocomerce/status', $data);
}


function importify_add_admin_css_js()
{
    wp_register_style('importify_style', IMPORTIFY_URL . '/assets/css/style.css');
    wp_enqueue_style('importify_style');
    wp_register_script('importify-admin', IMPORTIFY_URL . '/assets/js/script.js', array('jquery'), '1.0.0');
    wp_enqueue_script('importify-admin');
    wp_register_script('importify-admin-feather', IMPORTIFY_URL . '/assets/js/feather.min.js');
    wp_enqueue_script('importify-admin-feather');
}

function importify_admin_menu()
{
    add_menu_page('Importify Settings', 'Importify', 'manage_options', 'importify', 'importify_admin_menu_page_html', IMPORTIFY_URL . '/assets/images/importify_icon.png');
}

function importify_has_woocommerce()
{
    return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));
}

function importify_admin_menu_page_html()
{
    $data = array(
        'store' => get_site_url(),
        'event' => 'check_status'
    );

    $store_connected = false;

    $status_response = importify_send_request('/woocomerce/status', $data);

    if ($status_response && isset($status_response['success']) && $status_response['success'] == 0)
    {
        update_option('importify_error', 'yes');
        update_option('importify_error_message', isset($status_response['message']) ? $status_response['message'] : 'Connection error');
    }

    if ($status_response && isset($status_response['success']) && $status_response['success'] == 1)
    {
        delete_option('importify_error');
        delete_option('importify_error_message');

        if (isset($status_response['keys_ok']) && $status_response['keys_ok'] == "no")
        {
            $keys = importify_create_woo_keys($status_response['app_name'], $status_response['user_id'], $status_response['scope']);
            if ($keys)
            {
                $data = array(
                    'store' => get_site_url(),
                    'keys' => $keys,
                    'user_id' => $status_response['user_id'],
                    'event' => 'update_keys'
                );
                $keys_response = importify_send_request('/woocomerce/status', $data);
            }
        }

        if (!get_option('importify_api_key'))
        {
            add_option('importify_api_key', $status_response['api_key']);
        }
        else
        {
            update_option('importify_api_key', $status_response['api_key']);
        }

        if (isset($status_response['store_connected']) && $status_response['store_connected'] == "yes")
        {
            $store_connected = true;
        }
    }

    $tmp_check_data = array();

    // SSL check
    $tmp_check_data['ssl_active'] = is_ssl() ? "true" : "false";

    // Permalinks check
    $permalinks = get_option('permalink_structure');
    $tmp_check_data['permalinks'] = is_string($permalinks) ? $permalinks : '';

    // WooCommerce check
    $tmp_check_data['woocomerce_installed'] = importify_has_woocommerce();

    // PHP version check
    $tmp_check_data['php_version'] = phpversion();
    $tmp_check_data['php_ok'] = version_compare(PHP_VERSION, '7.2', '>=');

    // WooCommerce version check
    $tmp_check_data['woo_version'] = '';
    if ($tmp_check_data['woocomerce_installed'] && function_exists('WC')) {
        $tmp_check_data['woo_version'] = WC()->version;
    }

    $tmp_check_data['firewall_active'] = false;
    $tmp_check_data['cloudflare_active'] = false;
    $tmp_check_data['firewall_name'] = '';

    // Check for blocking plugins only if not connected
    if ($store_connected == FALSE)
    {
        // Cloudflare check
        $data = array(
            'store' => get_site_url(),
            'event' => 'check_cloudflare'
        );

        $cloudflare_check = importify_send_request('/woocomerce/status', $data);

        if ($cloudflare_check && isset($cloudflare_check['success']) && $cloudflare_check['success'] == 1)
        {
            if (isset($cloudflare_check['cloudflare_enabled']) && $cloudflare_check['cloudflare_enabled'] == "true")
            {
                $tmp_check_data['cloudflare_active'] = true;
            }
        }

        // Firewall/security plugin detection (expanded list)
        $firewall_plugins = array(
            'wordfence', 'jetpack', 'sucuri', 'ninjafirewall',
            'ithemes-security', 'better-wp-security', 'solid-security',
            'all-in-one-wp-security', 'aios-security',
            'shield-security', 'wp-simple-firewall',
            'malcare', 'developer-developer',
            'defender-security', 'wp-defender',
            'cerber', 'wp-cerber',
            'bulletproof', 'bulletproof-security',
            'secupress',
        );

        $plugin_list = get_plugins();

        foreach ($plugin_list as $key => $value)
        {
            $plugin_name = strtolower($value['Name']);
            $plugin_slug = strtolower($key);

            foreach ($firewall_plugins as $fw)
            {
                if (strpos($plugin_name, $fw) !== FALSE || strpos($plugin_slug, $fw) !== FALSE)
                {
                    // Only flag if the plugin is actually active
                    if (is_plugin_active($key))
                    {
                        $tmp_check_data['firewall_active'] = true;
                        $tmp_check_data['firewall_name'] = $value['Name'];
                        break 2;
                    }
                }
            }
        }
    }

    update_option('importify_check', $tmp_check_data);

    include_once IMPORTIFY_PATH . '/views/importify_admin_page.php';
}

function importify_send_request($path, $data)
{
    try
    {
        $headers = array(
            'Content-Type' => 'application/json',
            'User-Agent' => 'Importify Wp Plugin',
            'x-plugin-version' => IMPORTIFY_VERSION,
            'x-site-url' => get_site_url(),
            'x-wp-version' => get_bloginfo('version'),
        );

        if (importify_has_woocommerce() && function_exists('WC'))
        {
            $headers['x-woo-version'] = WC()->version;
        }

        $url = IMPORTIFY_API_URL . $path;
        $args = array(
            'headers' => $headers,
            'body' => json_encode($data),
            'method' => 'POST',
            'data_format' => 'body',
            'sslverify' => false,
            'timeout' => 15,
        );

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response))
        {
            // Log the error for diagnostics
            update_option('importify_last_error', $response->get_error_message());
            return 0;
        }

        $decoded_response = json_decode(wp_remote_retrieve_body($response), true);
        return $decoded_response;
    }
    catch (Exception $err)
    {
        update_option('importify_last_error', $err->getMessage());
        return 0;
    }
}


function importify_plugin_redirect()
{
    exit(wp_redirect("admin.php?page=Importify"));
}

function importify_clear_all_caches()
{
    try
    {
        global $wp_fastest_cache;

        if (function_exists('w3tc_flush_all'))
        {
            w3tc_flush_all();
        }

        if (function_exists('wp_cache_clean_cache'))
        {
            global $file_prefix, $supercachedir;

            if (empty($supercachedir) && function_exists('get_supercache_dir'))
            {
                $supercachedir = get_supercache_dir();
            }
            wp_cache_clean_cache($file_prefix);
        }

        if (method_exists('WpFastestCache', 'deleteCache') && !empty($wp_fastest_cache))
        {
            $wp_fastest_cache->deleteCache();
        }

        if (function_exists('rocket_clean_domain'))
        {
            rocket_clean_domain();
            if (function_exists('run_rocket_sitemap_preload')) {
                run_rocket_sitemap_preload();
            }
        }

        if (class_exists("autoptimizeCache") && method_exists("autoptimizeCache", "clearall"))
        {
            autoptimizeCache::clearall();
        }

        if (class_exists("LiteSpeed_Cache_API") && method_exists("LiteSpeed_Cache_API", "purge_all"))
        {
            LiteSpeed_Cache_API::purge_all();
        }

        if (class_exists('\Hummingbird\Core\Utils'))
        {
            $modules = \Hummingbird\Core\Utils::get_active_cache_modules();
            foreach ($modules as $module => $name)
            {
                $mod = \Hummingbird\Core\Utils::get_module($module);

                if ($mod->is_active())
                {
                    if ('minify' === $module)
                    {
                        $mod->clear_files();
                    }
                    else
                    {
                        $mod->clear_cache();
                    }
                }
            }
        }
    }
    catch (Exception $e)
    {
        return 1;
    }
}

?>
