<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ateculus_SEO_Bulk_Editor {

	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_aseo_bulk_save', array( $this, 'ajax_save' ) );

		// SEO + Links columns in post/page list
		add_filter( 'manage_post_posts_columns',  array( $this, 'add_column' ) );
		add_filter( 'manage_page_posts_columns',  array( $this, 'add_column' ) );
		add_action( 'manage_post_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_action( 'manage_page_posts_custom_column', array( $this, 'render_column' ), 10, 2 );

		// Sortable SEO score column
		add_filter( 'manage_edit-post_sortable_columns', array( $this, 'sortable_columns' ) );
		add_filter( 'manage_edit-page_sortable_columns', array( $this, 'sortable_columns' ) );
		add_action( 'pre_get_posts', array( $this, 'sort_by_score' ) );

		// Keep stored score + link counts up to date on save
		add_action( 'save_post', array( $this, 'update_score_meta' ), 20 );
	}

	/** Stores the requested sort direction for use in the JOIN/ORDER filters. */
	private $score_sort_order = 'DESC';

	public function sortable_columns( $columns ) {
		$columns['aseo_score'] = 'aseo_score';
		return $columns;
	}

	public function sort_by_score( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) return;
		if ( $query->get( 'orderby' ) !== 'aseo_score' ) return;
		$order = strtoupper( $query->get( 'order' ) );
		$this->score_sort_order = in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'DESC';
		// Use a LEFT JOIN so posts without a stored score still appear (sorted as 0)
		add_filter( 'posts_join',    array( $this, 'score_sort_join' ) );
		add_filter( 'posts_orderby', array( $this, 'score_sort_orderby' ) );
	}

	public function score_sort_join( $join ) {
		global $wpdb;
		$join .= " LEFT JOIN {$wpdb->postmeta} AS aseo_score_meta"
		       . " ON ( {$wpdb->posts}.ID = aseo_score_meta.post_id"
		       . $wpdb->prepare( ' AND aseo_score_meta.meta_key = %s )', '_aseo_score' );
		remove_filter( 'posts_join', array( $this, 'score_sort_join' ) );
		return $join;
	}

	public function score_sort_orderby( $orderby ) {
		$order   = $this->score_sort_order;
		remove_filter( 'posts_orderby', array( $this, 'score_sort_orderby' ) );
		return "COALESCE( CAST( aseo_score_meta.meta_value AS UNSIGNED ), 0 ) {$order}";
	}

	public function update_score_meta( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( wp_is_post_revision( $post_id ) ) return;
		$result = Ateculus_SEO_Score::calculate( $post_id );
		update_post_meta( $post_id, '_aseo_score',          (int) $result['score'] );
		update_post_meta( $post_id, '_aseo_links_internal', (int) $result['internal_links'] );
		update_post_meta( $post_id, '_aseo_links_external', (int) $result['external_links'] );
	}

	public function enqueue( $hook ) {
		$bulk_page = 'seo_page_ateculus-seo-bulk';
		if ( $hook !== $bulk_page ) return;

		wp_enqueue_style(
			'ateculus-seo-admin',
			ATECULUS_SEO_URL . 'admin/css/seo-admin.css',
			array(),
			ATECULUS_SEO_VERSION
		);
		wp_enqueue_script(
			'ateculus-seo-bulk',
			ATECULUS_SEO_URL . 'admin/js/bulk-editor.js',
			array( 'jquery' ),
			ATECULUS_SEO_VERSION,
			true
		);
		wp_localize_script( 'ateculus-seo-bulk', 'aseoBulk', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'aseo_bulk_save' ),
		) );
	}

	public function add_column( $columns ) {
		$columns['aseo_score'] = 'SEO';
		$columns['aseo_links'] = 'Links';
		return $columns;
	}

	public function render_column( $column, $post_id ) {
		if ( $column === 'aseo_score' ) {
			if ( get_post_meta( $post_id, '_aseo_noindex', true ) ) {
				echo '<span class="aseo-col-dot aseo-col-gray" title="No Index">N</span>';
				return;
			}
			$result = Ateculus_SEO_Score::calculate( $post_id );
			echo '<span class="aseo-col-dot aseo-col-' . esc_attr( $result['color'] ) . '" title="SEO Score: ' . esc_attr( $result['score'] ) . '/100">'
			     . esc_html( $result['score'] ) . '</span>';
		}

		if ( $column === 'aseo_links' ) {
			$int = (int) get_post_meta( $post_id, '_aseo_links_internal', true );
			$ext = (int) get_post_meta( $post_id, '_aseo_links_external', true );
			echo '<span class="aseo-links-col">';
			echo '<span class="aseo-links-int' . ( $int === 0 ? ' aseo-links-zero' : '' ) . '" title="Internal links">' . esc_html( $int ) . ' in</span> ';
			echo '<span class="aseo-links-ext' . ( $ext === 0 ? ' aseo-links-zero' : '' ) . '" title="External links">' . esc_html( $ext ) . ' out</span>';
			echo '</span>';
		}
	}

	public function ajax_save() {
		check_ajax_referer( 'aseo_bulk_save', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Unauthorized' );

		$post_id     = intval( $_POST['post_id'] ?? 0 );
		$title       = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		$description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( 'Cannot edit this post.' );
		}

		update_post_meta( $post_id, '_aseo_title',       $title );
		update_post_meta( $post_id, '_aseo_description', $description );

		$result = Ateculus_SEO_Score::calculate( $post_id );
		wp_send_json_success( array( 'score' => $result['score'], 'color' => $result['color'] ) );
	}

	public static function render_page_static() {
		self::render_page_content();
	}

	public function render_page() {
		self::render_page_content();
	}

	private static function render_page_content() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$allowed_types = array( 'post', 'page' );
		$cpts = get_post_types( array( 'public' => true, '_builtin' => false ), 'objects' );
		foreach ( $cpts as $cpt ) {
			$allowed_types[] = $cpt->name;
		}

		$post_type = sanitize_key( $_GET['post_type'] ?? 'post' );
		if ( ! in_array( $post_type, $allowed_types, true ) ) $post_type = 'post';

		$posts = get_posts( array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		) );
		?>
		<div class="wrap">
			<h1>Bulk SEO Editor</h1>
			<p class="description">Edit SEO titles and descriptions inline. Click <strong>Save</strong> on any row or use <strong>Save All</strong> at the top.</p>

			<div class="aseo-bulk-toolbar">
				<ul class="subsubsub" style="float:left;margin:0;padding:0">
					<?php foreach ( $allowed_types as $type ) :
						$label = ucfirst( $type );
						$obj = get_post_type_object( $type );
						if ( $obj ) $label = $obj->labels->name;
					?>
					<li>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=ateculus-seo-bulk&post_type=' . $type ) ); ?>"
						   <?php if ( $post_type === $type ) echo 'class="current"'; ?>>
							<?php echo esc_html( $label ); ?>
						</a> |
					</li>
					<?php endforeach; ?>
				</ul>
				<button type="button" id="aseo-save-all" class="button button-primary" style="float:right">Save All</button>
				<div style="clear:both"></div>
			</div>

			<div id="aseo-bulk-notice" style="display:none;margin:8px 0" class="notice is-dismissible"></div>

			<table class="widefat striped aseo-bulk-table">
				<thead>
					<tr>
						<th style="width:22%">Post</th>
						<th style="width:6%" class="aseo-center">Score</th>
						<th style="width:35%">SEO Title <span class="aseo-hint-inline">30–60 chars</span></th>
						<th>Meta Description <span class="aseo-hint-inline">100–160 chars</span></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $posts as $post ) :
						$noindex = get_post_meta( $post->ID, '_aseo_noindex', true );
						$title   = get_post_meta( $post->ID, '_aseo_title',       true );
						$desc    = get_post_meta( $post->ID, '_aseo_description', true );
						$result  = Ateculus_SEO_Score::calculate( $post->ID );
					?>
					<tr data-post-id="<?php echo esc_attr( $post->ID ); ?>" class="aseo-bulk-row">
						<td>
							<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>">
								<?php echo esc_html( $post->post_title ); ?>
							</a>
							<?php if ( $noindex ) : ?>
							<span class="aseo-noindex-badge">No Index</span>
							<?php endif; ?>
						</td>
						<td class="aseo-center">
							<span class="aseo-col-dot aseo-col-<?php echo esc_attr( $result['color'] ); ?> aseo-row-dot"
							      title="<?php echo esc_attr( $result['score'] ); ?>/100">
								<?php echo esc_html( $result['score'] ); ?>
							</span>
						</td>
						<td>
							<input type="text"
							       class="widefat aseo-bulk-title"
							       value="<?php echo esc_attr( $title ); ?>"
							       placeholder="<?php echo esc_attr( wp_trim_words( $post->post_title, 10 ) ); ?>"
							       maxlength="70"
							       data-field="title" />
							<span class="aseo-bulk-counter <?php echo strlen($title) > 60 ? 'over' : (strlen($title) >= 30 ? 'good' : ''); ?>">
								<?php echo strlen( $title ); ?>/60
							</span>
						</td>
						<td>
							<textarea class="widefat aseo-bulk-desc"
							          rows="2"
							          maxlength="320"
							          data-field="description"
							          placeholder="Auto-generated from content"><?php echo esc_textarea( $desc ); ?></textarea>
							<span class="aseo-bulk-counter <?php echo strlen($desc) > 160 ? 'over' : (strlen($desc) >= 100 ? 'good' : ''); ?>">
								<?php echo strlen( $desc ); ?>/160
							</span>
							<button type="button" class="button button-small aseo-row-save" style="float:right;margin-top:2px">Save</button>
						</td>
					</tr>
					<?php endforeach; ?>
					<?php if ( empty( $posts ) ) : ?>
					<tr><td colspan="4">No published <?php echo esc_html( $post_type ); ?>s found.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
