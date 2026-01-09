# WP Auto-Feature Gen

A WordPress plugin that bulk-generates featured images for drafted and scheduled posts/pages using AI (Kie.ai for images, OpenRouter for text enhancement).

## Features

### Core Capabilities

- **AI-Powered Image Generation**: Uses Kie.ai (nano-banana-pro model) for high-quality image generation
- **Smart Prompt Enhancement**: Uses OpenRouter (GPT-OSS-120b) to enhance prompts based on post content
- **Bulk Processing**: Process multiple posts/pages at once with a visual progress bar
- **Flexible Filtering**: Filter by post type (Posts/Pages/Both) and status (Draft/Scheduled/Both)
- **Client-Side Queue**: Processes posts one at a time via AJAX to avoid PHP timeouts
- **True Image Hosting**: Downloads and stores images locally in WordPress uploads directory
- **SEO-Friendly**: Automatically generates alt text for images using OpenRouter
- **Customizable Style**: Add global style instructions to all generated images
- **Stop Generation**: Ability to stop the generation process at any time
- **AJAX-Based Filtering**: Filter posts without page reloads (prevents 500 errors from other plugins)
- **Optional Debug Mode**: Enable/disable debug logging and display for troubleshooting

### Image Generation Process

1. **Prompt Enhancement**: 
   - Extracts first 1000 characters from post content
   - Sends title + content excerpt to OpenRouter for visual description
   - Receives enhanced prompt optimized for image generation

2. **Style Application**:
   - Combines enhanced prompt with your custom "Prompt Style"
   - Final format: `[Enhanced Prompt], style: [Your Style]`

3. **Image Generation**:
   - Sends final prompt to Kie.ai using callback method (no polling needed)
   - Uses 16:9 aspect ratio, 1K resolution, JPG format
   - Kie.ai calls back when image is ready

4. **Image Sideloading**:
   - Downloads image from Kie.ai CDN
   - Saves to WordPress uploads directory (`/wp-content/uploads/`)
   - Creates WordPress attachment with proper metadata
   - Sets as featured image for the post

5. **Alt Text Generation**:
   - Generates SEO-friendly alt text using OpenRouter
   - Limits to 100 characters maximum
   - Saves to attachment metadata (`_wp_attachment_image_alt`)

## Installation

1. Upload the plugin folder to `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **Settings > WP Auto-Feature Gen** in the WordPress admin menu
4. Configure your API keys:
   - **Kie.ai API Key**: Get your API key from [Kie.ai](https://kie.ai)
   - **OpenRouter API Key**: Get your API key from [OpenRouter](https://openrouter.ai)
   - **Prompt Style**: Optional global style instructions (e.g., "Photorealistic, 8k, cinematic lighting")
   - **Debug Mode**: Enable/disable debug logging (optional, for troubleshooting)

## Usage

### Basic Workflow

1. Go to **Settings > WP Auto-Feature Gen** in the WordPress admin menu
2. Configure your API keys and prompt style in the Settings section
3. Use the filters to select:
   - **Post Type**: Posts, Pages, or Both
   - **Status**: Draft, Scheduled, or Both
4. Click **Filter** to update the list (no page reload required)
5. The dashboard will display all matching posts/pages without featured images
6. Click **Generate All** to start processing
7. Watch the progress bar as each post/page is processed
8. Click **Stop** at any time to halt the generation process

### Status Indicators

- **Pending**: Not yet processed
- **Analyzing Content...**: Generating prompt description with OpenRouter
- **Rendering Image...**: Creating image with Kie.ai
- **Waiting for Callback...**: Image generation started, waiting for Kie.ai callback
- **Done**: Successfully completed (image saved and alt text generated)
- **Error**: Something went wrong (check error message in status column)
- **Stopped**: Generation was stopped by user

### Filtering Posts

The plugin supports flexible filtering:

- **Post Type Options**:
  - **Posts**: Only WordPress posts
  - **Pages**: Only WordPress pages
  - **Both**: Both posts and pages

- **Status Options**:
  - **Draft**: Posts/pages with draft status
  - **Scheduled**: Posts/pages with scheduled (future) status
  - **Both**: Both draft and scheduled posts/pages

Filtering is done via AJAX, so there's no page reload and no risk of 500 errors from other plugins.

### Debug Mode

When enabled, Debug Mode provides:

- **Debug Log Display**: Shows the last 50 log entries in a textarea on the dashboard
- **Detailed Logging**: Logs all operations including:
  - Filter queries and results
  - API calls and responses
  - Image download and attachment creation
  - Featured image assignment
  - Alt text generation
  - Error details with context

- **Log Management**:
  - **Refresh Log**: Reload the log display
  - **Clear Log**: Delete today's log file

Log files are stored in `/wp-content/uploads/wpafg-logs/debug-YYYY-MM-DD.log`

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Valid API keys for:
  - Kie.ai (for image generation)
  - OpenRouter (for prompt enhancement and alt text)

## API Configuration

### OpenRouter
- **Model**: `openai/gpt-oss-120b`
- **Endpoint**: `https://openrouter.ai/api/v1/chat/completions`
- **Used for**: 
  - Prompt enhancement (converts post title + content into visual description)
  - Alt text generation (creates SEO-friendly alt text)

