<?php
/**
 * Plugin Name: GPT Chat Assistant
 * Description: A plugin that allows admins to create and manage chat assistants using the OpenAI API.
 * Version: 1.0
 * Author: Dillon Lara
 * Author URI: https://example.com
 * Text Domain: gpt-chat-assistant
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('GPT_CHAT_PLUGIN_VERSION', '1.0');
define('GPT_CHAT_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Include Composer autoloader
$autoloader = plugin_dir_path(__FILE__) . 'vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
} else {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>ChatGPT Assistant Manager: Composer autoloader not found. Please reinstall the plugin or contact support.</p></div>';
    });
    return;
}

// Check if the autoloader is working
if (!class_exists('OpenAI\Client')) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>ChatGPT Assistant Manager: OpenAI client class not found. Please reinstall the plugin or contact support.</p></div>';
    });
    return;
}


add_filter('rest_authentication_errors', function ($result) {
    if (!empty($result)) {
        return $result;
    }

    if (!is_user_logged_in()) {
        return new WP_Error('rest_not_logged_in', 'You are not currently logged in.', array('status' => 401));
    }

    return $result;
});

// Include your plugin classes
require_once GPT_CHAT_PLUGIN_PATH . 'includes/class-gpt-chat-admin.php';
require_once GPT_CHAT_PLUGIN_PATH . 'includes/class-gpt-chat-shortcode.php';

// New API class
class GPT_Chat_API {
    public static function register_routes() {
        add_action('rest_api_init', function () {
            register_rest_route('gpt-chat/v1', '/api-keys', array(
                'methods' => 'GET',
                'callback' => array(__CLASS__, 'get_api_keys'),
                'permission_callback' => 'gpt_chatbot_permissions_check',
            ));
        });


    }
    
  
}


        /**
 * Permission callback to authenticate the request.
 *
 * @param WP_REST_Request $request
 * @return bool|WP_Error
 */
function gpt_chatbot_permissions_check($request) {
    // Retrieve the X-API-Token header
    $api_token = $request->get_header('X-API-Token');
    $secret_token = '5bwThLkRFv1Nw5aN2DXuCaMmltL0v2Nu'; // **Replace with your actual API token**

    if ($api_token && $api_token === $secret_token) {
        return true;
    }

    return new WP_Error('rest_not_authorized', 'You are not authorized to access this endpoint.', array('status' => 401));
}

