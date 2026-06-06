<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ateculus_SEO_Robots_Editor {

	private function file_path() {
		return ABSPATH . 'robots.txt';
	}

	public function __construct() {
		add_action( 'admin_post_aseo_save_robots', array( $this, 'save' ) );
	}

	private function default_content() {
		return "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n\nSitemap: " . home_url( '/sitemap.xml' );
	}

	public function get_content() {
		$path = $this->file_path();
		if ( file_exists( $path ) ) {
			return file_get_contents( $path );
		}
		return $this->default_content();
	}

	public function save() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
		check_admin_referer( 'aseo_save_robots' );

		$raw  = wp_unslash( $_POST['aseo_robots'] ?? '' );
		$safe = preg_replace( '/<\?/', '', $raw );
		$safe = wp_strip_all_tags( $safe );
		$safe = sanitize_textarea_field( $safe );

		$path = $this->file_path();
		// Guard against path traversal
		if ( strpos( realpath( dirname( $path ) ), realpath( ABSPATH ) ) === false ) {
			wp_die( 'Invalid path' );
		}

		$ok = file_put_contents( $path, $safe );

		wp_redirect( add_query_arg( $ok !== false ? 'saved' : 'error', '1',
			admin_url( 'admin.php?page=ateculus-seo-robots' )
		) );
		exit;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$writable = is_writable( ABSPATH ) || ( file_exists( $this->file_path() ) && is_writable( $this->file_path() ) );
		?>
		<div class="wrap">
			<h1>robots.txt Editor</h1>

			<?php if ( isset( $_GET['saved'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>robots.txt saved successfully.</p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['error'] ) ) : ?>
			<div class="notice notice-error is-dismissible"><p>Failed to write robots.txt — check file permissions.</p></div>
			<?php endif; ?>
			<?php if ( ! $writable ) : ?>
			<div class="notice notice-warning">
				<p><strong>Note:</strong> <code><?php echo esc_html( $this->file_path() ); ?></code> is not writable. Grant write permission to enable saving.</p>
			</div>
			<?php endif; ?>
			<?php if ( ! file_exists( $this->file_path() ) ) : ?>
			<div class="notice notice-info">
				<p>No physical <code>robots.txt</code> exists yet. WordPress serves a virtual one. Saving here will create a physical file which takes precedence.</p>
			</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="aseo_save_robots" />
				<?php wp_nonce_field( 'aseo_save_robots' ); ?>
				<textarea name="aseo_robots" rows="22" class="large-text code"
				          style="font-family:monospace"><?php echo esc_textarea( $this->get_content() ); ?></textarea>
				<p>
					<?php submit_button( 'Save robots.txt', 'primary', 'submit', false ); ?>
					&nbsp;
					<a href="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>" target="_blank" class="button">View Live</a>
				</p>
			</form>
		</div>
		<?php
	}
}
