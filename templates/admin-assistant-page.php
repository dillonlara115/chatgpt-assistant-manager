<div class="wrap">
	<h1><?php esc_html_e( 'Manage GPT Assistants', 'gpt-chat-assistant' ); ?></h1>

	<form method="POST" class="mb-4">
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
			<label class="label"><?php esc_html_e( 'Zapier Webhook URL(optional)', 'gpt-chat-assistant' ); ?></label>
			<div class="control">
				<input class="input" type="url" name="zapier_webhook_url" placeholder="https://hooks.zapier.com/...">
				<p class="help"><?php esc_html_e( 'Enter the Zapier webhook URL to integrate with', 'gpt-chat-assistant' ); ?></p>
			</div>
		</div>

		<div class="field">
			<label class="label"><?php esc_html_e( 'Description', 'gpt-chat-assistant' ); ?></label>
			<div class="control">
				<textarea class="textarea" name="assistant_description"></textarea>
			</div>
		</div>

		<div class="mb-4">
			<label class="label"><?php esc_html_e('Zapier Data Headers', 'gpt-chat-assistant'); ?></label>
			<div id="zapier-headers-container">
				<div class="field is-grouped">
					<p class="control is-expanded">
						<input type="text" class="input" name="zapier_headers[]" placeholder="Enter header name (e.g. first_name, email)">
					</p>
					<p class="control">
						<button type="button" class="button is-danger remove-header" style="margin-left: 10px;">Remove</button>
					</p>
				</div>
			</div>
			<div class="field">
				<button type="button" class="button is-info mt-2" id="add-header">Add Header</button>
			</div>
		</div>

		<div class="field">
			<label class="label"><?php esc_html_e( 'API Key', 'gpt-chat-assistant' ); ?></label>
			<div class="control">
				<select class="input" name="api_key_name">
                        <?php 
                        $api_keys = gpt_chat_get_api_keys();
                        foreach ($api_keys as $key_name => $key_value) : 
                        ?>
                            <option value="<?php echo esc_attr($key_name); ?>"><?php echo esc_html($key_name); ?></option>
                        <?php endforeach; ?>
                    </select>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#add-header').click(function() {
				var newRow = $('<div class="zapier-header-row field is-grouped">' +
					'<p class="control is-expanded"><input type="text" class="input zapier-header" name="zapier_headers[]" placeholder="Enter header name (e.g. first_name, email)"></p>' +
					'<p class="control"><button type="button" class="button is-danger remove-header" style="margin-left: 10px;">Remove</button></p >' +
					'</div>');
				$('#zapier-headers-container').append(newRow);
			});

			$(document).on('click', '.remove-header', function() {
				$(this).parent().remove();
			});
		});
		</script>

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
            <th><?php esc_html_e('Actions', 'gpt-chat-assistant'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($assistants as $assistant) : ?>
            <tr>
                <td><?php echo esc_html($assistant['assistant_name']); ?></td>
                <td><?php echo esc_html($assistant['assistant_description']); ?></td>
                <td><?php echo esc_html($assistant['api_key_name']); ?></td>
                <td><code>[<?php echo esc_html($assistant['shortcode']); ?>]</code></td>
                <td>
                    <button class="button edit-assistant" data-assistant-id="<?php echo esc_attr($assistant['id']); ?>">
                        <?php esc_html_e('Edit', 'gpt-chat-assistant'); ?>
                    </button>
                    <button class="button delete-assistant" data-assistant-id="<?php echo esc_attr($assistant['id']); ?>">
                        <?php esc_html_e('Delete', 'gpt-chat-assistant'); ?>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<div id="edit-assistant-modal" class="modal">
  <div class="modal-background"></div>
  <div class="modal-card">
    <header class="modal-card-head">
      <p class="modal-card-title"><?php esc_html_e('Edit Assistant', 'gpt-chat-assistant'); ?></p>
      <button class="delete" aria-label="close"></button>
    </header>
    <section class="modal-card-body">
      <!-- Form fields similar to the Add Assistant form -->
      <input type="hidden" name="edit_id" value="">
      <div class="field">
        <label class="label"><?php esc_html_e('Assistant Name', 'gpt-chat-assistant'); ?></label>
        <div class="control">
          <input class="input" type="text" name="edit_assistant_name" required>
        </div>

        <label class="label"><?php esc_html_e('Assistant ID', 'gpt-chat-assistant'); ?></label>
        <div class="control">
          <input class="input" type="text" name="edit_assistant_id" required>
        </div>
        <label class="label"><?php esc_html_e('Zapier Webhook URL', 'gpt-chat-assistant'); ?></label>
        <div class="control">
          <input class="input" type="url" name="edit_zapier_webhook_url" required>
        </div>

        <label class="label"><?php esc_html_e('Assistant Description', 'gpt-chat-assistant'); ?></label>
        <div class="control">
          <textarea class="textarea" name="edit_assistant_description" required></textarea>
        </div>
        <div class="mb-4">
            <label class="label"><?php esc_html_e('Zapier Data Headers', 'gpt-chat-assistant'); ?></label>
            <div id="edit-zapier-headers-container">
                <?php 
                $zapier_headers = json_decode($assistant['zapier_headers'], true) ?: array();
                foreach($zapier_headers as $header): ?>
                    <div class="field is-grouped">
                        <p class="control is-expanded">
                            <input type="text" class="input zapier-header" name="edit_zapier_headers[]" value="<?php echo esc_attr($header); ?>" placeholder="Enter header name (e.g. first_name, email)">
                        </p>
                        <p class="control">
                            <button type="button" class="button is-danger remove-header" data-header="<?php echo esc_attr($header); ?>" style="margin-left: 10px;">Remove</button>
                        </p>
                    </div>
                <?php endforeach; ?>
                <p class="control">
                    <button type="button" class="button is-info" id="edit-add-header">Add Header</button>
                </p>
            </div>
        </div>

        <label class="label"><?php esc_html_e('API Key Name', 'gpt-chat-assistant'); ?></label>
<div class="control">
    <select class="input" name="edit_api_key_name" required>
        <?php 
        $api_keys = gpt_chat_get_api_keys();
        foreach ($api_keys as $key_name => $key_value) : 
            // Debug the values
            error_log('Comparing key_name: ' . $key_name . ' with assistant api_key_name: ' . $assistant['api_key_name']);
            $selected = ($key_name === $assistant['api_key_name']) ? 'selected' : '';
        ?>
            <option value="<?php echo esc_attr($key_name); ?>" <?php echo $selected; ?>>
                <?php echo esc_html($key_name); ?>
            </option>r
        <?php endforeach; ?>
    </select>
</div>

<script>
jQuery(document).ready(function($) {
    // Change from direct binding to event delegation
    $(document).on('click', '#edit-add-header', function() {
        var newHeader = `
            <div class="field is-grouped">
                <p class="control is-expanded">
                    <input type="text" class="input zapier-header" name="edit_zapier_headers[]" placeholder="Enter header name (e.g. first_name, email)">
                </p>
                <p class="control">
                    <button type="button" class="button is-danger remove-header" style="margin-left: 10px;">Remove</button>
                </p>
            </div>`;
        $(this).parent().before(newHeader);  // Insert before the "Add Header" button container
    });
    // Remove header button click handler using event delegation
    $('#edit-zapier-headers-container').on('click', '.remove-header', function() {
        $(this).closest('.field').remove();
    });
});
</script>


      </div>
      <!-- Add other fields here -->
    </section>
    <footer class="modal-card-foot">
      <button class="button is-success"><?php esc_html_e('Save changes', 'gpt-chat-assistant'); ?></button>
      <button class="button"><?php esc_html_e('Cancel', 'gpt-chat-assistant'); ?></button>
    </footer>
  </div>
</div>



<script type="text/javascript">
jQuery(document).ready(function($) {

   

	function saveAssistantUpdates() {
		console.log('saveAssistantUpdates clicked');

        var zapierHeaders = [];
    $('#edit-zapier-headers-container input.zapier-header').each(function() {
        console.log('Found header input:', $(this).val());
        if ($(this).val().trim()) { // Only include non-empty values
            zapierHeaders.push($(this).val().trim());
        }
    });
    
    console.log('Collected headers:', zapierHeaders);
    
	    var assistantData = {
            action: 'save_assistant_updates',
            id: $('input[name="edit_id"]').val(),
            assistant_id: $('input[name="edit_assistant_id"]').val(),
            assistant_name: $('input[name="edit_assistant_name"]').val(),
            zapier_webhook_url: $('input[name="edit_zapier_webhook_url"]').val(),
            assistant_description: $('textarea[name="edit_assistant_description"]').val(),
            api_key_name: $('select[name="edit_api_key_name"]').val(),
            zapier_headers: zapierHeaders,
            security: '<?php echo wp_create_nonce('save_assistant_updates_nonce'); ?>'
        };

	$.ajax({
        url: ajaxurl,
        type: 'POST',
        data: assistantData,
        success: function(response) {
            if (response.success) {
                alert('Assistant updated successfully!');
                location.reload();
            } else {
                alert('Error: ' + response.data);
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.log('AJAX error: ' + textStatus + ' : ' + errorThrown);
        }
    });
	}

	$('.modal-card-foot .button.is-success').on('click', function(e) {
	    e.preventDefault();
	    saveAssistantUpdates();
	});
	$('.edit-assistant').on('click', function(e) {
    e.preventDefault();
    var assistantId = $(this).data('assistant-id');

    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'get_assistant_data',
            assistant_id: assistantId,
            security: '<?php echo wp_create_nonce('get_assistant_data_nonce'); ?>'
        },
        success: function(response) {
            if (response.success) {
                var assistant = response.data;
                // Clear existing headers
                $('#edit-zapier-headers-container').empty();
                
                // Add headers from the selected assistant
                if (assistant.zapier_headers) {
                    var headers = JSON.parse(assistant.zapier_headers);
                    headers.forEach(function(header) {
                        var headerRow = `
                            <div class="field is-grouped">
                                <p class="control is-expanded">
                                    <input type="text" class="input zapier-header" name="edit_zapier_headers[]" value="${header}" placeholder="Enter header name (e.g. first_name, email)">
                                </p>
                                <p class="control">
                                    <button type="button" class="button is-danger remove-header" style="margin-left: 10px;">Remove</button>
                                </p>
                            </div>`;
                        $('#edit-zapier-headers-container').append(headerRow);
                    });
                }
                
                // Add the "Add Header" button back
                $('#edit-zapier-headers-container').append(`
                    <p class="control">
                        <button type="button" class="button is-info" id="edit-add-header">Add Header</button>
                    </p>
                `);

                // Set other fields...
                $('input[name="edit_id"]').val(assistant.id);
                $('input[name="edit_assistant_name"]').val(assistant.assistant_name);
                // ... rest of your field population code ...

                $('#edit-assistant-modal').addClass('is-active');
            } else {
                alert('Error: ' + response.data);
            }
        }
    });
});

    $('.modal-background, .delete, .modal-card-foot .button').on('click', function() {
        $('#edit-assistant-modal').removeClass('is-active');
    });

    $('.delete-assistant').on('click', function(e) {
        e.preventDefault();
        if (confirm('<?php esc_html_e('Are you sure you want to delete this assistant?', 'gpt-chat-assistant'); ?>')) {
            var assistantId = $(this).data('assistant-id');
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'delete_assistant',
                    assistant_id: assistantId,
                    _wpnonce: '<?php echo wp_create_nonce('delete_assistant_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data);
                    }
                }
            });
        }
    });
});
</script>
<style>
    .wp-core-ui p .button {
        font-size: 16px;
        vertical-align: top;
    }
    .wp-core-ui .button-secondary:hover, .wp-core-ui .button.hover, .wp-core-ui .button:hover, .wp-core-ui .button:focus {
        background-color: #3488ce !important;
        color: white;
    }
</style>