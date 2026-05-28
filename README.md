# WP Auto-Feature Gen

[![CI](https://github.com/teamfrontrow-james/WPFeaturedImageGenerator/actions/workflows/ci.yml/badge.svg)](https://github.com/teamfrontrow-james/WPFeaturedImageGenerator/actions/workflows/ci.yml)
[![License: GPL v2 or later](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)

**Bulk-generate SEO-friendly featured images for draft and scheduled WordPress posts with Kie.ai image generation and OpenRouter prompt enhancement.**

```text
Draft/Scheduled post --> OpenRouter (enhance prompt)
                              |
                              v
                    Kie.ai (generate image) --callback--> WordPress
                              |
                              v
          Media Library sideload + featured image + alt text
```

## Features

- Generate featured images for draft and scheduled posts/pages in bulk.
- Enhance image prompts from the post title and content excerpt using OpenRouter.
- Create 16:9 JPG images with Kie.ai and save them locally to the WordPress Media Library.
- Set the generated image as the post featured image automatically.
- Generate concise image alt text for stronger image SEO and accessibility.
- Filter by post type and status without full-page reloads.
- Process posts one at a time with a visible queue, progress bar, and stop control.
- Enable optional debug logs for API, callback, sideloading, and filter troubleshooting.

## Prerequisites

- WordPress 5.0 or newer.
- PHP 7.4 or newer.
- A Kie.ai API key for image generation.
- An OpenRouter API key for prompt enhancement and alt text generation.
- A publicly reachable WordPress site so Kie.ai can POST callbacks to `admin-ajax.php?action=wpafg_kie_callback`.

Localhost and private staging URLs usually cannot receive Kie.ai callbacks unless they are exposed through a tunnel or public staging domain.

## Quickstart

1. Upload this plugin folder to `/wp-content/plugins/wp-auto-feature-gen/`.
2. Activate **WP Auto-Feature Gen** from **Plugins** in WordPress admin.
3. Open **Settings -> WP Auto-Feature Gen**.
4. Add your Kie.ai and OpenRouter API keys.
5. Optional: set a global prompt style such as `Photorealistic editorial image, natural light, no text`.
6. Choose post type/status filters, click **Filter**, then click **Generate All**.

## Usage

The dashboard lists draft and scheduled posts/pages that do not already have a featured image. The queue processes one item at a time to reduce PHP timeout risk and keep failures isolated to the current post.

Status indicators:

- **Pending**: queued but not started.
- **Analyzing Content...**: OpenRouter is creating the image prompt.
- **Rendering Image...**: Kie.ai generation has started.
- **Waiting for image...**: WordPress is polling local post meta while waiting for the Kie.ai callback.
- **Done**: image was sideloaded, attached, set as featured image, and alt text was saved.
- **Error**: processing failed; enable Debug Mode for details.
- **Stopped**: queue processing was halted by the user.

## Configuration

| Setting | Purpose | Default / implementation |
|---|---|---|
| Kie.ai API key | Authenticates image-generation requests. | Stored in `wpafg_kie_api_key`. |
| OpenRouter API key | Authenticates prompt and alt-text requests. | Stored in `wpafg_openrouter_api_key`. |
| Prompt Style | Appended to every enhanced image prompt. | Empty until configured. |
| Debug Mode | Writes and displays debug logs. | Off by default; logs are stored in `/wp-content/uploads/wpafg-logs/`. |
| OpenRouter model | Prompt enhancement and alt text. | `openai/gpt-oss-120b`. |
| Kie.ai model | Image rendering. | `nano-banana-pro`, `16:9`, `1K`, `jpg`. |

## Outputs

For each successful post, the plugin downloads the generated image into the WordPress uploads directory, creates a Media Library attachment, assigns it as the featured image, and stores generated alt text in `_wp_attachment_image_alt`.

## Examples

- Prompt style recipes live in [examples/prompt-styles.md](examples/prompt-styles.md).
- A production-safe WordPress debug logging snippet lives in [examples/wp-config-debug.php](examples/wp-config-debug.php).

## Documentation

- [API setup](docs/api-setup.md): Kie.ai, OpenRouter, callback reachability, and model defaults.
- [Security notes](docs/security.md): nonces, capabilities, stored options, callback handling, and debug/error-shield behavior.
- [Troubleshooting](docs/troubleshooting.md): common filtering, callback, sideloading, and debug-log issues.
- [Releasing](RELEASING.md): version sync, changelog, tag, zip, and GitHub release flow.

## Development

Run PHP syntax checks locally:

```bash
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 -P4 php -l
```

CI runs the same syntax lint across PHP 7.4 through 8.3. See [RELEASING.md](RELEASING.md) before tagging a release.

## External Services

This plugin sends post titles, content excerpts, generated prompts, callback URLs, and generated image URLs to third-party AI services only when an administrator starts generation. Review the service providers' terms and privacy policies before using the plugin on production content.

- Kie.ai: image generation.
- OpenRouter: prompt enhancement and alt text.

## Contributing

Issues and pull requests are welcome. Please start with [CONTRIBUTING.md](CONTRIBUTING.md), open a focused issue for bugs or feature requests, and avoid sharing private API keys or unpublished customer content in public reports.

## License

WP Auto-Feature Gen is licensed under the [GPL v2 or later](LICENSE).

## Author

Created by [James Ross](https://frontrowsales.com) / Front Row Sales.
