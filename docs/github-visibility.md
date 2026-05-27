# GitHub Visibility

Use this checklist after pushing the public-readiness files so the GitHub repository profile matches the project metadata.

## Recommended About Metadata

Description:

```text
WordPress plugin that bulk-generates SEO-friendly featured images for draft and scheduled posts using Kie.ai and OpenRouter.
```

Homepage:

```text
https://frontrowsales.com
```

Topics:

```text
wordpress, wordpress-plugin, featured-images, ai-images, image-seo, openrouter, kie-ai, php, media-library, bulk-editing
```

## Suggested GitHub CLI Commands

```bash
gh repo edit teamfrontrow-james/WPFeaturedImageGenerator \
  --description "WordPress plugin that bulk-generates SEO-friendly featured images for draft and scheduled posts using Kie.ai and OpenRouter." \
  --homepage "https://frontrowsales.com"
```

```bash
gh repo edit teamfrontrow-james/WPFeaturedImageGenerator \
  --add-topic wordpress \
  --add-topic wordpress-plugin \
  --add-topic featured-images \
  --add-topic ai-images \
  --add-topic image-seo \
  --add-topic openrouter \
  --add-topic kie-ai \
  --add-topic php \
  --add-topic media-library \
  --add-topic bulk-editing
```

## Naming Note

The current remote slug is `WPFeaturedImageGenerator`. If you ever decide to rename it for search tokenization, `wp-featured-image-generator` or `wp-auto-feature-gen` will be easier to read in GitHub search results. Rename only if preserving old links is not a concern.

