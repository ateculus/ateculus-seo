<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ateculus_SEO_Event_Schema {

	public function __construct() {
		add_shortcode( 'aseo_event', array( $this, 'event_shortcode' ) );
	}

	public function event_shortcode( $atts, $content = '' ) {
		$atts = shortcode_atts( array(
			'name'        => '',
			'start'       => '',
			'end'         => '',
			'location'    => '',
			'address'     => '',
			'organizer'   => '',
			'description' => '',
			'image'       => '',
			'url'         => '',
			'status'      => 'EventScheduled',
			'attendance'  => 'OfflineEventAttendanceMode',
		), $atts );

		if ( ! $atts['name'] || ! $atts['start'] ) return '';

		$schema = array(
			'@context'            => 'https://schema.org',
			'@type'               => 'Event',
			'name'                => $atts['name'],
			'startDate'           => $atts['start'],
			'eventStatus'         => 'https://schema.org/' . $atts['status'],
			'eventAttendanceMode' => 'https://schema.org/' . $atts['attendance'],
		);

		if ( $atts['end'] )         $schema['endDate']     = $atts['end'];
		if ( $atts['description'] ) $schema['description'] = $atts['description'];
		if ( $atts['url'] )         $schema['url']         = $atts['url'];
		if ( $atts['image'] )       $schema['image']       = $atts['image'];

		if ( $atts['location'] ) {
			$loc = array( '@type' => 'Place', 'name' => $atts['location'] );
			if ( $atts['address'] ) {
				$loc['address'] = array( '@type' => 'PostalAddress', 'streetAddress' => $atts['address'] );
			}
			$schema['location'] = $loc;
		} elseif ( strpos( $atts['attendance'], 'Online' ) !== false ) {
			$schema['location'] = array(
				'@type' => 'VirtualLocation',
				'url'   => $atts['url'] ?: get_permalink(),
			);
		}

		if ( $atts['organizer'] ) {
			$schema['organizer'] = array(
				'@type' => 'Organization',
				'name'  => $atts['organizer'],
				'url'   => home_url( '/' ),
			);
		}

		$json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		add_action( 'wp_footer', function() use ( $json ) {
			echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
		} );

		// Visual event card
		$start_ts = strtotime( $atts['start'] );
		$date_fmt = $start_ts ? date_i18n( 'F j, Y', $start_ts ) : esc_html( $atts['start'] );
		$time_fmt = $start_ts ? date_i18n( 'g:i A', $start_ts )  : '';

		$html  = '<div class="aseo-event-card">';
		$html .= '<div class="aseo-event-name">' . esc_html( $atts['name'] ) . '</div>';
		$html .= '<div class="aseo-event-meta"><span class="aseo-event-date">' . $date_fmt;
		if ( $time_fmt ) $html .= ' &middot; ' . $time_fmt;
		$html .= '</span>';
		if ( $atts['location'] ) {
			$html .= ' &middot; <span class="aseo-event-loc">' . esc_html( $atts['location'] ) . '</span>';
		}
		$html .= '</div>';
		if ( $atts['description'] ) {
			$html .= '<p class="aseo-event-desc">' . esc_html( $atts['description'] ) . '</p>';
		}
		if ( $atts['url'] ) {
			$html .= '<a href="' . esc_url( $atts['url'] ) . '" class="aseo-event-link">More Info / Register &rarr;</a>';
		}
		$html .= '</div>';
		return $html;
	}
}
