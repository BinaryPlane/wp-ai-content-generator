# Releasing updates (for plugin author)

Sites with the plugin installed receive updates from **GitHub Releases** when you follow this process.

## Before you release

1. **Bump version** in these two places (must match):
   - `ai-content-generator.php` – header `Version:` and constant `AICG_VERSION`
   - `readme.txt` – `Stable tag:` and the `== Changelog ==` / `== Upgrade Notice ==` blocks

2. **Update changelog** in `readme.txt` under `== Changelog ==` and optionally `== Upgrade Notice ==`.

## Creating the release on GitHub

1. Commit and push all changes to your default branch (e.g. `main`).

2. **Create a GitHub Release:**
   - Repo → **Releases** → **Draft a new release**
   - **Choose a tag:** create a new tag, e.g. `v1.0.1` (use `v` + semver: `v1.0.0`, `v1.2.3`).
   - **Release title:** e.g. `1.0.1` or `Version 1.0.1`
   - **Description:** paste your changelog (users see this in the “View details” popup when updating).

3. **No zip needed.** The plugin uses GitHub’s **Source code (zipball)** for the update. The updater’s `after_install` hook moves the extracted folder into the correct plugin path, so you do **not** need to run `build-release.ps1` or attach a custom zip. Just publish the release.

4. Publish the release.

## How sites get the update

- The plugin uses the same **GitHub_Plugin_Updater** pattern as BinaryPlane’s Helper Plugin: it checks `https://api.github.com/repos/BinaryPlane/wp-ai-content-generator/releases/latest`.
- If the release **tag** (e.g. `v1.0.1`) is **newer** than the installed plugin version, WordPress shows an update. The download uses GitHub’s **zipball_url**; after install, the updater moves files into the correct plugin folder and reactivates if needed.

## If your GitHub repo is not BinaryPlane/wp-ai-content-generator

In `wp-config.php` (on each site that should point to a different repo):

```php
define( 'AICG_GITHUB_REPO', 'your-username/your-repo-name' );
```

## Checklist for every release

- [ ] Version bumped in `ai-content-generator.php` (header + `AICG_VERSION`)
- [ ] `readme.txt` updated: `Stable tag`, Changelog, Upgrade Notice
- [ ] Changes committed and pushed
- [ ] Create a new **Release** on GitHub: use tag `vX.Y.Z`, add title and description (no zip attachment needed)
- [ ] Publish the release
