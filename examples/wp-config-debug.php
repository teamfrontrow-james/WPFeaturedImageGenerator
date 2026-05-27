<?php
/**
 * Production-safe WordPress debug logging snippet.
 *
 * Add these lines to wp-config.php, above the
 * "That's all, stop editing! Happy publishing." comment line.
 */

define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

@ini_set('display_errors', 0);

