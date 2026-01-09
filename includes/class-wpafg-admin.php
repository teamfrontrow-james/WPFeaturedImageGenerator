<?php
/**
 * Admin functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_AFG_Admin {
    
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
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ('settings_page_wp-auto-feature-gen' !== $hook) {
            return;
        }
        
        wp_add_inline_script('wpafg-admin', '
            jQuery(document).ready(function($) {
                $("#wpafg-refresh-log").on("click", function() {
                    location.reload();
                });
                $("#wpafg-clear-log").on("click", function() {
                    if (confirm("Are you sure you want to clear the debug log?")) {
                        $.ajax({
                            url: wpafg.ajax_url,
                            type: "POST",
                            data: {
                                action: "wpafg_clear_debug_log",
                                nonce: wpafg.nonce
                            },
                            success: function() {
                                location.reload();
                            }
                        });
                    }
                });
            });
        ');
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('wpafg_settings', 'wpafg_kie_api_key');
        register_setting('wpafg_settings', 'wpafg_openrouter_api_key');
        register_setting('wpafg_settings', 'wpafg_prompt_style');
        
        add_settings_section(
            'wpafg_api_section',
            __('API Configuration', 'wp-auto-feature-gen'),
            array($this, 'render_api_section'),
            'wpafg_settings'
        );
        
        add_settings_field(
            'wpafg_kie_api_key',
            __('Kie.ai API Key', 'wp-auto-feature-gen'),
            array($this, 'render_kie_api_key_field'),
            'wpafg_settings',
            'wpafg_api_section'
        );
        
        add_settings_field(
            'wpafg_openrouter_api_key',
            __('OpenRouter API Key', 'wp-auto-feature-gen'),
            array($this, 'render_openrouter_api_key_field'),
            'wpafg_settings',
            'wpafg_api_section'
        );
        
        add_settings_field(
            'wpafg_prompt_style',
            __('Prompt Style', 'wp-auto-feature-gen'),
            array($this, 'render_prompt_style_field'),
            'wpafg_settings',
            'wpafg_api_section'
        );
    }
    
    /**
     * Render API section
     */
    public function render_api_section() {
        echo '<p>' . esc_html__('Configure your API keys and prompt style preferences.', 'wp-auto-feature-gen') . '</p>';
    }
    
    /**
     * Render Kie.ai API key field
     */
    public function render_kie_api_key_field() {
        $value = get_option('wpafg_kie_api_key', '');
        ?>
        <input type="password" name="wpafg_kie_api_key" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description"><?php esc_html_e('Your Kie.ai API key for image generation.', 'wp-auto-feature-gen'); ?></p>
        <?php
    }
    
    /**
     * Render OpenRouter API key field
     */
    public function render_openrouter_api_key_field() {
        $value = get_option('wpafg_openrouter_api_key', '');
        ?>
        <input type="password" name="wpafg_openrouter_api_key" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description"><?php esc_html_e('Your OpenRouter API key for prompt enhancement.', 'wp-auto-feature-gen'); ?></p>
        <?php
    }
    
    /**
     * Render prompt style field
     */
    public function render_prompt_style_field() {
        $value = get_option('wpafg_prompt_style', '');
        ?>
        <input type="text" name="wpafg_prompt_style" value="<?php echo esc_attr($value); ?>" class="large-text" />
        <p class="description">
            <?php esc_html_e('Global style instructions added to every image prompt.', 'wp-auto-feature-gen'); ?><br>
            <?php esc_html_e('Examples: "Photorealistic, 8k, cinematic lighting", "Minimalist vector art, flat colors", "Cyberpunk, neon palette"', 'wp-auto-feature-gen'); ?>
        </p>
        <?php
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard() {
        // Suppress notices from other plugins that might interfere
        $error_reporting = error_reporting();
        error_reporting($error_reporting & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING);
        
        try {
            // Handle settings save
            if (isset($_POST['wpafg_save_settings']) && check_admin_referer('wpafg_save_settings')) {
                update_option('wpafg_kie_api_key', sanitize_text_field($_POST['wpafg_kie_api_key']));
                update_option('wpafg_openrouter_api_key', sanitize_text_field($_POST['wpafg_openrouter_api_key']));
                update_option('wpafg_prompt_style', sanitize_text_field($_POST['wpafg_prompt_style']));
                update_option('wpafg_debug_mode', isset($_POST['wpafg_debug_mode']) ? '1' : '0');
                echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'wp-auto-feature-gen') . '</p></div>';
            }
            
            // Get filter values with proper defaults
            $post_type = 'post';
            $post_status = 'draft';
            
            if (isset($_GET['post_type']) && is_string($_GET['post_type'])) {
                $post_type = sanitize_text_field($_GET['post_type']);
                if (!in_array($post_type, array('post', 'page', 'both'))) {
                    $post_type = 'post';
                }
            }
            
            if (isset($_GET['post_status']) && is_string($_GET['post_status'])) {
                $post_status = sanitize_text_field($_GET['post_status']);
                if (!in_array($post_status, array('draft', 'future', 'both'))) {
                    $post_status = 'draft';
                }
            }
            
            WP_AFG_Debug::info('render_dashboard called', array('post_type' => $post_type, 'post_status' => $post_status, 'get_params' => $_GET));
            
            // Get posts without featured images
            $posts = array();
            $posts = $this->get_posts_without_featured_images($post_type, $post_status);
            
            // Ensure $posts is an array
            if (!is_array($posts)) {
                $posts = array();
            }
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('WP Auto-Feature Gen', 'wp-auto-feature-gen'); ?></h1>
            
            <div class="wpafg-container">
                <div class="wpafg-settings-section">
                    <h2><?php esc_html_e('Settings', 'wp-auto-feature-gen'); ?></h2>
                    <form method="post" action="">
                        <?php wp_nonce_field('wpafg_save_settings'); ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="wpafg_kie_api_key"><?php esc_html_e('Kie.ai API Key', 'wp-auto-feature-gen'); ?></label>
                                </th>
                                <td>
                                    <input type="password" name="wpafg_kie_api_key" id="wpafg_kie_api_key" value="<?php echo esc_attr(get_option('wpafg_kie_api_key', '')); ?>" class="regular-text" />
                                    <p class="description"><?php esc_html_e('Your Kie.ai API key for image generation.', 'wp-auto-feature-gen'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="wpafg_openrouter_api_key"><?php esc_html_e('OpenRouter API Key', 'wp-auto-feature-gen'); ?></label>
                                </th>
                                <td>
                                    <input type="password" name="wpafg_openrouter_api_key" id="wpafg_openrouter_api_key" value="<?php echo esc_attr(get_option('wpafg_openrouter_api_key', '')); ?>" class="regular-text" />
                                    <p class="description"><?php esc_html_e('Your OpenRouter API key for prompt enhancement.', 'wp-auto-feature-gen'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="wpafg_prompt_style"><?php esc_html_e('Prompt Style', 'wp-auto-feature-gen'); ?></label>
                                </th>
                                <td>
                                    <input type="text" name="wpafg_prompt_style" id="wpafg_prompt_style" value="<?php echo esc_attr(get_option('wpafg_prompt_style', '')); ?>" class="large-text" />
                                    <p class="description">
                                        <?php esc_html_e('Global style instructions added to every image prompt.', 'wp-auto-feature-gen'); ?><br>
                                        <?php esc_html_e('Examples: "Photorealistic, 8k, cinematic lighting", "Minimalist vector art, flat colors", "Cyberpunk, neon palette"', 'wp-auto-feature-gen'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="wpafg_debug_mode"><?php esc_html_e('Debug Mode', 'wp-auto-feature-gen'); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="wpafg_debug_mode" id="wpafg_debug_mode" value="1" <?php checked(get_option('wpafg_debug_mode', '0'), '1'); ?> />
                                        <?php esc_html_e('Enable debug logging and display debug log box', 'wp-auto-feature-gen'); ?>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e('When enabled, detailed debug logs will be written to the log file and displayed in the dashboard. Useful for troubleshooting issues.', 'wp-auto-feature-gen'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button(__('Save Settings', 'wp-auto-feature-gen'), 'primary', 'wpafg_save_settings'); ?>
                    </form>
                </div>
                
                <div class="wpafg-dashboard-section">
                    <h2><?php esc_html_e('Posts/Pages Without Featured Images', 'wp-auto-feature-gen'); ?></h2>
                    
                    <?php if (get_option('wpafg_debug_mode', '0') === '1') : ?>
                    <div class="wpafg-debug-section" style="margin-bottom: 20px; padding: 10px; background: #f0f0f1; border-left: 4px solid #2271b1;">
                        <h3><?php esc_html_e('Debug Log', 'wp-auto-feature-gen'); ?></h3>
                        <textarea readonly style="width: 100%; height: 200px; font-family: monospace; font-size: 12px;"><?php echo esc_textarea(WP_AFG_Debug::get_log(50)); ?></textarea>
                        <button type="button" id="wpafg-refresh-log" class="button" style="margin-top: 10px;"><?php esc_html_e('Refresh Log', 'wp-auto-feature-gen'); ?></button>
                        <button type="button" id="wpafg-clear-log" class="button" style="margin-top: 10px;"><?php esc_html_e('Clear Log', 'wp-auto-feature-gen'); ?></button>
                    </div>
                    <?php endif; ?>
                    
                    <div class="wpafg-filters">
                        <form method="get" action="" id="wpafg-filter-form">
                            <input type="hidden" name="page" value="wp-auto-feature-gen" />
                            <label for="wpafg-post-type">
                                <?php esc_html_e('Post Type:', 'wp-auto-feature-gen'); ?>
                                <select name="post_type" id="wpafg-post-type">
                                    <option value="post" <?php selected($post_type, 'post'); ?>><?php esc_html_e('Posts', 'wp-auto-feature-gen'); ?></option>
                                    <option value="page" <?php selected($post_type, 'page'); ?>><?php esc_html_e('Pages', 'wp-auto-feature-gen'); ?></option>
                                    <option value="both" <?php selected($post_type, 'both'); ?>><?php esc_html_e('Both', 'wp-auto-feature-gen'); ?></option>
                                </select>
                            </label>
                            <label for="wpafg-post-status">
                                <?php esc_html_e('Status:', 'wp-auto-feature-gen'); ?>
                                <select name="post_status" id="wpafg-post-status">
                                    <option value="draft" <?php selected($post_status, 'draft'); ?>><?php esc_html_e('Draft', 'wp-auto-feature-gen'); ?></option>
                                    <option value="future" <?php selected($post_status, 'future'); ?>><?php esc_html_e('Scheduled', 'wp-auto-feature-gen'); ?></option>
                                    <option value="both" <?php selected($post_status, 'both'); ?>><?php esc_html_e('Both', 'wp-auto-feature-gen'); ?></option>
                                </select>
                            </label>
                            <button type="button" class="button" id="wpafg-apply-filter"><?php esc_html_e('Filter', 'wp-auto-feature-gen'); ?></button>
                            <noscript>
                                <button type="submit" class="button"><?php esc_html_e('Filter (no JS)', 'wp-auto-feature-gen'); ?></button>
                            </noscript>
                        </form>
                    </div>
                    
                    <div class="wpafg-controls" <?php echo empty($posts) ? 'style="display:none;"' : ''; ?>>
                            <button type="button" id="wpafg-generate-all" class="button button-primary">
                                <?php esc_html_e('Generate All', 'wp-auto-feature-gen'); ?>
                            </button>
                            <button type="button" id="wpafg-stop-all" class="button" style="display: none;">
                                <?php esc_html_e('Stop', 'wp-auto-feature-gen'); ?>
                            </button>
                            <div class="wpafg-progress-container" style="display: none;">
                                <div class="wpafg-progress-bar">
                                    <div class="wpafg-progress-fill" style="width: 0%;"></div>
                                </div>
                                <span class="wpafg-progress-text">0%</span>
                            </div>
                    </div>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 5%;"><?php esc_html_e('ID', 'wp-auto-feature-gen'); ?></th>
                                <th style="width: 10%;"><?php esc_html_e('Type', 'wp-auto-feature-gen'); ?></th>
                                <th style="width: 30%;"><?php esc_html_e('Title', 'wp-auto-feature-gen'); ?></th>
                                <th style="width: 15%;"><?php esc_html_e('Date', 'wp-auto-feature-gen'); ?></th>
                                <th style="width: 40%;"><?php esc_html_e('Status', 'wp-auto-feature-gen'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="wpafg-posts-list">
                            <?php if (empty($posts)) : ?>
                                <tr class="wpafg-empty">
                                    <td colspan="5"><?php esc_html_e('No posts/pages without featured images found for this filter.', 'wp-auto-feature-gen'); ?></td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($posts as $post) : ?>
                                    <?php
                                    // Ensure post object has required properties
                                    if (!isset($post->ID) || !isset($post->post_title) || !isset($post->post_type) || !isset($post->post_status)) {
                                        continue;
                                    }
                                    ?>
                                    <tr data-post-id="<?php echo esc_attr($post->ID); ?>" data-post-type="<?php echo esc_attr($post->post_type); ?>" data-post-status="<?php echo esc_attr($post->post_status); ?>">
                                        <td><?php echo esc_html($post->ID); ?></td>
                                        <td><?php echo esc_html(ucfirst($post->post_type)); ?></td>
                                        <td><strong><?php echo esc_html($post->post_title); ?></strong></td>
                                        <td><?php echo esc_html(get_the_date('Y-m-d H:i', $post->ID)); ?></td>
                                        <td class="wpafg-status" data-status="pending">
                                            <span class="wpafg-status-text"><?php esc_html_e('Pending', 'wp-auto-feature-gen'); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
        } catch (Exception $e) {
            error_reporting($error_reporting); // Restore error reporting
            WP_AFG_Debug::error('Exception in render_dashboard', array(
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ));
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('WP Auto-Feature Gen', 'wp-auto-feature-gen'); ?></h1>
                <div class="notice notice-error"><p><?php esc_html_e('An error occurred. Please check the debug log.', 'wp-auto-feature-gen'); ?></p></div>
                <p><strong><?php esc_html_e('Error:', 'wp-auto-feature-gen'); ?></strong> <?php echo esc_html($e->getMessage()); ?></p>
            </div>
            <?php
        } catch (Error $e) {
            error_reporting($error_reporting); // Restore error reporting
            WP_AFG_Debug::error('Fatal Error in render_dashboard', array(
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ));
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('WP Auto-Feature Gen', 'wp-auto-feature-gen'); ?></h1>
                <div class="notice notice-error"><p><?php esc_html_e('A fatal error occurred. Please check the debug log.', 'wp-auto-feature-gen'); ?></p></div>
                <p><strong><?php esc_html_e('Error:', 'wp-auto-feature-gen'); ?></strong> <?php echo esc_html($e->getMessage()); ?></p>
            </div>
            <?php
        } finally {
            error_reporting($error_reporting); // Always restore error reporting
        }
    }
    
    /**
     * Get posts without featured images
     */
    private function get_posts_without_featured_images($post_type = 'post', $post_status = 'draft') {
        global $wpdb;
        
        try {
            // Validate inputs
            $post_type = sanitize_text_field($post_type);
            $post_status = sanitize_text_field($post_status);
            
            // Ensure valid values
            if (!in_array($post_type, array('post', 'page', 'both'))) {
                $post_type = 'post';
            }
            if (!in_array($post_status, array('draft', 'future', 'both'))) {
                $post_status = 'draft';
            }
            
            // Log filter values
            WP_AFG_Debug::info('Getting posts without featured images', array('post_type' => $post_type, 'post_status' => $post_status));
            
            // Build WHERE clause - use direct values since they're validated
            // The values are validated to only be 'post', 'page', 'both', 'draft', 'future', 'both'
            // So they're safe to use directly in the query
            
            $post_type_where = '';
            if ($post_type === 'post') {
                $post_type_where = "p.post_type = 'post'";
            } elseif ($post_type === 'page') {
                $post_type_where = "p.post_type = 'page'";
            } else {
                $post_type_where = "p.post_type IN ('post', 'page')";
            }
            
            $post_status_where = '';
            if ($post_status === 'draft') {
                $post_status_where = "p.post_status = 'draft'";
            } elseif ($post_status === 'future') {
                $post_status_where = "p.post_status = 'future'";
            } else {
                $post_status_where = "p.post_status IN ('draft', 'future')";
            }
            
            // First, let's debug: check how many posts match the type/status filters
            $test_query = "SELECT COUNT(*) as count FROM {$wpdb->posts} p WHERE {$post_type_where} AND {$post_status_where}";
            $test_result = $wpdb->get_var($test_query);
            WP_AFG_Debug::info('Posts matching type/status filters', array('post_type' => $post_type, 'post_status' => $post_status, 'count' => $test_result, 'where_clauses' => array('type' => $post_type_where, 'status' => $post_status_where)));
            
            // Debug: Test the exact query we're building
            if ($post_type === 'post' && $post_status === 'future') {
                $test_exact = $wpdb->get_results("SELECT ID, post_title, post_type, post_status FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'future' LIMIT 5");
                $sample_data = array();
                if (is_array($test_exact)) {
                    foreach ($test_exact as $post) {
                        $sample_data[] = array(
                            'ID' => isset($post->ID) ? $post->ID : null,
                            'title' => isset($post->post_title) ? $post->post_title : null,
                            'type' => isset($post->post_type) ? $post->post_type : null,
                            'status' => isset($post->post_status) ? $post->post_status : null,
                        );
                    }
                }
                WP_AFG_Debug::info('Direct test query for post+future', array('count' => is_array($test_exact) ? count($test_exact) : 0, 'sample_posts' => $sample_data));
            }
            
            // Debug: Check all post statuses for this post type (fix table alias)
            $status_check_query = "SELECT post_status, COUNT(*) as count FROM {$wpdb->posts} p WHERE {$post_type_where} GROUP BY post_status";
            $status_results = $wpdb->get_results($status_check_query);
            $status_array = array();
            if (is_array($status_results)) {
                foreach ($status_results as $status) {
                    if (isset($status->post_status) && isset($status->count)) {
                        $status_array[$status->post_status] = $status->count;
                    }
                }
            }
            WP_AFG_Debug::info('All post statuses for this type', array('post_type' => $post_type, 'statuses' => $status_array));
            
            // Debug: Check if there are any posts at all
            $all_posts_query = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('post', 'page')";
            $all_posts_count = $wpdb->get_var($all_posts_query);
            WP_AFG_Debug::info('Total posts and pages in database', array('count' => $all_posts_count));
            
            // Debug: Specifically check post_type='post' with all statuses
            if ($post_type === 'post') {
                $post_statuses_query = "SELECT post_status, COUNT(*) as count FROM {$wpdb->posts} WHERE post_type = 'post' GROUP BY post_status";
                $post_statuses = $wpdb->get_results($post_statuses_query);
                $post_status_array = array();
                if (is_array($post_statuses)) {
                    foreach ($post_statuses as $status) {
                        if (isset($status->post_status) && isset($status->count)) {
                            $post_status_array[$status->post_status] = $status->count;
                        }
                    }
                }
                WP_AFG_Debug::info('All statuses for post_type=post', array('statuses' => $post_status_array));
            }
            
            // Check how many have featured images
            $with_thumb_query = "SELECT COUNT(DISTINCT p.ID) as count 
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE {$post_type_where}
                AND {$post_status_where}
                AND pm.meta_key = '_thumbnail_id'
                AND pm.meta_value != ''
                AND pm.meta_value IS NOT NULL";
            $with_thumb_result = $wpdb->get_var($with_thumb_query);
            WP_AFG_Debug::info('Posts with featured images', array('post_type' => $post_type, 'post_status' => $post_status, 'count' => $with_thumb_result));
            
            // Build query - use NOT EXISTS instead of COUNT for better performance and reliability
            $query = "SELECT p.ID, p.post_title, p.post_date, p.post_content, p.post_type, p.post_status
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
            
            WP_AFG_Debug::info('Executing query', array('query' => $query, 'post_type' => $post_type, 'post_status' => $post_status));
            
            // Execute query
            $posts = $wpdb->get_results($query);
            
            // Check for database errors
            if ($wpdb->last_error) {
                WP_AFG_Debug::error('Database query error', array('error' => $wpdb->last_error, 'query' => $query));
                return array();
            }
            
            WP_AFG_Debug::info('Query executed successfully', array('post_count' => count($posts), 'expected_count' => ($test_result - $with_thumb_result)));
            
            // Return empty array if query fails
            if ($posts === false || !is_array($posts)) {
                return array();
            }
            
            return $posts;
            
        } catch (Exception $e) {
            WP_AFG_Debug::error('Exception in get_posts_without_featured_images', array(
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ));
            error_log('WP Auto-Feature Gen Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            return array();
        } catch (Error $e) {
            WP_AFG_Debug::error('Fatal Error in get_posts_without_featured_images', array(
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ));
            error_log('WP Auto-Feature Gen Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            return array();
        }
    }
    
    /**
     * Close render_dashboard try-catch
     */
    private function close_render_dashboard() {
        // This is a placeholder - the actual closing is in render_dashboard
    }
}

