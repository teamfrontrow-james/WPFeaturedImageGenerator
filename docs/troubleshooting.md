# Troubleshooting

Enable **Debug Mode** in **Settings -> WP Auto-Feature Gen** while troubleshooting. Logs are written to `/wp-content/uploads/wpafg-logs/debug-YYYY-MM-DD.log` and the last entries appear in the dashboard.

## 500 Error When Filtering

Possible causes:

- Another plugin or theme prints notices before AJAX JSON headers.
- `WP_DEBUG_DISPLAY` or `display_errors` is enabled on a production-like site.
- A server security rule blocks `admin-ajax.php`.

What to try:

- Enable Debug Mode and retry the filter.
- Confirm `WP_DEBUG_DISPLAY` is false. See [`examples/wp-config-debug.php`](../examples/wp-config-debug.php).
- Check the browser Network tab for the AJAX response body.
- Check the server PHP error log and `/wp-content/debug.log`.

## Images Do Not Save

Possible causes:

- WordPress uploads directory is not writable.
- The generated image URL is not reachable from the WordPress server.
- The image download timed out.
- `media_handle_sideload()` failed because required WordPress media includes were unavailable or the file type was rejected.

What to try:

- Confirm `/wp-content/uploads/` is writable.
- Check the debug log for `download_url` or `media_handle_sideload` errors.
- Confirm the Kie.ai result URL can be reached from the server.
- Verify the site has enough disk space.

## No Posts Show In The Queue

The dashboard only lists posts/pages that match all of these conditions:

- Post type is `post`, `page`, or both.
- Status is `draft`, `future` (scheduled), or both.
- No `_thumbnail_id` featured image is already set.

What to try:

- Change both filters to **Both** and click **Filter**.
- Confirm the content really has draft or scheduled status.
- Remove the existing featured image from a test post.
- Read the filter debug counts if Debug Mode is enabled.

## Generation Stalls At "Waiting For Image"

This usually means WordPress started the Kie.ai task but never received the callback.

What to try:

- Confirm the site is publicly reachable over HTTPS.
- Confirm `admin-ajax.php?action=wpafg_kie_callback` is not blocked by a firewall, CDN, security plugin, maintenance mode, or basic authentication.
- Check whether the callback URL in the debug log uses the expected domain.
- Use a public staging domain or secure tunnel for local testing.

## API Key Errors

What to try:

- Re-save both API keys in **Settings -> WP Auto-Feature Gen**.
- Confirm the keys are active with the provider.
- Confirm the provider account has enough credits/quota.
- Review API error messages in the debug log, but redact keys before sharing.

## Stop Button Does Not Cancel A Remote Job

The stop control stops local queue processing and marks pending local jobs as stopped. It does not cancel an already-running Kie.ai generation job unless the provider exposes and the plugin implements a cancellation API.

If a callback arrives after a stop request, the plugin checks `_wpafg_stopped` and avoids completing that post.

