<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ateculus_SEO_Organization_Schema {

	public function __construct() {
		add_action( 'wp_head', array( $this, 'output' ), 5 );
	}

	private function opt( $key, $default = '' ) {
		$opts = get_option( 'aseo_options', array() );
		return $opts[ $key ] ?? $default;
	}

	public function output() {
		if ( ! is_front_page() ) return;

		$name = $this->opt( 'org_name', get_bloginfo( 'name' ) );
		if ( ! $name ) return;

		$type = $this->opt( 'org_type', 'Organization' );

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => $type,
			'name'     => $name,
			'url'      => home_url( '/' ),
		);

		if ( $logo = $this->opt( 'org_logo' ) ) {
			$schema['logo'] = array( '@type' => 'ImageObject', 'url' => $logo );
		}

		if ( $phone = $this->opt( 'org_phone' ) ) $schema['telephone'] = $phone;
		if ( $email = $this->opt( 'org_email' ) ) $schema['email']     = $email;

		$addr_fields = array_filter( array(
			'streetAddress'   => $this->opt( 'org_address' ),
			'addressLocality' => $this->opt( 'org_city' ),
			'addressRegion'   => $this->opt( 'org_state' ),
			'postalCode'      => $this->opt( 'org_zip' ),
		) );
		if ( ! empty( $addr_fields ) ) {
			$schema['address'] = array_merge(
				array( '@type' => 'PostalAddress' ),
				$addr_fields,
				array( 'addressCountry' => $this->opt( 'org_country', 'US' ) )
			);
		}

		// Opening hours specification
		$day_map = array(
			'mon' => 'Monday',
			'tue' => 'Tuesday',
			'wed' => 'Wednesday',
			'thu' => 'Thursday',
			'fri' => 'Friday',
			'sat' => 'Saturday',
			'sun' => 'Sunday',
		);
		$hours_spec = array();
		foreach ( $day_map as $key => $schema_day ) {
			$closed = $this->opt( 'org_hours_' . $key . '_closed' );
			$open   = $this->opt( 'org_hours_' . $key . '_open' );
			$close  = $this->opt( 'org_hours_' . $key . '_close' );
			if ( $closed ) {
				// Explicitly closed — no entry (absence implies closed)
				continue;
			}
			if ( $open && $close ) {
				$hours_spec[] = array(
					'@type'      => 'OpeningHoursSpecification',
					'dayOfWeek'  => 'https://schema.org/' . $schema_day,
					'opens'      => $open,
					'closes'     => $close,
				);
			}
		}
		if ( ! empty( $hours_spec ) ) {
			$schema['openingHoursSpecification'] = $hours_spec;
		}

		// Build sameAs from social handles
		$same_as = array();
		if ( $tw  = $this->opt( 'twitter_handle' ) ) $same_as[] = 'https://twitter.com/' . $tw;
		if ( $fb  = $this->opt( 'org_facebook' )   ) $same_as[] = $fb;
		if ( $li  = $this->opt( 'org_linkedin' )    ) $same_as[] = $li;
		if ( $ig  = $this->opt( 'org_instagram' )   ) $same_as[] = $ig;
		if ( $yt  = $this->opt( 'org_youtube' )     ) $same_as[] = $yt;
		if ( ! empty( $same_as ) ) $schema['sameAs'] = $same_as;

		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}
}
