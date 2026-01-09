<?php
/**
 * Debug logging functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_AFG_Debug {
    
    /**
     * Log file path
     */
    private static $log_file = null;
    
    /**
     * Get log file path
     */
    private static function get_log_file() {
        if (self::$log_file === null) {
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/wpafg-logs';
            
            // Create log directory if it doesn't exist
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
            }
            
            self::$log_file = $log_dir . '/debug-' . date('Y-m-d') . '.log';
        }
        
        return self::$log_file;
    }
    
    /**
     * Check if debug mode is enabled
     */
    private static function is_debug_enabled() {
        return get_option('wpafg_debug_mode', '0') === '1';
    }
    
    /**
     * Log a message
     */
    public static function log($message, $context = array()) {
        // Only log if debug mode is enabled
        if (!self::is_debug_enabled()) {
            return;
        }
        
        $log_file = self::get_log_file();
        
        $timestamp = current_time('mysql');
        $context_str = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        $log_message = "[{$timestamp}] {$message}{$context_str}\n";
        
        // Write to log file
        @file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
        
        // Also log to WordPress debug log if enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WP Auto-Feature Gen] ' . $message . $context_str);
        }
    }
    
    /**
     * Log error
     */
    public static function error($message, $context = array()) {
        self::log("ERROR: {$message}", $context);
    }
    
    /**
     * Log warning
     */
    public static function warning($message, $context = array()) {
        self::log("WARNING: {$message}", $context);
    }
    
    /**
     * Log info
     */
    public static function info($message, $context = array()) {
        self::log("INFO: {$message}", $context);
    }
    
    /**
     * Clear today's log
     */
    public static function clear_log() {
        $log_file = self::get_log_file();
        if (file_exists($log_file)) {
            @unlink($log_file);
        }
    }
    
    /**
     * Get log content
     */
    public static function get_log($lines = 100) {
        $log_file = self::get_log_file();
        
        if (!file_exists($log_file)) {
            return '';
        }
        
        $content = file_get_contents($log_file);
        $lines_array = explode("\n", $content);
        $lines_array = array_filter($lines_array);
        
        // Get last N lines
        if (count($lines_array) > $lines) {
            $lines_array = array_slice($lines_array, -$lines);
        }
        
        return implode("\n", $lines_array);
    }
}

