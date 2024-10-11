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
                    'OpenAI-Beta' => 'assistants=v2',
                ],
            ]));
    
            $client = OpenAI::factory()
                ->withApiKey($api_key)
                ->withHttpClient($httpClient)
                ->make();
    
            if ($thread_id) {
                // Continue the conversation in the existing thread
                $response = $client->threads()->messages()->create($thread_id, [
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $message,
                        ],
                    ],
                ]);
            } else {
                // Create a new thread and send the initial message
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
            }
    
            // Ensure the thread ID is correctly set
            $threadDetails = [
                'id' => $response->id,
                'object' => $response->object,
                'createdAt' => $response->createdAt,
                'assistantId' => $response->assistantId,
                'threadId' => $response->threadId,  // Ensure this is set correctly
                'status' => $response->status,
                'model' => $response->model,
                'usage' => $response->usage->total_tokens,
                'responseFormat' => $response->responseFormat,
            ];
    
            // Log the thread details for debugging
            error_log('Thread Details: ' . print_r($threadDetails, true));
    
            // Retrieve the messages from the thread
            $messages = $client->threads()->messages()->list($response->threadId);
    
            // Get the last message (which should be the assistant's response)
            $lastMessage = $messages->data[0];
            $assistantResponse = $lastMessage->content[0]->text->value;
    
            wp_send_json_success(['response' => $assistantResponse, 'thread_details' => $threadDetails]);
        } catch (Exception $e) {
            wp_send_json_error(['error' => __('Error communicating with OpenAI: ', 'gpt-chat-assistant') . $e->getMessage()]);
        }
    }
}