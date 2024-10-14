<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

use OpenAI;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpClient\HttpClient;

class GPT_Chat_Ajax {
    public static function send_message() {
        check_ajax_referer('gpt_chat_nonce', 'nonce');
    
        $message = sanitize_text_field($_POST['message']);
        $api_key_name = sanitize_text_field($_POST['api_key_name']);
        $assistant_id = sanitize_text_field($_POST['assistant_id']);
        $thread_id = isset($_POST['thread_id']) ? sanitize_text_field($_POST['thread_id']) : null;
    
        $api_keys = gpt_chat_get_api_keys();
        $api_key = isset($api_keys[$api_key_name]) ? $api_keys[$api_key_name] : '';
    
        if (empty($api_key)) {
            wp_send_json_error(['error' => __('API key not set. Please configure the API key in the plugin settings.', 'gpt-chat-assistant')]);
            return;
        }
    
        try {
            $httpClient = new Psr18Client(HttpClient::create([
                'headers' => [
                    'OpenAI-Beta' => 'assistants=v1',
                ],
            ]));
    
            $client = OpenAI::factory()
                ->withApiKey($api_key)
                ->withHttpClient($httpClient)
                ->make();
    
            if ($thread_id) {
                // Continue the conversation in the existing thread
                $client->threads()->messages()->create($thread_id, [
                    'role' => 'user',
                    'content' => $message,
                ]);
            } else {
                // Create a new thread
                $thread = $client->threads()->create([]);
                $thread_id = $thread->id;

                // Add the initial message to the thread
                $client->threads()->messages()->create($thread_id, [
                    'role' => 'user',
                    'content' => $message,
                ]);
            }

            // Run the assistant
            $run = $client->threads()->runs()->create($thread_id, [
                'assistant_id' => $assistant_id,
            ]);

            // Instead of waiting, return the run ID and thread ID
            wp_send_json_success([
                'status' => 'processing',
                'run_id' => $run->id,
                'thread_id' => $thread_id,
            ]);

        } catch (Exception $e) {
            error_log('GPT Chat Error: ' . $e->getMessage());
            wp_send_json_error(['error' => __('Error communicating with OpenAI. Please try again later.', 'gpt-chat-assistant')]);
        }
    }

    public static function check_run_status() {
        check_ajax_referer('gpt_chat_nonce', 'nonce');

        $run_id = sanitize_text_field($_POST['run_id']);
        $thread_id = sanitize_text_field($_POST['thread_id']);
        $api_key_name = sanitize_text_field($_POST['api_key_name']);

        $api_keys = gpt_chat_get_api_keys();
        $api_key = isset($api_keys[$api_key_name]) ? $api_keys[$api_key_name] : '';

        if (empty($api_key)) {
            wp_send_json_error(['error' => __('API key not set.', 'gpt-chat-assistant')]);
            return;
        }

        try {
            $client = OpenAI::client($api_key);

            $run = $client->threads()->runs()->retrieve($thread_id, $run_id);

            if ($run->status === 'completed') {
                $messages = $client->threads()->messages()->list($thread_id);
                $lastMessage = $messages->data[0];
                $assistantResponse = $lastMessage->content[0]->text->value;

                wp_send_json_success([
                    'status' => 'completed',
                    'response' => $assistantResponse,
                    'thread_id' => $thread_id,
                ]);
            } else {
                wp_send_json_success([
                    'status' => $run->status,
                    'run_id' => $run_id,
                    'thread_id' => $thread_id,
                ]);
            }
        } catch (Exception $e) {
            error_log('GPT Chat Error: ' . $e->getMessage());
            wp_send_json_error(['error' => __('Error checking run status. Please try again later.', 'gpt-chat-assistant')]);
        }
    }
}