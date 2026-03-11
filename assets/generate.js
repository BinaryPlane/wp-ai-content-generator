(function($){
	'use strict';

	var $form = $('#aicg-generate-form');
	var $progress = $('#aicg-progress');
	var $fill = $('.aicg-progress-fill');
	var $progressText = $('.aicg-progress-text');
	var $progressStatus = $('.aicg-progress-status');
	var $results = $('#aicg-results');
	var $resultsList = $('#aicg-results-list');
	var total = 0;
	var current = 0;
	var aborted = false;

	function updateProgress(done, status) {
		current = done;
		var pct = total ? Math.round((done / total) * 100) : 0;
		$fill.css('width', pct + '%');
		$progressText.text(done + ' / ' + total);
		if (status) {
			$progressStatus.text(status);
		}
	}

	function addResult(post) {
		var html = '<li><a href="' + (post.edit_url || '#') + '" target="_blank">' + (post.title || 'Post #' + post.post_id) + '</a></li>';
		$resultsList.append(html);
	}

	function runNext(topic, wordCount, index, delaySec) {
		if (aborted || index > total) {
			if (total > 0) {
				$progressStatus.text(current === total ? 'Done.' : 'Stopped.');
				if (current > 0) {
					$results.show();
				}
			}
			$('#aicg-start').prop('disabled', false);
			return;
		}
		if (typeof delaySec === 'undefined' || delaySec === null) {
			delaySec = parseInt($('#aicg-delay').val(), 10);
		}
		if (isNaN(delaySec) || delaySec < 0) {
			delaySec = 0;
		}
		delaySec = Math.min(120, delaySec);
		function doRequest() {
			$progressStatus.text('Generating post ' + index + ' of ' + total + '...');
			$.post(aicgGenerate.ajax_url, {
				action: 'aicg_generate_post',
				nonce: aicgGenerate.nonce,
				topic: topic,
				word_count: wordCount
			}).done(function(res) {
				if (res.success && res.data) {
					addResult(res.data);
					updateProgress(index, 'Post created: ' + res.data.title);
					if (delaySec > 0 && index < total) {
						$progressStatus.text('Waiting ' + delaySec + 's to avoid rate limit...');
						setTimeout(function() {
							runNext(topic, wordCount, index + 1, delaySec);
						}, delaySec * 1000);
					} else {
						runNext(topic, wordCount, index + 1, delaySec);
					}
				} else {
					$progressStatus.text('Error: ' + (res.data && res.data.message ? res.data.message : 'Unknown error'));
					updateProgress(index - 1);
					$('#aicg-start').prop('disabled', false);
				}
			}).fail(function(xhr, status, err) {
				$progressStatus.text('Request failed: ' + (err || status));
				updateProgress(index - 1);
				$('#aicg-start').prop('disabled', false);
			});
		}
		doRequest();
	}

	$form.on('submit', function(e) {
		e.preventDefault();
		var topic = $('#aicg-topic').val().trim();
		var count = parseInt($('#aicg-count').val(), 10) || 5;
		var wordCount = parseInt($('#aicg-word-count').val(), 10) || 800;
		if (!topic) {
			alert('Please enter a content topic.');
			return;
		}
		count = Math.max(1, Math.min(50, count));
		wordCount = Math.max(200, Math.min(5000, wordCount));
		var delaySec = parseInt($('#aicg-delay').val(), 10);
		if (isNaN(delaySec) || delaySec < 0) delaySec = 0;
		delaySec = Math.min(120, delaySec);
		aborted = false;
		total = count;
		current = 0;
		$results.hide();
		$resultsList.empty();
		$progress.show();
		updateProgress(0, 'Starting...');
		$('#aicg-start').prop('disabled', true);
		runNext(topic, wordCount, 1, delaySec);
	});

})(jQuery);
