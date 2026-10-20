<?php

/**
 * @package: importify-plugin
 */

/**
 * Plugin Name: Importify
 * Description: Easily import best-selling products, and automate your entire dropshipping process, all with a single click.
 * Version: 1.0.20
 * Author: Importify
 * Author URI: https://www.importify.com/
 * License: GPLv2 or later
 * Text Domain: importify
 * Requires at least: 6.0
 * Requires PHP: 7.2
 * Requires Plugins: woocommerce
 * WC requires at least: 5.0
 */

if (!defined('ABSPATH')) {
  die;
}

define("IMPORTIFY_API_URL", "https://app.importify.net/dashboard");
define('IMPORTIFY_VERSION', '1.0.20');
define('IMPORTIFY_PATH', dirname(__FILE__));
define('IMPORTIFY_FOLDER', basename(IMPORTIFY_PATH));
define('IMPORTIFY_URL', plugins_url() . '/' . IMPORTIFY_FOLDER);
define("IMPORTIFY_DEBUG", false);
// How long the settings page reuses the last status answer (seconds). "Re-run System Checks" skips it.
define('IMPORTIFY_STATUS_TTL', 10 * MINUTE_IN_SECONDS);

register_activation_hook(__FILE__, 'importify_activation_hook');
register_deactivation_hook(__FILE__, 'importify_deactivation_hook');
register_uninstall_hook(__FILE__, 'importify_uninstall_hook');
add_action('admin_enqueue_scripts', 'importify_add_admin_css_js');
add_action('admin_menu', 'importify_admin_menu');
add_action('before_woocommerce_init', 'importify_declare_wc_compatibility');
add_filter('wp_kses_allowed_html', 'importify_allow_video_tags', 10, 2);

/**
 * 1.0.20: the plugin never touches orders, so it is compatible with WooCommerce's order storage (HPOS)
 * and the block checkout. Without this WooCommerce lists Importify as incompatible on HPOS stores.
 */
function importify_declare_wc_compatibility()
{
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
}

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

/** Deletes one WooCommerce REST key this plugin created. */
function importify_delete_woo_key($key_id)
{
    global $wpdb;
    $key_id = absint($key_id);
    if ($key_id > 0) {
        $wpdb->delete($wpdb->prefix . 'woocommerce_api_keys', array('key_id' => $key_id), array('%d'));
    }
}

/** Only the two values Importify needs; the key id and WordPress user id stay on this site. */
function importify_keys_payload($keys)
{
    return array(
        'consumer_key' => isset($keys['consumer_key']) ? $keys['consumer_key'] : '',
        'consumer_secret' => isset($keys['consumer_secret']) ? $keys['consumer_secret'] : '',
    );
}

/** Saves the Importify api_key, never replacing a saved one with an empty value. */
function importify_save_api_key($api_key)
{
    if (is_string($api_key) && $api_key !== '') {
        update_option('importify_api_key', $api_key);
    }
}

/**
 * Deletes every "Importify - API" WooCommerce key except $keep_id (0 = all of them).
 * WooCommerce names the keys it creates for us "Importify - API (<date>)".
 */
function importify_delete_old_woo_keys($keep_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'woocommerce_api_keys';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return;
    $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE description LIKE %s AND key_id <> %d", $wpdb->esc_like('Importify - API (') . '%', absint($keep_id)));
}

/**
 * Sends new WooCommerce REST keys to Importify. When Importify accepts them, every older Importify key
 * is deleted from this site (Importify only uses the newest), so reconnecting does not pile up
 * "Importify - API" keys; when it refuses them, the new key is deleted instead.
 */
