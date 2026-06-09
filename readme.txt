=== Ateculus-SEO ===
Contributors: ateculus
Tags: seo, sitemap, meta, open graph, schema, redirects, analytics, breadcrumbs, robots, woocommerce
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Full-featured SEO plugin with meta tools, sitemaps, redirects, schema markup, analytics integration, and a bulk editor — without the bloat.

== Description ==

Ateculus-SEO gives you complete control over how your content appears in search engines and on social media.

**SEO Meta Box (on every post, page, and custom post type)**

* SEO Score (0–100) with actionable improvement tips
* Readability Score (0–100) — checks word count, sentence length, subheadings, paragraph length, and transition word usage
* Focus Keyword — checks keyword presence in title, description, and body content with density analysis
* Custom SEO Title with live character counter
* Meta Description with live character counter
* Live Google-style snippet preview that updates as you type
* Social / Open Graph image picker (falls back to featured image, then global default)
* Canonical URL override per post
* No Index / No Follow robots control per post

**Bulk SEO Editor**

* Edit SEO titles and descriptions for all posts, pages, and custom post types from one screen
* AJAX inline save — update individual rows or use Save All
* Color-coded SEO score column in the Posts and Pages admin lists

**Automatic Front-End Output**

* `<meta name="description">` tag
* `<link rel="canonical">`
* Full Open Graph tags (og:title, og:description, og:image, og:type, og:site_name, fb:app_id)
* Twitter Card tags (summary and summary_large_image) with @handle
* JSON-LD Article schema on posts and pages
* JSON-LD WebSite schema on the homepage
* Archive and taxonomy meta tags (category and tag pages)
* Google Search Console verification meta tag

**XML Sitemaps**

* `/sitemap.xml` — sitemap index
* `/sitemap-posts.xml` — all published posts
* `/sitemap-pages.xml` — all published pages including homepage
* `/sitemap-images.xml` — all post images (featured and inline)
* `/sitemap-categories.xml` — all non-empty categories
* `/sitemap-tags.xml` — all non-empty tags
* `/sitemap-{post-type}.xml` — auto-generated for every public custom post type
* Posts marked No Index are automatically excluded
* Transient-based caching with a one-click flush button
* Auto-pings Google when a post is published (max once per hour)

**301 Redirect Manager**

* Add, edit, and delete 301, 302, and 307 redirects from a simple admin table
* Hit counter tracks how often each redirect is used
* One-click redirect creation directly from the 404 Monitor

**404 Monitor**

* Automatically logs every 404 hit with URL, referrer, and hit count
* View the top 200 most-hit 404s and create redirects from them instantly

**robots.txt Editor**

* Edit your site's robots.txt directly from the WordPress admin
* Warns if the file is not writable or if no physical file exists yet

**Schema Markup**

* FAQ Schema — `[aseo_faq]` shortcode with `[aseo_question]` / `[aseo_answer]` child shortcodes outputs FAQPage JSON-LD + HTML accordion
* BreadcrumbList JSON-LD auto-output on singular posts, archives, and taxonomy pages
* `ateculus_seo_breadcrumbs()` template function for themes to render HTML breadcrumbs
* Organization / LocalBusiness JSON-LD on the homepage — configurable name, logo, address, phone, email, and social profiles
* WooCommerce Product JSON-LD — automatically outputs Product schema with price, availability, SKU, and aggregate rating on product pages (when WooCommerce is active)

**Analytics Integration**

* Google Analytics 4 — paste your Measurement ID (G-XXXXXXXXXX) and the gtag.js snippet is injected automatically
* Google Tag Manager — paste your Container ID (GTM-XXXXXXX) for full GTM head and body snippet output

**Tools**

* Import from Yoast SEO — migrates titles, descriptions, focus keywords, canonical URLs, OG images, and robots settings
* Import from Rank Math — migrates titles, descriptions, focus keywords, canonical URLs, and robots settings
* CSV Export — download all SEO data (title, description, focus keyword, score) for every post and page
* Canonical Issue Checker — lists posts where a custom canonical differs from the actual permalink

**Dashboard Widget**

* At-a-glance SEO health overview on the WordPress dashboard — green/orange/red post counts with a direct link to the Bulk Editor

**Global Settings**

* Title separator character
* Homepage SEO title and meta description
* Default social image (global fallback for posts without a featured image)
* Twitter / X handle
* Facebook App ID
* Google Search Console verification code
* GA4 Measurement ID
* GTM Container ID
* Full Organization / Local Business schema configuration

== Installation ==

1. Upload the `ateculus-seo` folder to `/wp-content/plugins/`.
2. Activate the plugin through **Plugins > Installed Plugins**.
3. Go to **Ateculus-SEO > Settings** to configure global options.
4. Edit any post or page to find the **Ateculus-SEO** meta box below the editor.
5. Go to **Ateculus-SEO > Sitemaps** and submit `sitemap.xml` to Google Search Console.

== Frequently Asked Questions ==

= Will this conflict with Yoast or other SEO plugins? =

Yes — running two SEO plugins together will output duplicate meta tags. Deactivate any other SEO plugin before activating Ateculus-SEO. Use the built-in Yoast or Rank Math importer under **Ateculus-SEO > Tools** to migrate your existing data first.

= Where do I find my sitemaps? =

Visit `yoursite.com/sitemap.xml`. Individual sitemaps are at `/sitemap-posts.xml`, `/sitemap-pages.xml`, `/sitemap-images.xml`, `/sitemap-categories.xml`, `/sitemap-tags.xml`, and `/sitemap-{post-type}.xml` for each custom post type.

