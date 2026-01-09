<?php
/**
 * Kie.ai API Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_AFG_API_Kie {
    
    /**
     * API endpoint for creating tasks
     */
    private $create_endpoint = 'https://api.kie.ai/api/v1/jobs/createTask';
    
    /**
     * API endpoint for checking job status
     */
    private $status_endpoint = 'https://api.kie.ai/api/v1/jobs/';
    
    /**
     * API key
     */
    private $api_key;
    
    /**
     * Model name
     */
    private $model = 'nano-banana-pro';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key = get_option('wpafg_kie_api_key', '');
    }
    
    /**
     * Set API key
     */
    public function set_api_key($api_key) {
        $this->api_key = $api_key;
    }
    
    /**
     * Generate image using callback method
     */
    public function generate_image($prompt, $aspect_ratio = '16:9', $resolution = '1K', $output_format = 'jpg', $task_id = null) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Kie.ai API key is not configured.', 'wp-auto-feature-gen'));
        }
        
        // Build callback URL
        $callback_url = admin_url('admin-ajax.php?action=wpafg_kie_callback');
        if ($task_id) {
            $callback_url .= '&task_id=' . urlencode($task_id);
        }
        
        // Create task with callback
        $request_body = array(
            'model' => $this->model,
            'callBackUrl' => $callback_url,
            'input' => array(
                'prompt' => $prompt,
                'aspect_ratio' => $aspect_ratio,
                'resolution' => $resolution,
                'output_format' => $output_format,
            ),
        );
        
        $response = wp_remote_post($this->create_endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($request_body),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $body = json_decode($response_body, true);
        
        // Check response structure based on new API format
        if ($status_code !== 200) {
            $error_message = __('Kie.ai API error', 'wp-auto-feature-gen');
            if (isset($body['message'])) {
                $error_message .= ': ' . $body['message'];
            } elseif (isset($body['msg'])) {
                $error_message .= ': ' . $body['msg'];
            } else {
                $error_message .= ' (HTTP ' . $status_code . ')';
                if (!empty($response_body)) {
                    $error_message .= ' - ' . substr($response_body, 0, 200);
                }
            }
            return new WP_Error('api_error', $error_message, array('status_code' => $status_code, 'response' => $body));
        }
        
        // Check for task ID in response
        if (isset($body['code']) && $body['code'] === 200 && isset($body['data']['taskId'])) {
            // Return task ID - callback will handle the rest
            return array(
                'task_id' => $body['data']['taskId'],
                'status' => 'pending',
            );
        }
        
        // If we get here, response format is unexpected
        return new WP_Error('invalid_response', sprintf(__('Invalid response from Kie.ai API. Response: %s', 'wp-auto-feature-gen'), substr($response_body, 0, 500)));
    }
    
    /**
     * Poll job status until completed
     */
    private function poll_job_status($job_id, $max_attempts = 30) {
        $attempt = 0;
        
        while ($attempt < $max_attempts) {
            sleep(2); // Wait 2 seconds between polls
            
            $response = wp_remote_get($this->status_endpoint . $job_id, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                ),
                'timeout' => 30,
            ));
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code !== 200) {
                $body = wp_remote_retrieve_body($response);
                return new WP_Error('api_error', sprintf(__('Kie.ai status check error: %s', 'wp-auto-feature-gen'), $status_code . ' - ' . $body));
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($body['status'])) {
                if ($body['status'] === 'COMPLETED') {
                    return $this->extract_image_url($body);
                } elseif ($body['status'] === 'FAILED' || $body['status'] === 'ERROR') {
                    return new WP_Error('job_failed', __('Job failed during processing.', 'wp-auto-feature-gen'));
                }
                // Continue polling if PENDING or PROCESSING
            }
            
            $attempt++;
        }
        
        return new WP_Error('timeout', __('Job polling timeout after 60 seconds.', 'wp-auto-feature-gen'));
    }
    
    /**
     * Extract image URL from response
     */
    private function extract_image_url($body) {
        if (isset($body['output']['image_url'])) {
            return $body['output']['image_url'];
        }
        
        return new WP_Error('no_image_url', __('No image URL found in response.', 'wp-auto-feature-gen'));
    }
}

