# Public-Readiness Plan — WP Auto-Feature Gen

Goal: bring **WP Auto-Feature Gen** up to the same "publicly seeable / usable"
standard as the reference repo
[`teamfrontrow-james/cloudflare-smtp-gateway`](https://github.com/teamfrontrow-james/cloudflare-smtp-gateway),
adapted from a TypeScript/Node app to a WordPress plugin.

## Mapping: reference repo → this plugin

| Reference (cloudflare-smtp-gateway) | This plugin (WP) | Status |
|---|---|---|
| `LICENSE` (MIT) | `LICENSE` — **GPL-2.0** (header already declares "GPL v2 or later"; file is missing) | DONE |
| Polished `README.md` (tagline + ASCII diagram + docs links) | Rewrite `README.md` in the same shape | DONE |
| — (N/A for Node) | `readme.txt` — WordPress.org plugin header (makes it installable/discoverable) | DONE |
| `docs/domain-setup.md` | `docs/api-setup.md` (Kie.ai + OpenRouter keys, models, callback reachability) | DONE |
| `docs/security.md` | `docs/security.md` (nonces, capability checks, key storage, nopriv callback) | DONE |
| `docs/troubleshooting.md` | `docs/troubleshooting.md` (move the troubleshooting section out of README) | DONE |
| `examples/` (curl, php, node) | `examples/` (prompt-style recipes, callback/debug snippets) | DONE |
| `.github/workflows/ci.yml` (lint/typecheck/test) | `.github/workflows/ci.yml` — PHP syntax-lint matrix | DONE |
| `RELEASING.md` | `RELEASING.md` — tag + build-zip release flow | DONE |
| `.gitignore` | Tighten (add `vendor/`, `node_modules/`, build artifacts) | DONE |

Things intentionally **not** copied (don't apply to a WP plugin): `.env.example`,
`Dockerfile`/`docker-compose*`, `deploy/`, `package.json`/`tsconfig.json`, npm/Docker
publish workflows.

---

## Step 1 — `LICENSE` (new file)

- Add the full **GNU General Public License v2** text (the plugin header already
  says `License: GPL v2 or later`, so this is the matching file — do **not** use MIT).
- Copyright line: `Copyright (c) 2026 James Ross (Front Row Sales)`.

## Step 2 — `readme.txt` (new file, WordPress.org format)

Standard plugin-repo header so the plugin is installable/discoverable:

```
=== WP Auto-Feature Gen ===
Contributors: jamesross
Tags: featured image, ai, openrouter, kie.ai, bulk, images, seo
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
```

Then the standard sections: `== Description ==`, `== Installation ==`,
`== Frequently Asked Questions ==`, `== Screenshots ==`, `== Changelog ==`,
`== Upgrade Notice ==`. Pull the prose from the current README.

## Step 3 — Rewrite `README.md`

Match the cloudflare README structure:

1. **Title + bold one-line tagline.**
2. **ASCII flow diagram**, e.g.:
   ```
   Draft/Scheduled post ──▶ OpenRouter (enhance prompt)
                                │
                                ▼
                        Kie.ai (generate image) ──callback──▶ WP
                                │
                                ▼
                  Sideload to Media Library + set featured image + alt text
   ```
3. **Feature bullets** (condensed from current README).
4. **Prerequisites** (WP 5.0+, PHP 7.4+, Kie.ai key, OpenRouter key, publicly
   reachable site for the Kie.ai callback).
5. **Quickstart / Install** (upload to `/wp-content/plugins/`, activate,
   Settings → WP Auto-Feature Gen, add keys).
6. **Usage** (filter → Generate All → progress/stop), with the status-indicator list.
7. **Configuration table** (API keys, prompt style, debug mode, models/endpoints).
8. **Documentation** links → `docs/*`.
9. **Development** (lint, how CI runs) → points to `RELEASING.md`.
10. **License** → GPL-2.0, link to `LICENSE`.

Move the long Troubleshooting and deep API-config sections **out** into `docs/`.

## Step 4 — `docs/` folder (3 new files)

- **`docs/api-setup.md`** — getting a Kie.ai key (model `nano-banana-pro`,
  endpoint `https://api.kie.ai/api/v1/jobs/createTask`, 16:9 / 1K / jpg, callback
  method) and an OpenRouter key (model `openai/gpt-oss-120b`, chat-completions
  endpoint). Note the **callback requires a publicly reachable site** (Kie.ai POSTs
  to `admin-ajax.php?action=wpafg_kie_callback`); localhost won't receive it.
- **`docs/security.md`** — nonce verification (`wpafg_nonce`), `manage_options`
  capability checks on every AJAX action, API keys stored as WP options (password
  fields), the `nopriv` callback endpoint and why it's safe (matched by task-id
  meta), and the "error shield" mitigation + the proper `wp-config.php` fix.
- **`docs/troubleshooting.md`** — relocate + expand the README troubleshooting
  (500 on filter, images not saving, no posts showing, generation stalls,
  callback never arrives → reachability, debug-log location
  `/wp-content/uploads/wpafg-logs/`).

## Step 5 — `examples/` folder

- **`examples/prompt-styles.md`** — copy-paste prompt-style strings
  (photorealistic, vector, cyberpunk, watercolor, corporate flat, etc.).
- **`examples/wp-config-debug.php`** — snippet enabling `WP_DEBUG` +
  `WP_DEBUG_LOG` with `WP_DEBUG_DISPLAY` off (the recommended production setting
  the error-shield comment references).

## Step 6 — `.github/workflows/ci.yml` (new file)

PHP syntax-lint matrix (the WP analog of the reference's lint/typecheck/test):

```yaml
name: CI
on:
  push:
    branches: [main]
  pull_request:
jobs:
  php-lint:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: ['7.4', '8.0', '8.1', '8.2', '8.3']
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
      - name: Lint all PHP files
        run: find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 -P4 php -l
```

(Optional later: a PHPCS / WordPress-Coding-Standards job — note current code has
direct `$wpdb` string-interpolated queries that WPCS will flag, so add it
`continue-on-error: true` first or clean up before making it blocking.)

## Step 7 — `RELEASING.md` (new file)

Plugin release flow (analog of the reference's automated release doc):

1. Bump version in **three** places that must stay in sync:
   `wp-auto-feature-gen.php` header `Version:`, the `WPAFG_VERSION` constant, and
   `readme.txt` `Stable tag:`.
2. Update `== Changelog ==` in `readme.txt` and the README changelog.
3. Tag: `git tag vX.Y.Z && git push --follow-tags`.
4. Build a distributable zip (exclude dev files):
   ```bash
   git archive --format=zip --prefix=wp-auto-feature-gen/ -o wp-auto-feature-gen-vX.Y.Z.zip HEAD
   ```
5. `gh release create vX.Y.Z --generate-notes` and attach the zip.
6. (If publishing to WordPress.org later: svn tag flow.)

## Step 8 — Tighten `.gitignore`

Add `vendor/`, `node_modules/`, and the build zip pattern
(`wp-auto-feature-gen-*.zip`); keep existing WP/IDE/log entries.

## Additional GitHub visibility updates — completed

- Added `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `SECURITY.md`, `SUPPORT.md`,
  and `CITATION.cff`.
- Added issue templates and a pull request template under `.github/`.
- Added `docs/github-visibility.md` with recommended GitHub description, homepage,
  topics, and naming notes.
- Added `.gitattributes` export-ignore rules so release zips created with
  `git archive` exclude development/community files.

---

## Suggested execution order

1. `LICENSE` (quick, unblocks license references)
2. `.gitignore`
3. `.github/workflows/ci.yml`
4. `docs/` (3 files)
5. `examples/` (2 files)
6. `readme.txt`
7. `README.md` rewrite (links to all the above)
8. `RELEASING.md`

## Acceptance check

- `php -l` passes on every `*.php` (mirror what CI does).
- Every link in `README.md` resolves to a file that exists.
- Version string identical across `wp-auto-feature-gen.php` (header + constant)
  and `readme.txt`.
- `LICENSE` present and referenced; no MIT remnants.
