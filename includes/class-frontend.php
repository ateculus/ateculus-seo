<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ateculus_SEO_Frontend {

	public function __construct() {
		add_action( 'wp_head',              array( $this, 'output_meta_tags' ), 1 );
		add_action( 'wp_head',              array( $this, 'output_google_verify' ), 1 );
		add_filter( 'document_title_parts', array( $this, 'filter_title' ), 10 );
		add_filter( 'wp_robots',            array( $this, 'filter_robots' ), 10 );
		add_action( 'wp_enqueue_scripts',   array( $this, 'enqueue_styles' ) );
	}

	public function enqueue_styles() {
		wp_enqueue_style( 'ateculus-seo-frontend', ATECULUS_SEO_URL . 'public/css/seo-frontend.css', array(), ATECULUS_SEO_VERSION );
		$toc_css    = '';
		$toc_bg     = $this->opt( 'toc_bg', '' );
		$toc_color  = $this->opt( 'toc_color', '' );
		if ( $toc_bg )    $toc_css .= 'background:' . sanitize_hex_color( $toc_bg ) . ';';
		if ( $toc_color ) $toc_css .= 'color:' . sanitize_hex_color( $toc_color ) . ';';

		// Border: distinguish "never set" (use CSS default) from "cleared" (remove border)
		$opts       = get_option( 'aseo_options', array() );
		$toc_border = array_key_exists( 'toc_border', $opts ) ? $opts['toc_border'] : null;
		if ( $toc_border === '' ) {
			$toc_css .= 'border:none;';
		} elseif ( $toc_border ) {
			$toc_css .= 'border-color:' . sanitize_hex_color( $toc_border ) . ';';
		}

		if ( $toc_css ) {
			wp_add_inline_style( 'ateculus-seo-frontend', '.aseo-toc{' . $toc_css . '}.aseo-toc-title,.aseo-toc-list li,.aseo-toc-list a{color:inherit;}' );
		}
	}

	private function get_meta( $post_id, $key ) {
		return get_post_meta( $post_id, '_aseo_' . $key, true );
	}

	private function opt( $key, $default = '' ) {
		$opts = get_option( 'aseo_options', array() );
		return $opts[ $key ] ?? $default;
	}

	public function filter_title( $parts ) {
		if ( is_front_page() ) {
			$home_title = $this->opt( 'home_title' );
			if ( $home_title ) $parts['title'] = $home_title;
			return $parts;
		}
		if ( ! is_singular() ) return $parts;
		$seo_title = $this->get_meta( get_queried_object_id(), 'title' );
		if ( $seo_title ) $parts['title'] = $seo_title;
		return $parts;
	}

	public function filter_robots( $robots ) {
		if ( ! is_singular() ) return $robots;
		$post_id  = get_queried_object_id();
		if ( $this->get_meta( $post_id, 'noindex' ) ) {
			$robots['noindex'] = true;
			unset( $robots['index'] );
		}
		if ( $this->get_meta( $post_id, 'nofollow' ) ) {
			$robots['nofollow'] = true;
			unset( $robots['follow'] );
		}
		return $robots;
	}

	public function output_google_verify() {
		$code = $this->opt( 'google_verify' );
		if ( $code ) {
			echo '<meta name="google-site-verification" content="' . esc_attr( $code ) . '" />' . "\n";
		}
	}

	public function output_meta_tags() {
		if ( is_front_page() ) {
			$this->output_home_tags();
			return;
		}
		if ( is_singular() ) {
			$this->output_singular_tags();
			return;
		}
		if ( is_category() || is_tag() || is_tax() ) {
			$this->output_archive_tags();
		}
	}

	private function output_home_tags() {
		$desc  = $this->opt( 'home_description' );
		$title = $this->opt( 'home_title', get_bloginfo( 'name' ) );
		$url   = home_url( '/' );
		$image = $this->opt( 'default_og_image' );

		echo '<link rel="canonical" href="' . esc_url( $url ) . '" />' . "\n";
		if ( $desc ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . '" />' . "\n";
		}
		$this->output_og( $title, $desc, $url, $image, 'website' );
		$this->output_twitter( $title, $desc, $image );

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'WebSite',
			'name'     => get_bloginfo( 'name' ),
			'url'      => $url,
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	private function output_singular_tags() {
		$post_id     = get_queried_object_id();
		$post        = get_post( $post_id );
		$seo_title   = $this->get_meta( $post_id, 'title' ) ?: get_the_title( $post_id );
		$description = $this->get_meta( $post_id, 'description' )
		               ?: wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
		$canonical   = $this->get_meta( $post_id, 'canonical' ) ?: get_permalink( $post_id );

		// OG image: post-specific → featured image → default
		$og_image = $this->get_meta( $post_id, 'og_image' );
		if ( ! $og_image && has_post_thumbnail( $post_id ) ) {
			$og_image = get_the_post_thumbnail_url( $post_id, 'large' );
		}
		if ( ! $og_image ) {
			$og_image = $this->opt( 'default_og_image' );
		}

		$keywords = $this->get_meta( $post_id, 'keywords' );

		echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
		if ( $description ) {
			echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
		}
		if ( $keywords ) {
			echo '<meta name="keywords" content="' . esc_attr( $keywords ) . '" />' . "\n";
		}
		$this->output_og( $seo_title, $description, $canonical, $og_image, 'article' );
		$this->output_twitter( $seo_title, $description, $og_image );

		// JSON-LD Article (skip on WooCommerce product pages)
		if ( ! apply_filters( 'aseo_skip_article_schema', false ) ) {
			$schema = array(
				'@context'      => 'https://schema.org',
				'@type'         => 'Article',
				'headline'      => $seo_title,
				'url'           => $canonical,
				'datePublished' => get_post_time( 'c', true, $post_id ),
				'dateModified'  => get_post_modified_time( 'c', true, $post_id ),
				'author'        => array(
					'@type' => 'Person',
					'name'  => get_the_author_meta( 'display_name', $post->post_author ),
				),
			);
			if ( $og_image ) $schema['image'] = $og_image;
			echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
		}
	}

	private function output_archive_tags() {
		$term      = get_queried_object();
		$title     = $term->name ?? '';
		$desc      = $term->description ?? '';
		$url       = get_term_link( $term );
		$image     = $this->opt( 'default_og_image' );

		if ( is_wp_error( $url ) ) return;

		echo '<link rel="canonical" href="' . esc_url( $url ) . '" />' . "\n";
		if ( $desc ) {
			echo '<meta name="description" content="' . esc_attr( wp_trim_words( $desc, 30 ) ) . '" />' . "\n";
		}
		$this->output_og( $title, $desc, $url, $image, 'website' );
		$this->output_twitter( $title, $desc, $image );
	}

	private function output_og( $title, $desc, $url, $image, $type = 'article' ) {
		$fb_app_id = $this->opt( 'fb_app_id' );
		echo '<meta property="og:type"      content="' . esc_attr( $type ) . '" />' . "\n";
		echo '<meta property="og:title"     content="' . esc_attr( $title ) . '" />' . "\n";
		echo '<meta property="og:url"       content="' . esc_url( $url ) . '" />' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";
		if ( $desc ) {
			echo '<meta property="og:description" content="' . esc_attr( $desc ) . '" />' . "\n";
		}
		if ( $image ) {
			echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
		}
		if ( $fb_app_id ) {
			echo '<meta property="fb:app_id" content="' . esc_attr( $fb_app_id ) . '" />' . "\n";
		}
	}

	private function output_twitter( $title, $desc, $image ) {
		$handle    = $this->opt( 'twitter_handle' );
		$card_type = $image ? 'summary_large_image' : 'summary';
		echo '<meta name="twitter:card"  content="' . esc_attr( $card_type ) . '" />' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . "\n";
		if ( $desc ) {
			echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '" />' . "\n";
		}
		if ( $image ) {
			echo '<meta name="twitter:image" content="' . esc_url( $image ) . '" />' . "\n";
		}
		if ( $handle ) {
			echo '<meta name="twitter:site" content="@' . esc_attr( $handle ) . '" />' . "\n";
		}
	}
}
