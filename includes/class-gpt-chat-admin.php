<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class GPT_Chat_Admin {


	public static function init() {
		add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
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
		 add_submenu_page(
			'gpt-chat-assistants', // Parent slug
			'API Token',          // Page title
			'API Token',          // Menu title
			'manage_options',     // Capability
			'gpt-chat-api-token', // Menu slug
			'gpt_chat_api_token_page' // Callback function
		);
	}
	public static function gpt_chat_api_token_page() {
		if (!current_user_can('manage_options')) {
			wp_die(__('You do not have sufficient permissions to access this page.'));
		}
	
		error_log('Rendering API Token page');
	
		try {
			if (isset($_POST['regenerate_token'])) {
				check_admin_referer('gpt_chat_regenerate_token');
				$token = gpt_chat_generate_api_token();
				echo '<div class="updated"><p>API token regenerated.</p></div>';
			} else {
				$token = get_option('gpt_chat_api_token');
				if (!$token) {
					$token = gpt_chat_generate_api_token();
				}
			}
	
			?>
			<div class="wrap">
				<h1>GPT Chat API Token</h1>
				<p>Use this token to authenticate API requests: <strong><?php echo esc_html($token); ?></strong></p>
				<form method="post">
					<?php wp_nonce_field('gpt_chat_regenerate_token'); ?>
					<input type="submit" name="regenerate_token" value="Regenerate Token" class="button button-primary">
				</form>
			</div>
			<?php
		} catch (Exception $e) {
			error_log('Error in gpt_chat_api_token_page: ' . $e->getMessage());
			echo '<div class="error"><p>An error occurred while rendering the API Token page. Please check the error logs.</p></div>';
		}
	}
	public static function render_api_settings_page() {
		if (!current_user_can('edit_posts')) {
			wp_die(__('You do not have sufficient permissions to access this page.', 'gpt-chat-assistant'));
		}
	
		if (isset($_POST['gpt_chat_api_key']) && isset($_POST['gpt_chat_api_key_name']) && check_admin_referer('gpt_chat_api_key_nonce', '_wpnonce')) {
			$key_name = sanitize_text_field($_POST['gpt_chat_api_key_name']);
			$api_key = sanitize_text_field($_POST['gpt_chat_api_key']);
			
			gpt_chat_save_api_key($key_name, $api_key);
			echo '<div class="updated"><p>' . __('API Key saved and encrypted.', 'gpt-chat-assistant') . '</p></div>';
		}
	
		// Fetch the API keys
		$api_keys = gpt_chat_get_api_keys();
	
		?>
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
									<!-- Add delete functionality if needed -->
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
