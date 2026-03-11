# AI Content Generator

WordPress plugin to mass-generate blog posts using AI (DeepSeek, Google Gemini) with automatic categories and featured images from free stock photo sources.

**Author:** [BinaryPlane](https://binaryplane.com)

## Features

- **AI providers:** DeepSeek and Google Gemini (API key in settings).
- **Batch generation:** Set topic, number of posts (1–50), and word count per post.
- **System prompt:** Editable so you can enforce human-like language, no em dashes, fewer emojis.
- **Categories:** AI suggests a category per post; categories are created automatically.
- **Featured images:** Fetched from Unsplash, Pexels, or Pixabay (user chooses sources and adds API keys).

## Requirements

- WordPress 5.8+
- PHP 7.4+

## Installation

1. Upload the `ai-content-generator` folder to `wp-content/plugins/`.
2. Activate **AI Content Generator** in the WordPress admin.
3. Go to **Settings → AI Content Generator** and set at least one AI API key (DeepSeek or Gemini).
4. Optionally enable image sources and add their API keys.
5. Use **Posts → Generate AI Posts** to create drafts.

## API keys

| Service   | Link |
|----------|------|
| DeepSeek | https://platform.deepseek.com/ |
| Gemini   | https://ai.google.dev/ (Google AI Studio) |
| Unsplash | https://unsplash.com/developers |
| Pexels   | https://www.pexels.com/api/ |
| Pixabay  | https://pixabay.com/api/docs/ |

## Publishing on WordPress.org

This plugin is structured for submission to the WordPress.org plugin directory:

- Main plugin file and `readme.txt` in the plugin root.
- `Stable tag` in `readme.txt` must match the version in the main plugin file for updates to work.

## Development / GitHub

- **Repository:** https://github.com/BinaryPlane/wp-ai-content-generator
- **Updates:** The plugin checks GitHub Releases for a newer version. See **RELEASES.md** for how to publish updates so other sites can update automatically.
- Use the `readme.txt` **Changelog** and **Upgrade Notice** sections when releasing.
- **Every release:** Bump version in `ai-content-generator.php` (header + `AICG_VERSION`) and in `readme.txt` (Stable tag + Changelog). Create a GitHub Release with tag `vX.Y.Z` (no zip attachment needed; the updater uses GitHub’s source zipball).
- For WordPress.org submission later, connect the repo via the plugin SVN repo's readme.

## License

GPL v2 or later.
