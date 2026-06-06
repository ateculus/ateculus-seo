/* Ateculus-SEO Admin JS */
(function ($) {
	'use strict';

	// ---------- Always-visible meta box ----------
	$(function () {
		var $box = $( '#ateculus_seo' );
		if ( ! $box.length ) return;

		// Strip closed class immediately and whenever WP tries to add it back
		$box.removeClass( 'closed' );
		var observer = new MutationObserver( function () {
			if ( $box.hasClass( 'closed' ) ) $box.removeClass( 'closed' );
		} );
		observer.observe( $box[0], { attributes: true, attributeFilter: [ 'class' ] } );

		// Block the postbox toggle click handler
		$box.find( '.postbox-header' ).on( 'click', function ( e ) {
			e.stopImmediatePropagation();
		} );
	} );

	// ---------- Tabs ----------
	$(document).on('click', '.aseo-tab', function () {
		var tab = $(this).data('tab');
		$('.aseo-tab').removeClass('active');
		$('.aseo-tab-content').removeClass('active');
		$(this).addClass('active');
		$('#aseo-tab-' + tab).addClass('active');
	});

	// ---------- Character Counters (meta box) ----------
	function updateCounter($input, counterId, soft, hard) {
		var len = $input.val().length;
		var $c  = $(counterId);
		$c.text(len);
		$c.removeClass('good warn over');
		if (len === 0)             return;
		if (len >= soft && len <= hard) $c.addClass('good');
		else if (len <= hard)      $c.addClass('warn');
		else                       $c.addClass('over');
	}

	$('#aseo_title').on('input', function () {
		updateCounter($(this), '#aseo_title_count', 30, 60);
		$('#aseo_preview_title').text($(this).val() || $('#aseo_title').attr('placeholder') || '');
	}).trigger('input');

	$('#aseo_description').on('input', function () {
		updateCounter($(this), '#aseo_desc_count', 100, 160);
		$('#aseo_preview_desc').text($(this).val());
	}).trigger('input');

	// ---------- OG Image Uploader (meta box) ----------
	var mediaFrame;

	function makeUploader(uploadBtn, removeBtn, inputField, previewImg) {
		$(document).on('click', uploadBtn, function (e) {
			e.preventDefault();
			if (mediaFrame) { mediaFrame.open(); return; }
			mediaFrame = wp.media({
				title: 'Choose Social Image',
				button: { text: 'Use this image' },
				multiple: false,
				library: { type: 'image' }
			});
			mediaFrame.on('select', function () {
				var att = mediaFrame.state().get('selection').first().toJSON();
				$(inputField).val(att.url);
				$(previewImg).attr('src', att.url).show();
				$(removeBtn).show();
			});
			mediaFrame.open();
		});

		$(document).on('click', removeBtn, function () {
			$(inputField).val('');
			$(previewImg).attr('src', '').hide();
			$(this).hide();
		});
	}

	// ---------- Color Pickers ----------
	if ( $.fn.wpColorPicker ) {
		$('.aseo-color-picker').wpColorPicker({
			clear: function() {
				$(this).val('').trigger('change');
			}
		});
	}

	if (typeof wp !== 'undefined' && wp.media) {
		makeUploader('#aseo_og_upload', '#aseo_og_remove', '#aseo_og_image', '#aseo_og_preview');

		// Settings page default OG image
		var defaultMediaFrame;
		$(document).on('click', '#aseo_default_og_upload', function (e) {
			e.preventDefault();
			if (defaultMediaFrame) { defaultMediaFrame.open(); return; }
			defaultMediaFrame = wp.media({
				title: 'Choose Default Social Image',
				button: { text: 'Use this image' },
				multiple: false,
				library: { type: 'image' }
			});
			defaultMediaFrame.on('select', function () {
				var att = defaultMediaFrame.state().get('selection').first().toJSON();
				$('#aseo_default_og_field').val(att.url);
				$('#aseo_default_og_preview').attr('src', att.url).show();
				$('#aseo_default_og_remove').show();
			});
			defaultMediaFrame.open();
		});
		$(document).on('click', '#aseo_default_og_remove', function () {
			$('#aseo_default_og_field').val('');
			$('#aseo_default_og_preview').attr('src', '').hide();
			$(this).hide();
		});

		// Generic media button for settings fields (.aseo-media-btn)
		var mediaFrames = {};
		$(document).on('click', '.aseo-media-btn', function (e) {
			e.preventDefault();
			var target = $(this).data('target');
			var $field = $('input[name="' + target + '"]');
			if (!mediaFrames[target]) {
				mediaFrames[target] = wp.media({ title: 'Choose Image', button: { text: 'Use this image' }, multiple: false, library: { type: 'image' } });
				mediaFrames[target].on('select', function () {
					var att = mediaFrames[target].state().get('selection').first().toJSON();
					$field.val(att.url);
				});
			}
			mediaFrames[target].open();
		});
	}


}(jQuery));
