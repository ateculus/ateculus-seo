<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ateculus_SEO_GA_GTM {

	public function __construct() {
		add_action( 'wp_head',      array( $this, 'output_head' ),    2 );
		add_action( 'wp_body_open', array( $this, 'output_gtm_body' ), 1 );
	}

	private function opt( $key ) {
		$opts = get_option( 'aseo_options', array() );
		return $opts[ $key ] ?? '';
	}

	public function output_head() {
		if ( is_admin() ) return;

		$ga4_id = $this->opt( 'ga4_id' );
		$gtm_id = $this->opt( 'gtm_id' );

		if ( $ga4_id ) {
			$ga4_id = esc_js( $ga4_id );
			echo '<!-- Google tag (gtag.js) -->' . "\n";
			echo '<script async src="https://www.googletagmanager.com/gtag/js?id=' . esc_attr( $ga4_id ) . '"></script>' . "\n";
			echo '<script>' . "\n";
			echo '  window.dataLayer = window.dataLayer || [];' . "\n";
			echo '  function gtag(){dataLayer.push(arguments);}' . "\n";
			echo '  gtag(\'js\', new Date());' . "\n";
			echo '  gtag(\'config\', \'' . $ga4_id . '\');' . "\n";
			echo '</script>' . "\n";
		}

		if ( $gtm_id ) {
			$gtm_id = esc_js( $gtm_id );
			echo '<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({\'gtm.start\':new Date().getTime(),event:\'gtm.js\'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!=\'dataLayer\'?\'&l=\'+l:\'\';j.async=true;j.src=\'https://www.googletagmanager.com/gtm.js?id=\'+i+dl;f.parentNode.insertBefore(j,f);})(window,document,\'script\',\'dataLayer\',\'' . $gtm_id . '\');</script>' . "\n";
		}
	}

	public function output_gtm_body() {
		if ( is_admin() ) return;
		$gtm_id = $this->opt( 'gtm_id' );
		if ( ! $gtm_id ) return;
		echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . esc_attr( $gtm_id ) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n";
	}
}
