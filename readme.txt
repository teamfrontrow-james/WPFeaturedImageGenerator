=== WP Auto-Feature Gen ===
Contributors: jamesross
Tags: featured images, ai images, openrouter, bulk edit, image seo
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bulk-generate SEO-friendly featured images for draft and scheduled WordPress posts with Kie.ai and OpenRouter.

== Description ==

WP Auto-Feature Gen helps editors fill missing featured images before posts go live. It finds draft and scheduled posts/pages without featured images, enhances image prompts with OpenRouter, renders images with Kie.ai, sideloads the completed image into the WordPress Media Library, sets it as the featured image, and saves generated alt text.

Core features:

* Bulk queue for draft and scheduled posts/pages.
* AJAX filtering by post type and status.
* OpenRouter prompt enhancement from title and content excerpt.
* Kie.ai image generation using callback delivery.
* Local Media Library sideloading and featured-image assignment.
* AI-generated alt text for image SEO and accessibility.
* Optional prompt style applied to every image.
* Optional debug logs in `/wp-content/uploads/wpafg-logs/`.

= External services =

This plugin depends on two third-party services and only sends data when an administrator starts image generation.

* Kie.ai generates images from prompts and receives the callback URL needed to return generation results.
* OpenRouter enhances prompts and generates alt text from the post title and content excerpt.

You need your own API keys for both services. Review each provider's current terms and privacy policy before using this plugin with production or private content.

== Installation ==

1. Upload the `wp-auto-feature-gen` folder to `/wp-content/plugins/`.
2. Activate **WP Auto-Feature Gen** through the WordPress **Plugins** screen.
3. Go to **Settings > WP Auto-Feature Gen**.
4. Add your Kie.ai API key and OpenRouter API key.
5. Optional: add a global prompt style such as `Photorealistic editorial image, natural light, no text`.
6. Confirm your site is publicly reachable so Kie.ai can POST callbacks to `admin-ajax.php?action=wpafg_kie_callback`.

== Frequently Asked Questions ==

= Does this work on localhost? =

The dashboard loads on localhost, but Kie.ai callbacks require a public URL. Use a public staging site or a secure tunnel if you need to test callbacks locally.

= Does the plugin overwrite existing featured images? =

No. The dashboard only lists draft or scheduled posts/pages that do not already have a featured image.

= Where are generated images stored? =

Generated images are downloaded into the standard WordPress uploads directory and registered as Media Library attachments.

= Where are debug logs stored? =

When Debug Mode is enabled, logs are written to `/wp-content/uploads/wpafg-logs/debug-YYYY-MM-DD.log` and displayed in the plugin dashboard.

= Which content is sent to AI services? =

The plugin sends the post title and a content excerpt to OpenRouter for prompt and alt-text generation. It sends the final image prompt and callback URL to Kie.ai for image generation.

== Screenshots ==

1. Settings panel for API keys, prompt style, and debug mode.
2. Filterable queue of posts/pages missing featured images.
3. Progress states while prompts, images, callbacks, and sideloading complete.

== Changelog ==

= 1.0.0 =

* Initial release.
* Bulk featured image generation for draft and scheduled posts/pages.
* OpenRouter prompt enhancement and alt text generation.
* Kie.ai callback-based image generation.
* AJAX filters, progress UI, stop control, and optional debug logs.

== Upgrade Notice ==

= 1.0.0 =

Initial public release.
