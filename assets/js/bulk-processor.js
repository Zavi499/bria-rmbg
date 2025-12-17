/**
 * Bulk Processor JavaScript
 * Handles bulk image processing for premium users
 */

import BackgroundRemover from './background-remover.js';

(function($) {
  'use strict';

  // Check if user has premium access
  if (!wpBgRemoverConfig.isPremium) {
    console.warn('Bulk processor is a premium feature');
    return;
  }

  let bgRemover = null;
  let imageQueue = [];
  let processedResults = [];
  let isProcessing = false;
  let isPaused = false;
  let currentIndex = 0;

  /**
   * Initialize bulk processor
   */
  function init() {
    bgRemover = new BackgroundRemover();

    // File upload
    $('#select-bulk-files-btn').on('click', function() {
      $('#bulk-image-upload').click();
    });

    $('#bulk-image-upload').on('change', handleBulkFileSelect);

    // Drag and drop
    const uploadBox = $('#bulk-upload-box');
    uploadBox.on('dragover', function(e) {
      e.preventDefault();
      $(this).addClass('dragover');
    });

    uploadBox.on('dragleave', function(e) {
      e.preventDefault();
      $(this).removeClass('dragover');
    });

    uploadBox.on('drop', function(e) {
      e.preventDefault();
      $(this).removeClass('dragover');

      const files = Array.from(e.originalEvent.dataTransfer.files);
      handleBulkFiles(files);
    });

    // Control buttons
    $('#bulk-start-btn').on('click', startBulkProcessing);
    $('#bulk-pause-btn').on('click', pauseBulkProcessing);
    $('#bulk-clear-btn').on('click', clearBulkQueue);

    // Result buttons
    $('#download-all-btn').on('click', downloadAllAsZip);
    $('#save-all-to-library-btn').on('click', saveAllToLibrary);
  }

  /**
   * Handle bulk file selection
   */
  function handleBulkFileSelect(e) {
    const files = Array.from(e.target.files);
    handleBulkFiles(files);
  }

  /**
   * Handle bulk files
   */
  function handleBulkFiles(files) {
    // Filter valid images
    const validFiles = files.filter(file =>
      file.type.match('image/(jpeg|png|webp)')
    );

    if (validFiles.length === 0) {
      alert('Please select valid image files (JPG, PNG, or WebP)');
      return;
    }

    // Check bulk limit
    const bulkLimit = wpBgRemoverConfig.settings.bulk_limit || 50;
    if (validFiles.length > bulkLimit) {
      alert(`You can only process up to ${bulkLimit} images at once`);
      return;
    }

    // Add to queue
    imageQueue = validFiles.map(file => ({
      file: file,
      status: 'pending',
      processedBlob: null,
      error: null
    }));

    // Show queue UI
    renderQueue();
    $('#bulk-upload-box').hide();
    $('#bulk-queue').show();
  }

  /**
   * Render queue UI
   */
  function renderQueue() {
    const $items = $('#bulk-items');
    $items.empty();

    imageQueue.forEach((item, index) => {
      const statusClass = item.status;
      const statusText = item.status.charAt(0).toUpperCase() + item.status.slice(1);

      const itemHtml = `
        <div class="bulk-item bulk-item-${statusClass}" data-index="${index}">
          <div class="item-thumbnail">
            <img src="${URL.createObjectURL(item.file)}" alt="${item.file.name}">
          </div>
          <div class="item-info">
            <div class="item-name">${item.file.name}</div>
            <div class="item-status">${statusText}</div>
            ${item.error ? `<div class="item-error">${item.error}</div>` : ''}
          </div>
          <div class="item-progress">
            <div class="progress-bar">
              <div class="progress-fill" style="width: ${item.progress || 0}%"></div>
            </div>
          </div>
        </div>
      `;

      $items.append(itemHtml);
    });
  }

  /**
   * Start bulk processing
   */
  async function startBulkProcessing() {
    if (isProcessing) {
      return;
    }

    isProcessing = true;
    isPaused = false;
    currentIndex = 0;
    processedResults = [];

    // Update UI
    $('#bulk-start-btn').prop('disabled', true);
    $('#bulk-pause-btn').prop('disabled', false);

    // Initialize model
    try {
      if (!bgRemover.getModelStatus().loaded) {
        await bgRemover.initialize(null, {
          devicePreference: wpBgRemoverConfig.settings.device_preference
        });
      }

      // Process images sequentially
      await processNextImage();

    } catch (error) {
      console.error('Initialization error:', error);
      alert(`Error: ${error.message}`);
      stopBulkProcessing();
    }
  }

  /**
   * Process next image in queue
   */
  async function processNextImage() {
    if (isPaused || currentIndex >= imageQueue.length) {
      finishBulkProcessing();
      return;
    }

    const item = imageQueue[currentIndex];
    item.status = 'processing';
    renderQueue();

    // Update progress
    updateBulkProgress();

    try {
      // Process image
      const processedBlob = await bgRemover.removeBackground(item.file, {
        maxDimension: wpBgRemoverConfig.settings.max_image_dimensions,
        quality: wpBgRemoverConfig.settings.output_quality / 100
      });

      item.processedBlob = processedBlob;
      item.status = 'completed';
      processedResults.push({
        filename: item.file.name,
        blob: processedBlob
      });

    } catch (error) {
      console.error(`Error processing ${item.file.name}:`, error);
      item.status = 'error';
      item.error = error.message;
    }

    renderQueue();
    updateBulkProgress();

    // Move to next
    currentIndex++;
    if (!isPaused) {
      setTimeout(() => processNextImage(), 100);
    }
  }

  /**
   * Update bulk progress indicator
   */
  function updateBulkProgress() {
    const total = imageQueue.length;
    const completed = imageQueue.filter(item =>
      item.status === 'completed' || item.status === 'error'
    ).length;

    const percentage = (completed / total) * 100;

    $('#bulk-progress-fill').css('width', `${percentage}%`);
    $('#bulk-progress-text').text(`${completed} / ${total}`);
  }

  /**
   * Pause bulk processing
   */
  function pauseBulkProcessing() {
    isPaused = true;
    $('#bulk-pause-btn').text('Resume').off('click').on('click', resumeBulkProcessing);
  }

  /**
   * Resume bulk processing
   */
  function resumeBulkProcessing() {
    isPaused = false;
    $('#bulk-pause-btn').text('Pause').off('click').on('click', pauseBulkProcessing);
    processNextImage();
  }

  /**
   * Stop bulk processing
   */
  function stopBulkProcessing() {
    isProcessing = false;
    isPaused = false;

    $('#bulk-start-btn').prop('disabled', false);
    $('#bulk-pause-btn').prop('disabled', true);
  }

  /**
   * Finish bulk processing
   */
  function finishBulkProcessing() {
    stopBulkProcessing();

    // Show results
    if (processedResults.length > 0) {
      renderResults();
      $('#bulk-results').show();
    }

    alert(`Processing complete! ${processedResults.length} images processed successfully.`);
  }

  /**
   * Render results
   */
  function renderResults() {
    const $grid = $('#results-grid');
    $grid.empty();

    processedResults.forEach((result, index) => {
      const resultHtml = `
        <div class="result-item">
          <canvas class="result-canvas" id="result-canvas-${index}"></canvas>
          <div class="result-filename">${result.filename}</div>
          <div class="result-actions">
            <button type="button" class="button button-small result-download" data-index="${index}">
              Download
            </button>
          </div>
        </div>
      `;

      $grid.append(resultHtml);

      // Draw to canvas
      const canvas = document.getElementById(`result-canvas-${index}`);
      const ctx = canvas.getContext('2d');
      const img = new Image();

      img.onload = function() {
        canvas.width = img.width;
        canvas.height = img.height;
        ctx.drawImage(img, 0, 0);
      };

      img.src = URL.createObjectURL(result.blob);
    });

    // Bind download buttons
    $('.result-download').on('click', function() {
      const index = $(this).data('index');
      downloadResult(index);
    });
  }

  /**
   * Download single result
   */
  function downloadResult(index) {
    const result = processedResults[index];
    const url = URL.createObjectURL(result.blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${result.filename.split('.')[0]}-no-bg.png`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  /**
   * Download all as ZIP (simplified version - requires JSZip library)
   */
  async function downloadAllAsZip() {
    alert('ZIP export feature coming soon! For now, please download images individually.');

    // TODO: Implement ZIP export
    // This would require including JSZip library
    // Example implementation:
    /*
    const JSZip = require('jszip');
    const zip = new JSZip();

    processedResults.forEach(result => {
      zip.file(result.filename, result.blob);
    });

    const zipBlob = await zip.generateAsync({type: 'blob'});
    const url = URL.createObjectURL(zipBlob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'background-removed-images.zip';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    */
  }

  /**
   * Save all to media library
   */
  async function saveAllToLibrary() {
    if (processedResults.length === 0) {
      return;
    }

    const $btn = $('#save-all-to-library-btn');
    $btn.prop('disabled', true).text('Saving...');

    let saved = 0;
    let failed = 0;

    for (const result of processedResults) {
      try {
        // Convert blob to base64
        const base64data = await blobToBase64(result.blob);

        // Send to REST API
        const response = await fetch(`${wpBgRemoverConfig.restUrl}/save-media`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': wpBgRemoverConfig.nonce
          },
          body: JSON.stringify({
            image_data: base64data,
            filename: `${result.filename.split('.')[0]}-no-bg.png`,
            title: `${result.filename.split('.')[0]} (Background Removed)`
          })
        });

        const data = await response.json();

        if (data.success) {
          saved++;
        } else {
          failed++;
        }

      } catch (error) {
        console.error(`Error saving ${result.filename}:`, error);
        failed++;
      }
    }

    $btn.prop('disabled', false).text('Save All to Media Library');

    alert(`Saved ${saved} images to media library. ${failed > 0 ? `${failed} failed.` : ''}`);

    if (saved > 0) {
      setTimeout(() => {
        window.location.reload();
      }, 1500);
    }
  }

  /**
   * Convert blob to base64
   */
  function blobToBase64(blob) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onloadend = () => resolve(reader.result);
      reader.onerror = reject;
      reader.readAsDataURL(blob);
    });
  }

  /**
   * Clear bulk queue
   */
  function clearBulkQueue() {
    if (isProcessing && !confirm('Processing is in progress. Are you sure you want to clear the queue?')) {
      return;
    }

    imageQueue = [];
    processedResults = [];
    isProcessing = false;
    isPaused = false;
    currentIndex = 0;

    $('#bulk-queue').hide();
    $('#bulk-results').hide();
    $('#bulk-upload-box').show();
    $('#bulk-image-upload').val('');
  }

  // Initialize when document is ready
  $(document).ready(init);

})(jQuery);