### Kie.ai
- **Model**: `nano-banana-pro`
- **Endpoint**: `https://api.kie.ai/api/v1/jobs/createTask`
- **Settings**: 
  - Aspect Ratio: 16:9
  - Resolution: 1K
  - Format: JPG
- **Method**: Callback-based (no polling required)
- **Used for**: Image generation

## Technical Details

### Architecture

- **Client-Side Queue**: JavaScript processes posts sequentially via AJAX to prevent PHP timeouts
- **AJAX Timeout**: 120 seconds per post (allows for API calls and callbacks)
- **Callback Handling**: Kie.ai sends POST request to plugin callback endpoint when image is ready
- **Error Shield**: Suppresses notices from other plugins to prevent 500 errors
- **Security**: Nonce verification and capability checks (`manage_options`)

### Database

- Stores task IDs in post meta (`_wpafg_task_id`, `_wpafg_kie_task_id`)
- Tracks status in post meta (`_wpafg_status`)
- Stores error messages in post meta (`_wpafg_error`)
- Cleans up meta after successful completion

### File Structure

```
wp-auto-feature-gen/
├── wp-auto-feature-gen.php          # Main plugin file
├── includes/
│   ├── class-wpafg-admin.php       # Admin dashboard and settings
│   ├── class-wpafg-ajax.php        # AJAX handlers
│   ├── class-wpafg-api-kie.php     # Kie.ai API integration
│   ├── class-wpafg-api-openrouter.php # OpenRouter API integration
│   └── class-wpafg-debug.php       # Debug logging
├── assets/
│   ├── js/
│   │   └── admin.js                # Client-side queue and UI
│   └── css/
│       └── admin.css               # Admin styles
└── README.md                        # This file
```

## Troubleshooting

### Common Issues

1. **500 Error When Filtering**:
   - This is usually caused by other plugins outputting notices before headers
   - The plugin includes an "error shield" to prevent this
   - If it persists, enable Debug Mode to see detailed logs

2. **Images Not Saving**:
   - Check that WordPress uploads directory is writable
   - Verify API keys are correct
   - Enable Debug Mode to see detailed error messages
   - Check that `media_handle_sideload()` has proper permissions

3. **No Posts Showing**:
   - Verify you have posts/pages with draft or scheduled status
   - Check that posts don't already have featured images
   - Use Debug Mode to see query results and counts

4. **Generation Stops Unexpectedly**:
   - Check browser console for JavaScript errors
   - Verify AJAX endpoint is accessible
   - Check WordPress error logs
   - Enable Debug Mode for detailed operation logs

### Debug Mode

Enable Debug Mode in Settings to get detailed information about:
- All database queries and results
- API request/response details
- Image download and attachment creation steps
- Featured image assignment verification
- Alt text generation results
- Error messages with full context

## Support

For issues or questions:
- Check WordPress error logs (`/wp-content/debug.log` if WP_DEBUG is enabled)
- Enable Debug Mode and check the debug log display
- Check browser console for JavaScript errors
- Verify API key validity
- Check network connectivity
- Review plugin debug logs in `/wp-content/uploads/wpafg-logs/`

## Changelog

### Version 1.0.0
- Initial release
- Bulk featured image generation for draft and scheduled posts/pages
- AI-powered prompt enhancement
- Customizable prompt styles
- AJAX-based filtering
- Stop generation functionality
- Optional debug mode
- Automatic alt text generation

## License

GPL v2 or later

## Author

**James Ross**  
Website: https://frontrowsales.com
