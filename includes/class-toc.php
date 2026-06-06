<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ateculus_SEO_TOC {

	public function __construct() {
		add_shortcode( 'aseo_toc',    array( $this, 'toc_shortcode' ) );
		add_filter( 'the_content',   array( $this, 'add_heading_anchors' ), 8 );
	}

	// Inject id attributes on h2/h3 headings when [aseo_toc] is present
	public function add_heading_anchors( $content ) {
		if ( ! is_singular() || strpos( $content, '[aseo_toc' ) === false ) return $content;

		$used    = array();
		$content = preg_replace_callback(
			'/<(h[23])([^>]*)>(.*?)<\/(h[23])>/is',
			function( $m ) use ( &$used ) {
				// Skip if id already present
				if ( preg_match( '/\bid=["\']/', $m[2] ) ) return $m[0];
				$base = sanitize_title( wp_strip_all_tags( $m[3] ) );
				$id   = $base;
				$n    = 2;
				while ( isset( $used[ $id ] ) ) { $id = $base . '-' . $n++; }
				$used[ $id ] = true;
				return '<' . $m[1] . $m[2] . ' id="' . esc_attr( $id ) . '">' . $m[3] . '</' . $m[1] . '>';
			},
			$content
		);
		return $content;
	}

	public function toc_shortcode( $atts ) {
		global $post;
		if ( ! $post ) return '';

		$atts = shortcode_atts( array(
			'title'        => 'Table of Contents',
			'min_headings' => '3',
		), $atts );

		if ( ! preg_match_all( '/<(h[23])([^>]*)>(.*?)<\/(h[23])>/is', $post->post_content, $m ) ) return '';

		$headings = array();
		$used     = array();
		foreach ( $m[1] as $i => $tag ) {
			$text = wp_strip_all_tags( $m[3][ $i ] );
			$base = sanitize_title( $text );
			$id   = $base;
			$n    = 2;
			while ( isset( $used[ $id ] ) ) { $id = $base . '-' . $n++; }
			$used[ $id ] = true;
			$headings[]  = array( 'tag' => $tag, 'text' => $text, 'id' => $id );
		}

		if ( count( $headings ) < (int) $atts['min_headings'] ) return '';

		// ItemList schema
		$items = array();
		foreach ( $headings as $i => $h ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => $h['text'],
				'url'      => get_permalink() . '#' . $h['id'],
			);
		}
		$schema = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'ItemList',
			'name'            => get_the_title(),
			'numberOfItems'   => count( $items ),
			'itemListElement' => $items,
		);
		$json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		add_action( 'wp_footer', function() use ( $json ) {
			echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
		} );

		// Render TOC
		$html  = '<nav class="aseo-toc" aria-label="Table of Contents">';
		if ( $atts['title'] ) {
			$html .= '<p class="aseo-toc-title">' . esc_html( $atts['title'] ) . '</p>';
		}
		$html .= '<ol class="aseo-toc-list">';
		foreach ( $headings as $h ) {
			$class = $h['tag'] === 'h3' ? ' class="aseo-toc-sub"' : '';
			$html .= '<li' . $class . '><a href="#' . esc_attr( $h['id'] ) . '">' . esc_html( $h['text'] ) . '</a></li>';
		}
		$html .= '</ol></nav>';
		return $html;
	}
}
