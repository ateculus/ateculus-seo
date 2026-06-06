<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ateculus_SEO_404_Monitor {

	public function __construct() {
		add_action( 'template_redirect',        array( $this, 'log' ), 5 );
		add_action( 'admin_post_aseo_clear_404', array( $this, 'clear' ) );
	}

	public function log() {
		if ( ! is_404() ) return;

		$url      = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
		$referrer = sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ?? '' ) );

		// Skip admin, cron, and REST
		if ( is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) return;
		if ( str_starts_with( $url, '/wp-' ) ) return;

		if ( strlen( $url ) > 512 ) $url = substr( $url, 0, 512 );
		if ( strlen( $referrer ) > 512 ) $referrer = substr( $referrer, 0, 512 );

		global $wpdb;
		$table = Ateculus_SEO_DB::log_table();

		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE request_url = %s LIMIT 1",
			$url
		) );

		if ( $existing ) {
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$table} SET hit_count = hit_count + 1, last_seen = %s WHERE id = %d",
				current_time( 'mysql' ), $existing
			) );
		} else {
			$wpdb->insert( $table, array(
				'request_url' => $url,
				'referrer'    => $referrer,
				'hit_count'   => 1,
				'last_seen'   => current_time( 'mysql' ),
			) );
		}
	}

	public function clear() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
		check_admin_referer( 'aseo_clear_404' );

		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . Ateculus_SEO_DB::log_table() );

		wp_redirect( add_query_arg( 'cleared', '1', admin_url( 'admin.php?page=ateculus-seo-404' ) ) );
		exit;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		global $wpdb;
		$rows = $wpdb->get_results(
			'SELECT * FROM ' . Ateculus_SEO_DB::log_table() . ' ORDER BY hit_count DESC LIMIT 200'
		);
		?>
		<div class="wrap">
			<h1>404 Monitor</h1>
			<?php if ( isset( $_GET['cleared'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>404 log cleared.</p></div>
			<?php endif; ?>
			<p>Showing top 200 most-hit 404 URLs. Click <strong>Create Redirect</strong> to fix one.</p>

			<table class="widefat striped">
				<thead>
					<tr><th>URL</th><th>Hits</th><th>Last Seen</th><th>Referrer</th><th></th></tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="5">No 404s logged yet.</td></tr>
					<?php else : ?>
					<?php foreach ( $rows as $r ) : ?>
					<tr>
						<td><code><?php echo esc_html( $r->request_url ); ?></code></td>
						<td><?php echo number_format( intval( $r->hit_count ) ); ?></td>
						<td><?php echo esc_html( date( 'Y-m-d', strtotime( $r->last_seen ) ) ); ?></td>
						<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
							<?php echo esc_html( $r->referrer ?: '—' ); ?>
						</td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=ateculus-seo-redirects&from=' . urlencode( $r->request_url ) ) ); ?>"
							   class="button button-small">Create Redirect</a>
						</td>
					</tr>
					<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<br>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="aseo_clear_404" />
				<?php wp_nonce_field( 'aseo_clear_404' ); ?>
				<?php submit_button( 'Clear 404 Log', 'secondary' ); ?>
			</form>
		</div>
		<?php
	}
}
