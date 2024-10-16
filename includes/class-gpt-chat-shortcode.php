<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class GPT_Chat_Shortcode {
    public static function render_chat($assistant) {
        if (!is_array($assistant) || empty($assistant)) {
            return __('Invalid assistant data', 'gpt-chat-assistant');
        }

        ob_start();
        ?>
        <div class="chatbot-container gpt-chatbot" 
     data-assistant-id="<?php echo esc_attr($assistant['assistant_id']); ?>" 
     data-api-key-name="<?php echo esc_attr($assistant['api_key_name']); ?>">
    <div class="chatbot-header">
        <img src="https://mixituponline.com/wp-content/uploads/2024/08/0005_molly_okane_08_23_2024-scaled.jpg" alt="Brand Voice Explorer">
        <span>Brand Voice Explorer for Small Businesses</span>
    </div>
    <div class="chat-messages gpt-chat-messages" id="gpt-chat-messages">
        <!-- Messages will be appended here -->
    </div>
    <div class="user-input gpt-chat-input">
        <input type="text" id="gpt-chat-message" placeholder="<?php esc_attr_e('Type a message...', 'gpt-chat-assistant'); ?>" />
        <button class="button is-link" id="gpt-chat-send"><?php esc_html_e('Send', 'gpt-chat-assistant'); ?></button>
    </div>
    <div class="powered-by">
        Powered by AI
    </div>
    <style>
        .chatbot-container {
    max-width: 600px;
    margin: 0 auto;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    font-family: Arial, sans-serif;
}

.chatbot-header {
    background-color: #f27931;
    color: white;
    padding: 10px;
    display: flex;
    align-items: center;
}

.chatbot-header img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    margin-right: 10px;
}

.chatbot-header span {
    font-weight: bold;
    font-size: 18px;
}

.chat-messages {
    height: 300px;
    overflow-y: auto;
    padding: 15px;
    background-color: #f9f9f9;
}

.message {
    margin-bottom: 10px;
    padding: 10px;
    border-radius: 5px;
    max-width: 80%;
}

.bot-message {
    background-color: #e1f5fe;
    align-self: flex-start;
}

.user-message {
    background-color: #fff;
    align-self: flex-end;
    margin-left: auto;
}

.user-input {
    display: flex;
    padding: 10px;
    background-color: #fff;
}

.user-input input {
    flex-grow: 1;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-right: 10px;
}

.user-input button {
    background-color: #f27931;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
}

.powered-by {
    text-align: center;
    font-size: 12px;
    color: #888;
    padding: 5px;
    background-color: #f9f9f9;
}
    </style>
</div>
        <?php
        return ob_get_clean();
    }
}