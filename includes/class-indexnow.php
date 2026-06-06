<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ateculus_SEO_IndexNow {

	public function __construct() {
		add_action( 'init',      array( $this, 'maybe_serve_key' ), 1 );
		add_action( 'save_post', array( $this, 'maybe_ping' ), 20, 2 );
		add_action( 'wp_ajax_aseo_indexnow_test', array( $this, 'ajax_test_ping' ) );
	}

	public function get_key() {
		$opts = get_option( 'aseo_options', array() );
		if ( empty( $opts['indexnow_key'] ) ) {
			$opts['indexnow_key'] = wp_generate_password( 32, false );
			update_option( 'aseo_options', $opts );
		}
		return $opts['indexnow_key'];
	}

	private function opt( $key, $default = '' ) {
		$opts = get_option( 'aseo_options', array() );
		return $opts[ $key ] ?? $default;
	}

	// Serve /{key}.txt for search engine verification
	public function maybe_serve_key() {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) return;
		$path = wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
		$key  = $this->get_key();
		if ( $path !== '/' . $key . '.txt' ) return;
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo esc_html( $key );
		exit;
	}

	public function maybe_ping( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( wp_is_post_revision( $post_id ) ) return;
		if ( $post->post_status !== 'publish' ) return;
		if ( ! $this->opt( 'indexnow_enabled' ) ) return;

		$public_types = get_post_types( array( 'public' => true ), 'names' );
		if ( ! in_array( $post->post_type, $public_types, true ) ) return;

		$url = get_permalink( $post_id );
		if ( ! $url ) return;

		$this->ping( $url );
	}

	public function ping( $url ) {
		$key          = $this->get_key();
		$key_location = home_url( '/' . $key . '.txt' );

		$api_url = add_query_arg( array(
			'url'         => rawurlencode( $url ),
			'key'         => $key,
			'keyLocation' => rawurlencode( $key_location ),
		), 'https://api.indexnow.org/indexnow' );

		$response = wp_remote_get( $api_url, array(
			'timeout'  => 8,
			'blocking' => true,
			'headers'  => array( 'User-Agent' => 'Ateculus-SEO/' . ATECULUS_SEO_VERSION ),
		) );

		$code = is_wp_error( $response ) ? 'error' : wp_remote_retrieve_response_code( $response );

		update_option( 'aseo_indexnow_last_ping', array(
			'url'  => $url,
			'time' => current_time( 'mysql' ),
			'code' => $code,
		), false );

		return $code;
	}

	public function ajax_test_ping() {
		check_ajax_referer( 'aseo_indexnow_test', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
		$code = $this->ping( home_url( '/' ) );
		if ( $code === 200 || $code === 202 ) {
			wp_send_json_success( 'Ping accepted (HTTP ' . $code . ').' );
		} elseif ( $code === 'error' ) {
			wp_send_json_error( 'Request failed — check your server can make outbound HTTP requests.' );
		} else {
			wp_send_json_error( 'Unexpected response: HTTP ' . $code . '.' );
		}
	}
}
