<?php
/**
 * OpenRouter API Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_AFG_API_OpenRouter {
    
    /**
     * API endpoint
     */
    private $api_endpoint = 'https://openrouter.ai/api/v1/chat/completions';
    
    /**
     * API key
     */
    private $api_key;
    
    /**
     * Model name
     */
    private $model = 'openai/gpt-oss-120b';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key = get_option('wpafg_openrouter_api_key', '');
    }
    
    /**
     * Set API key
     */
    public function set_api_key($api_key) {
        $this->api_key = $api_key;
    }
    
    /**
     * Enhance prompt for image generation
     */
    public function enhance_prompt($title, $content_excerpt) {
        $system_prompt = "You are an expert AI art prompter. Analyze the blog title and the provided content excerpt. Write a concise, detailed visual description for a featured image that represents the core topic. Do not include text in the image.";
        
        $user_content = "Title: {$title}\n\nContent Excerpt: {$content_excerpt}";
        
        return $this->make_request($system_prompt, $user_content);
    }
    
    /**
     * Generate alt text for image
     */
    public function generate_alt_text($title, $content_excerpt) {
        $system_prompt = "You are an SEO expert. Write concise, descriptive alt text for images.";
        
        $user_content = "Based on the following blog title and content excerpt, write a 100-character max SEO alt text for the featured image:\nTitle: {$title}\nContent: {$content_excerpt}";
        
        return $this->make_request($system_prompt, $user_content);
    }
    
    /**
     * Make API request
     */
    private function make_request($system_prompt, $user_content) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('OpenRouter API key is not configured.', 'wp-auto-feature-gen'));
        }
        
        $response = wp_remote_post($this->api_endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => $this->model,
                'messages' => array(
                    array(
                        'role' => 'system',
                        'content' => $system_prompt,
                    ),
                    array(
                        'role' => 'user',
                        'content' => $user_content,
                    ),
                ),
            )),
            'timeout' => 60,
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            return new WP_Error('api_error', sprintf(__('OpenRouter API error: %s', 'wp-auto-feature-gen'), $status_code . ' - ' . $body));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['choices'][0]['message']['content'])) {
            return trim($body['choices'][0]['message']['content']);
        }
        
        return new WP_Error('invalid_response', __('Invalid response from OpenRouter API.', 'wp-auto-feature-gen'));
    }
}

