# Contributing

Thanks for helping improve WP Auto-Feature Gen. The project is intentionally small, so focused issues and pull requests are the fastest way to move it forward.

## Good First Contributions

- Improve setup, troubleshooting, or WordPress.org readme clarity.
- Add regression notes for callback, sideload, or filtering edge cases.
- Improve accessibility and copy in the admin dashboard.
- Add tests or WordPress Coding Standards cleanup.

## Local Checks

Run PHP syntax checks before opening a pull request:

```bash
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 -P4 php -l
```

## Pull Request Guidelines

- Keep changes focused on one bug, feature, or documentation improvement.
- Do not commit API keys, generated images, logs, or customer content.
- Update `README.md`, `readme.txt`, or `docs/` if behavior changes.
- Include screenshots for admin UI changes when practical.

## Security Issues

Please do not open public issues for vulnerabilities. Use the process in [SECURITY.md](SECURITY.md).

