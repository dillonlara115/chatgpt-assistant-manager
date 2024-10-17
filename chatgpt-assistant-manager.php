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
                'permission_callback' => '__return_true',
            ));
        });
    }

    /**
     * Retrieves all stored API keys.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function get_api_keys($request) {
        $api_keys = gpt_chat_get_api_keys();
        $key_name = $request->get_param('gpt_chat_api_key_name');
        error_log('Received gpt_chat_api_key_name: ' . $key_name);
    
        if (empty($api_keys)) {
            return new WP_Error('no_keys', __('No API keys found.', 'gpt-chat-assistant'), array('status' => 404));
        }
    
        // If a specific key name is provided, return only that key
        if ($key_name) {
            if (isset($api_keys[$key_name])) {
                return rest_ensure_response(array('apiKey' => $api_keys[$key_name]));
            } else {
                return new WP_Error('key_not_found', __('API key not found.', 'gpt-chat-assistant'), array('status' => 404));
            }
        }
    
        // If no key name is provided, return all keys
        return rest_ensure_response(array('apiKeys' => $api_keys));
    }
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



add_action('init', 'ensure_gpt_chat_api_token');

function ensure_gpt_chat_api_token() {
    $token = get_option('gpt_chat_api_token');
    if (!$token) {
        $token = wp_generate_password(32, false);
        update_option('gpt_chat_api_token', $token);
    }
}


function gpt_chat_generate_api_token() {
    $token = wp_generate_password(32, false);
    update_option('gpt_chat_api_token', $token);
    return $token;
}

/**
 * Saves the API key without encryption.
 *
 * @param string $key_name The name of the API key.
 * @param string $api_key The API key value.
 */
function gpt_chat_save_api_key($key_name, $api_key) {
    // Directly save the API key without encryption
    $api_keys = get_option('gpt_chat_api_keys', array());
    $api_keys[$key_name] = $api_key;
    update_option('gpt_chat_api_keys', $api_keys);
}


function gpt_chat_get_api_keys() {
    return get_option('gpt_chat_api_keys', array());

}

