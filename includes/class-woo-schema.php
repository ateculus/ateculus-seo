<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ateculus_SEO_Woo_Schema {

	public function __construct() {
		if ( ! class_exists( 'WooCommerce' ) ) return;
		add_action( 'wp_head', array( $this, 'output' ), 5 );
		add_filter( 'aseo_skip_article_schema', array( $this, 'skip_on_product' ) );
	}

	public function skip_on_product( $skip ) {
		return is_singular( 'product' ) ? true : $skip;
	}

	public function output() {
		if ( ! is_singular( 'product' ) ) return;

		$product = wc_get_product( get_queried_object_id() );
		if ( ! $product ) return;

		$schema = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Product',
			'name'        => $product->get_name(),
			'description' => wp_strip_all_tags( $product->get_description() ?: $product->get_short_description() ),
			'sku'         => $product->get_sku(),
			'url'         => get_permalink( $product->get_id() ),
		);

		$image_id = $product->get_image_id();
		if ( $image_id ) {
			$schema['image'] = wp_get_attachment_url( $image_id );
		}

		$schema['offers'] = array(
			'@type'         => 'Offer',
			'price'         => $product->get_price(),
			'priceCurrency' => get_woocommerce_currency(),
			'availability'  => $product->is_in_stock()
				? 'https://schema.org/InStock'
				: 'https://schema.org/OutOfStock',
			'url'           => get_permalink( $product->get_id() ),
		);

		// Aggregate rating
		$count   = $product->get_review_count();
		$average = $product->get_average_rating();
		if ( $count > 0 ) {
			$schema['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => $average,
				'reviewCount' => $count,
			);
		}

		// Brand
		$brand_terms = get_the_terms( $product->get_id(), 'product_brand' );
		if ( $brand_terms && ! is_wp_error( $brand_terms ) ) {
			$schema['brand'] = array( '@type' => 'Brand', 'name' => $brand_terms[0]->name );
		}

		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}
}
