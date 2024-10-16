<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

ini_set('display_errors', 0);
error_reporting(E_ALL);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use OpenAI;

class GPT_Chat_Ajax {
    private static $max_tokens = 4000; // Set a default max token limit

    public static function send_message() {
        set_time_limit(60); // 5 minutes
        ini_set('memory_limit', '256M');
    
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
            $httpClient = new Client([
                'base_uri' => 'https://api.openai.com/v1/assistants',
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'OpenAI-Beta' => 'assistants=v2',
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 60,
                'connect_timeout' => 60
            ]);
    
            error_log("GPT Chat: HTTP Client created. Time elapsed: " . (microtime(true) - $start_time) . " seconds");
    
            $client = OpenAI::factory()
                ->withApiKey($api_key)
                ->withHttpClient($httpClient)
                ->withStreamHandler(fn (RequestInterface $request): ResponseInterface => $client->send($request, [
                    'stream' => true
                ]))
                ->make();
    
            error_log("GPT Chat: OpenAI client created. Time elapsed: " . (microtime(true) - $start_time) . " seconds");
    
            // Start output buffering
            ob_start();
    
            // Set headers for chunked transfer encoding
            header('Content-Type: application/json');
            header('Transfer-Encoding: chunked');
    
            // Flush headers
            ob_flush();
            flush();
    
            $new_session = !$thread_id;

        if ($new_session) {
            // Create a new thread for each new session
            $thread = $client->threads()->create([]);
            $thread_id = $thread->id;
            self::sendChunk(json_encode(['status' => 'thread_created', 'thread_id' => $thread_id]));
        }

        // Add the new message to the thread
        $client->threads()->messages()->create($thread_id, [
            'role' => 'user',
            'content' => $message,
        ]);

        // Create a new run with max_tokens parameter
        $run = $client->threads()->runs()->create($thread_id, [
            'assistant_id' => $assistant_id,
        ]);

        self::sendChunk(json_encode(['status' => 'run_created', 'run_id' => $run->id]));

        $maxAttempts = 60; // 3 minutes
        $attempt = 0;

        error_log('Max tokens set to: ' . self::$max_tokens);

        while ($attempt < $maxAttempts) {
            $runStatus = $client->threads()->runs()->retrieve($thread_id, $run->id);
            self::sendChunk(json_encode(['status' => $runStatus->status]));

            if ($runStatus->status === 'completed') {
                // Retrieve the last message, which should be the assistant's response
                $messages = $client->threads()->messages()->list($thread_id, [
                    'limit' => 1,
                    'order' => 'desc'
                ]);

                if (!empty($messages->data) && $messages->data[0]->role === 'assistant') {
                    $content = $messages->data[0]->content[0]->text->value;
                    $responseTokens = self::estimateTokens($content);
                    error_log("Estimated tokens in response: " . $responseTokens);
                    self::sendChunk(json_encode([
                        'type' => 'message',
                        'content' => $content,
                    ]));
                }
                break;
            } elseif (in_array($runStatus->status, ['failed', 'cancelled', 'expired'])) {
                throw new Exception("Run failed with status: " . $runStatus->status);
            }

            $attempt++;
            usleep(500000); // Sleep for 0.5 seconds
        }

        if ($attempt >= $maxAttempts) {
            throw new Exception("Run did not complete within the expected time.");
        }

    
            // Send the end chunk
            self::sendChunk("");

            exit();
        } catch (Exception $e) {
            $end_time = microtime(true);
            $execution_time = $end_time - $start_time;
            error_log("GPT Chat Error: {$e->getMessage()}. Execution time: {$execution_time} seconds. Last status: " . ($runStatus->status ?? 'unknown'));
            self::sendChunk(json_encode(['error' => __('Error communicating with OpenAI: ', 'gpt-chat-assistant') . $e->getMessage()]));
            exit();
        }
    }
    
    // Function to send a chunk of data
    public static function sendChunk($data) {
        echo sprintf("%x\r\n%s\r\n", strlen($data), $data);
        ob_flush();
        flush();
    }

    

    // Function to set max tokens
    public static function set_max_tokens($tokens) {
        self::$max_tokens = intval($tokens);
    }

    private static function estimateTokens($text) {
        // This is a rough estimate. OpenAI uses a more complex tokenization.
        return (int)(strlen($text) / 4);
    }
}