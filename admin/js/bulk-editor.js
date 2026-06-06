/* Ateculus-SEO Bulk Editor */
(function ($) {
	'use strict';

	function getLen(val) { return val.length; }

	function updateCounter($input, $counter, soft, hard) {
		var len = getLen($input.val());
		$counter.text(len + '/' + hard);
		$counter.removeClass('good warn over');
		if (len >= soft && len <= hard) $counter.addClass('good');
		else if (len > hard)            $counter.addClass('over');
		else if (len > 0)               $counter.addClass('warn');
	}

	// Live counters
	$(document).on('input', '.aseo-bulk-title', function () {
		updateCounter($(this), $(this).next('.aseo-bulk-counter'), 30, 60);
	});
	$(document).on('input', '.aseo-bulk-desc', function () {
		updateCounter($(this), $(this).next('.aseo-bulk-counter'), 100, 160);
	});

	function saveRow($row, cb) {
		var postId = $row.data('post-id');
		var title  = $row.find('.aseo-bulk-title').val();
		var desc   = $row.find('.aseo-bulk-desc').val();

		$.post(aseoBulk.ajaxurl, {
			action:      'aseo_bulk_save',
			nonce:       aseoBulk.nonce,
			post_id:     postId,
			title:       title,
			description: desc
		}, function (res) {
			if (res.success) {
				var $dot = $row.find('.aseo-row-dot');
				$dot.attr('class', 'aseo-col-dot aseo-row-dot aseo-col-' + res.data.color);
				$dot.text(res.data.score);
				$dot.attr('title', 'SEO Score: ' + res.data.score + '/100');
				$row.addClass('aseo-row-saved');
				setTimeout(function () { $row.removeClass('aseo-row-saved'); }, 1200);
			}
			if (cb) cb(res.success);
		});
	}

	// Per-row save button
	$(document).on('click', '.aseo-row-save', function () {
		var $row = $(this).closest('tr.aseo-bulk-row');
		var $btn = $(this);
		$btn.text('Saving…').prop('disabled', true);
		saveRow($row, function (ok) {
			$btn.text(ok ? 'Saved ✓' : 'Error').prop('disabled', false);
			setTimeout(function () { $btn.text('Save'); }, 1500);
		});
	});

	// Save All
	$('#aseo-save-all').on('click', function () {
		var $btn  = $(this);
		var $rows = $('tr.aseo-bulk-row');
		var done  = 0;
		var total = $rows.length;

		if (!total) return;
		$btn.text('Saving 0/' + total + '…').prop('disabled', true);

		var $notice = $('#aseo-bulk-notice');
		$notice.hide();

		$rows.each(function () {
			var $row = $(this);
			saveRow($row, function () {
				done++;
				$btn.text('Saving ' + done + '/' + total + '…');
				if (done === total) {
					$btn.text('Save All').prop('disabled', false);
					$notice.removeClass('notice-error').addClass('notice notice-success')
					       .text('All ' + total + ' posts saved.').show();
				}
			});
		});
	});

}(jQuery));
