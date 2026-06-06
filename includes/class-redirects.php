<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ateculus_SEO_Redirects {

	public function __construct() {
		add_action( 'template_redirect',    array( $this, 'handle' ), 1 );
		add_action( 'admin_post_aseo_save_redirect',   array( $this, 'save' ) );
		add_action( 'admin_post_aseo_delete_redirect', array( $this, 'delete' ) );
	}

	public function handle() {
		global $wpdb;
		$path = '/' . ltrim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );

		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT id, target_url, http_code FROM ' . Ateculus_SEO_DB::redirects_table() . ' WHERE source_url = %s LIMIT 1',
			$path
		) );

		if ( ! $row ) return;

		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . Ateculus_SEO_DB::redirects_table() . ' SET hit_count = hit_count + 1 WHERE id = %d',
			$row->id
		) );

		wp_redirect( esc_url_raw( $row->target_url ), intval( $row->http_code ) );
		exit;
	}

	public function save() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
		check_admin_referer( 'aseo_save_redirect' );

		global $wpdb;
		$source = '/' . ltrim( sanitize_text_field( wp_unslash( $_POST['aseo_source'] ?? '' ) ), '/' );
		$target = esc_url_raw( wp_unslash( $_POST['aseo_target'] ?? '' ) );
		$code   = intval( $_POST['aseo_code'] ?? 301 );
		$id     = intval( $_POST['aseo_redirect_id'] ?? 0 );

		if ( ! $source || ! $target || $source === $target ) {
			wp_redirect( add_query_arg( 'error', '1', admin_url( 'admin.php?page=ateculus-seo-redirects' ) ) );
			exit;
		}

		$data = array( 'source_url' => $source, 'target_url' => $target, 'http_code' => $code );

		if ( $id ) {
			$wpdb->update( Ateculus_SEO_DB::redirects_table(), $data, array( 'id' => $id ) );
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$data['hit_count']  = 0;
			$wpdb->insert( Ateculus_SEO_DB::redirects_table(), $data );
		}

		wp_redirect( add_query_arg( 'saved', '1', admin_url( 'admin.php?page=ateculus-seo-redirects' ) ) );
		exit;
	}

	public function delete() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
		check_admin_referer( 'aseo_delete_redirect' );

		global $wpdb;
		$id = intval( $_GET['id'] ?? 0 );
		if ( $id ) $wpdb->delete( Ateculus_SEO_DB::redirects_table(), array( 'id' => $id ) );

		wp_redirect( admin_url( 'admin.php?page=ateculus-seo-redirects' ) );
		exit;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . Ateculus_SEO_DB::redirects_table() . ' ORDER BY created_at DESC' );
		$from_404 = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
		?>
		<div class="wrap">
			<h1>301 Redirect Manager</h1>
			<?php if ( isset( $_GET['saved'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Redirect saved.</p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['error'] ) ) : ?>
			<div class="notice notice-error is-dismissible"><p>Invalid redirect — check source and target are different and non-empty.</p></div>
			<?php endif; ?>

			<div class="card" style="max-width:700px;padding:16px 20px;margin-bottom:24px">
				<h2 style="margin-top:0">Add New Redirect</h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="aseo_save_redirect" />
					<input type="hidden" name="aseo_redirect_id" value="0" />
					<?php wp_nonce_field( 'aseo_save_redirect' ); ?>
					<table class="form-table" style="margin:0">
						<tr>
							<th style="width:130px">From (path)</th>
							<td><input type="text" name="aseo_source" class="regular-text"
							           value="<?php echo esc_attr( $from_404 ); ?>"
							           placeholder="/old-page-url/" /></td>
						</tr>
						<tr>
							<th>To URL</th>
							<td><input type="text" name="aseo_target" class="regular-text" placeholder="/new-page/ or https://..." /></td>
						</tr>
						<tr>
							<th>Type</th>
							<td>
								<select name="aseo_code">
									<option value="301">301 — Permanent</option>
									<option value="302">302 — Temporary</option>
									<option value="307">307 — Temporary (preserve method)</option>
								</select>
							</td>
						</tr>
					</table>
					<p><?php submit_button( 'Add Redirect', 'primary', 'submit', false ); ?></p>
				</form>
			</div>

			<h2>Active Redirects (<?php echo count( $rows ); ?>)</h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>From</th><th>To</th><th>Code</th><th>Hits</th><th>Created</th><th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="6">No redirects yet.</td></tr>
					<?php else : ?>
					<?php foreach ( $rows as $r ) : ?>
					<tr>
						<td><code><?php echo esc_html( $r->source_url ); ?></code></td>
						<td><?php echo esc_html( $r->target_url ); ?></td>
						<td><?php echo esc_html( $r->http_code ); ?></td>
						<td><?php echo number_format( intval( $r->hit_count ) ); ?></td>
						<td><?php echo esc_html( date( 'Y-m-d', strtotime( $r->created_at ) ) ); ?></td>
						<td>
							<a href="<?php echo esc_url( wp_nonce_url(
								admin_url( 'admin-post.php?action=aseo_delete_redirect&id=' . $r->id ),
								'aseo_delete_redirect'
							) ); ?>"
							   onclick="return confirm('Delete this redirect?')"
							   class="button button-small button-link-delete">Delete</a>
						</td>
					</tr>
					<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
