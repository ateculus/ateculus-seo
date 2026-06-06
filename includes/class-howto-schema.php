<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ateculus_SEO_HowTo_Schema {

	private static $steps_buffer = array();

	public function __construct() {
		add_shortcode( 'aseo_howto', array( $this, 'howto_shortcode' ) );
		add_shortcode( 'aseo_step',  array( $this, 'step_shortcode' ) );
	}

	public function step_shortcode( $atts, $content = '' ) {
		$atts = shortcode_atts( array(
			'name'  => '',
			'text'  => '',
			'image' => '',
		), $atts );

		$text = $atts['text'] ?: trim( wp_strip_all_tags( do_shortcode( $content ) ) );

		self::$steps_buffer[] = array(
			'name'  => $atts['name'],
			'text'  => $text,
			'image' => $atts['image'],
		);

		$num   = count( self::$steps_buffer );
		$html  = '<div class="aseo-howto-step">';
		$html .= '<div class="aseo-howto-step-num">' . $num . '</div>';
		$html .= '<div class="aseo-howto-step-body">';
		if ( $atts['name'] ) {
			$html .= '<span class="aseo-howto-step-name">' . esc_html( $atts['name'] ) . '</span>';
		}
		if ( $text ) {
			$html .= '<p class="aseo-howto-step-text">' . esc_html( $text ) . '</p>';
		}
		if ( $atts['image'] ) {
			$html .= '<img src="' . esc_url( $atts['image'] ) . '" class="aseo-howto-step-img" alt="' . esc_attr( $atts['name'] ) . '">';
		}
		$html .= '</div></div>';
		return $html;
	}

	public function howto_shortcode( $atts, $content = '' ) {
		self::$steps_buffer = array();

		$atts = shortcode_atts( array(
			'title'       => '',
			'description' => '',
			'image'       => '',
			'total_time'  => '',
		), $atts );

		$inner = do_shortcode( $content );

		if ( ! empty( self::$steps_buffer ) ) {
			$schema_steps = array();
			foreach ( self::$steps_buffer as $i => $step ) {
				$s = array(
					'@type'    => 'HowToStep',
					'position' => $i + 1,
					'name'     => $step['name'] ?: ( 'Step ' . ( $i + 1 ) ),
					'text'     => $step['text'],
				);
				if ( $step['image'] ) {
					$s['image'] = array( '@type' => 'ImageObject', 'url' => $step['image'] );
				}
				$schema_steps[] = $s;
			}

			$schema = array(
				'@context' => 'https://schema.org',
				'@type'    => 'HowTo',
				'name'     => $atts['title'] ?: get_the_title(),
				'step'     => $schema_steps,
			);
			if ( $atts['description'] ) $schema['description'] = $atts['description'];
			if ( $atts['image'] )       $schema['image']       = array( '@type' => 'ImageObject', 'url' => $atts['image'] );
			if ( $atts['total_time'] )  $schema['totalTime']   = $atts['total_time'];

			$json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			add_action( 'wp_footer', function() use ( $json ) {
				echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
			} );
		}

		$html  = '<div class="aseo-howto">';
		if ( $atts['title'] ) {
			$html .= '<h3 class="aseo-howto-title">' . esc_html( $atts['title'] ) . '</h3>';
		}
		if ( $atts['description'] ) {
			$html .= '<p class="aseo-howto-desc">' . esc_html( $atts['description'] ) . '</p>';
		}
		$html .= '<div class="aseo-howto-steps">' . $inner . '</div>';
		$html .= '</div>';
		return $html;
	}
}
