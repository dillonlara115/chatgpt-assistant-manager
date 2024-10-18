<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class GPT_Chat_Admin {


	public static function init() {
		add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
		add_action('wp_ajax_delete_api_key', array(__CLASS__, 'delete_api_key'));   
		error_log('GPT_Chat_Admin init called, hooks added');
	}


	

	public static function get_api_key(WP_REST_Request $request) {
		$api_keys = gpt_chat_get_api_keys();
		return new WP_REST_Response($api_keys, 200);
	}

	// Add submenu under "Settings" for managing assistants
	public static function add_admin_menu() {
		add_menu_page(
			__( 'GPT Assistants', 'gpt-chat-assistant' ),
			__( 'GPT Assistants', 'gpt-chat-assistant' ),
			'edit_posts',
			'gpt-chat-assistants',
			array( __CLASS__, 'render_assistant_page' ),
			'dashicons-robot'
		);

		add_submenu_page(
			'gpt-chat-assistants',
			__('API Settings', 'gpt-chat-assistant'),
			__('API Settings', 'gpt-chat-assistant'),
			'edit_posts',
			'gpt-chat-api-settings',
			array(__CLASS__, 'render_api_settings_page')
		);
	}
	
	public static function render_api_settings_page() {
		if (!current_user_can('edit_posts')) {
			wp_die(__('You do not have sufficient permissions to access this page.', 'gpt-chat-assistant'));
		}
	
		if (isset($_POST['gpt_chat_api_key']) && isset($_POST['gpt_chat_api_key_name']) && check_admin_referer('gpt_chat_api_key_nonce', '_wpnonce')) {
			$key_name = sanitize_text_field($_POST['gpt_chat_api_key_name']);
			$api_key = sanitize_text_field($_POST['gpt_chat_api_key']);
			
			gpt_chat_save_api_key($key_name, $api_key);
			echo '<div class="updated"><p>' . __('API Key saved successfully.', 'gpt-chat-assistant') . '</p></div>';
		}
	
		// Fetch the API keys
		$api_keys = gpt_chat_get_api_keys();
	
		?>
		 <script type="text/javascript">
    jQuery(document).ready(function($) {
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    console.log('AJAX URL:', ajaxurl);

    // Use event delegation
    $(document).on('click', '.delete-api-key', function(e) {
        e.preventDefault();
        var keyName = $(this).data('key-name');
        if (confirm('<?php _e('Are you sure you want to delete this API key?', 'gpt-chat-assistant'); ?>')) {
            console.log('Sending AJAX request with key name:', keyName);
            $.post(ajaxurl, {
                action: 'delete_api_key',
                key_name: keyName,
                _wpnonce: '<?php echo wp_create_nonce('delete_api_key_nonce'); ?>' // Add nonce
            }, function(response) {
				console.log('Full AJAX response:', response);
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data);
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.log('AJAX error: ' + textStatus + ' : ' + errorThrown);
                console.log(jqXHR.responseText); // Log the server response
            });
        }
    });
});
</script>
		<div class="wrap">
			<h1><?php echo esc_html(get_admin_page_title()); ?></h1>
			<form method="post">
				<?php wp_nonce_field('gpt_chat_api_key_nonce', '_wpnonce'); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="gpt_chat_api_key_name"><?php _e('API Key Name', 'gpt-chat-assistant'); ?></label></th>
						<td><input type="text" id="gpt_chat_api_key_name" name="gpt_chat_api_key_name" class="regular-text" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="gpt_chat_api_key"><?php _e('OpenAI API Key', 'gpt-chat-assistant'); ?></label></th>
						<td><input type="text" id="gpt_chat_api_key" name="gpt_chat_api_key" class="regular-text" required></td>
					</tr>
				</table>
				<?php submit_button(__('Add API Key', 'gpt-chat-assistant')); ?>
			</form>
	
			<h2><?php _e('Existing API Keys', 'gpt-chat-assistant'); ?></h2>
			<?php if (!empty($api_keys)) : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php _e('Key Name', 'gpt-chat-assistant'); ?></th>
							<th><?php _e('Actions', 'gpt-chat-assistant'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($api_keys as $key_name => $key_value) : ?>
							<tr>
								<td><?php echo esc_html($key_name); ?></td>
								<td>
									<button class="button delete-api-key" data-key-name="<?php echo esc_attr($key_name); ?>">
										<?php _e('Delete', 'gpt-chat-assistant'); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php _e('No API keys found.', 'gpt-chat-assistant'); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function delete_api_key() {
        error_log('delete_api_key function called');
        error_log('POST data: ' . print_r($_POST, true));

        if (!check_ajax_referer('delete_api_key_nonce', '_wpnonce', false)) {
            error_log('Nonce verification failed');
            wp_send_json_error(__('Nonce verification failed.', 'gpt-chat-assistant'));
        }

        if (!current_user_can('edit_posts')) {
            error_log('Insufficient permissions');
            wp_send_json_error(__('You do not have sufficient permissions to delete this API key.', 'gpt-chat-assistant'));
        }

        $key_name = sanitize_text_field($_POST['key_name']);
        error_log('Attempting to delete API key: ' . $key_name);

        // Delete the API key from wp_options
        $api_keys = get_option('gpt_chat_api_keys', array());
        if (isset($api_keys[$key_name])) {
            unset($api_keys[$key_name]);
            update_option('gpt_chat_api_keys', $api_keys);
            error_log('API key deleted successfully');
            wp_send_json_success(__('API key deleted successfully.', 'gpt-chat-assistant'));
        } else {
            error_log('API key not found');
            wp_send_json_error(__('API key not found.', 'gpt-chat-assistant'));
        }
    }

	// Render the admin page for managing assistants
	public static function render_assistant_page() {
		if (!current_user_can('edit_posts')) {
			wp_die(__('You do not have sufficient permissions to access this page.', 'gpt-chat-assistant'));
		}
	
		global $wpdb;
		$table_name = $wpdb->prefix . 'gpt_assistants';
	
		// Handle form submission for adding new assistants
		if (isset($_POST['gpt_add_assistant']) && check_admin_referer('gpt_add_assistant_nonce', '_wpnonce')) {
			$assistant_name = sanitize_text_field($_POST['assistant_name']);
			$assistant_id = sanitize_text_field($_POST['assistant_id']);
			$assistant_description = sanitize_textarea_field($_POST['assistant_description']);
			$api_key_name = sanitize_text_field($_POST['api_key_name']);
	
			// Generate a unique shortcode
			$shortcode = 'gpt_assistant_' . wp_generate_password(6, false);
	
			$wpdb->insert($table_name, array(
				'assistant_name' => $assistant_name,
				'assistant_id' => $assistant_id,
				'assistant_description' => $assistant_description,
				'api_key_name' => $api_key_name,
				'shortcode' => $shortcode
			));
	
			echo '<div class="updated"><p>' . __('Assistant added successfully.', 'gpt-chat-assistant') . '</p></div>';
		}
	
		// Fetch existing assistants
		$assistants = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);

		// Initialize the admin class
		add_action('plugins_loaded', array('GPT_Chat_Admin', 'init'));
	
		// Render the form and list of assistants
		include GPT_CHAT_PLUGIN_PATH . 'templates/admin-assistant-page.php';
	}
}
