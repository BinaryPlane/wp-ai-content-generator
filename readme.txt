=== AI Content Generator ===
Contributors: binaryplane
Donate link: https://binaryplane.com
Tags: ai, content, blog, deepseek, gemini, auto post, bulk post, featured image, unsplash, pexels, pixabay
Requires at least: 5.8
Tested up to: 6.4
Stable tag: 1.0.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Mass-generate blog posts using AI (DeepSeek or Google Gemini) with automatic categories and featured images from free stock photo APIs.

== Description ==

AI Content Generator lets you create multiple blog posts at once using AI. You describe the kind of content you want, choose how many posts to generate and the word count per post, and the plugin creates drafts with titles, body content, categories, and featured images.

**Features:**

* **AI providers:** DeepSeek and Google Gemini. Set your API key in settings and switch between them.
* **Batch generation:** Choose the number of posts (1–50) and a single topic; each post varies within that theme.
* **Word count:** Set a default word count per post (200–5000) in settings and override it when generating.
* **System prompt:** Editable system prompt so you can enforce style (e.g. human-like language, no em dashes, fewer emojis).
* **Categories:** The AI suggests a category per post; categories are created automatically if they don't exist.
* **Featured images:** Automatically assign a featured image per post from free sources. The image is chosen based on the post's category/topic.
* **Image sources:** Unsplash, Pexels, and Pixabay. Enable the sources you want and add API keys where required. The first configured source is used for featured images.

**API keys:**

* DeepSeek: https://platform.deepseek.com/
* Google Gemini: https://ai.google.dev/ (Google AI Studio)
* Unsplash: https://unsplash.com/developers
* Pexels: https://www.pexels.com/api/
* Pixabay: https://pixabay.com/api/docs/

All generated posts are created as **drafts** so you can review and edit before publishing.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install via WordPress admin (Plugins → Add New → Upload).
2. Activate the plugin.
3. Go to Settings → AI Content Generator and enter at least one AI provider API key (DeepSeek or Gemini).
4. Optionally enable image sources and add their API keys.
5. Go to Posts → Generate AI Posts to create content.

== Frequently Asked Questions ==

= Do I need all API keys? =

You need at least one AI provider key (DeepSeek or Gemini). Image source keys are optional; if none are set, posts are created without featured images.

= Can I change the AI writing style? =

Yes. In Settings → AI Content Generator, edit the "System prompt" field. The default encourages natural language, no em dashes, and fewer emojis.

= Are generated posts published immediately? =

No. All posts are created as drafts. You can edit and publish them when ready.

== Screenshots ==

1. Settings: AI provider, API keys, word count, system prompt, image sources.
2. Generate page: Topic, number of posts, word count, and progress.

== Changelog ==

= 1.0.2 =
* Fix "Generate Posts" button not appearing next to Add Post (run script after DOM ready).
* Switch to GitHub_Plugin_Updater: updates use GitHub zipball, no custom zip or build script needed.
* Add AI provider dropdown on Generate AI Posts page (switch between DeepSeek and Gemini per run).
* Remove build-release.ps1 requirement from release process.

= 1.0.1 =
* Add "Generate Posts" button next to "Add New" on the Posts list screen.
* GitHub-based update checker for updates from BinaryPlane/wp-ai-content-generator releases.
* Randomize post date option (optional, disabled by default).
* Delay between posts (rate limiting) configurable on Settings and on the Generate page.
* Configurable Gemini model (2.5 Flash Lite, 2.5 Flash, 2.5 Pro, 3.x previews).
* Build script (build-release.ps1) for creating release zips.

= 1.0.0 =
* Initial release.
* DeepSeek and Google Gemini support.
* Batch post generation with configurable count and word count.
* Editable system prompt.
* Auto categories and featured images from Unsplash, Pexels, Pixabay.

== Upgrade Notice ==

= 1.0.2 =
Fixes Generate Posts button on Posts list; AI provider can be chosen on Generate page; simpler GitHub updates (no zip upload).

= 1.0.1 =
Adds "Generate Posts" button next to Add New, GitHub updates, randomize date option, and rate-limit controls.

= 1.0.0 =
Initial release of AI Content Generator.
