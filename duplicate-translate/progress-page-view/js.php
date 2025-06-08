<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

?>
<script type="text/javascript">
var ajaxurl = '<?php echo esc_url( admin_url('admin-ajax.php') ); ?>';
var ajaxnonce = '<?php echo esc_js( wp_create_nonce('ajax_nonce') ); ?>'; // General nonce for our AJAX actions
var originalPostId = <?php echo intval( $_GET['post_id'] ); ?>;
var parallelBatchSize = 10; // How many blocks to translate concurrently

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
		blockProgressInfo.text('Translated ' + processedBlockCount + ' of ' + totalBlocks + ' blocks. Active API calls: ' + activeRequests);
	}

	// Handle form submission
	$('#start-translation').on('click', function(e) {
		e.preventDefault();
		var targetLanguage = $('#target-language').val();
		var translationContext = $('#translation-context').val();
		
		if (!targetLanguage) {
			alert('<?php _e("Please select a target language.", "duplicate-translate"); ?>');
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
		addProgress('<?php _e("Initiating translation job...", "duplicate-translate"); ?>');
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
						addProgress('<?php _e("No content blocks found to translate. Finalizing...", "duplicate-translate"); ?>');
						finalizeJob();
					} else {
						addProgress('<?php _e("Starting block translations...", "duplicate-translate"); ?> (' + totalBlocks + ' blocks)');
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
		 // If all blocks are processed or in_progress but no active requests (e.g. initial run didn't fill batch), and not all done, this might indicate an issue.
		// However, the main check is `processedBlockCount === totalBlocks`.
	}

	// 3. Translate Single Block
	function translateSingleBlock(blockMetaIndex, blockMeta) {
		// The blockMeta here could contain the actual block data or just an identifier if server fetches it
		// For this example, let's assume blockMeta.raw_block is passed from `initiate_job`
		$.ajax({
			url: ajaxurl, type: 'POST', dataType: 'json',
			data: {
				action: 'process_block_translation',
				job_id: jobId,
				block_meta_index: blockMetaIndex,
				target_language: targetLanguage,
				translation_context: translationContext,
				_ajax_nonce: ajaxnonce
			},
			success: function(response) {
				activeRequests--;
				processedBlockCount++;
				if (response.success) {
					addProgress('Block ' + (blockMetaIndex + 1) + ' translated.', 'success');
					translatedBlocksData[blockMetaIndex] = response.data.translated_block_content; // Store serialized block
					blocksToTranslateMeta[blockMetaIndex].status = 'done';
				} else {
					addProgress('Error translating block ' + (blockMetaIndex + 1) + ': ' + (response.data.message || 'Unknown error'), 'error');
					// Store original block content on error to prevent data loss
					translatedBlocksData[blockMetaIndex] = response.data.original_block_content; // Server should send this
					blocksToTranslateMeta[blockMetaIndex].status = 'failed';
				}
				processBlockQueue(); // Check if more blocks can be processed
			},
			error: function(jqXHR, ts, et) {
				activeRequests--;
				processedBlockCount++; // Count as processed even on error to move on
				addProgress('AJAX Error translating block ' + (blockMetaIndex + 1) + ': ' + ts + ' - ' + et, 'error');
				blocksToTranslateMeta[blockMetaIndex].status = 'failed_ajax';
				processBlockQueue();
			}
		});
	}

	// 4. Finalize Job
	function finalizeJob() {
		spinner.show();
		updateBlockProgress(); // Final update
		addProgress('<?php _e("All blocks processed. Finalizing post...", "duplicate-translate"); ?>');

		// Filter out any undefined slots if some blocks failed catastrophically (should ideally not happen if server sends original on error)
		var finalBlockArray = translatedBlocksData.filter(function (el) { return el != null; });
		if (finalBlockArray.length !== totalBlocks && totalBlocks > 0) {
			 addProgress('Warning: Some blocks might be missing due to critical errors. Proceeding with available blocks.', 'error');
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
					addProgress('<?php _e("Translation process complete!", "duplicate-translate"); ?>', 'success');
					if(response.data.edit_link) {
						finalLink.html('<p class="done"><a href="' + response.data.edit_link + '" target="_blank"><?php _e("Edit Translated Post", "duplicate-translate"); ?> (ID: ' + newPostId + ')</a></p>');
					}
					addProgress('<?php _e("You can now close this tab.", "duplicate-translate"); ?>');
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
</script>