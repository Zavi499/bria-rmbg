/**
 * Admin Interface JavaScript
 * Handles single image processing in the admin interface
 */

import BackgroundRemover from './background-remover.js';

(function($) {
  'use strict';

  let bgRemover = null;
  let currentImageFile = null;
  let processedBlob = null;

  /**
   * Initialize the admin interface
   */
  function init() {
    // Initialize background remover
    bgRemover = new BackgroundRemover();

    // Tab switching
    $('.nav-tab').on('click', function(e) {
      e.preventDefault();
      const tabId = $(this).data('tab');

      // Check if premium tab without premium access
      if ($(this).hasClass('premium-feature') && !wpBgRemoverConfig.isPremium) {
        window.location.href = wpBgRemoverConfig.upgradeUrl;
        return;
      }

      // Switch tabs
      $('.nav-tab').removeClass('nav-tab-active');
      $(this).addClass('nav-tab-active');
      $('.tab-content').removeClass('active');
      $(`#${tabId}`).addClass('active');
    });

    // File upload
    $('#select-file-btn').on('click', function() {
      $('#image-upload').click();
    });

    $('#image-upload').on('change', handleFileSelect);

    // Drag and drop
    const uploadBox = $('#upload-box');
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

      const files = e.originalEvent.dataTransfer.files;
      if (files.length > 0) {
        handleFile(files[0]);
      }
    });

    // Background color controls
    $('#bg-color').on('change', updateBackgroundColor);
    $('#transparent-btn').on('click', function() {
      updateBackgroundColor('transparent');
    });

    // Action buttons
    $('#download-btn').on('click', downloadImage);
    $('#save-to-library-btn').on('click', saveToLibrary);
    $('#clear-btn').on('click', clearAll);

    // Account login form
    $('#account-login-form').on('submit', handleAccountLogin);

    // Logout button
    $('#logout-account-btn').on('click', handleAccountLogout);
  }

  /**
   * Handle file selection
   */
  function handleFileSelect(e) {
    const file = e.target.files[0];
    if (file) {
      handleFile(file);
    }
  }

  /**
   * Handle file upload
   */
  async function handleFile(file) {
    // Validate file type
    if (!file.type.match('image/(jpeg|png|webp)')) {
      alert('Please select a valid image file (JPG, PNG, or WebP)');
      return;
    }

    // Validate file size (max 10MB)
    if (file.size > 10 * 1024 * 1024) {
      alert('File size must be less than 10MB');
      return;
    }

    currentImageFile = file;

    // Show processing area
    $('#upload-box').hide();
    $('#processing-area').show();

    try {
      // Initialize model if not already loaded
      if (!bgRemover.getModelStatus().loaded) {
        await bgRemover.initialize(updateProgress, {
          devicePreference: wpBgRemoverConfig.settings.device_preference
        });
      }

      // Process image
      updateProgress({ message: 'Processing image...', percentage: 0 });
      processedBlob = await bgRemover.removeBackground(file, {
        maxDimension: wpBgRemoverConfig.settings.max_image_dimensions,
        quality: wpBgRemoverConfig.settings.output_quality / 100
      });

      // Show preview
      showPreview(file, processedBlob);

    } catch (error) {
      console.error('Processing error:', error);
      alert(`Error: ${error.message}`);
      clearAll();
    }
  }

  /**
   * Update progress indicator
   */
  function updateProgress(progress) {
    $('#progress-fill').css('width', `${progress.percentage}%`);
    $('#progress-text').text(progress.message);
  }

  /**
   * Show image preview
   */
  function showPreview(originalFile, processedBlob) {
    // Hide processing area
    $('#processing-area').hide();

    // Show preview area
    $('#preview-area').show();

    // Load original image
    const originalUrl = URL.createObjectURL(originalFile);
    $('#original-image').attr('src', originalUrl);

    // Display processed image on canvas
    displayProcessedImage(processedBlob);
  }

  /**
   * Display processed image on canvas
   */
  async function displayProcessedImage(blob, bgColor = 'transparent') {
    const canvas = document.getElementById('processed-canvas');
    const ctx = canvas.getContext('2d');

    const img = new Image();
    img.onload = function() {
      canvas.width = img.width;
      canvas.height = img.height;

      // Draw background color
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
   * Update background color
   */
  async function updateBackgroundColor(color) {
    if (!currentImageFile) {
      return;
    }

    if (typeof color !== 'string') {
      color = $('#bg-color').val();
    }

    try {
      // Re-process with new background color
      processedBlob = await bgRemover.replaceBackground(
        currentImageFile,
        color,
        {
          maxDimension: wpBgRemoverConfig.settings.max_image_dimensions,
          quality: wpBgRemoverConfig.settings.output_quality / 100
        }
      );

      displayProcessedImage(processedBlob, color);

    } catch (error) {
      console.error('Error updating background:', error);
      alert(`Error: ${error.message}`);
    }
  }

  /**
   * Download processed image
   */
  function downloadImage() {
    if (!processedBlob) {
      return;
    }

    const url = URL.createObjectURL(processedBlob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${currentImageFile.name.split('.')[0]}-no-bg.png`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  /**
   * Save image to media library
   */
  async function saveToLibrary() {
    if (!processedBlob) {
      return;
    }

    const $btn = $('#save-to-library-btn');
    const originalText = $btn.text();
    $btn.prop('disabled', true).text('Saving...');

    try {
      // Convert blob to base64
      const reader = new FileReader();
      reader.readAsDataURL(processedBlob);

      reader.onloadend = async function() {
        const base64data = reader.result;

        // Send to REST API
        const response = await fetch(`${wpBgRemoverConfig.restUrl}/save-media`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': wpBgRemoverConfig.nonce
          },
          body: JSON.stringify({
            image_data: base64data,
            filename: `${currentImageFile.name.split('.')[0]}-no-bg.png`,
            title: `${currentImageFile.name.split('.')[0]} (Background Removed)`
          })
        });

        const data = await response.json();

        if (data.success) {
          alert('Image saved to media library successfully!');
        } else {
          throw new Error(data.message || 'Failed to save image');
        }

        $btn.prop('disabled', false).text(originalText);
      };

    } catch (error) {
      console.error('Save error:', error);
      alert(`Error saving image: ${error.message}`);
      $btn.prop('disabled', false).text(originalText);
    }
  }

  /**
   * Clear all and reset
   */
  function clearAll() {
    currentImageFile = null;
    processedBlob = null;

    $('#preview-area').hide();
    $('#processing-area').hide();
    $('#upload-box').show();

    $('#image-upload').val('');
    $('#original-image').attr('src', '');
    $('#processed-canvas').get(0).getContext('2d').clearRect(0, 0, 1000, 1000);
  }

  /**
   * Handle account login
   */
  async function handleAccountLogin(e) {
    e.preventDefault();

    const $form = $(this);
    const $btn = $form.find('button[type="submit"]');
    const $message = $('#account-message');

    const email = $('#account-email').val();
    const apiToken = $('#account-token').val();

    $btn.prop('disabled', true).text('Verifying...');
    $message.hide();

    try {
      const response = await fetch(`${wpBgRemoverConfig.restUrl}/verify-account`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': wpBgRemoverConfig.nonce
        },
        body: JSON.stringify({
          email: email,
          api_token: apiToken
        })
      });

      const data = await response.json();

      if (data.success) {
        $message
          .removeClass('notice-error')
          .addClass('notice-success')
          .html(`<p>${data.message || 'Account verified successfully!'}</p>`)
          .show();

        // Reload page after success
        setTimeout(() => {
          window.location.reload();
        }, 1500);
      } else {
        throw new Error(data.message || 'Account verification failed');
      }

    } catch (error) {
      console.error('Login error:', error);
      $message
        .removeClass('notice-success')
        .addClass('notice-error')
        .html(`<p>${error.message}</p>`)
        .show();

      $btn.prop('disabled', false).text('Verify Account');
    }
  }

  /**
   * Handle account logout
   */
  async function handleAccountLogout(e) {
    e.preventDefault();

    if (!confirm('Are you sure you want to disconnect your account?')) {
      return;
    }

    const $btn = $(this);
    $btn.prop('disabled', true).text('Disconnecting...');

    try {
      const response = await $.ajax({
        url: wpBgRemoverConfig.ajaxUrl,
        type: 'POST',
        data: {
          action: 'wp_bg_remover_logout_account',
          nonce: wpBgRemoverConfig.nonce
        }
      });

      if (response.success) {
        alert('Account disconnected successfully.');
        window.location.reload();
      } else {
        throw new Error(response.data.message || 'Failed to disconnect account');
      }

    } catch (error) {
      console.error('Logout error:', error);
      alert(`Error: ${error.message}`);
      $btn.prop('disabled', false).text('Disconnect Account');
    }
  }

  // Initialize when document is ready
  $(document).ready(init);

})(jQuery);