function importify_send_keys($keys, $user_id)
{
    if (!$keys || empty($keys['consumer_key'])) return false;

    $response = importify_send_request('/woocomerce/status', array(
        'store' => get_site_url(),
        'keys' => importify_keys_payload($keys),
        'user_id' => $user_id,
        'api_key' => get_option('importify_api_key'),
        'event' => 'update_keys',
    ));

    if ($response && isset($response['success']) && $response['success'] == 1) {
        importify_delete_old_woo_keys($keys['key_id']);
        update_option('importify_wc_key_id', (int)$keys['key_id']);
        return true;
    }

    importify_delete_woo_key($keys['key_id']);
    if ($response && isset($response['message'])) {
        update_option('importify_error', 'yes');
        update_option('importify_error_message', $response['message']);
    }
    return false;
}

function importify_activation_hook()
{
    // 1.0.20: create the WooCommerce keys first and send them with the install. Keys that work on this
    // store prove to Importify that this is the store's admin, so a reinstall reconnects the existing
    // account even when the admin email changed.
    $keys = importify_create_woo_keys('Importify', 0, 'read_write');

    $data = array(
        'store' => get_site_url(),
        'email' => get_option('admin_email'),
        'event' => 'install',
    );
    if ($keys) {
        $data['keys'] = importify_keys_payload($keys);
    }

    $response = importify_send_request('/woocomerce/status', $data);
    delete_transient('importify_status');

    if ($response && isset($response['success']) && $response['success'] > 0)
    {
        delete_option('importify_error');
        delete_option('importify_error_message');
        importify_save_api_key(isset($response['api_key']) ? $response['api_key'] : '');

        if ($keys) {
            importify_send_keys($keys, $response['user_id']);
        }
        return;
    }

    if ($keys) {
        importify_delete_woo_key($keys['key_id']);
    }
    update_option('importify_error', 'yes');
    if ($response) {
        update_option('importify_error_message', isset($response['message']) ? $response['message'] : 'Error activation plugin!');
    } else {
        update_option('importify_error_message', 'Could not connect to Importify server. Please check your internet connection and try again.');
    }
}

function importify_deactivation_hook()
{
    if (!current_user_can('activate_plugins'))
    {
        return;
    }
    delete_transient('importify_status');
    $data = array(
        'store' => get_site_url(),
        'event' => 'deactivated',
    );
    return importify_send_request('/woocomerce/status', $data);
}

function importify_uninstall_hook()
{
    delete_option('importify_api_key');
    delete_option('importify_error');
    delete_option('importify_error_message');
    delete_option('importify_check');
    delete_option('importify_last_error');
    delete_option('importify_wc_key_id');
    delete_transient('importify_status');

    // 1.0.20: remove the WooCommerce REST keys Importify was given, so access ends with the plugin.
    // WooCommerce names them "Importify - API (<date>)".
    importify_delete_old_woo_keys(0);

    $data = array(
        'store' => get_site_url(),
        'event' => 'uninstall',
    );
    return importify_send_request('/woocomerce/status', $data);
}


function importify_add_admin_css_js($hook)
{
    // 1.0.20: only on the Importify page, not on every admin screen.
    if ($hook !== 'toplevel_page_importify') {
        return;
    }
    // System fonts and inline SVG icons: nothing is loaded from outside the site.
    wp_enqueue_style('importify_style', IMPORTIFY_URL . '/assets/css/style.css', array(), IMPORTIFY_VERSION);
    // "How to fix" toggle and the Re-run loading state.
    wp_enqueue_script('importify_admin', IMPORTIFY_URL . '/assets/js/admin.js', array(), IMPORTIFY_VERSION, true);
}

function importify_admin_menu()
{
    add_menu_page('Importify', 'Importify', 'manage_options', 'importify', 'importify_admin_menu_page_html', IMPORTIFY_URL . '/assets/images/importify_icon.png');
}

function importify_has_woocommerce()
{
    return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));
}

/**
 * The account and connection state for the settings page, from Importify's server.
 *   account:   ok | not_found | no_answer
 *   connected: store has WooCommerce keys at Importify (yes | no)
 *   reachable: Importify can read this store with those keys (yes | no | ''), http_code when not
 *   plan, account_email: for the account strip ('' when unknown)
 * Cached for IMPORTIFY_STATUS_TTL; $force skips the cache.
 */
