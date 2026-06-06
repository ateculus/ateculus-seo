<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ateculus_SEO_Video_Schema {

	public function __construct() {
		add_action( 'wp_head', array( $this, 'output' ), 6 );
	}

	public function output() {
		if ( ! is_singular() ) return;

		$post = get_post();
		if ( ! $post ) return;

		$videos = $this->find_youtube( $post->post_content );
		if ( empty( $videos ) ) return;

		$title    = get_the_title( $post );
		$desc     = wp_trim_words( get_the_excerpt() ?: wp_strip_all_tags( $post->post_content ), 30 );
		$uploaded = get_the_date( 'c', $post );

		foreach ( $videos as $video ) {
			$schema = array(
				'@context'     => 'https://schema.org',
				'@type'        => 'VideoObject',
				'name'         => $title,
				'description'  => $desc,
				'thumbnailUrl' => 'https://i.ytimg.com/vi/' . $video['id'] . '/maxresdefault.jpg',
				'uploadDate'   => $uploaded,
				'embedUrl'     => 'https://www.youtube.com/embed/' . $video['id'],
				'contentUrl'   => 'https://www.youtube.com/watch?v=' . $video['id'],
			);
			echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
		}
	}

	private function find_youtube( $content ) {
		$videos = array();
		if ( preg_match_all(
			'/<iframe[^>]+src=["\']https?:\/\/(?:www\.)?youtube(?:-nocookie)?\.com\/embed\/([a-zA-Z0-9_-]+)/i',
			$content,
			$matches
		) ) {
			foreach ( array_unique( $matches[1] ) as $id ) {
				$videos[] = array( 'id' => sanitize_text_field( $id ) );
			}
		}
		return $videos;
	}
}
