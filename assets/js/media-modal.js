/**
 * Media Modal JavaScript
 * Handles background removal in the media library
 */

import BackgroundRemover from './background-remover.js';

(function($) {
  'use strict';

  let bgRemover = null;
  let currentModal = null;

  /**
   * Initialize media library integration
   */
  function init() {
    // Initialize background remover
    bgRemover = new BackgroundRemover();

    // Handle "Remove Background" clicks
    $(document).on('click', '.wp-bg-remover-media-action, .wp-bg-remover-attachment-btn', handleRemoveBackgroundClick);
  }

  /**
   * Handle remove background button click
   */
  async function handleRemoveBackgroundClick(e) {
    e.preventDefault();

    const attachmentId = $(this).data('attachment-id');

    if (!attachmentId) {
      alert('Invalid attachment ID');
      return;
    }

    // Get attachment data
    const attachment = wp.media.attachment(attachmentId);
    await attachment.fetch();

    const imageUrl = attachment.get('url');
    const imageTitle = attachment.get('title');

    // Open modal
    openModal(imageUrl, imageTitle, attachmentId);
  }

  /**
   * Open background removal modal
   */
  function openModal(imageUrl, imageTitle, attachmentId) {
    // Create modal HTML
    const modalHtml = `
      <div class="wp-bg-remover-modal-overlay">
        <div class="wp-bg-remover-modal">
          <div class="modal-header">
            <h2>${wpBgRemoverMedia.strings.modalTitle}</h2>
            <button class="modal-close">&times;</button>
          </div>
          <div class="modal-body">
            <div class="original-image-preview">
              <h3>Original</h3>
              <img src="${imageUrl}" alt="${imageTitle}">
            </div>
            <div class="processing-status" style="display: none;">
              <div class="progress-bar">
                <div class="progress-fill"></div>
              </div>
              <p class="progress-text">${wpBgRemoverMedia.strings.processing}</p>
            </div>
            <div class="processed-preview" style="display: none;">
              <h3>Processed</h3>
              <canvas id="modal-processed-canvas"></canvas>
              <div class="bg-color-controls">
                <label>${wpBgRemoverMedia.strings.backgroundColor}:</label>
                <input type="color" id="modal-bg-color" value="#ffffff">
                <button type="button" class="button" id="modal-transparent-btn">
                  ${wpBgRemoverMedia.strings.transparent}
                </button>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="button button-large" id="modal-save-new">
              ${wpBgRemoverMedia.strings.saveAsNew}
            </button>
            <button type="button" class="button button-large button-primary" id="modal-replace">
              ${wpBgRemoverMedia.strings.replaceOriginal}
            </button>
            <button type="button" class="button button-large" id="modal-cancel">
              ${wpBgRemoverMedia.strings.cancel}
            </button>
          </div>
        </div>
      </div>
    `;

    // Add modal to page
    $('body').append(modalHtml);
    currentModal = $('.wp-bg-remover-modal-overlay');

    // Bind events
    currentModal.find('.modal-close, #modal-cancel').on('click', closeModal);
    currentModal.find('#modal-bg-color').on('change', updateModalBackground);
    currentModal.find('#modal-transparent-btn').on('click', () => updateModalBackground('transparent'));
    currentModal.find('#modal-save-new').on('click', () => saveProcessedImage(attachmentId, false));
    currentModal.find('#modal-replace').on('click', () => saveProcessedImage(attachmentId, true));

    // Start processing
    processImage(imageUrl, imageTitle);
  }

  /**
   * Process image in modal
   */
  async function processImage(imageUrl, imageTitle) {
    const $processingStatus = currentModal.find('.processing-status');
    const $progressFill = currentModal.find('.progress-fill');
    const $progressText = currentModal.find('.progress-text');
    const $processedPreview = currentModal.find('.processed-preview');

    $processingStatus.show();

    try {
      // Initialize model if needed
      if (!bgRemover.getModelStatus().loaded) {
        await bgRemover.initialize(
          (progress) => {
            $progressFill.css('width', `${progress.percentage}%`);
            $progressText.text(progress.message);
          },
          {
            devicePreference: wpBgRemoverMedia.settings.device_preference
          }
        );
      }

      // Download image
      $progressText.text('Downloading image...');
      const response = await fetch(imageUrl);
      if (!response.ok) {
        throw new Error(wpBgRemoverMedia.strings.downloadFailed);
      }
      const blob = await response.blob();

      // Process image
      const processedBlob = await bgRemover.removeBackground(blob, {
        maxDimension: wpBgRemoverMedia.settings.max_image_dimensions,
        quality: wpBgRemoverMedia.settings.output_quality / 100
      });

      // Store processed blob for later use
      currentModal.data('processedBlob', processedBlob);
      currentModal.data('originalUrl', imageUrl);

      // Display processed image
      displayModalProcessedImage(processedBlob);

      // Hide processing, show preview
      $processingStatus.hide();
      $processedPreview.show();

    } catch (error) {
      console.error('Processing error:', error);
      $progressText.text(`${wpBgRemoverMedia.strings.error}: ${error.message}`);

      // Enable cancel button
      currentModal.find('#modal-cancel').prop('disabled', false);
    }
  }

  /**
   * Display processed image in modal
   */
  function displayModalProcessedImage(blob, bgColor = 'transparent') {
    const canvas = document.getElementById('modal-processed-canvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const img = new Image();

    img.onload = function() {
      canvas.width = img.width;
      canvas.height = img.height;

      // Draw background
      if (bgColor !== 'transparent') {
        ctx.fillStyle = bgColor;
        ctx.fillRect(0, 0, canvas.width, canvas.height);
      } else {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
      }

      // Draw image
      ctx.drawImage(img, 0, 0);
    };

    img.src = URL.createObjectURL(blob);
  }

  /**
   * Update modal background color
   */
  async function updateModalBackground(color) {
    if (typeof color !== 'string') {
      color = $('#modal-bg-color').val();
    }

    const originalUrl = currentModal.data('originalUrl');
    if (!originalUrl) return;

    try {
      // Re-process with new background
      const response = await fetch(originalUrl);
      const blob = await response.blob();

      const processedBlob = await bgRemover.replaceBackground(
        blob,
        color,
        {
          maxDimension: wpBgRemoverMedia.settings.max_image_dimensions,
          quality: wpBgRemoverMedia.settings.output_quality / 100
        }
      );

      currentModal.data('processedBlob', processedBlob);
      displayModalProcessedImage(processedBlob, color);

    } catch (error) {
      console.error('Error updating background:', error);
      alert(`${wpBgRemoverMedia.strings.error}: ${error.message}`);
    }
  }

  /**
   * Save processed image
   */
  async function saveProcessedImage(attachmentId, replaceOriginal) {
    const processedBlob = currentModal.data('processedBlob');
    if (!processedBlob) {
      alert(wpBgRemoverMedia.strings.processingFailed);
      return;
    }

    // Disable buttons
    currentModal.find('.modal-footer button').prop('disabled', true);

    try {
      // Convert blob to base64
      const reader = new FileReader();
      reader.readAsDataURL(processedBlob);

      reader.onloadend = async function() {
        const base64data = reader.result;

        // Get original filename
        const attachment = wp.media.attachment(attachmentId);
        const originalFilename = attachment.get('filename') || 'image.png';
        const newFilename = replaceOriginal
          ? originalFilename
          : `${originalFilename.split('.')[0]}-no-bg.png`;

        // Send to REST API
        const response = await fetch(`${wpBgRemoverMedia.restUrl}/save-media`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': wpBgRemoverMedia.nonce
          },
          body: JSON.stringify({
            image_data: base64data,
            filename: newFilename,
            title: `${attachment.get('title')} (Background Removed)`
          })
        });

        const data = await response.json();

        if (data.success) {
          alert(wpBgRemoverMedia.strings.saveSuccess);
          closeModal();

          // Refresh media library if possible
          if (wp.media.frame) {
            wp.media.frame.content.get().collection.props.set({ignore: (+ new Date())});
          }

          // Reload page to show new image
          setTimeout(() => {
            window.location.reload();
          }, 1000);
        } else {
          throw new Error(data.message || wpBgRemoverMedia.strings.saveFailed);
        }
      };

    } catch (error) {
      console.error('Save error:', error);
      alert(`${wpBgRemoverMedia.strings.saveFailed}: ${error.message}`);

      // Re-enable buttons
      currentModal.find('.modal-footer button').prop('disabled', false);
    }
  }

  /**
   * Close modal
   */
  function closeModal() {
    if (currentModal) {
      currentModal.remove();
      currentModal = null;
    }
  }

  // Initialize when document is ready
  $(document).ready(init);

})(jQuery);