function importify_get_status($force = false)
{
    if (!$force) {
        $cached = get_transient('importify_status');
        if (is_array($cached)) return $cached;
    }

    $status = array('account' => 'no_answer', 'message' => '', 'connected' => 'no', 'reachable' => '', 'http_code' => 0, 'plan' => '', 'account_email' => '');

    $response = importify_send_request('/woocomerce/status', array(
        'store' => get_site_url(),
        'api_key' => get_option('importify_api_key'),
        'event' => 'check_status',
    ));

    if ($response && isset($response['success']) && $response['success'] == 1)
    {
        delete_option('importify_error');
        delete_option('importify_error_message');
        importify_save_api_key(isset($response['api_key']) ? $response['api_key'] : '');
        $status['account'] = 'ok';
        $status['plan'] = isset($response['plan']) ? (string)$response['plan'] : '';
        $status['account_email'] = isset($response['account_email']) ? (string)$response['account_email'] : '';

        if (isset($response['keys_ok']) && $response['keys_ok'] == "no")
        {
            $keys = importify_create_woo_keys('Importify', $response['user_id'], 'read_write');
            importify_send_keys($keys, $response['user_id']);
        }

        $connection = importify_send_request('/woocomerce/status', array(
            'store' => get_site_url(),
            'api_key' => get_option('importify_api_key'),
            'event' => 'check_connection',
        ));
        if ($connection && isset($connection['success']) && $connection['success'] == 1) {
            $status['connected'] = (isset($connection['store_connected']) && $connection['store_connected'] === 'yes') ? 'yes' : 'no';
            $status['reachable'] = isset($connection['reachable']) ? $connection['reachable'] : '';
            $status['http_code'] = isset($connection['http_code']) ? (int)$connection['http_code'] : 0;
        }
    }
    elseif ($response && isset($response['success']))
    {
        $status['account'] = 'not_found';
        $status['message'] = isset($response['message']) ? $response['message'] : '';
        update_option('importify_error', 'yes');
        update_option('importify_error_message', $status['message'] !== '' ? $status['message'] : 'Connection error');
    }

    set_transient('importify_status', $status, IMPORTIFY_STATUS_TTL);
    return $status;
}

function importify_admin_menu_page_html()
{
    $force = isset($_GET['importify_recheck']) && isset($_GET['_wpnonce'])
        && wp_verify_nonce(sanitize_key(wp_unslash($_GET['_wpnonce'])), 'importify_recheck');

    $importify_status = importify_get_status($force);
    $store_connected = ($importify_status['connected'] === 'yes' && $importify_status['reachable'] === 'yes');

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

    // Check for blocking plugins only if Importify cannot reach the store
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

        // Firewall/security plugin detection
        $firewall_plugins = array(
            'wordfence', 'jetpack', 'sucuri', 'ninjafirewall',
            'ithemes-security', 'better-wp-security', 'solid-security',
            'all-in-one-wp-security', 'aios-security',
            'shield-security', 'wp-simple-firewall',
            'malcare',
            'defender-security', 'wp-defender',
            'cerber', 'wp-cerber',
            'bulletproof', 'bulletproof-security',
            'secupress',
        );

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
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
            'sslverify' => true,
            'timeout' => 15,
        );

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response))
        {
            // Log the error for diagnostics
            update_option('importify_last_error', $response->get_error_message());
            return 0;
        }

        // 1.0.20: an answer arrived, so an earlier connection error no longer applies.
        delete_option('importify_last_error');

        $decoded_response = json_decode(wp_remote_retrieve_body($response), true);
        return $decoded_response;
    }
    catch (Exception $err)
    {
        update_option('importify_last_error', $err->getMessage());
        return 0;
    }
}
