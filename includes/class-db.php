<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ateculus_SEO_DB {

	const VERSION = '1.0';

	public static function install() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$redirects = $wpdb->prefix . 'aseo_redirects';
		dbDelta( "CREATE TABLE {$redirects} (
			id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			source_url VARCHAR(512)        NOT NULL DEFAULT '',
			target_url VARCHAR(512)        NOT NULL DEFAULT '',
			http_code  SMALLINT(4)         NOT NULL DEFAULT 301,
			hit_count  BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME            NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY source_url (source_url(191))
		) {$charset};" );

		$log = $wpdb->prefix . 'aseo_404_log';
		dbDelta( "CREATE TABLE {$log} (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			request_url VARCHAR(512)        NOT NULL DEFAULT '',
			referrer    VARCHAR(512)        NOT NULL DEFAULT '',
			hit_count   BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
			last_seen   DATETIME            NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY request_url (request_url(191))
		) {$charset};" );

		update_option( 'aseo_db_version', self::VERSION );
	}

	public static function redirects_table() {
		global $wpdb;
		return $wpdb->prefix . 'aseo_redirects';
	}

	public static function log_table() {
		global $wpdb;
		return $wpdb->prefix . 'aseo_404_log';
	}
}
