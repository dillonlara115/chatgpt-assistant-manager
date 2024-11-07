<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class GPT_Chat_Admin {

	// Add submenu under "Settings" for managing assistants
	public static function add_admin_menu() {
		add_menu_page(
			__( 'GPT Assistants', 'gpt-chat-assistant' ),
			__( 'GPT Assistants', 'gpt-chat-assistant' ),
			'edit_posts',
			'gpt-chat-assistants',
			array( __CLASS__, 'render_assistant_page' ),
			'dashicons-format-chat', // Icon
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
			echo '<div class="updated"><p>' . __('API Key saved.', 'gpt-chat-assistant') . '</p></div>';
		}
	
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
	
		// Render the form and list of assistants
		include GPT_CHAT_PLUGIN_PATH . 'templates/admin-assistant-page.php';
	}
}