/**
 * Callback to retrieve the API key.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function gpt_chatbot_get_api_key($request) {
    $key_name = $request->get_param('keyName');
    $api_keys = get_option('gpt_chatbot_api_keys'); // **Assuming you store API keys as an option. Adjust as needed.**

    if (isset($api_keys[$key_name])) {
        return rest_ensure_response(array('apiKey' => $api_keys[$key_name]));
    }

    return new WP_Error('invalid_key', 'API key not found.', array('status' => 404));
}

// Register the API routes
GPT_Chat_API::register_routes();


function gpt_chat_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gpt_assistants';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        assistant_name varchar(50) NOT NULL,
        assistant_id varchar(50) NOT NULL,
        assistant_description text NOT NULL,
        api_key_name varchar(50) NOT NULL,
        shortcode varchar(50) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    // Store the current database version
    add_option('gpt_chat_db_version', '1.1');
}
register_activation_hook(__FILE__, 'gpt_chat_activate');

// Deactivation hook
register_deactivation_hook(__FILE__, 'gpt_chat_deactivate');
function gpt_chat_deactivate() {
    // Cleanup tasks (if any)
}

function gpt_chat_update_db_check() {
    $current_version = get_option('gpt_chat_db_version', '1.0');
    if ($current_version !== '1.1') {
        gpt_chat_activate();
    }
}

add_action('plugins_loaded', 'gpt_chat_update_db_check');

// Initialize plugin
add_action('plugins_loaded', 'gpt_chat_init');
function gpt_chat_init() {
    // Initialization code here
}

// Add admin menu
add_action('admin_menu', array('GPT_Chat_Admin', 'add_admin_menu'));

// Register shortcode
function gpt_chat_register_shortcodes() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gpt_assistants';
    $assistants = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);

    foreach ($assistants as $assistant) {
        add_shortcode($assistant['shortcode'], function($atts) use ($assistant) {
            return GPT_Chat_Shortcode::render_chat($assistant);
        });
    }
}
add_action('init', 'gpt_chat_register_shortcodes');

// Register AJAX handlers
add_action('wp_ajax_gpt_chat_send_message', array('GPT_Chat_Ajax', 'send_message'));
add_action('wp_ajax_nopriv_gpt_chat_send_message', array('GPT_Chat_Ajax', 'send_message'));

// Enqueue frontend assets
add_action('wp_enqueue_scripts', 'gpt_chat_enqueue_frontend_assets');
function gpt_chat_enqueue_frontend_assets() {
    wp_enqueue_style('bulma-css', 'https://cdn.jsdelivr.net/npm/bulma@0.9.3/css/bulma.min.css');
    wp_enqueue_script('marked-js', 'https://cdn.jsdelivr.net/npm/marked/marked.min.js', array(), null, true);

    wp_enqueue_script('gpt-chat-frontend', plugins_url('/assets/js/gpt-chat-frontend.js', __FILE__), array('jquery', 'marked-js'), GPT_CHAT_PLUGIN_VERSION, true);
    wp_localize_script('gpt-chat-frontend', 'gptChatAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('gpt_chat_nonce'),
    ));
}
function gpt_chat_encrypt_string($string) {
    $key = wp_salt('auth');
    $method = 'aes-256-cbc';
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
    $encrypted = openssl_encrypt($string, $method, $key, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

function gpt_chat_decrypt_string($encrypted_string) {
    if (empty($encrypted_string)) {
        error_log("Encrypted string is empty");
        return '';
    }

    $key = wp_salt('auth');
    $method = 'aes-256-cbc';
    $decoded = base64_decode($encrypted_string);
    
    if ($decoded === false) {
        error_log("Failed to base64 decode the encrypted string");
        return '';
    }

    $parts = explode('::', $decoded, 2);

    if (count($parts) !== 2) {
        error_log("Invalid encrypted string format. Parts count: " . count($parts));
        return '';
    }

    list($encrypted_data, $iv) = $parts;

    if (empty($iv)) {
        error_log("Empty initialization vector");
        return '';
    }

    $decrypted = openssl_decrypt($encrypted_data, $method, $key, 0, $iv);

    if ($decrypted === false) {
        error_log("Decryption failed: " . openssl_error_string());
        return '';
    }

    return $decrypted;
}


add_action('init', 'ensure_gpt_chat_api_token');

function ensure_gpt_chat_api_token() {
    $token = get_option('gpt_chat_api_token');
    if (!$token) {
        $token = wp_generate_password(32, false);
        update_option('gpt_chat_api_token', $token);
    }
}


function gpt_chat_reencrypt_keys() {
    $existing_keys = get_option('gpt_chat_api_keys', array());
    $reencrypted_keys = array();

    foreach ($existing_keys as $key_name => $value) {
        // Try to decrypt first in case it's already encrypted
        $decrypted = gpt_chat_decrypt_string($value);
        if (empty($decrypted)) {
            // If decryption failed, assume it wasn't encrypted
            $decrypted = $value;
        }
        $reencrypted_keys[$key_name] = gpt_chat_encrypt_string($decrypted);
    }

    update_option('gpt_chat_api_keys', $reencrypted_keys);
    error_log("API keys re-encrypted");
}

add_action('admin_init', 'gpt_chat_maybe_reencrypt');

function gpt_chat_maybe_reencrypt() {
    if (get_option('gpt_chat_keys_reencrypted') !== 'yes') {
        gpt_chat_reencrypt_keys();
        update_option('gpt_chat_keys_reencrypted', 'yes');
    }
}

function gpt_chat_generate_api_token() {
    $token = wp_generate_password(32, false);
    update_option('gpt_chat_api_token', $token);
    return $token;
}

function gpt_chat_save_api_key($key_name, $api_key) {
    $encrypted_key = gpt_chat_encrypt_string($api_key);
    $api_keys = get_option('gpt_chat_api_keys', array());
    $api_keys[$key_name] = $encrypted_key;
    update_option('gpt_chat_api_keys', $api_keys);
}

function gpt_chat_get_api_keys() {
    $encrypted_keys = get_option('gpt_chat_api_keys', array());
    $decrypted_keys = array();
    foreach ($encrypted_keys as $key_name => $encrypted_key) {
        $decrypted_key = gpt_chat_decrypt_string($encrypted_key);
        if (!empty($decrypted_key)) {
            $decrypted_keys[$key_name] = $decrypted_key;
        } else {
            error_log("Failed to decrypt key: $key_name");
        }
    }
    return $decrypted_keys;
}

