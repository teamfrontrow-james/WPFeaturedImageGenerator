# Security Notes

This document describes the security posture of WP Auto-Feature Gen and the areas to review before publishing or installing it on a production site.

## Admin-Only Actions

All administrator-triggered AJAX actions verify the `wpafg_nonce` nonce and require the `manage_options` capability before reading posts, starting generation, stopping generation, checking status, or clearing logs.

Protected AJAX actions include:

- `wpafg_generate_featured_image`
- `wpafg_get_draft_posts`
- `wpafg_get_filtered_posts`
- `wpafg_stop_generation`
- `wpafg_check_status`
- `wpafg_clear_debug_log`

## API Key Storage

Kie.ai and OpenRouter keys are saved as WordPress options and rendered as password inputs in the admin UI.

Operational guidance:

- Limit administrator access to trusted users.
- Do not paste API keys into GitHub issues, screenshots, debug logs, or pull requests.
- Rotate API keys if a staging site, backup, or log file is exposed.
- Prefer production keys with usage limits when the provider supports them.

## Callback Endpoint

The Kie.ai callback is registered for both authenticated and unauthenticated requests:

- `wp_ajax_wpafg_kie_callback`
- `wp_ajax_nopriv_wpafg_kie_callback`

The unauthenticated callback is required because Kie.ai cannot log in to WordPress. The handler looks up the post by the local `_wpafg_task_id` query value or the Kie.ai `_wpafg_kie_task_id` value before processing image URLs.

Review item before broad public release: add a provider-signed callback secret or HMAC verification if Kie.ai supports it. Task ID matching narrows callbacks to known pending jobs, but a signed callback would be stronger.

## Error Shield

The plugin disables on-screen notices/warnings on its own admin page and AJAX actions to prevent third-party PHP notices from corrupting JSON responses.

This is a mitigation, not the preferred production setting. The production fix is to keep WordPress debug display disabled in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
@ini_set('display_errors', 0);
```

See [`examples/wp-config-debug.php`](../examples/wp-config-debug.php) for a copy-paste snippet.

## Debug Logs

When Debug Mode is enabled, logs are written to `/wp-content/uploads/wpafg-logs/`.

Logs may include task IDs, callback payload excerpts, image URLs, post IDs, post titles, API error bodies, and operational context. Disable Debug Mode after troubleshooting and avoid attaching raw logs to public issues unless they have been reviewed for sensitive data.

## Current Review Notes

- API requests are administrator initiated.
- Generated images are downloaded with WordPress core media sideloading functions.
- The callback endpoint returns HTTP 200 for handled errors so Kie.ai does not repeatedly retry malformed or unmatched callbacks.
- Direct database queries use validated allowlist values or prepared values in AJAX filtering, but a full WordPress Coding Standards pass is still recommended before WordPress.org submission.

