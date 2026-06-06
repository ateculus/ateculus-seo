<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ateculus_SEO_FAQ_Schema {

	private static $current_questions = array();
	private static $current_answers   = array();
	private static $in_faq            = false;

	public function __construct() {
		add_shortcode( 'aseo_faq',      array( $this, 'render_faq' ) );
		add_shortcode( 'aseo_question', array( $this, 'render_question' ) );
		add_shortcode( 'aseo_answer',   array( $this, 'render_answer' ) );
	}

	public function render_question( $atts, $content = '' ) {
		if ( self::$in_faq ) {
			self::$current_questions[] = do_shortcode( $content );
			return '';
		}
		return '<h3 class="aseo-faq-question">' . wp_kses_post( do_shortcode( $content ) ) . '</h3>';
	}

	public function render_answer( $atts, $content = '' ) {
		if ( self::$in_faq ) {
			self::$current_answers[] = do_shortcode( $content );
			return '';
		}
		return '<div class="aseo-faq-answer">' . wp_kses_post( do_shortcode( $content ) ) . '</div>';
	}

	public function render_faq( $atts, $content = '' ) {
		self::$in_faq            = true;
		self::$current_questions = array();
		self::$current_answers   = array();

		do_shortcode( $content );

		self::$in_faq = false;

		$questions = self::$current_questions;
		$answers   = self::$current_answers;
		$count     = min( count( $questions ), count( $answers ) );

		if ( $count === 0 ) return '';

		$html       = '<div class="aseo-faq">';
		$schema_items = array();

		for ( $i = 0; $i < $count; $i++ ) {
			$q = wp_kses_post( $questions[ $i ] );
			$a = wp_kses_post( $answers[ $i ] );
			$q_text = wp_strip_all_tags( $q );
			$a_text = wp_strip_all_tags( $a );

			$html .= '<div class="aseo-faq-item">';
			$html .= '<h3 class="aseo-faq-question">' . $q . '</h3>';
			$html .= '<div class="aseo-faq-answer">' . $a . '</div>';
			$html .= '</div>';

			$schema_items[] = array(
				'@type' => 'Question',
				'name'  => $q_text,
				'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $a_text ),
			);
		}

		$html .= '</div>';

		$post_id = get_the_ID();
		$schema  = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'name'       => get_the_title( $post_id ),
			'url'        => get_permalink( $post_id ),
			'mainEntity' => $schema_items,
		);

		$html .= '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';

		return $html;
	}
}
