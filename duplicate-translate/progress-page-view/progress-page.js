const { ajaxurl, ajaxnonce, originalPostId, i18n, parallelBatchSize } = progressPageData;

jQuery(document).ready(function($) {
	var progressLog = $('#progress-log');
	var finalLink = $('#final-link');
	var spinner = $('#spinner');
	var blockProgressInfo = $('#block-progress-info');
	var translationForm = $('#translation-form');
	var progressContainer = $('#progress-container');

	var jobId = null;
	var newPostId = null;
	var blocksToTranslateMeta = []; // Array of { index: i, blockName: 'core/para...', status: 'pending' }
	var translatedBlocksData = []; // Array to store translated block structures in order
	var activeRequests = 0;
	var totalBlocks = 0;
	var processedBlockCount = 0;

	function addProgress(message, type = 'info') {
		var date = new Date();
		var timeString = '[' + ('0' + date.getHours()).slice(-2) + ':' + ('0' + date.getMinutes()).slice(-2) + ':' + ('0' + date.getSeconds()).slice(-2) + '] ';
		var pClass = type === 'error' ? ' class="error"' : (type === 'success' ? ' class="success"' : '');
		progressLog.append('<p' + pClass + '>' + timeString + message + '</p>');
		progressLog.scrollTop(progressLog[0].scrollHeight);
	}

	function updateBlockProgress() {
		$('#block-progress-bar').val(processedBlockCount/totalBlocks);
		blockProgressInfo.text(i18n.blocksTranslated.replace('%d1', processedBlockCount).replace('%d2', totalBlocks) + ' ' + i18n.activeAPI.replace('%d', activeRequests));
	}

	// Handle form submission
	$('#start-translation').on('click', function(e) {
		e.preventDefault();
		var targetLanguage = $('#target-language').val();
		var translationContext = $('#translation-context').val();
		
		if (!targetLanguage) {
			alert(i18n.selectLanguage);
			return;
		}

		// Hide form and show progress container
		translationForm.hide();
		progressContainer.show();
		spinner.show();

		// Start the translation process with the selected options
		initiateTranslationJob(targetLanguage, translationContext);
	});

	// 1. Initiate Job
	function initiateTranslationJob(targetLanguage, translationContext) {
		addProgress(i18n.initiatingJob);
		$.ajax({
			url: ajaxurl, type: 'POST', dataType: 'json',
			data: {
				action: 'initiate_job',
				original_post_id: originalPostId,
				target_language: targetLanguage,
				translation_context: translationContext,
				_ajax_nonce: ajaxnonce
			},
			success: function(response) {
				if (response.success) {
					jobId = response.data.job_id;
					newPostId = response.data.new_post_id;
					blocksToTranslateMeta = response.data.blocks_meta;
					totalBlocks = blocksToTranslateMeta.length;
					translatedBlocksData = new Array(totalBlocks); // Initialize array for ordered results

					addProgress(response.data.message, 'success');
					if (totalBlocks === 0) {
						addProgress(i18n.noBlocks);
						finalizeJob();
					} else {
						addProgress(i18n.startingBlockTranslations + ' (' + totalBlocks + ' ' + i18n.blocks + ')');
						processBlockQueue();
					}
				} else {
					spinner.hide();
					addProgress('Error initiating job: ' + (response.data.message || 'Unknown error'), 'error');
				}
			},
			error: function(jqXHR, ts, et) {
				spinner.hide();
				addProgress('AJAX Error initiating job: ' + ts + ' - ' + et, 'error');
			}
		});
	}

	// 2. Process Block Queue (Manages concurrent requests)
	function processBlockQueue() {
		updateBlockProgress();
		if (processedBlockCount === totalBlocks && activeRequests === 0) {
			finalizeJob();
			return;
		}

		for (var i = 0; i < blocksToTranslateMeta.length; i++) {
			if (activeRequests >= parallelBatchSize) break; // Limit concurrent requests

			if (blocksToTranslateMeta[i].status === 'pending') {
				blocksToTranslateMeta[i].status = 'in_progress';
				activeRequests++;
				translateSingleBlock(i, blocksToTranslateMeta[i]); // Pass index and block meta
				updateBlockProgress();
			}
		}
	}

	// 3. Translate Single Block
	function translateSingleBlock(blockMetaIndex, blockMeta) {
		$.ajax({
			url: ajaxurl, type: 'POST', dataType: 'json',
			data: {
				action: 'process_block_translation',
				job_id: jobId,
				block_meta_index: blockMetaIndex,
				_ajax_nonce: ajaxnonce
			},
			success: function(response) {
				activeRequests--;
				processedBlockCount++;
				if (response.success) {
					addProgress(i18n.blockTranslated.replace('%d', blockMetaIndex + 1), 'success');
					translatedBlocksData[blockMetaIndex] = response.data.translated_block_content; // Store serialized block
					blocksToTranslateMeta[blockMetaIndex].status = 'done';
				} else {
					addProgress(i18n.errorTranslatingBlock.replace('%d', blockMetaIndex + 1) + (response.data.message || 'Unknown error'), 'error');
					// Store original block content on error to prevent data loss
					translatedBlocksData[blockMetaIndex] = response.data.original_block_content; // Server should send this
					blocksToTranslateMeta[blockMetaIndex].status = 'failed';
				}
				processBlockQueue(); // Check if more blocks can be processed
			},
			error: function(jqXHR, ts, et) {
				activeRequests--;
				processedBlockCount++; // Count as processed even on error to move on
				addProgress(i18n.ajaxErrorTranslatingBlock.replace('%d', blockMetaIndex + 1) + ts + ' - ' + et, 'error');
				blocksToTranslateMeta[blockMetaIndex].status = 'failed_ajax';
				processBlockQueue();
			}
		});
	}

	// 4. Finalize Job
	function finalizeJob() {
		spinner.show();
		updateBlockProgress(); // Final update
		addProgress(i18n.finalizingPost);
		blockProgressInfo.hide();

		var finalBlockArray = translatedBlocksData.filter(function (el) { return el != null; });
		if (finalBlockArray.length !== totalBlocks && totalBlocks > 0) {
			 addProgress(i18n.missingBlocksWarning, 'error');
		}

		$.ajax({
			url: ajaxurl, type: 'POST', dataType: 'json',
			data: {
				action: 'finalize_job',
				job_id: jobId,
				new_post_id: newPostId,
				translated_blocks_serialized: finalBlockArray, // Array of serialized block strings
				_ajax_nonce: ajaxnonce
			},
			success: function(response) {
				spinner.hide();
				if (response.success) {
					addProgress(i18n.complete, 'success');
					if(response.data.edit_link) {
						finalLink.prepend('<p class="done"><a href="' + response.data.edit_link + '" target="_blank">' + i18n.editPost + ' (ID: ' + newPostId + ')</a></p>');
					}
					addProgress(i18n.canClose);
				} else {
					addProgress('Error finalizing job: ' + (response.data.message || 'Unknown error'), 'error');
				}
			},
			error: function(jqXHR, ts, et) {
				spinner.hide();
				addProgress('AJAX Error finalizing job: ' + ts + ' - ' + et, 'error');
			}
		});
	}
}); 