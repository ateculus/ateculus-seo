<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ateculus_SEO_Breadcrumbs {

	private static $instance;

	public function __construct() {
		add_action( 'wp_head',  array( $this, 'output_schema' ), 4 );
		add_shortcode( 'aseo_breadcrumbs', array( $this, 'shortcode' ) );
	}

	public function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'separator' => ' &rsaquo; ' ), $atts );
		ob_start();
		$this->render_html( array( 'separator' => $atts['separator'] ) );
		return ob_get_clean();
	}

	public static function get_instance() {
		if ( ! self::$instance ) self::$instance = new self();
		return self::$instance;
	}

	public function get_crumbs() {
		$crumbs = array(
			array( 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ) ),
		);

		if ( is_singular() ) {
			$post = get_post();
			if ( $post->post_type === 'post' ) {
				$cats = get_the_category( $post->ID );
				if ( $cats ) {
					$crumbs[] = array(
						'name' => $cats[0]->name,
						'url'  => get_category_link( $cats[0]->term_id ),
					);
				}
			} elseif ( $post->post_type !== 'page' ) {
				$obj = get_post_type_object( $post->post_type );
				if ( $obj ) {
					$crumbs[] = array(
						'name' => $obj->labels->name,
						'url'  => get_post_type_archive_link( $post->post_type ) ?: home_url( '/' ),
					);
				}
			}
			$crumbs[] = array( 'name' => get_the_title(), 'url' => get_permalink() );

		} elseif ( is_category() ) {
			$cat = get_queried_object();
			if ( $cat->parent ) {
				$parent = get_category( $cat->parent );
				$crumbs[] = array( 'name' => $parent->name, 'url' => get_category_link( $parent->term_id ) );
			}
			$crumbs[] = array( 'name' => $cat->name, 'url' => get_category_link( $cat->term_id ) );

		} elseif ( is_tag() ) {
			$tag = get_queried_object();
			$crumbs[] = array( 'name' => $tag->name, 'url' => get_tag_link( $tag->term_id ) );

		} elseif ( is_author() ) {
			$crumbs[] = array( 'name' => get_the_author_meta( 'display_name', get_queried_object_id() ), 'url' => get_author_posts_url( get_queried_object_id() ) );

		} elseif ( is_post_type_archive() ) {
			$crumbs[] = array( 'name' => post_type_archive_title( '', false ), 'url' => get_post_type_archive_link( get_queried_object()->name ) );

		} elseif ( is_search() ) {
			$crumbs[] = array( 'name' => 'Search: ' . get_search_query(), 'url' => get_search_link() );
		}

		return $crumbs;
	}

	public function output_schema() {
		if ( is_front_page() ) return;
		$crumbs = $this->get_crumbs();
		if ( count( $crumbs ) < 2 ) return;

		$items = array();
		foreach ( $crumbs as $i => $crumb ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => $crumb['name'],
				'item'     => $crumb['url'],
			);
		}

		$schema = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		);

		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	public function render_html( $args = array() ) {
		$defaults = array(
			'separator' => ' &rsaquo; ',
			'before'    => '<nav class="aseo-breadcrumbs" aria-label="Breadcrumb"><ol>',
			'after'     => '</ol></nav>',
		);
		$args   = wp_parse_args( $args, $defaults );
		$crumbs = $this->get_crumbs();
		$last   = count( $crumbs ) - 1;

		echo $args['before'];
		foreach ( $crumbs as $i => $crumb ) {
			$is_last = ( $i === $last );
			echo '<li>';
			if ( ! $is_last ) {
				echo '<a href="' . esc_url( $crumb['url'] ) . '">' . esc_html( $crumb['name'] ) . '</a>';
				echo $args['separator'];
			} else {
				echo '<span aria-current="page">' . esc_html( $crumb['name'] ) . '</span>';
			}
			echo '</li>';
		}
		echo $args['after'];
	}
}

function ateculus_seo_breadcrumbs( $args = array() ) {
	Ateculus_SEO_Breadcrumbs::get_instance()->render_html( $args );
}
