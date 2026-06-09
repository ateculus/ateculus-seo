<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ateculus_SEO_Meta_Box {

	public function __construct() {
		add_action( 'add_meta_boxes',        array( $this, 'register' ) );
		add_action( 'save_post',             array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		// Prevent Screen Options from hiding our meta box on any post type
		add_action( 'current_screen',        array( $this, 'lock_visibility' ) );
	}

	public function lock_visibility( $screen ) {
		if ( $screen->base !== 'post' ) return;
		add_filter(
			'get_user_option_metaboxhidden_' . $screen->post_type,
			array( $this, 'remove_from_hidden' )
		);
	}

	public function remove_from_hidden( $hidden ) {
		if ( ! is_array( $hidden ) ) return $hidden;
		return array_values( array_diff( $hidden, array( 'ateculus_seo' ) ) );
	}

	public function register() {
		$screens = array( 'post', 'page' );
		$cpts    = get_post_types( array( 'public' => true, '_builtin' => false ), 'names' );
		$screens = array_merge( $screens, array_values( $cpts ) );

		foreach ( $screens as $screen ) {
			add_meta_box(
				'ateculus_seo',
				'Ateculus-SEO',
				array( $this, 'render' ),
				$screen,
				'normal',
				'high'
			);
		}
	}

	public function enqueue( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) return;
		wp_enqueue_style(
			'ateculus-seo-admin',
			ATECULUS_SEO_URL . 'admin/css/seo-admin.css',
			array(),
			ATECULUS_SEO_VERSION
		);
		wp_enqueue_media();
		wp_enqueue_script(
			'ateculus-seo-admin',
			ATECULUS_SEO_URL . 'admin/js/seo-admin.js',
			array( 'jquery' ),
			ATECULUS_SEO_VERSION,
			true
		);

		global $post;
		wp_localize_script( 'ateculus-seo-admin', 'aseoAI', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'aseo_ai_suggest' ),
			'postId'  => $post ? $post->ID : 0,
		) );
	}

	private function get_meta( $post_id, $key ) {
		return get_post_meta( $post_id, '_aseo_' . $key, true );
	}

	public function render( $post ) {
		wp_nonce_field( 'ateculus_seo_save', 'ateculus_seo_nonce' );

		$title       = $this->get_meta( $post->ID, 'title' );
		$description = $this->get_meta( $post->ID, 'description' );
		$keywords    = $this->get_meta( $post->ID, 'keywords' );
		$focus_kw    = $this->get_meta( $post->ID, 'focus_kw' );
		$canonical   = $this->get_meta( $post->ID, 'canonical' );
		$og_image    = $this->get_meta( $post->ID, 'og_image' );
		$noindex     = $this->get_meta( $post->ID, 'noindex' );
		$nofollow    = $this->get_meta( $post->ID, 'nofollow' );

		$seo_data  = Ateculus_SEO_Score::calculate( $post->ID );
		$read_data = ( new Ateculus_SEO_Readability() )->analyze( $post->post_content );
		?>
		<div class="aseo-wrap">

			<!-- SEO Score bar -->
			<div class="aseo-score-bar">
				<span class="aseo-score-label">SEO Score</span>
				<div class="aseo-score-track">
					<div class="aseo-score-fill aseo-score-<?php echo esc_attr( $seo_data['color'] ); ?>"
					     style="width:<?php echo esc_attr( $seo_data['score'] ); ?>%"></div>
				</div>
				<span class="aseo-score-num"><?php echo esc_html( $seo_data['score'] ); ?>/100</span>
			</div>

			<div class="aseo-tabs">
				<button type="button" class="aseo-tab active" data-tab="general">General</button>
				<button type="button" class="aseo-tab" data-tab="readability">Readability</button>
				<button type="button" class="aseo-tab" data-tab="social">Social</button>
				<button type="button" class="aseo-tab" data-tab="advanced">Advanced</button>
			</div>

			<!-- General Tab -->
			<div class="aseo-tab-content active" id="aseo-tab-general">

				<?php
				$_aseo_opts     = get_option( 'aseo_ai_options', array() );
				$_aseo_provider = $_aseo_opts['ai_provider'] ?? 'groq';
				$_aseo_has_key  = ( $_aseo_provider === 'gemini' )
					? ! empty( $_aseo_opts['gemini_api_key'] )
					: ! empty( $_aseo_opts['groq_api_key'] );
				if ( $_aseo_has_key ) :
				?>
				<div class="aseo-ai-bar">
					<button type="button" id="aseo-ai-suggest-btn" class="button button-primary">
						&#10024; Suggest with AI
					</button>
					<span id="aseo-ai-spinner" class="spinner" style="float:none;vertical-align:middle;margin:0 4px;display:none"></span>
					<span id="aseo-ai-error" style="display:none;color:#dc3232;font-size:12px;margin-left:4px"></span>
					<span class="aseo-hint" style="margin-left:4px">Auto-fills all fields below from your post content.</span>
				</div>
				<?php endif; ?>

				<?php if ( ! empty( $seo_data['tips'] ) ) : ?>
				<ul class="aseo-tips">
					<?php foreach ( $seo_data['tips'] as $tip ) : ?>
					<li><?php echo esc_html( $tip ); ?></li>
					<?php endforeach; ?>
				</ul>
				<?php endif; ?>

				<div class="aseo-field">
					<label for="aseo_focus_kw">Focus Keyphrases</label>
					<input type="text" id="aseo_focus_kw" name="aseo_focus_kw"
					       value="<?php echo esc_attr( $focus_kw ); ?>"
					       placeholder="e.g. wordpress seo, seo plugin, best seo tool" />
					<p class="aseo-hint">One phrase or several comma-separated phrases you want this page to rank for.</p>
				</div>

				<?php if ( ! empty( $seo_data['keyphrases'] ) ) : ?>
				<div class="aseo-kw-analysis">
					<div class="aseo-kw-analysis-title">Keyphrase Analysis</div>
					<table class="aseo-kw-table">
						<thead>
							<tr>
								<th>Phrase</th>
								<th title="In SEO title">SEO Title</th>
								<th title="In meta description">Description</th>
								<th title="In first paragraph">1st Para</th>
								<th title="In a subheading H2/H3">Heading</th>
								<th title="Found in content with density">Content</th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $seo_data['keyphrases'] as $idx => $kp ) :
							$d_class = ( $kp['density'] >= 0.5 && $kp['density'] <= 2.5 ) ? 'aseo-kw-density-ok' : ( $kp['count'] > 0 ? 'aseo-kw-density-warn' : '' );
						?>
						<tr>
							<td class="aseo-kw-phrase-cell">
								<span class="aseo-kw-primary-badge"><?php echo esc_html( $idx + 1 ); ?></span>
								<?php echo esc_html( $kp['phrase'] ); ?>
							</td>
							<td class="aseo-kw-cell"><?php echo $kp['in_title']   ? '<span class="aseo-kw-dot aseo-kw-dot-pass">✓</span>' : '<span class="aseo-kw-dot aseo-kw-dot-fail">✗</span>'; ?></td>
							<td class="aseo-kw-cell"><?php echo $kp['in_desc']    ? '<span class="aseo-kw-dot aseo-kw-dot-pass">✓</span>' : '<span class="aseo-kw-dot aseo-kw-dot-fail">✗</span>'; ?></td>
							<td class="aseo-kw-cell"><?php echo $kp['in_first']   ? '<span class="aseo-kw-dot aseo-kw-dot-pass">✓</span>' : '<span class="aseo-kw-dot aseo-kw-dot-fail">✗</span>'; ?></td>
							<td class="aseo-kw-cell"><?php echo $kp['in_heading'] ? '<span class="aseo-kw-dot aseo-kw-dot-pass">✓</span>' : '<span class="aseo-kw-dot aseo-kw-dot-fail">✗</span>'; ?></td>
							<td class="aseo-kw-cell">
								<?php if ( $kp['count'] > 0 ) : ?>
								<span class="<?php echo esc_attr( $d_class ); ?>"><?php echo esc_html( $kp['count'] ); ?>× (<?php echo esc_html( $kp['density'] ); ?>%)</span>
								<?php else : ?>
								<span class="aseo-kw-dot aseo-kw-dot-fail">✗</span>
								<?php endif; ?>
							</td>
						</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php endif; ?>

				<hr style="border:none;border-top:1px solid #dcdcde;margin:20px 0" />

				<div class="aseo-field">
					<label for="aseo_title">
						SEO Title
						<span class="aseo-counter" id="aseo_title_count">0</span>/60
					</label>
					<input type="text" id="aseo_title" name="aseo_title"
					       value="<?php echo esc_attr( $title ); ?>"
					       placeholder="Leave blank to use the post title"
					       maxlength="70" />
					<div class="aseo-snippet-preview">
						<div class="aseo-snippet-title" id="aseo_preview_title">
							<?php echo esc_html( $title ?: get_the_title( $post->ID ) ); ?>
						</div>
						<div class="aseo-snippet-url">
							<?php echo esc_url( get_permalink( $post->ID ) ?: home_url( '/?p=' . $post->ID ) ); ?>
						</div>
						<div class="aseo-snippet-desc" id="aseo_preview_desc">
							<?php echo esc_html( $description ?: wp_trim_words( wp_strip_all_tags( $post->post_content ), 25 ) ); ?>
						</div>
					</div>
				</div>

				<div class="aseo-field">
					<label for="aseo_description">
						Meta Description
						<span class="aseo-counter" id="aseo_desc_count">0</span>/160
					</label>
					<textarea id="aseo_description" name="aseo_description"
					          rows="3" maxlength="320"
					          placeholder="Leave blank to auto-generate from post content"><?php echo esc_textarea( $description ); ?></textarea>
				</div>

				<div class="aseo-field">
					<label for="aseo_keywords">Meta Keywords <span class="aseo-hint-inline">(optional)</span></label>
					<input type="text" id="aseo_keywords" name="aseo_keywords"
					       value="<?php echo esc_attr( $keywords ); ?>"
					       placeholder="keyword1, keyword2, keyword3" />
				</div>

			</div>

			<!-- Readability Tab -->
			<div class="aseo-tab-content" id="aseo-tab-readability">

				<div class="aseo-score-bar">
					<span class="aseo-score-label">Readability</span>
					<div class="aseo-score-track">
						<div class="aseo-score-fill aseo-score-<?php echo esc_attr( $read_data['color'] ); ?>"
						     style="width:<?php echo esc_attr( $read_data['score'] ); ?>%"></div>
					</div>
					<span class="aseo-score-num"><?php echo esc_html( $read_data['score'] ); ?>/100</span>
				</div>

				<div class="aseo-read-stats">
					<span><?php echo esc_html( $read_data['word_count'] ); ?> words</span>
					&middot;
					<span><?php echo esc_html( $read_data['sentence_count'] ); ?> sentences</span>
					&middot;
					<span>Avg <?php echo esc_html( $read_data['avg_sentence'] ); ?> words/sentence</span>
				</div>

				<div class="aseo-content-stats">
					<div class="aseo-content-stat">
						<span class="aseo-content-stat-label">Internal links</span>
						<span class="aseo-content-stat-value <?php echo $seo_data['internal_links'] === 0 ? 'aseo-stat-warn' : 'aseo-stat-ok'; ?>">
							<?php echo esc_html( $seo_data['internal_links'] ); ?>
						</span>
					</div>
					<div class="aseo-content-stat">
						<span class="aseo-content-stat-label">External links</span>
						<span class="aseo-content-stat-value <?php echo $seo_data['external_links'] === 0 ? 'aseo-stat-warn' : 'aseo-stat-ok'; ?>">
							<?php echo esc_html( $seo_data['external_links'] ); ?>
						</span>
					</div>
					<div class="aseo-content-stat">
						<span class="aseo-content-stat-label">Images</span>
						<span class="aseo-content-stat-value aseo-stat-ok"><?php echo esc_html( $seo_data['images_total'] ); ?></span>
					</div>
					<?php if ( $seo_data['images_no_alt'] > 0 ) : ?>
					<div class="aseo-content-stat">
						<span class="aseo-content-stat-label">Missing alt text</span>
						<span class="aseo-content-stat-value aseo-stat-warn"><?php echo esc_html( $seo_data['images_no_alt'] ); ?></span>
					</div>
					<?php endif; ?>
				</div>

				<?php if ( ! empty( $read_data['tips'] ) ) : ?>
				<ul class="aseo-tips">
					<?php foreach ( $read_data['tips'] as $tip ) : ?>
					<li><?php echo esc_html( $tip ); ?></li>
					<?php endforeach; ?>
				</ul>
				<?php else : ?>
				<p style="color:#46b450;font-weight:600">Readability looks great!</p>
				<?php endif; ?>

				<div class="aseo-faq-help">
					<p><strong>FAQ Rich Results Shortcode</strong> &mdash; lets Google show your Q&amp;A directly in search results.</p>
					<p class="aseo-hint">Paste the shortcode below into your post content, then replace the example questions and answers with your own. Add as many pairs as you need.</p>

					<?php
