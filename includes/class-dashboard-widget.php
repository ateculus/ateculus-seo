<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ateculus_SEO_Dashboard_Widget {

	public function __construct() {
		add_action( 'wp_dashboard_setup', array( $this, 'register' ) );
	}

	public function register() {
		if ( ! current_user_can( 'edit_posts' ) ) return;
		wp_add_dashboard_widget(
			'aseo_overview',
			'Ateculus-SEO Overview',
			array( $this, 'render' )
		);
	}

	public function render() {
		$posts = get_posts( array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => 500,
			'no_found_rows'  => true,
		) );

		$green = $orange = $red = $noindex = $no_title = $no_desc = 0;

		foreach ( $posts as $post ) {
			if ( get_post_meta( $post->ID, '_aseo_noindex', true ) ) {
				$noindex++;
				continue;
			}
			$title = get_post_meta( $post->ID, '_aseo_title',       true );
			$desc  = get_post_meta( $post->ID, '_aseo_description', true );
			if ( ! $title ) $no_title++;
			if ( ! $desc  ) $no_desc++;

			$result = Ateculus_SEO_Score::calculate( $post->ID );
			switch ( $result['color'] ) {
				case 'green':  $green++;  break;
				case 'orange': $orange++; break;
				default:       $red++;
			}
		}
		?>
		<div class="aseo-widget">
			<div class="aseo-widget-counts">
				<div class="aseo-wcount aseo-wcount-green">
					<span class="aseo-wnum"><?php echo $green; ?></span>
					<span class="aseo-wlbl">Good</span>
				</div>
				<div class="aseo-wcount aseo-wcount-orange">
					<span class="aseo-wnum"><?php echo $orange; ?></span>
					<span class="aseo-wlbl">OK</span>
				</div>
				<div class="aseo-wcount aseo-wcount-red">
					<span class="aseo-wnum"><?php echo $red; ?></span>
					<span class="aseo-wlbl">Poor</span>
				</div>
			</div>
			<ul class="aseo-widget-list">
				<li><?php echo $no_title; ?> post(s) missing SEO title</li>
				<li><?php echo $no_desc;  ?> post(s) missing meta description</li>
				<li><?php echo $noindex;  ?> post(s) set to No Index</li>
			</ul>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ateculus-seo-bulk' ) ); ?>"
				   class="button button-primary button-small">Open Bulk Editor</a>
				&nbsp;
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ateculus-seo' ) ); ?>"
				   class="button button-small">Settings</a>
			</p>
		</div>
		<?php
	}
}
