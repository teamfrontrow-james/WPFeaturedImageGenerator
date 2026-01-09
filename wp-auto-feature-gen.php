<?php
/**
 * Plugin Name: WP Auto-Feature Gen
 * Plugin URI: https://frontrowsales.com
 * Description: Bulk generate featured images for drafted posts using AI (Kie.ai and OpenRouter)
 * Version: 1.0.0
 * Author: James Ross
 * Author URI: https://frontrowsales.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-auto-feature-gen
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WPAFG_VERSION', '1.0.0');
define('WPAFG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPAFG_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once WPAFG_PLUGIN_DIR . 'includes/class-wpafg-debug.php';
require_once WPAFG_PLUGIN_DIR . 'includes/class-wpafg-api-openrouter.php';
require_once WPAFG_PLUGIN_DIR . 'includes/class-wpafg-api-kie.php';
require_once WPAFG_PLUGIN_DIR . 'includes/class-wpafg-admin.php';
require_once WPAFG_PLUGIN_DIR . 'includes/class-wpafg-ajax.php';

/**
 * Main plugin class
 */
class WP_Auto_Feature_Gen {
    
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
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        // Shield our admin page + AJAX responses from other plugins/themes printing notices
        // (common cause of "headers already sent" / broken JSON / 500s when WP_DEBUG_DISPLAY is enabled).
        add_action('admin_init', array($this, 'maybe_enable_error_shield'), 0);
        
        // Initialize admin
        WP_AFG_Admin::get_instance();
        
        // Initialize AJAX
        WP_AFG_Ajax::get_instance();
    }
    
    /**
     * Prevent other plugins/themes from breaking our admin page / AJAX responses by printing PHP notices.
     *
     * IMPORTANT: This is a mitigation when WP_DEBUG_DISPLAY/display_errors is enabled on a production site.
     * The proper fix is to disable on-screen error display in wp-config.php.
     */
    public function maybe_enable_error_shield() {
        if (!is_admin()) {
            return;
        }

        $is_our_settings_page = isset($_GET['page']) && $_GET['page'] === 'wp-auto-feature-gen';
        $is_our_ajax = (defined('DOING_AJAX') && DOING_AJAX && isset($_REQUEST['action']) && is_string($_REQUEST['action']) && strpos($_REQUEST['action'], 'wpafg_') === 0);

        if (!$is_our_settings_page && !$is_our_ajax) {
            return;
        }

        // Disable display of notices/warnings that would otherwise get printed before headers.
        // We still log via WP_AFG_Debug and the server error log.
        @ini_set('display_errors', '0');
        @ini_set('display_startup_errors', '0');
        @ini_set('html_errors', '0');
        @ini_set('log_errors', '1');

        // Keep fatals/errors, but suppress notices/warnings/deprecations from third-party code.
        error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

        if (class_exists('WP_AFG_Debug')) {
            WP_AFG_Debug::info('Enabled error shield', array(
                'is_our_settings_page' => $is_our_settings_page,
                'is_our_ajax' => $is_our_ajax,
                'page' => isset($_GET['page']) ? $_GET['page'] : null,
                'action' => isset($_REQUEST['action']) ? $_REQUEST['action'] : null,
                'request_uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null,
                'get' => $_GET,
            ));
        }
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        // Load textdomain on init to avoid "too early" warnings
        add_action('init', function() {
            load_plugin_textdomain('wp-auto-feature-gen', false, dirname(plugin_basename(__FILE__)) . '/languages');
        }, 1);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('WP Auto-Feature Gen', 'wp-auto-feature-gen'),
            __('WP Auto-Feature Gen', 'wp-auto-feature-gen'),
            'manage_options',
            'wp-auto-feature-gen',
            array($this, 'render_dashboard_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ('settings_page_wp-auto-feature-gen' !== $hook) {
            return;
        }
        
        wp_enqueue_script(
            'wpafg-admin',
            WPAFG_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            WPAFG_VERSION,
            true
        );
        
        wp_enqueue_style(
            'wpafg-admin',
            WPAFG_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WPAFG_VERSION
        );
        
        // Localize script
        wp_localize_script('wpafg-admin', 'wpafg', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpafg_nonce'),
            'strings' => array(
                'generating' => __('Generating...', 'wp-auto-feature-gen'),
                'analyzing' => __('Analyzing Content...', 'wp-auto-feature-gen'),
                'rendering' => __('Rendering Image...', 'wp-auto-feature-gen'),
                'done' => __('Done', 'wp-auto-feature-gen'),
                'error' => __('Error', 'wp-auto-feature-gen'),
            )
        ));
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        WP_AFG_Admin::get_instance()->render_dashboard();
    }
}

/**
 * Initialize the plugin
 */
function wpafg_init() {
    return WP_Auto_Feature_Gen::get_instance();
}

// Start the plugin
wpafg_init();