= How do I submit the sitemap to Google? =

Go to Google Search Console, select your property, click **Sitemaps** in the left menu, enter `sitemap.xml`, and click Submit.

= Why isn't my sitemap showing up? =

After activation, WordPress may need its rewrite rules flushed. Go to **Settings > Permalinks** and click **Save Changes** without changing anything. This regenerates rewrite rules.

= What is the SEO score based on? =

The score checks for: a focus keyword set, the keyword in the SEO title, the keyword in the meta description, keyword density in content (0.5–2.5%), SEO title length (30–60 chars), meta description length (100–160 chars), and a featured image.

= What is the Readability score based on? =

The score checks: word count (300+ is good), average sentence length (under 20 words), use of subheadings (H2/H3), paragraph length (under 150 words per paragraph), and use of transition words (however, therefore, furthermore, etc.).

= Can I control robots per post? =

Yes. In the **Advanced** tab of the SEO meta box you can enable No Index and/or No Follow on any individual post or page. No Index posts are also excluded from sitemaps.

= How do I use the FAQ shortcode? =

Add this to any post or page:

`[aseo_faq]`
`[aseo_question]Your question here?[/aseo_question]`
`[aseo_answer]Your answer here.[/aseo_answer]`
`[/aseo_faq]`

This outputs an HTML accordion and injects FAQPage JSON-LD schema for Google rich results.

= How do I display breadcrumbs in my theme? =

Add `<?php ateculus_seo_breadcrumbs(); ?>` to your theme template where you want breadcrumbs to appear. BreadcrumbList JSON-LD schema is also output automatically in `<head>`.

= Does it work with WooCommerce? =

Yes. When WooCommerce is active, the plugin automatically outputs Product JSON-LD schema on single product pages with price, availability, SKU, and review data.

= How do I migrate from Yoast SEO? =

Go to **Ateculus-SEO > Tools** and click **Import from Yoast**. This copies all Yoast meta fields into Ateculus-SEO without requiring Yoast to be active — the data only needs to exist in the database.

== Screenshots ==

1. SEO meta box on the post edit screen showing score bar, tabs, and snippet preview.
2. Readability tab showing score, stats, and improvement tips.
3. Bulk SEO Editor — inline editing for all posts on one screen.
4. 301 Redirect Manager.
5. 404 Monitor with one-click redirect creation.
6. Sitemaps admin page.
7. Settings page showing analytics and organization schema sections.
8. Dashboard widget showing SEO health overview.

== Changelog ==

= 1.3.0 =
* Added AI Suggestions — one-click auto-fill of focus keyphrases, SEO title, meta description, and meta keywords from post content
* Added Groq AI provider (free, no credit card) with Llama 3.1 8B and Llama 3.3 70B model options
* Added Google Gemini provider with 2.0 Flash, 2.5 Flash, 2.5 Flash Lite, and 2.5 Pro model options
* Added AI Suggestions settings page (Ateculus SEO → AI Suggestions) with provider dropdown and per-provider API key fields
* Suggest with AI button is hidden in the meta box when no API key is configured
* Keyphrases are extracted server-side from article H2/H3 headings — guarantees all suggested keyphrases exist verbatim in the content
* Supports up to 3 comma-separated focus keyphrases; primary keyphrase is placed verbatim in title and description for maximum score impact
* Provider fields (Groq / Gemini) show/hide dynamically based on selected provider

= 1.2.5 =
* Fixed GA4 snippet to use official Google tag format with `<!-- Google tag (gtag.js) -->` comment and multi-line output
* Fixed sitemap canonical redirect conflict — WordPress no longer 301-redirects sitemap URLs
* Made trailing slash optional on all sitemap rewrite rules (`/sitemap.xml` and `/sitemap.xml/` both resolve)

= 1.2 =
* Added Readability Score tab in the SEO meta box (word count, sentence length, subheadings, transition words, paragraph length)
* Added Bulk SEO Editor — inline edit titles and descriptions for all posts from one screen
* Added SEO score column in the Posts and Pages admin list
* Added 301 Redirect Manager with hit counter
* Added 404 Monitor with one-click redirect creation
* Added robots.txt editor
* Added FAQ Schema shortcode ([aseo_faq])
* Added BreadcrumbList JSON-LD schema and ateculus_seo_breadcrumbs() template function
* Added Organization / LocalBusiness JSON-LD schema (configurable in Settings)
* Added WooCommerce Product JSON-LD schema (auto-detected when WooCommerce is active)
* Added Google Analytics 4 and Google Tag Manager snippet injection
* Added Import from Yoast SEO and Rank Math
* Added CSV export of all SEO data
* Added Canonical Issue Checker
* Added Dashboard widget with at-a-glance SEO health overview
* Added taxonomy sitemaps (categories and tags)
* Added automatic sitemap generation for all public custom post types
* Added auto-ping to Google on post publish (max once per hour)
* Added default social image fallback in Settings
* Added archive and taxonomy page meta tag output
* Added WebSite JSON-LD schema on homepage
* Extended SEO meta box to all public custom post types
* Refactored score calculation into shared Ateculus_SEO_Score class
* Created custom DB tables for redirects and 404 logging (auto-installed on activation)

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.2 =
Major feature release. After upgrading, deactivate and reactivate the plugin once to create the new database tables required for the Redirect Manager and 404 Monitor. No existing SEO data is affected.

= 1.0.0 =
Initial release — no upgrade steps needed.
