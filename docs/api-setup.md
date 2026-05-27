# API Setup

WP Auto-Feature Gen uses OpenRouter for text tasks and Kie.ai for image generation. Both API keys are configured in **Settings -> WP Auto-Feature Gen**.

## Kie.ai

Kie.ai renders the featured image and sends the completed job back to WordPress by callback.

Current plugin defaults:

| Option | Value |
|---|---|
| Model | `nano-banana-pro` |
| Endpoint | `https://api.kie.ai/api/v1/jobs/createTask` |
| Aspect ratio | `16:9` |
| Resolution | `1K` |
| Format | `jpg` |
| Delivery | Callback to WordPress admin AJAX |

Callback URL format:

```text
https://example.com/wp-admin/admin-ajax.php?action=wpafg_kie_callback&task_id=post_123_1710000000
```

The callback must be reachable by Kie.ai over the public internet. Localhost, private networks, basic-auth staging sites, blocked firewalls, and maintenance-mode pages can prevent the callback from arriving.

## OpenRouter

OpenRouter enhances the post title/content excerpt into a visual image prompt and later generates concise image alt text.

Current plugin defaults:

| Option | Value |
|---|---|
| Model | `openai/gpt-oss-120b` |
| Endpoint | `https://openrouter.ai/api/v1/chat/completions` |
| Prompt input | Post title and first 1000 characters of stripped post content |
| Alt text limit | 100 characters after trimming |

## Prompt Style

The optional **Prompt Style** setting is appended to every enhanced prompt:

```text
[Enhanced prompt], style: [Prompt Style]
```

Keep prompt styles short and reusable. If the site has a brand guide, prefer concrete visual rules such as medium, lighting, color palette, composition, and "no text in image".

## Public Callback Checklist

- The WordPress site resolves to a public HTTPS URL.
- `wp-admin/admin-ajax.php` is not blocked by a firewall, CDN rule, maintenance mode, or basic authentication.
- The Kie.ai account has enough credits/quota.
- The callback URL in the debug log matches the public site URL.
- Debug Mode is enabled only while troubleshooting.

