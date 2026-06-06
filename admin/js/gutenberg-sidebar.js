/* Ateculus-SEO Gutenberg Sidebar */
(function () {
	'use strict';

	if ( ! wp || ! wp.plugins || ! wp.components || ! wp.data || ! wp.element ) return;

	var registerPlugin = wp.plugins.registerPlugin;

	// PluginSidebar moved from wp.editPost → wp.editor in WP 6.2
	var editorPkg                 = ( wp.editor && wp.editor.PluginSidebar ) ? wp.editor : wp.editPost;
	var PluginSidebar             = editorPkg && editorPkg.PluginSidebar;
	var PluginSidebarMoreMenuItem = editorPkg && editorPkg.PluginSidebarMoreMenuItem;

	if ( ! PluginSidebar ) return;

	// "SEO" text icon as an SVG so it fits the 24px toolbar slot
	var seoIcon = h( 'svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24 },
		h( 'text', {
			x: '50%', y: '50%',
			dominantBaseline: 'central',
			textAnchor: 'middle',
			fontSize: '8.5',
			fontWeight: 'bold',
			fontFamily: 'Arial, sans-serif',
			fill: 'currentColor',
			letterSpacing: '0.5'
		}, 'SEO' )
	);
	var PanelBody                                = wp.components.PanelBody;
	var TextControl                              = wp.components.TextControl;
	var TextareaControl                          = wp.components.TextareaControl;
	var ToggleControl                            = wp.components.ToggleControl;
	var Button                                   = wp.components.Button;
	var useSelect                                = wp.data.useSelect;
	var useDispatch                              = wp.data.useDispatch;
	var Fragment                                 = wp.element.Fragment;
	var h                                        = wp.element.createElement;

	// ── Live SEO score ─────────────────────────────────────────
	function calcScore( focusKw, title, desc ) {
		var score = 0;
		var tips  = [];
		var kw    = ( focusKw || '' ).toLowerCase();
		var t     = ( title   || '' ).toLowerCase();
		var d     = ( desc    || '' ).toLowerCase();

		if ( kw ) {
			score += 10;
			if ( t.indexOf( kw ) !== -1 ) { score += 20; }
			else { tips.push( 'Add your focus keyword to the SEO title.' ); }
			if ( d.indexOf( kw ) !== -1 ) { score += 20; }
			else { tips.push( 'Add your focus keyword to the meta description.' ); }
		} else {
			tips.push( 'Set a focus keyword for this post.' );
		}

		var tLen = ( title || '' ).length;
		if ( tLen >= 30 && tLen <= 60 ) { score += 15; }
		else if ( tLen > 0 )            { score += 5; tips.push( 'SEO title should be 30–60 characters (currently ' + tLen + ').' ); }
		else                            { tips.push( 'Add a custom SEO title.' ); }

		var dLen = ( desc || '' ).length;
		if ( dLen >= 100 && dLen <= 160 ) { score += 15; }
		else if ( dLen > 0 )              { score += 5; tips.push( 'Meta description should be 100–160 characters (currently ' + dLen + ').' ); }
		else                              { tips.push( 'Add a meta description.' ); }

		return { score: Math.min( 100, score ), tips: tips };
	}

	function scoreColor( score ) {
		return score >= 70 ? '#46b450' : score >= 40 ? '#ffb900' : '#dc3232';
	}

	// ── Sub-components ─────────────────────────────────────────
	function ScoreBar( props ) {
		var score = props.score;
		var color = scoreColor( score );
		return h( 'div', { className: 'aseo-gb-score-wrap' },
			h( 'div', { className: 'aseo-gb-score-row' },
				h( 'span', { className: 'aseo-gb-score-label' }, 'SEO Score' ),
				h( 'span', { style: { color: color, fontWeight: 700 } }, score + '/100' )
			),
			h( 'div', { className: 'aseo-gb-track' },
				h( 'div', { className: 'aseo-gb-fill', style: { width: score + '%', background: color } } )
			)
		);
	}

	function Counter( props ) {
		var len   = ( props.value || '' ).length;
		var color = len === 0                          ? '#999'
		          : len >= props.soft && len <= props.hard ? '#46b450'
		          : len > props.hard                  ? '#dc3232'
		          :                                    '#ffb900';
		return h( 'span', { style: { fontSize: 11, color: color, marginLeft: 6 } },
			len + '/' + props.hard
		);
	}

	// ── Main sidebar component ──────────────────────────────────
	function AteculusSEOSidebar() {
		var meta = useSelect( function( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		} );
		var editPost = useDispatch( 'core/editor' ).editPost;

		function set( key, val ) {
			var update = {};
			update[ key ] = val;
			editPost( { meta: update } );
		}

		var focusKw    = meta._aseo_focus_kw    || '';
		var title      = meta._aseo_title       || '';
		var description= meta._aseo_description || '';
		var keywords   = meta._aseo_keywords    || '';
		var canonical  = meta._aseo_canonical   || '';
		var ogImage    = meta._aseo_og_image     || '';
		var noindex    = meta._aseo_noindex      === '1';
		var nofollow   = meta._aseo_nofollow     === '1';

		var result = calcScore( focusKw, title, description );

		function openMedia() {
			var frame = wp.media( {
				title:    'Choose Social Image',
				button:   { text: 'Use this image' },
				multiple: false,
				library:  { type: 'image' }
			} );
			frame.on( 'select', function () {
				var att = frame.state().get( 'selection' ).first().toJSON();
				set( '_aseo_og_image', att.url );
			} );
			frame.open();
		}

		return h( Fragment, null,

			h( PluginSidebarMoreMenuItem, { target: 'ateculus-seo-sidebar', icon: seoIcon },
				'Ateculus-SEO'
			),

			h( PluginSidebar, {
				name:  'ateculus-seo-sidebar',
				title: 'Ateculus-SEO',
				icon:  seoIcon,
			},

				// ── SEO panel ──────────────────────────────────
				h( PanelBody, { title: 'SEO', initialOpen: true },

					// Score bar + tips live inside the panel for proper padding
					h( ScoreBar, { score: result.score } ),
					result.tips.length > 0 && h( 'ul', { className: 'aseo-gb-tips' },
						result.tips.map( function( tip, i ) {
							return h( 'li', { key: i }, tip );
						} )
					),

					h( TextControl, {
						label:       'Focus Keyword',
						value:       focusKw,
						onChange:    function( v ) { set( '_aseo_focus_kw', v ); },
						placeholder: 'Main keyword for this post'
					} ),

					h( 'div', { className: 'aseo-gb-field' },
						h( 'span', { className: 'aseo-gb-label' },
							'SEO Title',
							h( Counter, { value: title, soft: 30, hard: 60 } )
						),
						h( TextControl, {
							label:              'SEO Title',
							hideLabelFromVision: true,
							value:              title,
							onChange:           function( v ) { set( '_aseo_title', v ); },
							placeholder:        'Leave blank to use the post title'
						} )
					),

					h( 'div', { className: 'aseo-gb-field' },
						h( 'span', { className: 'aseo-gb-label' },
							'Meta Description',
							h( Counter, { value: description, soft: 100, hard: 160 } )
						),
						h( TextareaControl, {
							label:              'Meta Description',
							hideLabelFromVision: true,
							value:              description,
							onChange:           function( v ) { set( '_aseo_description', v ); },
							placeholder:        'Leave blank to auto-generate from content',
							rows:               3
						} )
					),

					h( TextControl, {
						label:       'Meta Keywords',
						value:       keywords,
						onChange:    function( v ) { set( '_aseo_keywords', v ); },
						placeholder: 'keyword1, keyword2, keyword3'
					} )

				),

				// ── Social panel ────────────────────────────────
				h( PanelBody, { title: 'Social', initialOpen: false },
					h( 'div', { className: 'aseo-gb-field' },
						h( 'span', { className: 'aseo-gb-label' }, 'Open Graph Image' ),
						ogImage && h( 'img', { src: ogImage, className: 'aseo-gb-og-preview' } ),
						h( 'div', { style: { display: 'flex', gap: 8, marginTop: 8 } },
							h( Button, {
								variant: 'secondary',
								onClick: openMedia,
								__next40pxDefaultSize: true,
							}, ogImage ? 'Change Image' : 'Choose Image' ),
							ogImage && h( Button, {
								variant:        'secondary',
								isDestructive:  true,
								onClick:        function() { set( '_aseo_og_image', '' ); },
								__next40pxDefaultSize: true,
							}, 'Remove' )
						),
						h( 'p', { className: 'aseo-gb-hint' }, 'Falls back to featured image, then the default in Settings.' )
					)
				),

				// ── Advanced panel ──────────────────────────────
				h( PanelBody, { title: 'Advanced', initialOpen: false },
					h( TextControl, {
						label:       'Canonical URL',
						type:        'url',
						value:       canonical,
						onChange:    function( v ) { set( '_aseo_canonical', v ); },
						placeholder: 'Leave blank for default permalink'
					} ),
					h( ToggleControl, {
						label:   'No Index',
						help:    'Tell search engines not to index this page.',
						checked: noindex,
						onChange: function( v ) { set( '_aseo_noindex', v ? '1' : '' ); }
					} ),
					h( ToggleControl, {
						label:   'No Follow',
						help:    'Tell search engines not to follow links on this page.',
						checked: nofollow,
						onChange: function( v ) { set( '_aseo_nofollow', v ? '1' : '' ); }
					} )
				)

			) // end PluginSidebar
		); // end Fragment
	}

	registerPlugin( 'ateculus-seo', {
		render: AteculusSEOSidebar,
		icon:   seoIcon
	} );

}() );
