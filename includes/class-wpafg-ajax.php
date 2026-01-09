<?php
/**
 * AJAX handlers
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_AFG_Ajax {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Get instance of this class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('wp_ajax_wpafg_generate_featured_image', array($this, 'generate_featured_image'));
        add_action('wp_ajax_wpafg_get_draft_posts', array($this, 'get_draft_posts'));
        add_action('wp_ajax_wpafg_get_filtered_posts', array($this, 'get_filtered_posts'));
        add_action('wp_ajax_wpafg_kie_callback', array($this, 'handle_kie_callback'));
        add_action('wp_ajax_nopriv_wpafg_kie_callback', array($this, 'handle_kie_callback'));
        add_action('wp_ajax_wpafg_stop_generation', array($this, 'stop_generation'));
        add_action('wp_ajax_wpafg_check_status', array($this, 'check_status'));
        add_action('wp_ajax_wpafg_clear_debug_log', array($this, 'clear_debug_log'));
    }
    
    /**
     * Generate featured image for a post
     */
    public function generate_featured_image() {
        // Verify nonce
        check_ajax_referer('wpafg_nonce', 'nonce');
        
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'wp-auto-feature-gen')));
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid post ID.', 'wp-auto-feature-gen')));
        }
        
        // Get post
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(array('message' => __('Post not found.', 'wp-auto-feature-gen')));
        }
        
        // Check if post status is draft or scheduled
        if (!in_array($post->post_status, array('draft', 'future'))) {
            wp_send_json_error(array('message' => __('Post must be draft or scheduled.', 'wp-auto-feature-gen')));
        }
        
        // Check if post type is post or page
        if (!in_array($post->post_type, array('post', 'page'))) {
            wp_send_json_error(array('message' => __('Only posts and pages are supported.', 'wp-auto-feature-gen')));
        }
        
        // Check if already has featured image
        if (get_post_thumbnail_id($post_id)) {
            wp_send_json_error(array('message' => __('Post already has a featured image.', 'wp-auto-feature-gen')));
        }
        
        // Initialize API classes
        $openrouter = new WP_AFG_API_OpenRouter();
        $kie = new WP_AFG_API_Kie();
        
        // Step A: Enhance prompt with OpenRouter
        $content_excerpt = wp_strip_all_tags($post->post_content);
        $content_excerpt = substr($content_excerpt, 0, 1000);
        
        $enhanced_prompt = $openrouter->enhance_prompt($post->post_title, $content_excerpt);
        
        if (is_wp_error($enhanced_prompt)) {
            wp_send_json_error(array('message' => $enhanced_prompt->get_error_message()));
        }
        
        // Apply prompt style
        $prompt_style = get_option('wpafg_prompt_style', '');
        $final_prompt = $enhanced_prompt;
        if (!empty($prompt_style)) {
            $final_prompt = $enhanced_prompt . ', style: ' . $prompt_style;
        }
        
        // Step B: Generate image with Kie.ai (callback method)
        // Store task info for callback
        $task_id = 'post_' . $post_id . '_' . time();
        update_post_meta($post_id, '_wpafg_task_id', $task_id);
        update_post_meta($post_id, '_wpafg_status', 'pending');
        
        WP_AFG_Debug::info('Starting image generation', array('post_id' => $post_id, 'task_id' => $task_id, 'prompt_length' => strlen($final_prompt)));
        
        $result = $kie->generate_image($final_prompt, '16:9', '1K', 'jpg', $task_id);
        
        if (is_wp_error($result)) {
            // Get detailed error information
            $error_data = $result->get_error_data();
            $error_message = $result->get_error_message();
            
            WP_AFG_Debug::error('Kie.ai API error', array('post_id' => $post_id, 'error' => $error_message, 'error_data' => $error_data));
            
            // Include additional error details if available
            if (isset($error_data['response'])) {
                $error_message .= ' | Response: ' . json_encode($error_data['response']);
            }
            
            update_post_meta($post_id, '_wpafg_status', 'error');
            wp_send_json_error(array('message' => $error_message));
        }
        
        // Store Kie.ai task ID
        if (isset($result['task_id'])) {
            update_post_meta($post_id, '_wpafg_kie_task_id', $result['task_id']);
            WP_AFG_Debug::info('Kie.ai task created', array('post_id' => $post_id, 'kie_task_id' => $result['task_id']));
        }
        
        // Return immediately - callback will handle completion
        wp_send_json_success(array(
            'message' => __('Image generation started. Waiting for callback...', 'wp-auto-feature-gen'),
            'task_id' => $task_id,
            'status' => 'pending',
        ));
    }
    
    /**
     * Get draft posts without featured images
     */
    public function get_draft_posts() {
        // Verify nonce
        check_ajax_referer('wpafg_nonce', 'nonce');
        
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'wp-auto-feature-gen')));
        }
        
        global $wpdb;
        
        $posts = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, p.post_title, p.post_date
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_thumbnail_id'
            WHERE p.post_status = %s
            AND (pm.meta_value IS NULL OR pm.meta_value = '')
            ORDER BY p.post_date DESC
        ", 'draft'));
        
        $formatted_posts = array();
        foreach ($posts as $post) {
            $formatted_posts[] = array(
                'ID' => $post->ID,
                'title' => $post->post_title,
                'date' => get_the_date('Y-m-d H:i', $post->ID),
            );
        }
        
        wp_send_json_success(array('posts' => $formatted_posts));
    }

    /**
     * Get filtered posts/pages without featured images.
     *
     * This avoids full page reloads (which can 500 if other plugins print notices and break headers),
     * and keeps the filtering logic server-side.
     */
    public function get_filtered_posts() {
        check_ajax_referer('wpafg_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'wp-auto-feature-gen')));
        }

        $raw_post_type = isset($_POST['post_type']) ? $_POST['post_type'] : null;
        $raw_post_status = isset($_POST['post_status']) ? $_POST['post_status'] : null;

        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'post';
        $post_status = isset($_POST['post_status']) ? sanitize_text_field($_POST['post_status']) : 'draft';

        // Accept a couple of common human labels just in case.
        if ($post_status === 'scheduled') {
            $post_status = 'future';
        }

        if (!in_array($post_type, array('post', 'page', 'both'), true)) {
            $post_type = 'post';
        }
        if (!in_array($post_status, array('draft', 'future', 'both'), true)) {
            $post_status = 'draft';
        }

        global $wpdb;

        $post_type_where = ($post_type === 'both')
            ? "p.post_type IN ('post', 'page')"
            : $wpdb->prepare("p.post_type = %s", $post_type);

        $post_status_where = ($post_status === 'both')
            ? "p.post_status IN ('draft', 'future')"
            : $wpdb->prepare("p.post_status = %s", $post_status);

        // Debug counts to help diagnose "0 results" cases.
        $total_matching = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} p WHERE {$post_type_where} AND {$post_status_where}");
        $with_thumb = (int) $wpdb->get_var("SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE {$post_type_where}
            AND {$post_status_where}
            AND pm.meta_key = '_thumbnail_id'
            AND pm.meta_value != ''
            AND pm.meta_value IS NOT NULL");
        $without_thumb_expected = max(0, $total_matching - $with_thumb);

        $query = "SELECT p.ID, p.post_title, p.post_date, p.post_type, p.post_status
            FROM {$wpdb->posts} p
            WHERE {$post_type_where}
            AND {$post_status_where}
            AND NOT EXISTS (
                SELECT 1
                FROM {$wpdb->postmeta} pm2
                WHERE pm2.post_id = p.ID
                AND pm2.meta_key = '_thumbnail_id'
                AND pm2.meta_value != ''
                AND pm2.meta_value IS NOT NULL
            )
            ORDER BY p.post_date DESC";

        WP_AFG_Debug::info('AJAX get_filtered_posts', array(
            'raw_post_type' => $raw_post_type,
            'raw_post_status' => $raw_post_status,
            'post_type' => $post_type,
            'post_status' => $post_status,
            'counts' => array(
                'total_matching' => $total_matching,
                'with_thumb' => $with_thumb,
                'without_thumb_expected' => $without_thumb_expected,
            ),
            'query' => $query,
        ));

        $rows = $wpdb->get_results($query);

        if ($wpdb->last_error) {
            WP_AFG_Debug::error('AJAX get_filtered_posts DB error', array(
                'error' => $wpdb->last_error,
                'query' => $query,
            ));
            wp_send_json_error(array('message' => $wpdb->last_error));
        }

        if (!is_array($rows)) {
            $rows = array();
        }

        $posts = array();
        foreach ($rows as $row) {
            if (!isset($row->ID)) {
                continue;
            }
            $posts[] = array(
                'ID' => (int) $row->ID,
                'title' => isset($row->post_title) ? (string) $row->post_title : '',
                'date' => get_the_date('Y-m-d H:i', $row->ID),
                'post_type' => isset($row->post_type) ? (string) $row->post_type : '',
                'post_status' => isset($row->post_status) ? (string) $row->post_status : '',
            );
        }

        WP_AFG_Debug::info('AJAX get_filtered_posts results', array(
            'post_type' => $post_type,
            'post_status' => $post_status,
            'count' => count($posts),
            'expected_without_thumb' => $without_thumb_expected,
        ));

        wp_send_json_success(array(
            'posts' => $posts,
            'post_type' => $post_type,
            'post_status' => $post_status,
            'debug' => array(
                'raw_post_type' => $raw_post_type,
                'raw_post_status' => $raw_post_status,
                'post_type_where' => $post_type_where,
                'post_status_where' => $post_status_where,
                'total_matching' => $total_matching,
                'with_thumb' => $with_thumb,
                'without_thumb_expected' => $without_thumb_expected,
            ),
        ));
    }
    
    /**
     * Handle Kie.ai callback
     */
    public function handle_kie_callback() {
        // Suppress errors from other plugins
        $error_reporting = error_reporting();
        error_reporting($error_reporting & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING);
        
        try {
            // Get raw POST data
            $raw_data = file_get_contents('php://input');
            $data = json_decode($raw_data, true);
            
            // Log callback for debugging
            WP_AFG_Debug::info('Kie.ai callback received', array('raw_data' => substr($raw_data, 0, 500), 'parsed_data_keys' => is_array($data) ? array_keys($data) : 'not_array', 'has_code' => isset($data['code']), 'has_data' => isset($data['data'])));
            
            // Check callback structure
            if (!is_array($data) || !isset($data['code']) || !isset($data['data'])) {
                WP_AFG_Debug::error('Invalid callback structure', array('data' => $data, 'is_array' => is_array($data), 'keys' => is_array($data) ? array_keys($data) : 'not_array'));
                status_header(200);
                echo json_encode(array('error' => 'Invalid callback data'));
                exit;
            }
            
            WP_AFG_Debug::info('Callback structure valid', array('code' => $data['code']));
            
            $code = $data['code'];
            $callback_data = $data['data'];
            
            WP_AFG_Debug::info('Extracted callback data', array('code' => $code, 'callback_data_keys' => is_array($callback_data) ? array_keys($callback_data) : 'not_array'));
            
            // Extract task ID from callback - try multiple methods
            $task_id = isset($_GET['task_id']) ? sanitize_text_field($_GET['task_id']) : '';
            $kie_task_id = isset($callback_data['taskId']) ? $callback_data['taskId'] : '';
            
            WP_AFG_Debug::info('Extracting task IDs', array('get_task_id' => $task_id, 'kie_task_id' => $kie_task_id, 'get_params' => $_GET));
            
            // Find post by task ID - try both our task ID and Kie.ai task ID
            $post_id = null;
            
            if (!empty($task_id)) {
                global $wpdb;
                $post_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wpafg_task_id' AND meta_value = %s LIMIT 1",
                    $task_id
                ));
                WP_AFG_Debug::info('Found post by task_id', array('task_id' => $task_id, 'post_id' => $post_id));
            }
            
            // Also try by Kie.ai task ID
            if (!$post_id && !empty($kie_task_id)) {
                global $wpdb;
                $post_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wpafg_kie_task_id' AND meta_value = %s LIMIT 1",
                    $kie_task_id
                ));
                WP_AFG_Debug::info('Found post by kie_task_id', array('kie_task_id' => $kie_task_id, 'post_id' => $post_id));
            }
            
            if (!$post_id) {
                WP_AFG_Debug::error('Post not found for callback', array('task_id' => $task_id, 'kie_task_id' => $kie_task_id, 'callback_data' => $callback_data));
                status_header(200);
                echo json_encode(array('error' => 'Post not found'));
                exit;
            }
            
            // Check if generation was stopped
            $is_stopped = get_post_meta($post_id, '_wpafg_stopped', true);
            if ($is_stopped) {
                WP_AFG_Debug::info('Generation stopped by user', array('post_id' => $post_id));
                status_header(200);
                echo json_encode(array('message' => 'Generation stopped by user'));
                exit;
            }
        
        // Handle success
        if ($code === 200 && isset($callback_data['state']) && $callback_data['state'] === 'success') {
            WP_AFG_Debug::info('Callback success received', array('post_id' => $post_id, 'callback_data' => $callback_data));
            
            $result_json = isset($callback_data['resultJson']) ? json_decode($callback_data['resultJson'], true) : null;
            
            WP_AFG_Debug::info('Parsed result JSON', array('post_id' => $post_id, 'result_json' => $result_json));
            
            // Extract image URL from result
            $image_url = null;
            if ($result_json && isset($result_json['resultUrls']) && !empty($result_json['resultUrls'])) {
                $image_url = $result_json['resultUrls'][0];
                WP_AFG_Debug::info('Found image URL in resultUrls', array('post_id' => $post_id, 'image_url' => $image_url));
            } elseif (isset($callback_data['output']['image_url'])) {
                // Fallback to direct output field
                $image_url = $callback_data['output']['image_url'];
                WP_AFG_Debug::info('Found image URL in output', array('post_id' => $post_id, 'image_url' => $image_url));
            } else {
                WP_AFG_Debug::warning('No image URL found in callback', array('post_id' => $post_id, 'callback_data' => $callback_data));
            }
            
            if ($image_url) {
                // Sideload and attach image
                require_once(ABSPATH . 'wp-admin/includes/media.php');
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                
                $post = get_post($post_id);
                $content_excerpt = wp_strip_all_tags($post->post_content);
                $content_excerpt = substr($content_excerpt, 0, 1000);
                
                // Download image
                $tmp = download_url($image_url);
                
                if (!is_wp_error($tmp)) {
                    $file_array = array(
                        'name' => basename($image_url),
                        'tmp_name' => $tmp,
                    );
                    
                    WP_AFG_Debug::info('Downloaded image successfully', array('post_id' => $post_id, 'image_url' => $image_url));
                    
                    $attachment_id = media_handle_sideload($file_array, $post_id);
                    
                    WP_AFG_Debug::info('media_handle_sideload result', array('post_id' => $post_id, 'attachment_id' => $attachment_id, 'is_error' => is_wp_error($attachment_id)));
                    
                    if (!is_wp_error($attachment_id)) {
                        // Ensure attachment metadata is generated
                        $attach_data = wp_generate_attachment_metadata($attachment_id, get_attached_file($attachment_id));
                        wp_update_attachment_metadata($attachment_id, $attach_data);
                        
                        WP_AFG_Debug::info('Generated attachment metadata', array('attachment_id' => $attachment_id, 'metadata' => $attach_data));
                        
                        // Set as featured image
                        $thumbnail_result = set_post_thumbnail($post_id, $attachment_id);
                        
                        WP_AFG_Debug::info('set_post_thumbnail result', array('post_id' => $post_id, 'attachment_id' => $attachment_id, 'result' => $thumbnail_result));
                        
                        // Verify featured image was set
                        $thumbnail_id = get_post_thumbnail_id($post_id);
                        WP_AFG_Debug::info('Verified featured image', array('post_id' => $post_id, 'thumbnail_id' => $thumbnail_id, 'expected' => $attachment_id));
                        
                        // Generate alt text
                        $openrouter = new WP_AFG_API_OpenRouter();
                        $alt_text = $openrouter->generate_alt_text($post->post_title, $content_excerpt);
                        
                        WP_AFG_Debug::info('Generated alt text', array('post_id' => $post_id, 'alt_text' => $alt_text, 'is_error' => is_wp_error($alt_text)));
                        
                        if (!is_wp_error($alt_text)) {
                            $alt_text = substr(trim($alt_text), 0, 100);
                            $alt_result = update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
                            
                            WP_AFG_Debug::info('Updated alt text meta', array('attachment_id' => $attachment_id, 'alt_text' => $alt_text, 'result' => $alt_result));
                            
                            // Verify alt text was saved
                            $saved_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
                            WP_AFG_Debug::info('Verified alt text saved', array('attachment_id' => $attachment_id, 'saved_alt' => $saved_alt));
                        }
                        
                        update_post_meta($post_id, '_wpafg_status', 'completed');
                        delete_post_meta($post_id, '_wpafg_task_id');
                        delete_post_meta($post_id, '_wpafg_kie_task_id');
                        
                        // Verify everything was saved
                        $final_thumbnail_id = get_post_thumbnail_id($post_id);
                        $final_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
                        
                        WP_AFG_Debug::info('Image processing completed successfully', array(
                            'post_id' => $post_id, 
                            'attachment_id' => $attachment_id,
                            'thumbnail_id' => $final_thumbnail_id,
                            'alt_text' => $final_alt,
                            'thumbnail_match' => ($final_thumbnail_id == $attachment_id)
                        ));
                    } else {
                        @unlink($file_array['tmp_name']);
                        WP_AFG_Debug::error('media_handle_sideload failed', array('post_id' => $post_id, 'error' => $attachment_id->get_error_message()));
                        update_post_meta($post_id, '_wpafg_status', 'error');
                        update_post_meta($post_id, '_wpafg_error', $attachment_id->get_error_message());
                    }
                } else {
                    update_post_meta($post_id, '_wpafg_status', 'error');
                    update_post_meta($post_id, '_wpafg_error', $tmp->get_error_message());
                }
            } else {
                // No image URL found in response
                update_post_meta($post_id, '_wpafg_status', 'error');
                update_post_meta($post_id, '_wpafg_error', __('No image URL found in callback response.', 'wp-auto-feature-gen'));
            }
        } else {
            // Handle failure
            $fail_msg = isset($callback_data['failMsg']) ? $callback_data['failMsg'] : 'Unknown error';
            $fail_code = isset($callback_data['failCode']) ? $callback_data['failCode'] : $code;
            
            update_post_meta($post_id, '_wpafg_status', 'error');
            update_post_meta($post_id, '_wpafg_error', sprintf(__('Kie.ai Error (Code: %s): %s', 'wp-auto-feature-gen'), $fail_code, $fail_msg));
        }
        
        status_header(200);
        echo json_encode(array('message' => 'Callback processed'));
        exit;
        
        } catch (Exception $e) {
            error_reporting($error_reporting);
            WP_AFG_Debug::error('Exception in callback handler', array(
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ));
            status_header(200);
            echo json_encode(array('error' => 'Callback processing failed: ' . $e->getMessage()));
            exit;
        } catch (Error $e) {
            error_reporting($error_reporting);
            WP_AFG_Debug::error('Fatal Error in callback handler', array(
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ));
            status_header(200);
            echo json_encode(array('error' => 'Fatal error in callback: ' . $e->getMessage()));
            exit;
        } finally {
            error_reporting($error_reporting);
        }
    }
    
    /**
     * Stop generation
     */
    public function stop_generation() {
        check_ajax_referer('wpafg_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'wp-auto-feature-gen')));
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if ($post_id) {
            update_post_meta($post_id, '_wpafg_stopped', true);
            wp_send_json_success(array('message' => __('Generation stopped.', 'wp-auto-feature-gen')));
        } else {
            // Stop all pending generations
            global $wpdb;
            $wpdb->query("UPDATE {$wpdb->postmeta} SET meta_value = '1' WHERE meta_key = '_wpafg_status' AND meta_value = 'pending'");
            $wpdb->query("INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) SELECT post_id, '_wpafg_stopped', '1' FROM {$wpdb->postmeta} WHERE meta_key = '_wpafg_status' AND meta_value = 'pending' ON DUPLICATE KEY UPDATE meta_value = '1'");
            
            wp_send_json_success(array('message' => __('All generations stopped.', 'wp-auto-feature-gen')));
        }
    }
    
    /**
     * Check status of image generation
     */
    public function check_status() {
        check_ajax_referer('wpafg_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'wp-auto-feature-gen')));
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid post ID.', 'wp-auto-feature-gen')));
        }
        
        $status = get_post_meta($post_id, '_wpafg_status', true);
        $error = get_post_meta($post_id, '_wpafg_error', true);
        
        wp_send_json_success(array(
            'status' => $status ? $status : 'pending',
            'error' => $error ? $error : '',
        ));
    }
    
    /**
     * Clear debug log
     */
    public function clear_debug_log() {
        check_ajax_referer('wpafg_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'wp-auto-feature-gen')));
        }
        
        WP_AFG_Debug::clear_log();
        wp_send_json_success(array('message' => __('Debug log cleared.', 'wp-auto-feature-gen')));
    }
}

