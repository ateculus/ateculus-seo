<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ateculus_SEO_Sitemap {

	public function __construct() {
		add_action( 'init',               array( $this, 'add_rewrite_rules' ), 20 );
		add_filter( 'query_vars',         array( $this, 'add_query_vars' ) );
		add_filter( 'redirect_canonical', array( $this, 'no_redirect_sitemaps' ), 10, 2 );
		add_action( 'template_redirect',  array( $this, 'handle_request' ), 10 );
		add_action( 'save_post',          array( $this, 'flush_on_save' ) );
		add_action( 'publish_post',       array( $this, 'ping_google' ) );
	}

	public function add_rewrite_rules() {
		add_rewrite_rule( '^sitemap\.xml/?$',              'index.php?aseo_sitemap=index',      'top' );
		add_rewrite_rule( '^sitemap-posts\.xml/?$',        'index.php?aseo_sitemap=posts',      'top' );
		add_rewrite_rule( '^sitemap-pages\.xml/?$',        'index.php?aseo_sitemap=pages',      'top' );
		add_rewrite_rule( '^sitemap-images\.xml/?$',       'index.php?aseo_sitemap=images',     'top' );
		add_rewrite_rule( '^sitemap-categories\.xml/?$',   'index.php?aseo_sitemap=categories', 'top' );
		add_rewrite_rule( '^sitemap-tags\.xml/?$',         'index.php?aseo_sitemap=tags',       'top' );

		// Dynamic CPT sitemaps
		$cpts = get_post_types( array( 'public' => true, '_builtin' => false ), 'names' );
		foreach ( $cpts as $cpt ) {
			$slug = sanitize_key( $cpt );
			add_rewrite_rule(
				'^sitemap-' . $slug . '\.xml/?$',
				'index.php?aseo_sitemap=cpt_' . $slug,
				'top'
			);
		}
	}

	public function add_query_vars( $vars ) {
		$vars[] = 'aseo_sitemap';
		return $vars;
	}

	public function no_redirect_sitemaps( $redirect_url, $requested_url ) {
		if ( preg_match( '/sitemap[^?]*\.xml/i', $requested_url ) ) {
			return false;
		}
		return $redirect_url;
	}

	public function handle_request() {
		$type = get_query_var( 'aseo_sitemap' );
		if ( ! $type ) return;

		$this->send_headers();

		switch ( $type ) {
			case 'index':      $this->output_index();             break;
			case 'posts':      $this->output_posts();             break;
			case 'pages':      $this->output_pages();             break;
			case 'images':     $this->output_images();            break;
			case 'categories': $this->output_taxonomy( 'category' ); break;
			case 'tags':       $this->output_taxonomy( 'post_tag' ); break;
			default:
				if ( strpos( $type, 'cpt_' ) === 0 ) {
					$cpt = substr( $type, 4 );
					$this->output_cpt( $cpt );
				} else {
					status_header( 404 );
				}
				exit;
		}
	}

	private function send_headers() {
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow', true );
		status_header( 200 );
	}

	private function xml_open() {
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	}

	private function output_index() {
		$this->xml_open();
		echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		$entries = array(
			home_url( '/sitemap-posts.xml' )      => get_lastpostdate( 'gmt' ),
			home_url( '/sitemap-pages.xml' )      => get_lastpostdate( 'gmt', 'page' ),
			home_url( '/sitemap-images.xml' )     => get_lastpostdate( 'gmt' ),
			home_url( '/sitemap-categories.xml' ) => get_lastpostdate( 'gmt' ),
			home_url( '/sitemap-tags.xml' )       => get_lastpostdate( 'gmt' ),
		);

		// CPT sitemaps
		$cpts = get_post_types( array( 'public' => true, '_builtin' => false ), 'names' );
		foreach ( $cpts as $cpt ) {
			$entries[ home_url( '/sitemap-' . $cpt . '.xml' ) ] = get_lastpostdate( 'gmt', $cpt );
		}

		foreach ( $entries as $url => $lastmod ) {
			echo "\t<sitemap>\n";
			echo "\t\t<loc>" . esc_url( $url ) . "</loc>\n";
			if ( $lastmod ) {
				echo "\t\t<lastmod>" . esc_html( date( 'c', strtotime( $lastmod ) ) ) . "</lastmod>\n";
			}
			echo "\t</sitemap>\n";
		}

		echo '</sitemapindex>';
		exit;
	}

	private function output_posts() {
		$posts = $this->cached_posts( 'post' );
		$this->xml_open();
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		foreach ( $posts as $post ) {
			if ( $this->is_noindex( $post->ID ) ) continue;
			$this->url_entry( get_permalink( $post->ID ), $post->post_modified_gmt, 'weekly', '0.7' );
		}
		echo '</urlset>';
		exit;
	}

	private function output_pages() {
		$pages = $this->cached_posts( 'page' );
		$this->xml_open();
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		$this->url_entry( home_url( '/' ), null, 'daily', '1.0' );
		foreach ( $pages as $page ) {
			if ( $this->is_noindex( $page->ID ) ) continue;
			$this->url_entry( get_permalink( $page->ID ), $page->post_modified_gmt, 'monthly', '0.5' );
		}
		echo '</urlset>';
		exit;
	}

	private function output_cpt( $post_type ) {
		if ( ! post_type_exists( $post_type ) ) { status_header( 404 ); exit; }
		$posts = $this->cached_posts( $post_type );
		$this->xml_open();
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		foreach ( $posts as $post ) {
			if ( $this->is_noindex( $post->ID ) ) continue;
			$this->url_entry( get_permalink( $post->ID ), $post->post_modified_gmt, 'weekly', '0.6' );
		}
		echo '</urlset>';
		exit;
	}

	private function output_taxonomy( $taxonomy ) {
		$terms = get_terms( array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
			'number'     => 1000,
		) );
		if ( is_wp_error( $terms ) ) { status_header( 404 ); exit; }

		$this->xml_open();
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		foreach ( $terms as $term ) {
			$link = get_term_link( $term );
			if ( is_wp_error( $link ) ) continue;
			$this->url_entry( $link, null, 'weekly', '0.4' );
		}
		echo '</urlset>';
		exit;
	}

	private function output_images() {
		$posts = $this->cached_posts( 'post' );
		$this->xml_open();
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
		echo '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

		foreach ( $posts as $post ) {
			if ( $this->is_noindex( $post->ID ) ) continue;
			$images = array();

			if ( has_post_thumbnail( $post->ID ) ) {
				$images[] = array(
					'loc'     => get_the_post_thumbnail_url( $post->ID, 'full' ),
					'title'   => get_the_title( $post->ID ),
					'caption' => get_post_field( 'post_excerpt', get_post_thumbnail_id( $post->ID ) ),
				);
			}

			preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $post->post_content, $matches );
			foreach ( $matches[1] as $src ) {
				if ( strpos( $src, home_url() ) === 0 ) {
					$images[] = array( 'loc' => $src, 'title' => '', 'caption' => '' );
				}
			}

			if ( empty( $images ) ) continue;

			echo "\t<url>\n\t\t<loc>" . esc_url( get_permalink( $post->ID ) ) . "</loc>\n";
			foreach ( $images as $img ) {
				echo "\t\t<image:image>\n";
				echo "\t\t\t<image:loc>" . esc_url( $img['loc'] ) . "</image:loc>\n";
				if ( $img['title'] )   echo "\t\t\t<image:title>"   . esc_html( $img['title'] )   . "</image:title>\n";
				if ( $img['caption'] ) echo "\t\t\t<image:caption>" . esc_html( $img['caption'] ) . "</image:caption>\n";
				echo "\t\t</image:image>\n";
			}
			echo "\t</url>\n";
		}

		echo '</urlset>';
		exit;
	}

	private function url_entry( $url, $modified_gmt = null, $changefreq = 'weekly', $priority = '0.5' ) {
		echo "\t<url>\n";
		echo "\t\t<loc>" . esc_url( $url ) . "</loc>\n";
		if ( $modified_gmt ) {
			echo "\t\t<lastmod>" . esc_html( date( 'c', strtotime( $modified_gmt . ' UTC' ) ) ) . "</lastmod>\n";
		}
		echo "\t\t<changefreq>" . esc_html( $changefreq ) . "</changefreq>\n";
		echo "\t\t<priority>" . esc_html( $priority ) . "</priority>\n";
		echo "\t</url>\n";
	}

	private function is_noindex( $post_id ) {
		return get_post_meta( $post_id, '_aseo_noindex', true ) === '1';
	}

	private function cached_posts( $post_type ) {
		$key    = 'aseo_sitemap_' . $post_type;
		$cached = get_transient( $key );
		if ( $cached !== false ) return $cached;

		$posts = get_posts( array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => 1000,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		) );

		set_transient( $key, $posts, HOUR_IN_SECONDS );
		return $posts;
	}

	public function flush_on_save() {
		$cpts   = get_post_types( array( 'public' => true ), 'names' );
		foreach ( $cpts as $cpt ) {
			delete_transient( 'aseo_sitemap_' . $cpt );
		}
	}

	public function ping_google( $post_id ) {
		// Only ping once per hour maximum
		if ( get_transient( 'aseo_pinged_google' ) ) return;
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;

		$sitemap_url = home_url( '/sitemap.xml' );
		wp_remote_get(
			'https://www.google.com/ping?sitemap=' . rawurlencode( $sitemap_url ),
			array( 'timeout' => 3, 'blocking' => false )
		);

		set_transient( 'aseo_pinged_google', 1, HOUR_IN_SECONDS );
	}
}
