<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ateculus_SEO_Importer {

	public function __construct() {
		add_action( 'admin_post_aseo_import_yoast',    array( $this, 'import_yoast' ) );
		add_action( 'admin_post_aseo_import_rankmath', array( $this, 'import_rankmath' ) );
		add_action( 'admin_post_aseo_export_csv',      array( $this, 'export_csv' ) );
	}

	public function import_yoast() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
		check_admin_referer( 'aseo_import_yoast' );

		global $wpdb;

		$map = array(
			'_yoast_wpseo_title'                => '_aseo_title',
			'_yoast_wpseo_metadesc'             => '_aseo_description',
			'_yoast_wpseo_focuskw'              => '_aseo_focus_kw',
			'_yoast_wpseo_canonical'            => '_aseo_canonical',
			'_yoast_wpseo_opengraph-image'      => '_aseo_og_image',
			'_yoast_wpseo_meta-robots-noindex'  => '_aseo_noindex',
			'_yoast_wpseo_meta-robots-nofollow' => '_aseo_nofollow',
		);

		$keys        = array_map( array( $wpdb, 'esc_like' ), array_keys( $map ) );
		$placeholders = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key IN ({$placeholders})",
			array_keys( $map )
		) );

		$count = 0;
		foreach ( $rows as $row ) {
			if ( ! isset( $map[ $row->meta_key ] ) ) continue;
			update_post_meta( (int) $row->post_id, $map[ $row->meta_key ], $row->meta_value );
			$count++;
		}

		wp_redirect( add_query_arg( array( 'imported' => $count, 'source' => 'yoast' ),
			admin_url( 'admin.php?page=ateculus-seo-tools' )
		) );
		exit;
	}

	public function import_rankmath() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
		check_admin_referer( 'aseo_import_rankmath' );

		global $wpdb;

		$map = array(
			'rank_math_title'         => '_aseo_title',
			'rank_math_description'   => '_aseo_description',
			'rank_math_focus_keyword' => '_aseo_focus_kw',
			'rank_math_canonical_url' => '_aseo_canonical',
		);

		$placeholders = implode( ', ', array_fill( 0, count( $map ) + 1, '%s' ) );
		$keys = array_merge( array_keys( $map ), array( 'rank_math_robots' ) );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key IN ({$placeholders})",
			$keys
		) );

		$count = 0;
		foreach ( $rows as $row ) {
			if ( isset( $map[ $row->meta_key ] ) ) {
				update_post_meta( (int) $row->post_id, $map[ $row->meta_key ], $row->meta_value );
				$count++;
			} elseif ( $row->meta_key === 'rank_math_robots' ) {
				$robots = (array) maybe_unserialize( $row->meta_value );
				if ( in_array( 'noindex',  $robots, true ) ) update_post_meta( (int) $row->post_id, '_aseo_noindex',  '1' );
				if ( in_array( 'nofollow', $robots, true ) ) update_post_meta( (int) $row->post_id, '_aseo_nofollow', '1' );
				$count++;
			}
		}

		wp_redirect( add_query_arg( array( 'imported' => $count, 'source' => 'rankmath' ),
			admin_url( 'admin.php?page=ateculus-seo-tools' )
		) );
		exit;
	}

	public function export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
		check_admin_referer( 'aseo_export_csv' );

		$posts = get_posts( array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		) );

		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="ateculus-seo-' . date( 'Y-m-d' ) . '.csv"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'ID', 'Type', 'Post Title', 'URL', 'SEO Title', 'Meta Description', 'Focus Keyphrase', 'Canonical', 'No Index', 'No Follow', 'SEO Score' ) );

		foreach ( $posts as $post ) {
			$result = Ateculus_SEO_Score::calculate( $post->ID );
			fputcsv( $out, array(
				$post->ID,
				$post->post_type,
				$post->post_title,
				get_permalink( $post->ID ),
				get_post_meta( $post->ID, '_aseo_title',       true ),
				get_post_meta( $post->ID, '_aseo_description', true ),
				get_post_meta( $post->ID, '_aseo_focus_kw',    true ),
				get_post_meta( $post->ID, '_aseo_canonical',   true ),
				get_post_meta( $post->ID, '_aseo_noindex',  true ) ? 'Yes' : 'No',
				get_post_meta( $post->ID, '_aseo_nofollow', true ) ? 'Yes' : 'No',
				$result['score'],
			) );
		}

		fclose( $out );
		exit;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$imported = isset( $_GET['imported'] ) ? intval( $_GET['imported'] ) : null;
		$source   = sanitize_key( $_GET['source'] ?? '' );
		?>
		<div class="wrap">
			<h1>SEO Tools</h1>

			<?php if ( $imported !== null && $source === 'yoast' ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo $imported; ?> fields imported from Yoast SEO.</p></div>
			<?php elseif ( $imported !== null && $source === 'rankmath' ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo $imported; ?> fields imported from Rank Math.</p></div>
			<?php endif; ?>

			<!-- Export -->
			<div class="card" style="max-width:620px;padding:16px 20px;margin-bottom:20px">
				<h2 style="margin-top:0">Export SEO Data</h2>
				<p>Download all post SEO titles, descriptions, and scores as a CSV file.</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="aseo_export_csv" />
					<?php wp_nonce_field( 'aseo_export_csv' ); ?>
					<?php submit_button( 'Export to CSV', 'secondary', 'submit', false ); ?>
				</form>
			</div>

			<!-- Import Yoast -->
			<div class="card" style="max-width:620px;padding:16px 20px;margin-bottom:20px">
				<h2 style="margin-top:0">Import from Yoast SEO</h2>
				<p>Copies SEO titles, descriptions, focus keywords, canonical URLs, Open Graph images, and robots settings from Yoast meta fields. Yoast does not need to be active — the data only needs to exist in the database.</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="aseo_import_yoast" />
					<?php wp_nonce_field( 'aseo_import_yoast' ); ?>
					<?php submit_button( 'Import from Yoast', 'secondary', 'submit', false ); ?>
				</form>
			</div>

			<!-- Import Rank Math -->
			<div class="card" style="max-width:620px;padding:16px 20px;margin-bottom:20px">
				<h2 style="margin-top:0">Import from Rank Math</h2>
				<p>Copies SEO data from Rank Math meta fields into Ateculus-SEO.</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="aseo_import_rankmath" />
					<?php wp_nonce_field( 'aseo_import_rankmath' ); ?>
					<?php submit_button( 'Import from Rank Math', 'secondary', 'submit', false ); ?>
				</form>
			</div>

			<!-- Canonical Issues -->
			<div class="card" style="max-width:900px;padding:16px 20px;margin-bottom:20px">
				<h2 style="margin-top:0">Canonical Issues</h2>
				<?php $this->render_canonical_checker(); ?>
			</div>
		</div>
		<?php
	}

	private function render_canonical_checker() {
		$posts = get_posts( array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => 500,
			'no_found_rows'  => true,
		) );

		$issues = array();
		foreach ( $posts as $post ) {
			$canonical = get_post_meta( $post->ID, '_aseo_canonical', true );
			if ( ! $canonical ) continue;
			$permalink = get_permalink( $post->ID );
			if ( rtrim( $canonical, '/' ) !== rtrim( $permalink, '/' ) ) {
				$issues[] = compact( 'post', 'canonical', 'permalink' );
			}
		}

		if ( empty( $issues ) ) {
			echo '<p>No canonical issues found. All custom canonicals match their permalinks.</p>';
			return;
		}

		echo '<p>' . count( $issues ) . ' post(s) have a custom canonical that differs from their permalink:</p>';
		echo '<table class="widefat striped"><thead><tr><th>Post</th><th>Permalink</th><th>Custom Canonical</th></tr></thead><tbody>';
		foreach ( $issues as $issue ) {
			echo '<tr>';
			echo '<td><a href="' . esc_url( get_edit_post_link( $issue['post']->ID ) ) . '">' . esc_html( $issue['post']->post_title ) . '</a></td>';
			echo '<td><code>' . esc_html( $issue['permalink'] ) . '</code></td>';
			echo '<td><code>' . esc_html( $issue['canonical'] ) . '</code></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}
}
