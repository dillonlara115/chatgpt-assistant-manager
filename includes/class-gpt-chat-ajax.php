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
        $assistant_id = sanitize_text_field($_POST['assistant_id']); // Retrieve the assistant_id from the request

        
        $api_keys = gpt_chat_get_api_keys();
        $api_key = isset($api_keys[$api_key_name]) ? $api_keys[$api_key_name] : '';

        if (empty($api_key)) {
            wp_send_json_error(['error' => __('API key not set. Please configure the API key in the plugin settings.', 'gpt-chat-assistant')]);
            return;
        }

        try {
            $httpClient = new Psr18Client(HttpClient::create([
                'headers' => [
                    'OpenAI-Beta' => 'assistants=v2',
                ],
            ]));

            $client = OpenAI::factory()
                ->withApiKey($api_key)
                ->withHttpClient($httpClient)
                ->make();

            // Send a message to the assistant
            $response = $client->threads()->createAndRun([
                'assistant_id' => $assistant_id,
                'thread' => [
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $message,
                        ],
                    ],
                ],
            ]);

            // Check if there's an existing thread ID in the session
            $threadId = isset($_SESSION['gpt_chat_thread_id']) ? $_SESSION['gpt_chat_thread_id'] : null;

            if ($threadId) {
                // If a thread exists, add the new message to it
                $message = $client->threads()->messages()->create($threadId, [
                    'role' => 'user',
                    'content' => $message,
                ]);

                // Run the assistant on the existing thread
                $run = $client->threads()->runs()->create($threadId, [
                    'assistant_id' => $assistant_id,
                ]);
            } else {
                // If no thread exists, create a new one
                $thread = $client->threads()->create([]);
                $threadId = $thread->id;

                // Add the message to the new thread
                $message = $client->threads()->messages()->create($threadId, [
                    'role' => 'user',
                    'content' => $message,
                ]);

                // Run the assistant on the new thread
                $run = $client->threads()->runs()->create($threadId, [
                    'assistant_id' => $assistant_id,
                ]);

                // Store the thread ID in the session for future use
                $_SESSION['gpt_chat_thread_id'] = $threadId;
            }

            // Wait for the run to complete
            while ($run->status !== 'completed') {
                sleep(1); // Wait for 1 second before checking again
                $run = $client->threads()->runs()->retrieve(
                    threadId: $threadId,
                    runId: $run->id
                );
            }

            // Retrieve the messages from the thread
            $messages = $client->threads()->messages()->list($threadId);

            // Get the last message (which should be the assistant's response)
            $lastMessage = $messages->data[0];
            $assistantResponse = $lastMessage->content[0]->text->value;

            wp_send_json_success([
                'response' => $assistantResponse,
                'thread_id' => $threadId
            ]);
        } catch (Exception $e) {
            wp_send_json_error(['error' => __('Error communicating with OpenAI: ', 'gpt-chat-assistant') . $e->getMessage()]);
        }
    }
}