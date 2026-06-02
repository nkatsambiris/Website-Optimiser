# Website Optimiser

![website optimiser header image](banner-1544x500.jpg)

A WordPress plugin that connects to Google Gemini AI to generate meta descriptions for your pages.

This plugin streamlines the process of generating SEO-optimized meta descriptions using Google's Gemini API (default model: `gemini-2.5-flash`).

It is currently compatible with ACF, WooCommerce, and the default Classic Editor.

## WordPress Connectors API (7.0+)

On WordPress 7.0 and later, Google Gemini API keys are read from **Settings → Connectors** (connector ID: `google`). The same priority as core applies:

1. `GOOGLE_API_KEY` environment variable  
2. `GOOGLE_API_KEY` PHP constant (e.g. in `wp-config.php`)  
3. Database option `connectors_ai_google_api_key` (saved via Connectors UI)

If no connector key is set, the plugin falls back to the legacy **Google Gemini API Key** field in Website Optimiser settings (WordPress 6.x and earlier).

## WordPress Abilities API

Website Optimiser registers abilities on WordPress 6.9+ (or when the [Abilities API](https://github.com/WordPress/abilities-api) plugin / Composer package is available). These expose optimisation data and AI tools to PHP, REST (`/wp-json/wp-abilities/v1/`), and AI agents.

| Ability | Description |
| --- | --- |
| `website-optimiser/get-seo-summary` | Aggregate dashboard optimisation summary |
| `website-optimiser/get-meta-description-stats` | Meta description coverage |
| `website-optimiser/get-h1-stats` | H1 heading analysis stats |
| `website-optimiser/get-alt-text-stats` | Image alt text coverage |
| `website-optimiser/get-featured-image-stats` | Featured image coverage |
| `website-optimiser/get-optimisation-checks` | All module checks (robots, sitemap, plugins, WooCommerce, etc.) |
| `website-optimiser/get-h1-detailed-results` | Per-post H1 analysis breakdown |
| `website-optimiser/get-images-without-alt-text` | List images missing alt text (`limit` input) |
| `website-optimiser/detect-seo-plugins` | Active SEO plugins on the site |
| `website-optimiser/check-robots-txt` | robots.txt availability check |
| `website-optimiser/check-llms-txt` | llms.txt availability check |
| `website-optimiser/check-xml-sitemap` | XML sitemap availability check |
| `website-optimiser/get-ai-config-status` | AI enabled, key configured, model ID (no secrets) |
| `website-optimiser/generate-meta-description` | Generate meta description (`post_id`) |
| `website-optimiser/generate-alt-text` | Generate alt text (`attachment_id`, optional `save`) |
| `website-optimiser/refresh-h1-analysis` | Re-run and store H1 analysis |
| `website-optimiser/clear-optimisation-cache` | Clear plugin caches and transients |

Example (PHP):

```php
$ability = wp_get_ability( 'website-optimiser/get-meta-description-stats' );
$stats   = $ability->execute();
```

REST (authenticated): `GET /wp-json/wp-abilities/v1/abilities` and `POST /wp-json/wp-abilities/v1/website-optimiser/generate-meta-description/run` with JSON body `{ "post_id": 123 }`.

## Setup

1. Get your Google Gemini API key from [Google AI Studio](https://aistudio.google.com/app/apikey)
2. Go to Website Optimiser > Settings in your WordPress admin
3. Enter your API key and configure your settings
4. Start generating meta descriptions!

## Version 2.0.4 Changes
- FIX: Resolve issue with search and replace not saving status

## Version 2.0.3 Changes
- FIX: Resolve issue with search and replace not saving status

## Version 2.0.2 Changes
- UPDATE: Enhance the optimisation dashboard with new styling and functionality for SEO statistics, and improve Gravity Forms notifications handling with DMARC approval checks.

## Version 2.0.1 Changes
- NEW: Add new modules for Google Search Console Sitemap and Yoast SEO, and enhance caching plugin recommendations in the dashboard widget.

## Version 2.0.0 Changes
- FIX: Improvements to code quality and speed optimisation

## Version 1.9.9 Changes
- FIX: Resolve issue with WP DEBUG master toggle check

## Version 1.9.8 Changes
- FIX: Improve H1 persistence analysis (take 2)

## Version 1.9.7 Changes
- FIX: Improve GF Recaptcha checking for v3 enterprise keys

## Version 1.9.6 Changes
- FIX: Improve H1 persistence analysis

## Version 1.9.5 Changes
- NEW: Exclude pages from H1 check
- FIX: Improve image alt description for http auth sites

## Version 1.9.4 Changes
- FIX: Resolve issue with H1 Heading check if website is password protected

## Version 1.9.3 Changes
- NEW: Module added to confirm hover states on buttons, links and cards

## Version 1.9.2 Changes
- FIX: Store h1 analysis results in db

## Version 1.9.1 Changes
- FIX: Resolve issue with h1 heading analysis

## Version 1.9.0 Changes
- NEW: Added new module for checking navigation font sizes

## Version 1.8.0 Changes
- NEW: Added new module for checking clickable links

## Version 1.7.1 Changes
- Update: recommended dynamic year option

## Version 1.7.0 Changes
- Update: Improved handling for H1 headings analysis

## Version 1.6.0 Changes
- Update: Minor design tweaks
- Fix: Performance improvements
- New: Compact view mode
- New: Global caching system in website optimiser settings

## Version 1.5.0 Changes
- Fix issue with email report

## Version 1.4.0 Changes
- Option to bulk update image alt descriptions

## Version 1.3.0 Changes
- Fix for H1 Headings check

## Version 1.2.0 Changes
- Allow videos under 5MB

## Version 1.1.0 Changes
- Resolve issue with checking for ManageWP connection

## Version 1.0.9 Changes
- Add widget for conversion event tracking

## Version 1.0.8 Changes
- Resolve issue with generating meta description on single edit screen. *Thanks to @ben.grave for identifying this issue

## Version 1.0.7 Changes
- Flags if videos are added to media library

## Version 1.0.6 Changes
- ManageWP check integration

## Version 1.0.5 Changes

- Resolve auto-updater issue

## Version 1.0.4 Changes

- Ability to email optimisation report

## Version 1.0.3 Changes

- Add checks for WooCommerce

## Version 1.0.2 Changes

- Add copyright year check

## Version 1.0.1 Changes

- Change to website optimiser

## Version 1.0.0 Changes

- **BREAKING CHANGE**: Switched from OpenAI to Google Gemini AI
- Removed model selection (now uses Gemini 2.0 Flash automatically)
- Improved API integration and error handling
- Added direct link to Google AI Studio for easy API key access
