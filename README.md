# Ateculus SEO

A lightweight WordPress SEO plugin covering on-page optimization, XML sitemaps, redirects, structured data, and analytics — without the bloat.

## Features

### On-Page SEO
- Per-post SEO meta box with title, meta description, focus keyphrases, noindex toggle, and Open Graph fields
- Live character counters on title and description fields
- SEO score analysis — checks keyphrase presence in title, description, headings, and body
- Readability analysis — flags passive voice, long sentences, and dense paragraphs

### Technical SEO
- XML sitemap index at `/sitemap.xml` with child sitemaps for posts, pages, images, categories, tags, and every public custom post type
- Pings Google on publish
- IndexNow integration — automatically notifies Bing and Yandex on publish/update
- Robots.txt editor — edit directly from the admin without touching the server

### Redirects & 404
- Redirect manager — create 301 and 302 redirects with hit counters
- 404 monitor — logs every 404 hit with URL and timestamp

### Structured Data (Schema)
- FAQ schema
- HowTo schema
- Event schema
- Video schema
- Organization schema (site-wide)
- BreadcrumbList (site-wide)
- WooCommerce Product schema (auto-populated)

### Analytics
- Google Analytics (GA4) snippet injection
- Google Tag Manager container injection

### Admin Tools
- Bulk SEO editor — edit title and description for all posts from a single table
- Yoast & RankMath importer — migrate existing SEO data with one click
- Dashboard widget — quick overview of posts missing titles, descriptions, or focus keyphrases
- Table of contents shortcode — `[aseo_toc]`

## Requirements

- WordPress 6.0+
- PHP 8.0+

## Installation

1. Download the latest ZIP from the [Releases](../../releases) page
2. Go to **Plugins → Add New → Upload Plugin** in your WordPress admin
3. Upload the ZIP and click **Activate Plugin**

## Sitemaps

| URL | Contents |
|-----|----------|
| `/sitemap.xml` | Index — links to all child sitemaps |
| `/sitemap-posts.xml` | All published posts |
| `/sitemap-pages.xml` | All published pages |
| `/sitemap-images.xml` | Images attached to published content |
| `/sitemap-categories.xml` | Category archive pages |
| `/sitemap-tags.xml` | Tag archive pages |
| `/sitemap-{cpt}.xml` | One sitemap per public custom post type |

## License

Free for personal use. Commercial use requires written authorization — see [LICENSE](LICENSE) for full terms.

## Author

Built by [Ateculus](https://ateculus.com)
