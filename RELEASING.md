# Releasing

Use this checklist when preparing a public plugin release.

## Version Sync

Keep the version number in sync across all release metadata:

- `wp-auto-feature-gen.php` plugin header `Version:`
- `wp-auto-feature-gen.php` `WPAFG_VERSION`
- `readme.txt` `Stable tag:`
- `readme.txt` `== Changelog ==`

## Preflight

```bash
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 -P4 php -l
```

Also confirm the README and `readme.txt` links resolve and the version strings
listed under [Version Sync](#version-sync) all match before tagging.

## Tag And Build

Replace `X.Y.Z` with the release version.

```bash
git tag vX.Y.Z
git push --follow-tags
```

```bash
git archive --format=zip --prefix=wp-auto-feature-gen/ -o wp-auto-feature-gen-vX.Y.Z.zip HEAD
```

## GitHub Release

```bash
gh release create vX.Y.Z wp-auto-feature-gen-vX.Y.Z.zip --generate-notes
```

## WordPress.org Later

If this plugin is submitted to WordPress.org, use the WordPress.org SVN release flow after approval:

- Copy the release files to `/trunk`.
- Copy the same release files to `/tags/X.Y.Z`.
- Confirm `readme.txt` `Stable tag:` points to the released version.
- Commit with a clear release message.

