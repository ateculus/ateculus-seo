<?php
/**
 * Plugin Name: Ateculus-SEO
 * Plugin URI:  https://ateculus.com
 * Description: Lightweight SEO tools for posts/pages, XML sitemaps, redirects, schema, and analytics integration.
 * Version:     1.3.0
 * Requires at least: 6.0
 * Tested up to:      7.0
 * Requires PHP:      8.0
 * Author:      Ateculus
 * Author URI:  https://ateculus.com
 * License:     Ateculus Source License 1.0
 * Text Domain: ateculus-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ATECULUS_SEO_VERSION',    '1.3.0' );
define( 'ATECULUS_SEO_DB_VERSION', '1.0' );
define( 'ATECULUS_SEO_PATH',       plugin_dir_path( __FILE__ ) );
define( 'ATECULUS_SEO_URL',        plugin_dir_url( __FILE__ ) );

// Utilities (load first — other classes depend on these)
require_once ATECULUS_SEO_PATH . 'includes/class-db.php';
require_once ATECULUS_SEO_PATH . 'includes/class-score.php';
require_once ATECULUS_SEO_PATH . 'includes/class-readability.php';

// Core features
require_once ATECULUS_SEO_PATH . 'includes/class-frontend.php';
require_once ATECULUS_SEO_PATH . 'includes/class-meta-box.php';
require_once ATECULUS_SEO_PATH . 'includes/class-settings.php';

// Sitemaps & robots
require_once ATECULUS_SEO_PATH . 'includes/class-sitemap.php';
require_once ATECULUS_SEO_PATH . 'includes/class-robots-editor.php';

// Redirects & 404 (order matters — redirects at priority 1, 404 monitor at priority 5)
require_once ATECULUS_SEO_PATH . 'includes/class-redirects.php';
require_once ATECULUS_SEO_PATH . 'includes/class-404-monitor.php';

// Schema
require_once ATECULUS_SEO_PATH . 'includes/class-faq-schema.php';
require_once ATECULUS_SEO_PATH . 'includes/class-breadcrumbs.php';
require_once ATECULUS_SEO_PATH . 'includes/class-organization-schema.php';
require_once ATECULUS_SEO_PATH . 'includes/class-woo-schema.php';
require_once ATECULUS_SEO_PATH . 'includes/class-howto-schema.php';
require_once ATECULUS_SEO_PATH . 'includes/class-event-schema.php';
require_once ATECULUS_SEO_PATH . 'includes/class-video-schema.php';
require_once ATECULUS_SEO_PATH . 'includes/class-toc.php';

// Analytics
require_once ATECULUS_SEO_PATH . 'includes/class-ga-gtm.php';

// IndexNow
require_once ATECULUS_SEO_PATH . 'includes/class-indexnow.php';

// AI SEO Suggestions
require_once ATECULUS_SEO_PATH . 'includes/class-ai-suggest.php';

// Admin tools
require_once ATECULUS_SEO_PATH . 'includes/class-bulk-editor.php';
require_once ATECULUS_SEO_PATH . 'includes/class-importer.php';
require_once ATECULUS_SEO_PATH . 'includes/class-dashboard-widget.php';

// Activation hook
register_activation_hook( __FILE__, 'ateculus_seo_activate' );
function ateculus_seo_activate() {
	Ateculus_SEO_DB::install();
	// Register rewrite rules before flushing
	$sitemap = new Ateculus_SEO_Sitemap();
	$sitemap->add_rewrite_rules();
	flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

// Run DB upgrade on plugin update without re-activation
add_action( 'plugins_loaded', function () {
	if ( get_option( 'aseo_db_version' ) !== ATECULUS_SEO_DB_VERSION ) {
		Ateculus_SEO_DB::install();
	}
} );

new Ateculus_SEO_Settings();
new Ateculus_SEO_Dashboard_Widget();

add_action( 'plugins_loaded', function () {
	new Ateculus_SEO_Frontend();
	new Ateculus_SEO_Meta_Box();
	new Ateculus_SEO_Sitemap();
	new Ateculus_SEO_Robots_Editor();
	new Ateculus_SEO_Redirects();
	new Ateculus_SEO_404_Monitor();
	new Ateculus_SEO_FAQ_Schema();
	new Ateculus_SEO_Breadcrumbs();
	new Ateculus_SEO_Organization_Schema();
	new Ateculus_SEO_Woo_Schema();
	new Ateculus_SEO_HowTo_Schema();
	new Ateculus_SEO_Event_Schema();
	new Ateculus_SEO_Video_Schema();
	new Ateculus_SEO_TOC();
	new Ateculus_SEO_GA_GTM();
	new Ateculus_SEO_Bulk_Editor();
	new Ateculus_SEO_Importer();
	new Ateculus_SEO_IndexNow();
	new Ateculus_SEO_AI_Suggest();
} );
