<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ateculus_SEO_Settings {

	/** @var Ateculus_SEO_Robots_Editor */
	private $robots;
	/** @var Ateculus_SEO_Redirects */
	private $redirects;
	/** @var Ateculus_SEO_404_Monitor */
	private $monitor;
	/** @var Ateculus_SEO_Bulk_Editor */
	private $bulk;
	/** @var Ateculus_SEO_Importer */
	private $importer;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_aseo_flush_sitemap', array( $this, 'flush_sitemap_cache' ) );
		add_filter( 'plugin_action_links_ateculus-seo/ateculus-seo.php', array( $this, 'action_links' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings_media' ) );
		add_filter( 'admin_footer_text', array( $this, 'footer_text' ) );
	}

	public function footer_text( $text ) {
		$screen = get_current_screen();
		if ( $screen && strpos( $screen->id, 'ateculus-seo' ) !== false ) {
			return 'Thank you for using <strong>Ateculus-SEO</strong> &mdash; built by <a href="https://ateculus.com" target="_blank">Ateculus</a>.';
		}
		return $text;
	}

	private function get_submodule( $class ) {
		static $instances = array();
		if ( ! isset( $instances[ $class ] ) ) {
			$instances[ $class ] = new $class();
		}
		return $instances[ $class ];
	}

	public function enqueue_settings_media( $hook ) {
		if ( strpos( $hook, 'ateculus-seo' ) === false ) return;
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'ateculus-seo-admin', ATECULUS_SEO_URL . 'admin/css/seo-admin.css', array(), ATECULUS_SEO_VERSION );
		wp_enqueue_script( 'ateculus-seo-admin', ATECULUS_SEO_URL . 'admin/js/seo-admin.js', array( 'jquery', 'wp-color-picker' ), ATECULUS_SEO_VERSION, true );
	}

	public function register_menu() {
		add_menu_page(
			'Ateculus-SEO',
			'Ateculus-SEO',
			'manage_options',
			'ateculus-seo',
			array( $this, 'render_settings_page' ),
			'dashicons-search',
			80
		);

		add_submenu_page( 'ateculus-seo', 'Settings',      'Settings',     'manage_options', 'ateculus-seo',          array( $this, 'render_settings_page' ) );
		add_submenu_page( 'ateculus-seo', 'Bulk Editor',   'Bulk Editor',  'manage_options', 'ateculus-seo-bulk',     array( 'Ateculus_SEO_Bulk_Editor', 'render_page_static' ) );
		add_submenu_page( 'ateculus-seo', 'Sitemaps',      'Sitemaps',     'manage_options', 'ateculus-seo-sitemaps', array( $this, 'render_sitemaps_page' ) );
		add_submenu_page( 'ateculus-seo', 'Redirects',     'Redirects',    'manage_options', 'ateculus-seo-redirects',array( new Ateculus_SEO_Redirects(), 'render_page' ) );
		add_submenu_page( 'ateculus-seo', '404 Monitor',   '404 Monitor',  'manage_options', 'ateculus-seo-404',      array( new Ateculus_SEO_404_Monitor(), 'render_page' ) );
		add_submenu_page( 'ateculus-seo', 'robots.txt',    'robots.txt',   'manage_options', 'ateculus-seo-robots',   array( new Ateculus_SEO_Robots_Editor(), 'render_page' ) );
		add_submenu_page( 'ateculus-seo', 'AI Suggestions', 'AI Suggestions','manage_options', 'ateculus-seo-ai',       array( $this, 'render_ai_page' ) );
		add_submenu_page( 'ateculus-seo', 'Tools',         'Tools',        'manage_options', 'ateculus-seo-tools',    array( new Ateculus_SEO_Importer(), 'render_page' ) );
		add_submenu_page( 'ateculus-seo', 'Help',          'Help',         'manage_options', 'ateculus-seo-help',     array( $this, 'render_help_page' ) );
	}

	public function register_settings() {
		register_setting( 'aseo_settings', 'aseo_options', array( 'sanitize_callback' => array( $this, 'sanitize_options' ) ) );

		// --- General ---
		add_settings_section( 'aseo_general', 'General', null, 'aseo_settings' );
		add_settings_field( 'separator',         'Title Separator',                   array( $this, 'f_separator' ),         'aseo_settings', 'aseo_general' );
		add_settings_field( 'home_title',        'Homepage SEO Title',                array( $this, 'f_home_title' ),        'aseo_settings', 'aseo_general' );
		add_settings_field( 'home_description',  'Homepage Meta Description',         array( $this, 'f_home_description' ),  'aseo_settings', 'aseo_general' );
		add_settings_field( 'default_og_image',  'Default Social Image',              array( $this, 'f_default_og_image' ),  'aseo_settings', 'aseo_general' );
		add_settings_field( 'fb_app_id',         'Facebook App ID',                   array( $this, 'f_fb_app_id' ),         'aseo_settings', 'aseo_general' );
		add_settings_field( 'google_verify',     'Google Search Console Verification',array( $this, 'f_google_verify' ),     'aseo_settings', 'aseo_general' );
		add_settings_field( 'toc_bg',            'Table of Contents Background',      array( $this, 'f_toc_bg' ),            'aseo_settings', 'aseo_general' );
		add_settings_field( 'toc_border',        'Table of Contents Border Color',    array( $this, 'f_toc_border' ),        'aseo_settings', 'aseo_general' );
		add_settings_field( 'toc_color',         'Table of Contents Text Color',      array( $this, 'f_toc_color' ),         'aseo_settings', 'aseo_general' );

		// --- IndexNow ---
		add_settings_section( 'aseo_indexnow', 'IndexNow — Instant Indexing', array( $this, 'indexnow_section_desc' ), 'aseo_settings' );
		add_settings_field( 'indexnow_enabled', 'Enable IndexNow', array( $this, 'f_indexnow_enabled' ), 'aseo_settings', 'aseo_indexnow' );
		add_settings_field( 'indexnow_info',    'Site Token &amp; Status', array( $this, 'f_indexnow_info' ), 'aseo_settings', 'aseo_indexnow' );

		// --- AI Suggestions (own submenu page, own option key) ---
		register_setting( 'aseo_ai_settings', 'aseo_ai_options', array( 'sanitize_callback' => array( $this, 'sanitize_ai_options' ) ) );
		add_settings_section( 'aseo_ai', 'AI Suggestions', array( $this, 'ai_section_desc' ), 'aseo_ai_page' );
		add_settings_field( 'ai_provider',    'AI Provider',    array( $this, 'f_ai_provider' ),    'aseo_ai_page', 'aseo_ai' );
		add_settings_field( 'groq_api_key',   'Groq API Key',   array( $this, 'f_groq_api_key' ),   'aseo_ai_page', 'aseo_ai' );
		add_settings_field( 'groq_model',     'Groq Model',     array( $this, 'f_groq_model' ),     'aseo_ai_page', 'aseo_ai' );
		add_settings_field( 'gemini_api_key', 'Gemini API Key', array( $this, 'f_gemini_api_key' ), 'aseo_ai_page', 'aseo_ai' );
		add_settings_field( 'gemini_model',   'Gemini Model',   array( $this, 'f_gemini_model' ),   'aseo_ai_page', 'aseo_ai' );

		// --- Analytics ---
		add_settings_section( 'aseo_analytics', 'Analytics', null, 'aseo_settings' );
		add_settings_field( 'ga4_id', 'GA4 Measurement ID', array( $this, 'f_ga4_id' ), 'aseo_settings', 'aseo_analytics' );
		add_settings_field( 'gtm_id', 'GTM Container ID',   array( $this, 'f_gtm_id' ), 'aseo_settings', 'aseo_analytics' );

		// --- Organization Schema ---
		add_settings_section( 'aseo_org', 'Organization / Local Business Schema', null, 'aseo_settings' );
		add_settings_field( 'org_type',      'Type',         array( $this, 'f_org_type' ),     'aseo_settings', 'aseo_org' );
		add_settings_field( 'org_name',      'Name',         array( $this, 'f_org_name' ),     'aseo_settings', 'aseo_org' );
		add_settings_field( 'org_logo',      'Logo URL',     array( $this, 'f_org_logo' ),     'aseo_settings', 'aseo_org' );
		add_settings_field( 'org_phone',     'Phone',        array( $this, 'f_org_phone' ),    'aseo_settings', 'aseo_org' );
		add_settings_field( 'org_email',     'Email',        array( $this, 'f_org_email' ),    'aseo_settings', 'aseo_org' );
		add_settings_field( 'org_address',   'Street',       array( $this, 'f_org_address' ),  'aseo_settings', 'aseo_org' );
		add_settings_field( 'org_city',      'City',         array( $this, 'f_org_city' ),     'aseo_settings', 'aseo_org' );
		add_settings_field( 'org_state',     'State',        array( $this, 'f_org_state' ),    'aseo_settings', 'aseo_org' );
		add_settings_field( 'org_zip',       'Zip / Postal', array( $this, 'f_org_zip' ),      'aseo_settings', 'aseo_org' );
		add_settings_field( 'org_country',   'Country Code', array( $this, 'f_org_country' ),  'aseo_settings', 'aseo_org' );
		add_settings_field( 'twitter_handle', 'Twitter / X URL', array( $this, 'f_twitter' ),      'aseo_settings', 'aseo_org' );
		add_settings_field( 'org_facebook',  'Facebook URL',    array( $this, 'f_org_facebook' ), 'aseo_settings', 'aseo_org' );
		add_settings_field( 'org_linkedin',  'LinkedIn URL',    array( $this, 'f_org_linkedin' ), 'aseo_settings', 'aseo_org' );
		add_settings_field( 'org_instagram', 'Instagram URL',   array( $this, 'f_org_instagram' ),'aseo_settings', 'aseo_org' );
		add_settings_field( 'org_youtube',   'YouTube URL',     array( $this, 'f_org_youtube' ),  'aseo_settings', 'aseo_org' );
		add_settings_field( 'org_hours',     'Opening Hours',   array( $this, 'f_org_hours' ),    'aseo_settings', 'aseo_org' );
	}

	public function sanitize_options( $input ) {
		$clean = array();
		$text_fields = array(
			'separator', 'home_title', 'twitter_handle', 'fb_app_id', 'google_verify',
			'ga4_id', 'gtm_id',
			'org_type', 'org_name', 'org_phone', 'org_email',
			'org_address', 'org_city', 'org_state', 'org_zip', 'org_country',
		);
		foreach ( $text_fields as $f ) {
			$clean[ $f ] = sanitize_text_field( $input[ $f ] ?? '' );
		}
		$clean['home_description'] = sanitize_textarea_field( $input['home_description'] ?? '' );
		$toc_bg = sanitize_text_field( $input['toc_bg'] ?? '' );
		$clean['toc_bg'] = preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $toc_bg ) ? $toc_bg : '';
		$toc_border = sanitize_text_field( $input['toc_border'] ?? '' );
		$clean['toc_border'] = preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $toc_border ) ? $toc_border : '';
		$toc_color = sanitize_text_field( $input['toc_color'] ?? '' );
		$clean['toc_color'] = preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $toc_color ) ? $toc_color : '';
		$url_fields = array( 'default_og_image', 'org_logo', 'org_facebook', 'org_linkedin', 'org_instagram', 'org_youtube' );
		foreach ( $url_fields as $f ) {
			$clean[ $f ] = esc_url_raw( $input[ $f ] ?? '' );
		}
		$clean['twitter_handle']    = ltrim( $clean['twitter_handle'], '@' );
		$clean['org_country']       = strtoupper( substr( $clean['org_country'] ?: 'US', 0, 2 ) );
		// Opening hours: each day stores "HH:MM-HH:MM" or empty if closed
		$days = array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' );
		foreach ( $days as $d ) {
			$open  = sanitize_text_field( $input[ 'org_hours_' . $d . '_open'  ] ?? '' );
			$close = sanitize_text_field( $input[ 'org_hours_' . $d . '_close' ] ?? '' );
			$clean[ 'org_hours_' . $d . '_open'  ] = preg_match( '/^\d{2}:\d{2}$/', $open )  ? $open  : '';
			$clean[ 'org_hours_' . $d . '_close' ] = preg_match( '/^\d{2}:\d{2}$/', $close ) ? $close : '';
			$clean[ 'org_hours_' . $d . '_closed' ] = ! empty( $input[ 'org_hours_' . $d . '_closed' ] ) ? '1' : '';
		}
		$clean['indexnow_enabled']  = ! empty( $input['indexnow_enabled'] ) ? '1' : '';
		$existing = get_option( 'aseo_options', array() );
		// Preserve the auto-generated IndexNow key — never overwrite from form input
		if ( ! empty( $existing['indexnow_key'] ) ) {
			$clean['indexnow_key'] = $existing['indexnow_key'];
		}
		return $clean;
	}

	private function opt( $key, $default = '' ) {
		$opts = get_option( 'aseo_options', array() );
		return $opts[ $key ] ?? $default;
	}

	private function tf( $key, $placeholder = '' ) {
		echo '<input type="text" class="regular-text" name="aseo_options[' . esc_attr( $key ) . ']" value="' . esc_attr( $this->opt( $key ) ) . '" placeholder="' . esc_attr( $placeholder ) . '" />';
	}

	private function uf( $key, $placeholder = '' ) {
		echo '<input type="url" class="regular-text" name="aseo_options[' . esc_attr( $key ) . ']" value="' . esc_attr( $this->opt( $key ) ) . '" placeholder="' . esc_attr( $placeholder ) . '" />';
	}

	public function f_separator()        { $this->tf( 'separator', '|' ); echo '<p class="description">Between title and site name. Common: | – • —</p>'; }
	public function f_home_title()       { echo '<input type="text" class="large-text" name="aseo_options[home_title]" value="' . esc_attr( $this->opt('home_title') ) . '" />'; }
	public function f_home_description() { echo '<textarea class="large-text" rows="3" name="aseo_options[home_description]">' . esc_textarea( $this->opt('home_description') ) . '</textarea>'; }
	public function f_twitter()          { echo '@'; $this->tf( 'twitter_handle', 'yourhandle' ); echo '<p class="description">Handle only (no @). Used in Twitter Card tags and the sameAs schema.</p>'; }
	public function f_fb_app_id()        { $this->tf( 'fb_app_id', '123456789' ); }
	public function f_ga4_id()           { $this->tf( 'ga4_id', 'G-XXXXXXXXXX' ); echo '<p class="description">Google Analytics 4 Measurement ID.</p>'; }
	public function f_gtm_id()           { $this->tf( 'gtm_id', 'GTM-XXXXXXX' ); echo '<p class="description">Google Tag Manager Container ID.</p>'; }

	public function indexnow_section_desc() {
		echo '<p>When enabled, every time you publish or update a post WordPress will automatically notify search engines (Bing, Yandex and others) so your content is crawled within minutes instead of days.</p>';
	}

	public function f_indexnow_enabled() {
		$val = $this->opt( 'indexnow_enabled' );
		echo '<label><input type="checkbox" name="aseo_options[indexnow_enabled]" value="1" ' . checked( $val, '1', false ) . ' /> ';
		echo 'Ping search engines automatically when a post is published or updated</label>';
	}

	public function f_indexnow_info() {
		$indexnow = new Ateculus_SEO_IndexNow();
		$key      = $indexnow->get_key();
		$key_url  = home_url( '/' . $key . '.txt' );
		$last     = get_option( 'aseo_indexnow_last_ping' );
		?>
		<div style="max-width:600px">
			<p><strong>Your Site Token:</strong><br>
			<code style="font-size:13px;user-select:all"><?php echo esc_html( $key ); ?></code></p>

			<p><strong>Verification file URL:</strong><br>
			<a href="<?php echo esc_url( $key_url ); ?>" target="_blank"><?php echo esc_html( $key_url ); ?></a><br>
			<span class="description">Search engines fetch this URL to confirm the token belongs to your site. It is served automatically — no file upload needed. The token is not a password and is safe to be public.</span></p>

			<?php if ( $last ) : ?>
			<p><strong>Last ping:</strong>
				<?php echo esc_html( $last['url'] ); ?> &mdash;
				<?php echo esc_html( $last['time'] ); ?>
				(HTTP <?php echo esc_html( $last['code'] ); ?>)
			</p>
			<?php endif; ?>

			<p>
				<button type="button" class="button" id="aseo-indexnow-test">Send Test Ping</button>
				<span id="aseo-indexnow-result" style="margin-left:10px;font-style:italic"></span>
			</p>
			<script>
			document.getElementById('aseo-indexnow-test').addEventListener('click', function() {
				var btn = this;
				var res = document.getElementById('aseo-indexnow-result');
				btn.disabled = true;
				res.textContent = 'Sending…';
				var fd = new FormData();
				fd.append('action', 'aseo_indexnow_test');
				fd.append('nonce', '<?php echo esc_js( wp_create_nonce( 'aseo_indexnow_test' ) ); ?>');
				fetch(ajaxurl, { method: 'POST', body: fd })
					.then(function(r){ return r.json(); })
					.then(function(d){
						res.textContent = d.success ? '✓ ' + d.data : '✗ ' + d.data;
						res.style.color = d.success ? '#46b450' : '#dc3232';
						btn.disabled = false;
					});
			});
			</script>
		</div>
		<?php
	}
	public function f_org_type() {
		$val = $this->opt( 'org_type', 'Organization' );
		echo '<select name="aseo_options[org_type]">';
		foreach ( array( 'Organization', 'LocalBusiness', 'Store', 'Restaurant', 'MedicalBusiness', 'EducationalOrganization' ) as $t ) {
			echo '<option value="' . esc_attr( $t ) . '" ' . selected( $val, $t, false ) . '>' . esc_html( $t ) . '</option>';
		}
		echo '</select>';
	}
	public function f_org_name()     { echo '<input type="text" class="large-text" name="aseo_options[org_name]" value="' . esc_attr( $this->opt('org_name', get_bloginfo('name')) ) . '" />'; }
	public function f_org_logo()     { $this->uf( 'org_logo' ); echo ' <button type="button" class="button aseo-media-btn" data-target="aseo_options[org_logo]">Choose</button>'; }
	public function f_org_phone()    { $this->tf( 'org_phone', '+1-555-555-5555' ); }
	public function f_org_email()    { echo '<input type="email" class="regular-text" name="aseo_options[org_email]" value="' . esc_attr( $this->opt('org_email') ) . '" />'; }
	public function f_org_address()  { $this->tf( 'org_address', '123 Main St' ); }
	public function f_org_city()     { $this->tf( 'org_city' ); }
	public function f_org_state()    { $this->tf( 'org_state' ); }
	public function f_org_zip()      { $this->tf( 'org_zip' ); }
	public function f_org_country()  { $this->tf( 'org_country', 'US' ); echo '<p class="description">2-letter ISO country code.</p>'; }
	public function f_org_facebook() { $this->uf( 'org_facebook', 'https://facebook.com/yourpage' ); }
	public function f_org_linkedin() { $this->uf( 'org_linkedin', 'https://linkedin.com/company/yourcompany' ); }
	public function f_org_instagram(){ $this->uf( 'org_instagram', 'https://instagram.com/yourhandle' ); }
	public function f_org_youtube()  { $this->uf( 'org_youtube', 'https://youtube.com/yourchannel' ); }

	public function f_org_hours() {
		$days = array(
			'mon' => 'Monday',
			'tue' => 'Tuesday',
			'wed' => 'Wednesday',
			'thu' => 'Thursday',
			'fri' => 'Friday',
			'sat' => 'Saturday',
			'sun' => 'Sunday',
		);
		echo '<table style="border-collapse:collapse;font-size:13px">';
		echo '<thead><tr>';
		echo '<th style="text-align:left;padding:4px 12px 4px 0;min-width:90px">Day</th>';
		echo '<th style="padding:4px 12px 4px 0">Open</th>';
		echo '<th style="padding:4px 12px 4px 0">Close</th>';
		echo '<th style="padding:4px 0">Closed</th>';
		echo '</tr></thead><tbody>';
		foreach ( $days as $key => $label ) {
			$open   = esc_attr( $this->opt( 'org_hours_' . $key . '_open',   '' ) );
			$close  = esc_attr( $this->opt( 'org_hours_' . $key . '_close',  '' ) );
			$closed = $this->opt( 'org_hours_' . $key . '_closed', '' );
			$row_id = 'aseo_hours_row_' . $key;
			echo '<tr>';
			echo '<td style="padding:4px 12px 4px 0;font-weight:600">' . esc_html( $label ) . '</td>';
			echo '<td style="padding:4px 12px 4px 0">';
			echo '<input type="time" name="aseo_options[org_hours_' . $key . '_open]" value="' . $open . '" id="' . $row_id . '_open" style="width:110px" ' . ( $closed ? 'disabled' : '' ) . '>';
			echo '</td>';
			echo '<td style="padding:4px 12px 4px 0">';
			echo '<input type="time" name="aseo_options[org_hours_' . $key . '_close]" value="' . $close . '" id="' . $row_id . '_close" style="width:110px" ' . ( $closed ? 'disabled' : '' ) . '>';
			echo '</td>';
			echo '<td style="padding:4px 0">';
			echo '<label style="font-weight:normal"><input type="checkbox" name="aseo_options[org_hours_' . $key . '_closed]" value="1" id="' . $row_id . '_closed" ' . checked( $closed, '1', false ) . ' onchange="aseoClosed(this,\'' . $row_id . '\')"> Closed</label>';
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description">Leave Open/Close blank for days with no set hours. Check "Closed" to mark a day as explicitly closed.</p>';
		?>
		<script>
		function aseoClosed(cb, rowId) {
			var open  = document.getElementById(rowId + '_open');
			var close = document.getElementById(rowId + '_close');
			if (open)  open.disabled  = cb.checked;
			if (close) close.disabled = cb.checked;
		}
		</script>
		<?php
	}

	public function f_default_og_image() {
		$val = $this->opt( 'default_og_image' );
		?>
		<div class="aseo-og-image-wrap">
			<img src="<?php echo esc_url( $val ); ?>" id="aseo_default_og_preview"
			     class="aseo-og-preview" <?php if ( ! $val ) echo 'style="display:none"'; ?> />
			<input type="hidden" id="aseo_default_og_field" name="aseo_options[default_og_image]"
			       value="<?php echo esc_attr( $val ); ?>" />
			<button type="button" class="button" id="aseo_default_og_upload">Choose Image</button>
			<button type="button" class="button" id="aseo_default_og_remove"
			        <?php if ( ! $val ) echo 'style="display:none"'; ?>>Remove</button>
		</div>
		<p class="description">Fallback image used when a post has no featured image or custom OG image. Recommended: 1200×630px.</p>
		<?php
	}

	public function f_google_verify() {
		$this->tf( 'google_verify' );
		echo '<p class="description">Paste only the <code>content</code> value from the Google verification meta tag.</p>';
	}

	public function f_toc_bg() {
		$val = $this->opt( 'toc_bg', '' );
		echo '<input type="text" class="aseo-color-picker" name="aseo_options[toc_bg]" value="' . esc_attr( $val ) . '" placeholder="transparent" data-default-color="" />';
		echo '<p class="description">Leave blank for transparent.</p>';
	}

	public function f_toc_border() {
		$val = $this->opt( 'toc_border', '' );
		echo '<input type="text" class="aseo-color-picker" name="aseo_options[toc_border]" value="' . esc_attr( $val ) . '" placeholder="#dcdcde" data-default-color="" />';
		echo '<p class="description">Leave blank to use the default gray border. Clear the color to remove the border entirely.</p>';
	}

	public function f_toc_color() {
		$val = $this->opt( 'toc_color', '' );
		echo '<input type="text" class="aseo-color-picker" name="aseo_options[toc_color]" value="' . esc_attr( $val ) . '" placeholder="inherit" data-default-color="" />';
		echo '<p class="description">Leave blank to inherit the color from your theme.</p>';
	}

	public function render_help_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		?>
		<div class="wrap">
			<h1>Ateculus-SEO — Help &amp; Shortcode Reference</h1>
			<p style="color:#666;max-width:700px">A quick reference for every shortcode the plugin provides. Copy any example directly into the WordPress editor (Text/HTML mode or a Shortcode block in Gutenberg).</p>

			<?php
			$sections = array(

				array(
					'title' => 'Table of Contents',
					'tag'   => 'aseo_toc',
					'desc'  => 'Automatically builds a clickable table of contents from the H2 and H3 headings in your post and injects an <strong>ItemList</strong> schema for Google. Place the shortcode near the top of your post.',
					'attrs' => array(
						'title'        => array( 'default' => 'Table of Contents', 'desc' => 'Heading shown above the list.' ),
						'min_headings' => array( 'default' => '3',                 'desc' => 'Minimum headings required before the TOC renders.' ),
					),
					'example' => "[aseo_toc]\n\n[aseo_toc title=\"In this guide\" min_headings=\"2\"]",
					'notes'   => 'The shortcode adds <code>id</code> attributes to your headings automatically so the anchor links work.',
				),

				array(
					'title' => 'FAQ',
					'tag'   => 'aseo_faq',
					'desc'  => 'Renders a styled FAQ block and injects a <strong>FAQPage</strong> schema. Google may show the questions and answers as an expandable rich result directly in search.',
					'attrs' => array(),
					'example' => "[aseo_faq]\n[aseo_question]What is Ateculus?[/aseo_question]\n[aseo_answer]Ateculus is a technology services company.[/aseo_answer]\n\n[aseo_question]Do you offer support?[/aseo_question]\n[aseo_answer]Yes — 24/7 support is included with every plan.[/aseo_answer]\n[/aseo_faq]",
					'notes'   => 'Pair each <code>[aseo_question]</code> with an <code>[aseo_answer]</code>. You can have as many Q&amp;A pairs as you like inside one <code>[aseo_faq]</code> block.',
				),

				array(
					'title' => 'HowTo',
					'tag'   => 'aseo_howto',
					'desc'  => 'Renders a numbered step-by-step guide and injects a <strong>HowTo</strong> schema. Google can show the steps as a rich result with step numbers and images.',
					'attrs' => array(
						'title'       => array( 'default' => '',     'desc' => 'Title of the how-to guide (defaults to the post title if omitted).' ),
						'description' => array( 'default' => '',     'desc' => 'Short summary shown below the title.' ),
						'image'       => array( 'default' => '',     'desc' => 'URL of a representative image for the whole guide.' ),
						'total_time'  => array( 'default' => '',     'desc' => 'ISO 8601 duration, e.g. <code>PT30M</code> = 30 minutes, <code>PT1H</code> = 1 hour.' ),
					),
					'inner_tag' => 'aseo_step',
					'inner_attrs' => array(
						'name'  => array( 'desc' => 'Step heading / label.' ),
						'text'  => array( 'desc' => 'Step description (can also be the shortcode content).' ),
						'image' => array( 'desc' => 'URL of an image illustrating this step.' ),
					),
					'example' => "[aseo_howto title=\"How to reset your password\" total_time=\"PT5M\"]\n[aseo_step name=\"Go to the login page\" text=\"Visit the login page and click Forgot Password.\"]\n[aseo_step name=\"Check your email\" text=\"Open the reset email and click the link inside.\"]\n[aseo_step name=\"Set a new password\" text=\"Enter and confirm your new password, then click Save.\"]\n[/aseo_howto]",
					'notes'   => '<code>[aseo_step]</code> tags must be inside <code>[aseo_howto]</code>. Step text can go in the <code>text</code> attribute or as the shortcode content.',
				),

				array(
					'title' => 'Event',
					'tag'   => 'aseo_event',
					'desc'  => 'Renders an event card and injects an <strong>Event</strong> schema. Google can show events in a dedicated Events rich result with date, location, and a registration link.',
					'attrs' => array(
						'name'        => array( 'default' => '',                        'desc' => '<strong>Required.</strong> Name of the event.' ),
						'start'       => array( 'default' => '',                        'desc' => '<strong>Required.</strong> Start date/time in ISO 8601, e.g. <code>2026-06-15T14:00</code>.' ),
						'end'         => array( 'default' => '',                        'desc' => 'End date/time in ISO 8601.' ),
						'location'    => array( 'default' => '',                        'desc' => 'Venue name (for in-person events).' ),
						'address'     => array( 'default' => '',                        'desc' => 'Street address of the venue.' ),
						'organizer'   => array( 'default' => '',                        'desc' => 'Name of the organizing company or person.' ),
						'description' => array( 'default' => '',                        'desc' => 'Short event description.' ),
						'image'       => array( 'default' => '',                        'desc' => 'URL of a promotional image.' ),
						'url'         => array( 'default' => '',                        'desc' => 'Registration or info page URL.' ),
						'status'      => array( 'default' => 'EventScheduled',          'desc' => '<code>EventScheduled</code>, <code>EventCancelled</code>, <code>EventPostponed</code>, or <code>EventRescheduled</code>.' ),
						'attendance'  => array( 'default' => 'OfflineEventAttendanceMode', 'desc' => '<code>OfflineEventAttendanceMode</code>, <code>OnlineEventAttendanceMode</code>, or <code>MixedEventAttendanceMode</code>.' ),
					),
					'example' => "[aseo_event\n  name=\"Monthly Tech Webinar\"\n  start=\"2026-06-15T14:00\"\n  end=\"2026-06-15T15:30\"\n  attendance=\"OnlineEventAttendanceMode\"\n  url=\"https://ateculus.com/register\"\n  organizer=\"Ateculus\"\n  description=\"Join us for our monthly deep-dive into cloud infrastructure.\"\n]",
					'notes'   => 'For online events, set <code>attendance="OnlineEventAttendanceMode"</code> — the location will automatically become a VirtualLocation pointing to the <code>url</code>.',
				),

				array(
					'title' => 'Video (automatic)',
					'tag'   => null,
					'desc'  => 'No shortcode needed. Any post that contains a YouTube iframe embed automatically gets a <strong>VideoObject</strong> schema injected. Google can show a video thumbnail directly in search results.',
					'attrs' => array(),
					'example' => "Just embed a YouTube video in your post using the normal WordPress embed or Gutenberg block.\nThe plugin detects it and adds the schema automatically.",
					'notes'   => 'Currently supports YouTube only. The schema uses the post title, excerpt, and post date as metadata.',
				),

				array(
					'title' => 'Breadcrumbs',
					'tag'   => 'aseo_breadcrumbs',
					'desc'  => 'Renders a visible breadcrumb trail and injects a <strong>BreadcrumbList</strong> schema. The schema is added automatically to every page — the shortcode (or template tag) is only needed if you want the visible trail to appear.',
					'attrs' => array(
						'separator' => array( 'default' => '›', 'desc' => 'Character shown between crumbs.' ),
					),
					'example' => "[aseo_breadcrumbs]\n\n[aseo_breadcrumbs separator=\" / \"]",
					'notes'   => 'You can also add it to your theme template files with PHP: <code>if ( function_exists( \'ateculus_seo_breadcrumbs\' ) ) { ateculus_seo_breadcrumbs(); }</code>',
				),

			);

			foreach ( $sections as $s ) :
				$has_code = ! empty( $s['tag'] ) || ! empty( $s['example'] );
			?>
			<div style="max-width:860px;margin-bottom:32px;border:1px solid #dcdcde;border-radius:6px;overflow:hidden">
				<div style="background:#f6f7f7;padding:12px 18px;border-bottom:1px solid #dcdcde;display:flex;align-items:center;gap:10px">
					<strong style="font-size:14px"><?php echo esc_html( $s['title'] ); ?></strong>
					<?php if ( ! empty( $s['tag'] ) ) : ?>
					<code style="background:#fff;border:1px solid #dcdcde;padding:2px 8px;border-radius:3px;font-size:12px">[<?php echo esc_html( $s['tag'] ); ?>]</code>
					<?php endif; ?>
				</div>
				<div style="padding:16px 18px">
					<p style="margin:0 0 12px;color:#333"><?php echo $s['desc']; ?></p>

					<?php if ( ! empty( $s['attrs'] ) ) : ?>
					<table style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:14px">
						<thead>
							<tr style="border-bottom:2px solid #dcdcde">
								<th style="text-align:left;padding:6px 12px 6px 0;color:#50575e;white-space:nowrap">Attribute</th>
								<th style="text-align:left;padding:6px 12px 6px 0;color:#50575e;white-space:nowrap">Default</th>
								<th style="text-align:left;padding:6px 0;color:#50575e">Description</th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $s['attrs'] as $attr => $info ) : ?>
							<tr style="border-bottom:1px solid #ebebeb">
								<td style="padding:7px 12px 7px 0;font-family:monospace;color:#1d2327"><?php echo esc_html( $attr ); ?></td>
								<td style="padding:7px 12px 7px 0;color:#757575"><?php echo ! empty( $info['default'] ) ? '<code>' . esc_html( $info['default'] ) . '</code>' : '<em>—</em>'; ?></td>
								<td style="padding:7px 0;color:#3c434a"><?php echo $info['desc']; ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<?php endif; ?>

					<?php if ( ! empty( $s['inner_tag'] ) ) : ?>
					<p style="font-weight:600;font-size:12px;margin:0 0 6px;color:#50575e">INNER TAG: <code>[<?php echo esc_html( $s['inner_tag'] ); ?>]</code></p>
					<table style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:14px">
						<thead>
							<tr style="border-bottom:2px solid #dcdcde">
								<th style="text-align:left;padding:6px 12px 6px 0;color:#50575e;white-space:nowrap">Attribute</th>
								<th style="text-align:left;padding:6px 0;color:#50575e">Description</th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $s['inner_attrs'] as $attr => $info ) : ?>
							<tr style="border-bottom:1px solid #ebebeb">
								<td style="padding:7px 12px 7px 0;font-family:monospace;color:#1d2327"><?php echo esc_html( $attr ); ?></td>
								<td style="padding:7px 0;color:#3c434a"><?php echo $info['desc']; ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<?php endif; ?>

					<?php if ( ! empty( $s['example'] ) ) : ?>
					<p style="font-weight:600;font-size:12px;margin:0 0 6px;color:#50575e">EXAMPLE</p>
					<div style="position:relative">
						<pre style="background:#1d2327;color:#f0f0f1;padding:14px 16px;border-radius:4px;font-size:12px;overflow-x:auto;margin:0;white-space:pre-wrap"><?php echo esc_html( $s['example'] ); ?></pre>
						<button type="button" class="button button-small"
						        style="position:absolute;top:8px;right:8px;font-size:11px"
						        onclick="aseoHelpCopy(this, <?php echo wp_json_encode( $s['example'] ); ?>)">Copy</button>
					</div>
					<?php endif; ?>

					<?php if ( ! empty( $s['notes'] ) ) : ?>
					<p style="margin:10px 0 0;font-size:12px;color:#757575"><strong>Note:</strong> <?php echo $s['notes']; ?></p>
					<?php endif; ?>
				</div>
			</div>
			<?php endforeach; ?>

			<script>
			function aseoHelpCopy(btn, text) {
				function flash() { btn.textContent = 'Copied!'; setTimeout(function(){ btn.textContent = 'Copy'; }, 2000); }
				if (window.isSecureContext && navigator.clipboard) {
					navigator.clipboard.writeText(text).then(flash, fallback);
				} else { fallback(); }
				function fallback() {
					var ta = document.createElement('textarea');
					ta.value = text;
					ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px';
					document.body.appendChild(ta);
					ta.focus(); ta.select();
					try { document.execCommand('copy'); flash(); } catch(e) {}
					document.body.removeChild(ta);
				}
			}
			</script>
		</div>
		<?php
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		?>
		<div class="wrap">
			<h1>Ateculus-SEO Settings</h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'aseo_settings' );
				do_settings_sections( 'aseo_settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function render_sitemaps_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$sitemaps = array(
			'Sitemap Index'      => home_url( '/sitemap.xml' ),
			'Posts'              => home_url( '/sitemap-posts.xml' ),
			'Pages'              => home_url( '/sitemap-pages.xml' ),
			'Images'             => home_url( '/sitemap-images.xml' ),
			'Categories'         => home_url( '/sitemap-categories.xml' ),
			'Tags'               => home_url( '/sitemap-tags.xml' ),
		);

		$cpts = get_post_types( array( 'public' => true, '_builtin' => false ), 'objects' );
		foreach ( $cpts as $cpt ) {
			$sitemaps[ $cpt->labels->name ] = home_url( '/sitemap-' . $cpt->name . '.xml' );
		}
		?>
		<div class="wrap">
			<h1>Sitemaps</h1>
			<?php if ( isset( $_GET['flushed'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Sitemap cache flushed.</p></div>
			<?php endif; ?>
			<p>Submit your sitemap index to
				<a href="https://search.google.com/search-console" target="_blank">Google Search Console</a>.
			</p>

			<script>
			function aseoCopySitemap(btn, url) {
				function flash() { btn.textContent = 'Copied!'; setTimeout(function(){ btn.textContent = 'Copy'; }, 2000); }
				if (window.isSecureContext && navigator.clipboard) {
					navigator.clipboard.writeText(url).then(flash, function() {
						var ta = document.createElement('textarea');
						ta.value = url; ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px';
						document.body.appendChild(ta); ta.select();
						try { document.execCommand('copy'); flash(); } catch(e){}
						document.body.removeChild(ta);
					});
				} else {
					var ta = document.createElement('textarea');
					ta.value = url; ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px';
					document.body.appendChild(ta); ta.focus(); ta.select();
					try { document.execCommand('copy'); flash(); } catch(e){}
					document.body.removeChild(ta);
				}
			}
			</script>
			<table class="widefat striped" style="max-width:700px">
				<thead><tr><th>Sitemap</th><th>URL</th><th></th></tr></thead>
				<tbody>
					<?php foreach ( $sitemaps as $label => $url ) : ?>
					<tr>
						<td><?php echo esc_html( $label ); ?></td>
						<td><a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_url( $url ); ?></a></td>
						<td style="white-space:nowrap">
							<a href="<?php echo esc_url( $url ); ?>" target="_blank" class="button button-small">View</a>
							<button type="button" class="button button-small" style="margin-left:4px"
							        onclick="aseoCopySitemap(this,'<?php echo esc_js( $url ); ?>')">Copy</button>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<br>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="aseo_flush_sitemap" />
				<?php wp_nonce_field( 'aseo_flush_sitemap' ); ?>
				<?php submit_button( 'Flush Sitemap Cache', 'secondary' ); ?>
			</form>

			<h2>How to submit to Google</h2>
			<ol>
				<li>Go to <a href="https://search.google.com/search-console" target="_blank">Google Search Console</a>.</li>
				<li>Select your property.</li>
				<li>In the left menu click <strong>Sitemaps</strong>.</li>
				<li>Enter <code>sitemap.xml</code> and click <strong>Submit</strong>.</li>
			</ol>
		</div>
		<?php
	}

	public function flush_sitemap_cache() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
		check_admin_referer( 'aseo_flush_sitemap' );

		$cpts = get_post_types( array( 'public' => true ), 'names' );
		foreach ( $cpts as $cpt ) {
			delete_transient( 'aseo_sitemap_' . $cpt );
		}

		wp_redirect( add_query_arg( 'flushed', '1', admin_url( 'admin.php?page=ateculus-seo-sitemaps' ) ) );
		exit;
	}

	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . admin_url( 'admin.php?page=ateculus-seo' ) . '">Settings</a>' );
		return $links;
	}

	public function render_ai_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		?>
		<div class="wrap">
			<h1>AI Suggestions</h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'aseo_ai_settings' );
				do_settings_sections( 'aseo_ai_page' );
				submit_button( 'Save AI Settings' );
				?>
			</form>
		</div>
		<?php
	}

	public function sanitize_ai_options( $input ) {
		$existing = get_option( 'aseo_ai_options', array() );
		$clean    = array();

		$clean['ai_provider'] = in_array( $input['ai_provider'] ?? 'groq', array( 'groq', 'gemini' ), true )
			? $input['ai_provider']
			: 'groq';

		// Groq
		$submitted = sanitize_text_field( $input['groq_api_key'] ?? '' );
		$clean['groq_api_key'] = $submitted !== '' ? $submitted : ( $existing['groq_api_key'] ?? '' );

		$allowed_groq = array( 'llama-3.1-8b-instant', 'llama-3.3-70b-versatile' );
		$clean['groq_model'] = in_array( $input['groq_model'] ?? '', $allowed_groq, true )
			? $input['groq_model']
			: 'llama-3.1-8b-instant';

		// Gemini
		$submitted = sanitize_text_field( $input['gemini_api_key'] ?? '' );
		$clean['gemini_api_key'] = $submitted !== '' ? $submitted : ( $existing['gemini_api_key'] ?? '' );

		$allowed_gemini = array( 'gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-2.0-flash-lite', 'gemini-2.5-flash-lite', 'gemini-2.5-pro' );
		$clean['gemini_model'] = in_array( $input['gemini_model'] ?? '', $allowed_gemini, true )
			? $input['gemini_model']
			: 'gemini-2.5-flash';

		return $clean;
	}

	// ── AI Suggestions ────────────────────────────────────────────────

	public function ai_section_desc() {
		echo '<p>Choose an AI provider and enter your API key to enable one-click SEO suggestions in the post editor. <strong>Groq is recommended</strong> — free account at <a href="https://console.groq.com" target="_blank">console.groq.com</a>, no credit card required.</p>';
	}

	private function ai_opt( $key, $default = '' ) {
		$opts = get_option( 'aseo_ai_options', array() );
		return $opts[ $key ] ?? $default;
	}

	public function f_ai_provider() {
		$val = $this->ai_opt( 'ai_provider', 'groq' );
		?>
		<select name="aseo_ai_options[ai_provider]" id="aseo_ai_provider_select"
		        onchange="aseoToggleAIProvider(this.value)">
			<option value="groq"   <?php selected( $val, 'groq' ); ?>>Groq (Recommended — Free)</option>
			<option value="gemini" <?php selected( $val, 'gemini' ); ?>>Google Gemini</option>
		</select>
		<script>
		function aseoToggleAIProvider(val) {
			document.querySelectorAll('.aseo-groq-only').forEach(function(el) {
				var tr = el.closest('tr');
				if (tr) tr.style.display = val === 'groq' ? '' : 'none';
			});
			document.querySelectorAll('.aseo-gemini-only').forEach(function(el) {
				var tr = el.closest('tr');
				if (tr) tr.style.display = val === 'gemini' ? '' : 'none';
			});
		}
		document.addEventListener('DOMContentLoaded', function() {
			var sel = document.getElementById('aseo_ai_provider_select');
			if (sel) aseoToggleAIProvider(sel.value);
		});
		</script>
		<?php
	}

	public function f_groq_api_key() {
		$val = $this->ai_opt( 'groq_api_key', '' );
		?>
		<div class="aseo-groq-only">
			<input type="password" class="regular-text" name="aseo_ai_options[groq_api_key]"
			       value="<?php echo esc_attr( $val ); ?>"
			       placeholder="gsk_..."
			       autocomplete="new-password" />
			<p class="description">
				Free key at <a href="https://console.groq.com/keys" target="_blank">console.groq.com</a>. No credit card required. Leave blank to keep the existing key.
			</p>
		</div>
		<?php
	}

	public function f_groq_model() {
		$val    = $this->ai_opt( 'groq_model', 'llama-3.1-8b-instant' );
		$models = array(
			'llama-3.1-8b-instant'    => '[Free] Llama 3.1 8B — Fast, ~429 req/day',
			'llama-3.3-70b-versatile' => '[Free] Llama 3.3 70B — More capable, ~85 req/day',
		);
		echo '<div class="aseo-groq-only"><select name="aseo_ai_options[groq_model]">';
		foreach ( $models as $id => $label ) {
			echo '<option value="' . esc_attr( $id ) . '" ' . selected( $val, $id, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></div>';
	}

	public function f_gemini_api_key() {
		$val = $this->ai_opt( 'gemini_api_key', '' );
		?>
		<div class="aseo-gemini-only">
			<input type="password" class="regular-text" name="aseo_ai_options[gemini_api_key]"
			       value="<?php echo esc_attr( $val ); ?>"
			       placeholder="AIza..."
			       autocomplete="new-password" />
			<p class="description">
				Free key at <a href="https://aistudio.google.com/apikey" target="_blank">aistudio.google.com</a>. Leave blank to keep the existing key.
			</p>
		</div>
		<?php
	}

	public function f_gemini_model() {
		$val    = $this->ai_opt( 'gemini_model', 'gemini-2.5-flash' );
		$models = array(
			'gemini-2.5-flash'      => '[Free] Gemini 2.5 Flash — Latest, 20 req/day',
			'gemini-2.0-flash'      => '[Free] Gemini 2.0 Flash — Fast, 200 req/day',
			'gemini-2.0-flash-lite' => '[Free] Gemini 2.0 Flash Lite — Lightest, 1500 req/day',
			'gemini-2.5-flash-lite' => '[Free] Gemini 2.5 Flash Lite — Latest lite, 500 req/day',
			'gemini-2.5-pro'        => '[Paid] Gemini 2.5 Pro — Most capable',
		);
		echo '<div class="aseo-gemini-only"><select name="aseo_ai_options[gemini_model]">';
		foreach ( $models as $id => $label ) {
			echo '<option value="' . esc_attr( $id ) . '" ' . selected( $val, $id, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></div>';
	}
}
