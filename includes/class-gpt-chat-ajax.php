<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

ini_set('display_errors', 0);
error_reporting(E_ALL);

use OpenAI;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpClient\HttpClient;

class GPT_Chat_Ajax {
    public static function send_message() {
        check_ajax_referer('gpt_chat_nonce', 'nonce');
    
        $message = sanitize_text_field($_POST['message']);
        $api_key_name = sanitize_text_field($_POST['api_key_name']);
        $assistant_id = sanitize_text_field($_POST['assistant_id']);
        $thread_id = isset($_POST['thread_id']) && $_POST['thread_id'] !== 'null' ? sanitize_text_field($_POST['thread_id']) : null;
    
        $api_keys = gpt_chat_get_api_keys();
        $api_key = isset($api_keys[$api_key_name]) ? $api_keys[$api_key_name] : '';
    
        if (empty($api_key)) {
            wp_send_json_error(['error' => __('API key not set. Please configure the API key in the plugin settings.', 'gpt-chat-assistant')]);
            return;
        }
        $start_time = microtime(true);
        error_log("GPT Chat: Starting request. Thread ID: " . ($thread_id ?? 'New Thread'));
    
        try {
            $httpClient = new Psr18Client(HttpClient::create([
                'headers' => [
                    'OpenAI-Beta' => 'assistants=v2',
                ],
                'timeout' => 280,
                'max_duration' => 300, // 5 minutes total duration
                'http_version' => '2.0',
                
                
            ]));
    
            error_log("GPT Chat: HTTP Client created. Time elapsed: " . (microtime(true) - $start_time) . " seconds");
    
            $client = OpenAI::factory()
                ->withApiKey($api_key)
                ->withHttpClient($httpClient)
                ->make();
    
            error_log("GPT Chat: OpenAI client created. Time elapsed: " . (microtime(true) - $start_time) . " seconds");
    
            if (!$thread_id) {
                $thread = $client->threads()->create([]);
                $thread_id = $thread->id;
                error_log("GPT Chat: New thread created. Thread ID: $thread_id. Time elapsed: " . (microtime(true) - $start_time) . " seconds");
            }
    
            $client->threads()->messages()->create($thread_id, [
                'role' => 'user',
                'content' => $message,
            ]);
            error_log("GPT Chat: Message added to thread. Time elapsed: " . (microtime(true) - $start_time) . " seconds");
    
            $run = $client->threads()->runs()->create($thread_id, [
                'assistant_id' => $assistant_id,
            ]);
            error_log("GPT Chat: Run created. Run ID: {$run->id}. Time elapsed: " . (microtime(true) - $start_time) . " seconds");
    
            $max_wait_time = 280; // Reduced from 190 to allow for processing time
            $start_wait_time = time();
    
            // Wait for the run to complete
            while ($run->status !== 'completed') {
                if (time() - $start_wait_time > $max_wait_time) {
                    error_log("GPT Chat: Run timed out after {$max_wait_time} seconds. Run ID: {$run->id}, Thread ID: {$thread_id}");
                    throw new Exception("Request timed out after {$max_wait_time} seconds.");
                }
                sleep(1);
                $run = $client->threads()->runs()->retrieve($thread_id, $run->id);
                error_log("GPT Chat: Run status: {$run->status}. Time elapsed: " . (microtime(true) - $start_time) . " seconds");
            }
    
            $messages = $client->threads()->messages()->list($thread_id);
            error_log("GPT Chat: Messages retrieved. Time elapsed: " . (microtime(true) - $start_time) . " seconds");
    
            $assistantResponse = '';
            foreach ($messages->data as $msg) {
                if ($msg->role === 'assistant') {
                    $assistantResponse = $msg->content[0]->text->value;
                    break;
                }
            }
    
            $end_time = microtime(true);
            $execution_time = $end_time - $start_time;
            error_log("GPT Chat: Request completed in {$execution_time} seconds. Thread ID: {$thread_id}");
    
            wp_send_json_success([
                'thread_id' => $thread_id,
                'response' => $assistantResponse,
            ]);
    
        } catch (Exception $e) {
            $end_time = microtime(true);
            $execution_time = $end_time - $start_time;
            error_log("GPT Chat Error: {$e->getMessage()}. Execution time: {$execution_time} seconds.");
            wp_send_json_error(['error' => __('Error communicating with OpenAI: ', 'gpt-chat-assistant') . $e->getMessage()]);
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
            $httpClient = new Psr18Client(HttpClient::create([
                'headers' => [
                    'OpenAI-Beta' => 'assistants=v2',
                ],
                'timeout' => 280,
                'max_duration' => 300, // 5 minutes total duration
                'http_version' => '2.0',
                
            ]));

            $client = OpenAI::factory()
                ->withApiKey($api_key)
                ->withHttpClient($httpClient)
                ->withStreamHandler(fn (RequestInterface $request): ResponseInterface => $client->send($request, [
                    'stream' => true // Allows to provide a custom stream handler for the http client.
                ]))
                ->make();

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
            wp_send_json_error(['error' => __('Error communicating with OpenAI: ', 'gpt-chat-assistant') . $e->getMessage()]);
        }
    }
}