$snippet = "[aseo_faq]\n\n[aseo_question]Your question here?[/aseo_question]\n[aseo_answer]Your answer here.[/aseo_answer]\n\n[aseo_question]Another question?[/aseo_question]\n[aseo_answer]Another answer.[/aseo_answer]\n\n[/aseo_faq]";
?>
					<script>
					function aseoCopyFaq(btn) {
						var text = <?php echo wp_json_encode( $snippet ); ?>;
						function flash() { btn.textContent = 'Copied!'; setTimeout(function(){ btn.textContent = 'Copy Shortcode'; }, 2000); }
						if (window.isSecureContext && navigator.clipboard) {
							navigator.clipboard.writeText(text).then(flash, function(){ fallback(); });
						} else { fallback(); }
						function fallback() {
							var ta = document.createElement('textarea');
							ta.value = text; ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px';
							document.body.appendChild(ta); ta.focus(); ta.select();
							try { document.execCommand('copy'); flash(); } catch(e){}
							document.body.removeChild(ta);
						}
					}
					</script>

					<table class="aseo-faq-table">
						<tr><td><code>[aseo_faq]</code></td><td class="aseo-hint">Opening wrapper &mdash; required</td></tr>
						<tr><td><code>[aseo_question]...[/aseo_question]</code></td><td class="aseo-hint">One question</td></tr>
						<tr><td><code>[aseo_answer]...[/aseo_answer]</code></td><td class="aseo-hint">The answer to that question</td></tr>
						<tr><td><code>[/aseo_faq]</code></td><td class="aseo-hint">Closing wrapper &mdash; required</td></tr>
					</table>

					<button type="button" class="button" onclick="aseoCopyFaq(this)" style="margin:10px 0 6px">Copy Example Shortcode</button>

					<p class="aseo-hint">Tip: keep answers under 300 characters &mdash; test with <a href="https://search.google.com/test/rich-results" target="_blank">Google Rich Results Test</a>.</p>
				</div>

			</div>

			<!-- Social Tab -->
			<div class="aseo-tab-content" id="aseo-tab-social">

				<div class="aseo-field">
					<label>Open Graph / Social Image</label>
					<div class="aseo-og-image-wrap">
						<?php if ( $og_image ) : ?>
						<img src="<?php echo esc_url( $og_image ); ?>" class="aseo-og-preview" id="aseo_og_preview" />
						<?php else : ?>
						<img src="" class="aseo-og-preview" id="aseo_og_preview" style="display:none" />
						<?php endif; ?>
						<input type="hidden" id="aseo_og_image" name="aseo_og_image"
						       value="<?php echo esc_attr( $og_image ); ?>" />
						<button type="button" class="button" id="aseo_og_upload">Choose Image</button>
						<button type="button" class="button" id="aseo_og_remove"
						        <?php if ( ! $og_image ) echo 'style="display:none"'; ?>>Remove</button>
					</div>
					<p class="aseo-hint">Recommended: 1200×630px. Falls back to featured image, then the default OG image in Settings.</p>
				</div>

			</div>

			<!-- Advanced Tab -->
			<div class="aseo-tab-content" id="aseo-tab-advanced">

				<div class="aseo-field">
					<label for="aseo_canonical">Canonical URL</label>
					<input type="url" id="aseo_canonical" name="aseo_canonical"
					       value="<?php echo esc_attr( $canonical ); ?>"
					       placeholder="<?php echo esc_attr( get_permalink( $post->ID ) ?: '' ); ?>" />
					<p class="aseo-hint">Leave blank to use the default permalink.</p>
				</div>

				<div class="aseo-field aseo-field-row">
					<label>
						<input type="checkbox" name="aseo_noindex" value="1"
						       <?php checked( $noindex, '1' ); ?> />
						No Index &mdash; tell search engines not to index this page
					</label>
				</div>

				<div class="aseo-field aseo-field-row">
					<label>
						<input type="checkbox" name="aseo_nofollow" value="1"
						       <?php checked( $nofollow, '1' ); ?> />
						No Follow &mdash; tell search engines not to follow links on this page
					</label>
				</div>

			</div>

		</div>
		<?php
	}

	public function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! isset( $_POST['ateculus_seo_nonce'] ) ) return;
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ateculus_seo_nonce'] ) ), 'ateculus_seo_save' ) ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		$fields = array(
			'title'       => 'sanitize_text_field',
			'description' => 'sanitize_textarea_field',
			'keywords'    => 'sanitize_text_field',
			'focus_kw'    => 'sanitize_text_field',
			'canonical'   => 'esc_url_raw',
			'og_image'    => 'esc_url_raw',
		);

		foreach ( $fields as $field => $sanitizer ) {
			$val = isset( $_POST[ 'aseo_' . $field ] )
				? call_user_func( $sanitizer, wp_unslash( $_POST[ 'aseo_' . $field ] ) )
				: '';
			update_post_meta( $post_id, '_aseo_' . $field, $val );
		}

		update_post_meta( $post_id, '_aseo_noindex',  isset( $_POST['aseo_noindex']  ) ? '1' : '' );
		update_post_meta( $post_id, '_aseo_nofollow', isset( $_POST['aseo_nofollow'] ) ? '1' : '' );
	}
}
