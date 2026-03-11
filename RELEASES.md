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
   - **Choose a tag:** create a new tag, e.g. `v1.0.1` (must start with `v` and be semver: `v1.0.0`, `v1.2.3`).
   - **Release title:** e.g. `1.0.1` or `Version 1.0.1`
   - **Description:** paste your changelog (users see this in the “View details” popup when updating).

3. **Attach the plugin ZIP (required for updates to work):**
   - The ZIP must contain a **single root folder** named `ai-content-generator` with all plugin files inside it (so the path inside the zip is `ai-content-generator/ai-content-generator.php`, etc.).
   - Create the zip from your repo root: run **PowerShell** at repo root: `.\build-release.ps1` (reads version from plugin file; or `.\build-release.ps1 1.0.1`).
   - Upload this zip as a **Release asset** (Attach binaries / drag and drop).
   - Name doesn’t have to be exact; the updater uses the first `.zip` asset.

4. Publish the release.

## How sites get the update

- The plugin checks `https://api.github.com/repos/OWNER/REPO/releases/latest` (cached for 12 hours).
- If the release **tag** (e.g. `v1.0.1`) is **newer** than the installed `AICG_VERSION` and the release has **at least one .zip asset**, WordPress will show an update in **Dashboard → Updates** and under **Plugins**.
- Users click **Update** and WordPress installs the attached zip.

## If your GitHub repo is not BinaryPlane/wp-ai-content-generator

In `wp-config.php` (on each site that should point to a different repo):

```php
define( 'AICG_GITHUB_REPO', 'your-username/your-repo-name' );
```

## Checklist for every release

- [ ] Version bumped in `ai-content-generator.php` (header + `AICG_VERSION`)
- [ ] `readme.txt` updated: `Stable tag`, Changelog, Upgrade Notice
- [ ] Changes committed and pushed
- [ ] Run `.\build-release.ps1` to create the zip (from repo root)
- [ ] Create a new **Release** on GitHub: use tag `vX.Y.Z`, add title and description, **attach the generated .zip** as an asset
- [ ] Publish the release
