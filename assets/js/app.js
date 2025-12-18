/**
 * Frontend App JavaScript
 * Handles bulk image processing for public-facing shortcode
 */

import BackgroundRemover from './background-remover.js';

(function() {
  'use strict';

  let bgRemover = null;
  let imageQueue = [];
  let processedResults = [];
  let isProcessing = false;
  let isPaused = false;
  let currentIndex = 0;
  let maxImages = 50;

  /**
   * Initialize the app
   */
  function init() {
    const container = document.querySelector('.wp-bg-remover');
    if (!container) return;

    bgRemover = new BackgroundRemover();
    maxImages = parseInt(container.dataset.maxImages) || 50;

    // File upload
    const selectBtn = document.getElementById('select-files-btn');
    const fileInput = document.getElementById('image-upload');

    if (selectBtn && fileInput) {
      selectBtn.addEventListener('click', () => fileInput.click());
      fileInput.addEventListener('change', handleFileSelect);
    }

    // Drag and drop
    const uploadBox = document.getElementById('upload-box');
    if (uploadBox) {
      uploadBox.addEventListener('dragover', handleDragOver);
      uploadBox.addEventListener('dragleave', handleDragLeave);
      uploadBox.addEventListener('drop', handleDrop);
    }

    // Control buttons
    const pauseBtn = document.getElementById('pause-btn');
    const cancelBtn = document.getElementById('cancel-btn');
    const downloadAllBtn = document.getElementById('download-all-btn');
    const processMoreBtn = document.getElementById('process-more-btn');

    if (pauseBtn) pauseBtn.addEventListener('click', togglePause);
    if (cancelBtn) cancelBtn.addEventListener('click', cancelProcessing);
    if (downloadAllBtn) downloadAllBtn.addEventListener('click', downloadAll);
    if (processMoreBtn) processMoreBtn.addEventListener('click', processMore);
  }

  /**
   * Handle file selection
   */
  function handleFileSelect(e) {
    const files = Array.from(e.target.files);
    handleFiles(files);
  }

  /**
   * Handle drag over
   */
  function handleDragOver(e) {
    e.preventDefault();
    e.currentTarget.classList.add('dragover');
  }

  /**
   * Handle drag leave
   */
  function handleDragLeave(e) {
    e.preventDefault();
    e.currentTarget.classList.remove('dragover');
  }

  /**
   * Handle file drop
   */
  function handleDrop(e) {
    e.preventDefault();
    e.currentTarget.classList.remove('dragover');

    const files = Array.from(e.dataTransfer.files);
    handleFiles(files);
  }

  /**
   * Handle files upload
   */
  function handleFiles(files) {
    // Filter valid images
    const validFiles = files.filter(file =>
      file.type.match('image/(jpeg|png|webp)')
    );

    if (validFiles.length === 0) {
      alert('Please select valid image files (JPG, PNG, or WebP)');
      return;
    }

    // Check limit
    if (validFiles.length > maxImages) {
      alert(`You can only process up to ${maxImages} images at once`);
      return;
    }

    // Create queue
    imageQueue = validFiles.map(file => ({
      file: file,
      status: 'pending',
      processedBlob: null,
      error: null,
      progress: 0
    }));

    // Show processing area
    document.getElementById('upload-area').style.display = 'none';
    document.getElementById('processing-area').style.display = 'block';

    // Render queue
    renderQueue();

    // Start processing
    startProcessing();
  }

  /**
   * Render image queue
   */
  function renderQueue() {
    const queueContainer = document.getElementById('image-queue');
    if (!queueContainer) return;

    queueContainer.innerHTML = '';

    imageQueue.forEach((item, index) => {
      const itemEl = document.createElement('div');
      itemEl.className = `queue-item queue-item-${item.status}`;
      itemEl.innerHTML = `
        <div class="item-thumbnail">
          <img src="${URL.createObjectURL(item.file)}" alt="${item.file.name}">
        </div>
        <div class="item-info">
          <div class="item-name">${item.file.name}</div>
          <div class="item-status">${getStatusText(item.status)}</div>
          ${item.error ? `<div class="item-error">${item.error}</div>` : ''}
        </div>
        <div class="item-progress-bar">
          <div class="item-progress-fill" style="width: ${item.progress}%"></div>
        </div>
      `;
      queueContainer.appendChild(itemEl);
    });
  }

  /**
   * Get status text
   */
  function getStatusText(status) {
    const texts = {
      'pending': 'Waiting...',
      'processing': 'Processing...',
      'completed': 'Complete ✓',
      'error': 'Error'
    };
    return texts[status] || status;
  }

  /**
   * Start processing
   */
  async function startProcessing() {
    if (isProcessing) return;

    isProcessing = true;
    isPaused = false;
    currentIndex = 0;
    processedResults = [];

    try {
      // Initialize model
      if (!bgRemover.getModelStatus().loaded) {
        updateProgress('Loading AI model...', 0);
        await bgRemover.initialize((progress) => {
          updateProgress(progress.message, progress.percentage);
        });
      }

      // Process images
      await processNextImage();

    } catch (error) {
      console.error('Failed to start processing:', error);
      alert('Error: ' + error.message);
      cancelProcessing();
    }
  }

  /**
   * Process next image in queue
   */
  async function processNextImage() {
    if (isPaused || currentIndex >= imageQueue.length) {
      finishProcessing();
      return;
    }

    const item = imageQueue[currentIndex];
    item.status = 'processing';
    renderQueue();

    // Update overall progress
    updateProgress(
      `Processing ${currentIndex + 1} of ${imageQueue.length}...`,
      (currentIndex / imageQueue.length) * 100
    );

    try {
      // Process image
      const processedBlob = await bgRemover.removeBackground(item.file, {
        maxDimension: 4000,
        quality: 0.95
      });

      item.processedBlob = processedBlob;
      item.status = 'completed';
      item.progress = 100;

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

    // Move to next
    currentIndex++;
    if (!isPaused && currentIndex < imageQueue.length) {
      setTimeout(() => processNextImage(), 100);
    } else {
      finishProcessing();
    }
  }

  /**
   * Update progress
   */
  function updateProgress(message, percentage) {
    const progressFill = document.getElementById('progress-fill');
    const progressText = document.getElementById('progress-text');

    if (progressFill) {
      progressFill.style.width = `${Math.min(percentage, 100)}%`;
    }
    if (progressText) {
      progressText.textContent = message;
    }
  }

  /**
   * Toggle pause
   */
  function togglePause() {
    const pauseBtn = document.getElementById('pause-btn');
    if (!pauseBtn) return;

    isPaused = !isPaused;
    pauseBtn.textContent = isPaused ? 'Resume' : 'Pause';

    if (!isPaused) {
      processNextImage();
    }
  }

  /**
   * Cancel processing
   */
  function cancelProcessing() {
    isProcessing = false;
    isPaused = false;

    // Reset UI
    document.getElementById('processing-area').style.display = 'none';
    document.getElementById('upload-area').style.display = 'block';
    document.getElementById('image-upload').value = '';

    imageQueue = [];
    processedResults = [];
    currentIndex = 0;
  }

  /**
   * Finish processing
   */
  function finishProcessing() {
    isProcessing = false;
    isPaused = false;

    // Show results if we have any
    if (processedResults.length > 0) {
      document.getElementById('processing-area').style.display = 'none';
      document.getElementById('results-area').style.display = 'block';
      renderResults();
    } else {
      alert('No images were processed successfully.');
      cancelProcessing();
    }
  }

  /**
   * Render results
   */
  function renderResults() {
    const resultsGrid = document.getElementById('results-grid');
    if (!resultsGrid) return;

    resultsGrid.innerHTML = '';

    processedResults.forEach((result, index) => {
      const resultEl = document.createElement('div');
      resultEl.className = 'result-item';

      const img = document.createElement('img');
      img.src = URL.createObjectURL(result.blob);
      img.alt = result.filename;
      img.className = 'result-image';

      const filenameEl = document.createElement('div');
      filenameEl.className = 'result-filename';
      filenameEl.textContent = result.filename;

      const downloadBtn = document.createElement('button');
      downloadBtn.type = 'button';
      downloadBtn.className = 'wp-bg-remover-button';
      downloadBtn.textContent = 'Download';
      downloadBtn.addEventListener('click', () => downloadSingle(index));

      resultEl.appendChild(img);
      resultEl.appendChild(filenameEl);
      resultEl.appendChild(downloadBtn);

      resultsGrid.appendChild(resultEl);
    });
  }

  /**
   * Download single image
   */
  function downloadSingle(index) {
    const result = processedResults[index];
    const url = URL.createObjectURL(result.blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = result.filename.replace(/\.[^.]+$/, '') + '-no-bg.png';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  /**
   * Download all images
   */
  function downloadAll() {
    processedResults.forEach((result, index) => {
      setTimeout(() => downloadSingle(index), index * 200);
    });
  }

  /**
   * Process more images
   */
  function processMore() {
    document.getElementById('results-area').style.display = 'none';
    document.getElementById('upload-area').style.display = 'block';
    document.getElementById('image-upload').value = '';

    imageQueue = [];
    processedResults = [];
    currentIndex = 0;
  }

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
