<div class="wrap">
	<h1><?php esc_html_e( 'Manage GPT Assistants', 'gpt-chat-assistant' ); ?></h1>

	<form method="POST">
		<?php wp_nonce_field( 'gpt_add_assistant_nonce' ); ?>
		<div class="field">
			<label class="label"><?php esc_html_e( 'Assistant Name', 'gpt-chat-assistant' ); ?></label>
			<div class="control">
				<input class="input" type="text" name="assistant_name" required>
			</div>
		</div>

		<div class="field">
			<label class="label"><?php esc_html_e( 'Assistant ID', 'gpt-chat-assistant' ); ?></label>
			<div class="control">
				<input class="input" type="text" name="assistant_id" required>
				<p class="help"><?php esc_html_e( 'Unique identifier for this assistant', 'gpt-chat-assistant' ); ?></p>
			</div>
		</div>

		<div class="field">
			<label class="label"><?php esc_html_e( 'Description', 'gpt-chat-assistant' ); ?></label>
			<div class="control">
				<textarea class="textarea" name="assistant_description"></textarea>
			</div>
		</div>

		<div class="field">
			<label class="label"><?php esc_html_e( 'API Key', 'gpt-chat-assistant' ); ?></label>
			<div class="control">
				<div class="select">
				<select name="api_key_name">
                        <?php 
                        $api_keys = gpt_chat_get_api_keys();
                        foreach ($api_keys as $key_name => $key_value) : 
                        ?>
                            <option value="<?php echo esc_attr($key_name); ?>"><?php echo esc_html($key_name); ?></option>
                        <?php endforeach; ?>
                    </select>
				</div>
			</div>
		</div>

		<div class="field">
			<div class="control">
				<button type="submit" class="button is-primary" name="gpt_add_assistant"><?php esc_html_e( 'Add Assistant', 'gpt-chat-assistant' ); ?></button>
			</div>
		</div>
	</form>

	<h2 class="title is-4"><?php esc_html_e('Existing Assistants', 'gpt-chat-assistant'); ?></h2>
<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th><?php esc_html_e('Name', 'gpt-chat-assistant'); ?></th>
            <th><?php esc_html_e('Description', 'gpt-chat-assistant'); ?></th>
            <th><?php esc_html_e('API Key', 'gpt-chat-assistant'); ?></th>
            <th><?php esc_html_e('Shortcode', 'gpt-chat-assistant'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($assistants as $assistant) : ?>
            <tr>
                <td><?php echo esc_html($assistant['assistant_name']); ?></td>
                <td><?php echo esc_html($assistant['assistant_description']); ?></td>
                <td><?php echo esc_html($assistant['api_key_name']); ?></td>
                <td><code>[<?php echo esc_html($assistant['shortcode']); ?>]</code></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
