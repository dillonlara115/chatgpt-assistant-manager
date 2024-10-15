<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

ini_set('display_errors', 0);
error_reporting(E_ALL);

use OpenAI;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;


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

        // Increase PHP execution time and memory limit
        set_time_limit(120); // 120 seconds
        ini_set('memory_limit', '256M'); // 256MB

        $start_time = microtime(true);

        try {
            // Initialize Guzzle client with appropriate settings
            $guzzleClient = new GuzzleClient([
                'headers' => [
                    'OpenAI-Beta' => 'assistants=v2',
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 90, // Total timeout in seconds
                'connect_timeout' => 30, // Connection timeout in seconds
                'http_errors' => false, // Prevent Guzzle from throwing exceptions on HTTP errors
            ]);
    
            // Initialize the OpenAI client with Guzzle
            $client = OpenAI::factory()
                ->withHttpClient($guzzleClient)
                ->make();
    
            if (!$thread_id) {
                $thread = $client->threads()->create([]);
                $thread_id = $thread->id;
            }
    
            // Send user message
            $client->threads()->messages()->create($thread_id, [
                'role' => 'user',
                'content' => $message,
            ]);
    
            // Initiate a run with the assistant
            $run = $client->threads()->runs()->create($thread_id, [
                'assistant_id' => $assistant_id,
            ]);
            error_log("GPT Chat: Request started. Thread ID: {$thread_id}");

    
            $max_wait_time = 90; // Set a maximum wait time (in seconds)
            $start_wait_time = time();
    
            // Wait for the run to complete
            while ($run->status !== 'completed') {
                if (time() - $start_wait_time > $max_wait_time) {
                    error_log("GPT Chat: Run timed out after {$max_wait_time} seconds. Run ID: {$run->id}, Thread ID: {$thread_id}");
                    throw new Exception("Request timed out after {$max_wait_time} seconds.");
                }
                sleep(1);
                $run = $client->threads()->runs()->retrieve($thread_id, $run->id);
            }
    
            // Retrieve messages from the thread
            $messages = $client->threads()->messages()->list($thread_id);
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

    function send_openai_request($client, $thread_id, $assistant_id, $message, $max_retries = 3) {
        $attempt = 0;
        $retry_delay = 2; // seconds
    
        while ($attempt < $max_retries) {
            try {
                if (!$thread_id) {
                    $thread = $client->threads()->create([]);
                    $thread_id = $thread->id;
                }
    
                $client->threads()->messages()->create($thread_id, [
                    'role' => 'user',
                    'content' => $message,
                ]);
    
                $run = $client->threads()->runs()->create($thread_id, [
                    'assistant_id' => $assistant_id,
                ]);
    
                $max_wait_time = 90; // seconds
                $start_wait_time = time();
    
                // Wait for the run to complete
                while ($run->status !== 'completed') {
                    if (time() - $start_wait_time > $max_wait_time) {
                        error_log("GPT Chat: Run timed out after {$max_wait_time} seconds. Run ID: {$run->id}, Thread ID: {$thread_id}");
                        throw new Exception("Request timed out after {$max_wait_time} seconds.");
                    }
                    sleep(1);
                    $run = $client->threads()->runs()->retrieve($thread_id, $run->id);
                }
    
                // Retrieve assistant response
                $messages = $client->threads()->messages()->list($thread_id);
                foreach ($messages->data as $msg) {
                    if ($msg->role === 'assistant') {
                        return $msg->content[0]->text->value;
                    }
                }
    
                throw new Exception("No assistant response found.");
    
            } catch (Exception $e) {
                $attempt++;
                if ($attempt >= $max_retries) {
                    throw $e; // Re-throw the exception if max retries reached
                }
                // Wait before retrying
                error_log("GPT Chat: Attempt {$attempt} failed with error: {$e->getMessage()}. Retrying in {$retry_delay} seconds...");
                sleep($retry_delay);
                $retry_delay *= 2; // Exponential backoff
            }
        }
    }
    

    // public static function check_run_status() {
    //     check_ajax_referer('gpt_chat_nonce', 'nonce');

    //     $run_id = sanitize_text_field($_POST['run_id']);
    //     $thread_id = sanitize_text_field($_POST['thread_id']);
    //     $api_key_name = sanitize_text_field($_POST['api_key_name']);

    //     $api_keys = gpt_chat_get_api_keys();
    //     $api_key = isset($api_keys[$api_key_name]) ? $api_keys[$api_key_name] : '';

    //     if (empty($api_key)) {
    //         wp_send_json_error(['error' => __('API key not set.', 'gpt-chat-assistant')]);
    //         return;
    //     }

    //     try {
    //         $httpClient = new Psr18Client(HttpClient::create([
    //             'headers' => [
    //                 'OpenAI-Beta' => 'assistants=v2',
    //             ],
    //             'timeout' => 90, // Increase timeout for status checks
    //         ]));

    //         $client = OpenAI::factory()
    //             ->withApiKey($api_key)
    //             ->withHttpClient($httpClient)
    //             ->make();

    //         $run = $client->threads()->runs()->retrieve($thread_id, $run_id);

    //         if ($run->status === 'completed') {
    //             $messages = $client->threads()->messages()->list($thread_id);
    //             $lastMessage = $messages->data[0];
    //             $assistantResponse = $lastMessage->content[0]->text->value;

    //             wp_send_json_success([
    //                 'status' => 'completed',
    //                 'response' => $assistantResponse,
    //                 'thread_id' => $thread_id,
    //             ]);
    //         } else {
    //             wp_send_json_success([
    //                 'status' => $run->status,
    //                 'run_id' => $run_id,
    //                 'thread_id' => $thread_id,
    //             ]);
    //         }
    //     } catch (Exception $e) {
    //         error_log('GPT Chat Error: ' . $e->getMessage());
    //         wp_send_json_error(['error' => __('Error communicating with OpenAI: ', 'gpt-chat-assistant') . $e->getMessage()]);
    //     }
    // }
}
