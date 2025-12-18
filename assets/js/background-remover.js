/**
 * Background Remover Engine
 * Uses BRIA-RMBG-1.4 model via Transformers.js for client-side background removal
 */

import { pipeline, env, RawImage } from '@huggingface/transformers';

/**
 * Background Remover class
 * Handles AI-powered background removal using WebGPU or WASM
 */
class BackgroundRemover {
  constructor() {
    this.model = null;
    this.modelLoaded = false;
    this.device = null;
    this.progressCallback = null;
  }

  /**
   * Initialize the background remover with model loading
   * @param {Function} progressCallback - Callback function for progress updates
   * @param {Object} options - Configuration options
   * @returns {Promise<Object>} Status object with device info
   */
  async initialize(progressCallback = null, options = {}) {
    this.progressCallback = progressCallback;

    try {
      // Report initialization start
      this._reportProgress('Initializing background remover...', 0);

      // Detect and set device (WebGPU or WASM)
      await this._detectDevice(options.devicePreference);

      // Configure environment
      env.allowLocalModels = false;
      env.allowRemoteModels = true;

      // Use IndexedDB for caching
      env.useBrowserCache = true;

      this._reportProgress('Loading BRIA-RMBG-1.4 model...', 10);

      // Load the image segmentation pipeline with BRIA-RMBG-1.4 model
      this.model = await pipeline(
        'image-segmentation',
        'briaai/RMBG-1.4',
        {
          device: this.device,
          progress_callback: (progress) => {
            // Calculate percentage based on progress
            const percentage = Math.min(10 + (progress.progress || 0) * 80, 90);
            this._reportProgress(
              `Loading model: ${progress.status || 'downloading'}...`,
              percentage
            );
          }
        }
      );

      this.modelLoaded = true;
      this._reportProgress('Model loaded successfully!', 100);

      return {
        success: true,
        device: this.device,
        modelLoaded: true
      };
    } catch (error) {
      console.error('Failed to initialize background remover:', error);
      this._reportProgress(`Error: ${error.message}`, 0);

      return {
        success: false,
        error: error.message,
        device: this.device
      };
    }
  }

  /**
   * Detect the best available device (WebGPU or WASM)
   * @param {string} preference - User's device preference ('auto', 'webgpu', 'wasm')
   * @private
   */
  async _detectDevice(preference = 'auto') {
    // Check WebGPU support
    const hasWebGPU = 'gpu' in navigator;

    if (preference === 'wasm') {
      this.device = 'wasm';
      console.log('Using WASM (user preference)');
      return;
    }

    if (preference === 'webgpu' && hasWebGPU) {
      try {
        const adapter = await navigator.gpu.requestAdapter();
        if (adapter) {
          this.device = 'webgpu';
          console.log('Using WebGPU (user preference)');
          return;
        }
      } catch (error) {
        console.warn('WebGPU requested but not available:', error);
      }
    }

    // Auto detection
    if (hasWebGPU) {
      try {
        const adapter = await navigator.gpu.requestAdapter();
        if (adapter) {
          this.device = 'webgpu';
          console.log('Using WebGPU (auto-detected)');
          return;
        }
      } catch (error) {
        console.warn('WebGPU available but failed to initialize:', error);
      }
    }

    // Fallback to WASM
    this.device = 'wasm';
    console.log('Using WASM (fallback)');
  }

  /**
   * Remove background from an image
   * @param {File|Blob|string} imageInput - Image file, blob, or data URL
   * @param {Object} options - Processing options
   * @returns {Promise<Blob>} Processed image blob with transparent background
   */
  async removeBackground(imageInput, options = {}) {
    if (!this.modelLoaded) {
      throw new Error('Model not loaded. Call initialize() first.');
    }

    try {
      this._reportProgress('Processing image...', 0);

      // Load image as RawImage for the model
      let rawImage;
      let blobUrl = null;

      if (imageInput instanceof File || imageInput instanceof Blob) {
        console.log('Converting File/Blob to blob URL');
        // Convert File/Blob to blob URL first, then use fromURL
        blobUrl = URL.createObjectURL(imageInput);
        console.log('Blob URL created:', blobUrl);
        rawImage = await RawImage.fromURL(blobUrl);
      } else if (typeof imageInput === 'string') {
        console.log('Loading image from URL');
        rawImage = await RawImage.fromURL(imageInput);
      } else {
        throw new Error('Invalid image input type');
      }

      console.log('RawImage loaded:', rawImage.width, 'x', rawImage.height);

      // Check image dimensions
      const maxDimension = options.maxDimension || 4000;
      if (rawImage.width > maxDimension || rawImage.height > maxDimension) {
        if (blobUrl) URL.revokeObjectURL(blobUrl);
        throw new Error(`Image dimensions exceed maximum of ${maxDimension}px`);
      }

      this._reportProgress('Running AI model...', 30);

      // Run the model with RawImage
      console.log('Running model...');
      const result = await this.model(rawImage);
      console.log('Model result:', result);

      this._reportProgress('Applying mask...', 70);

      // Convert RawImage to canvas for processing
      const canvas = rawImage.toCanvas();
      console.log('Canvas created:', canvas.width, 'x', canvas.height);

      // Clean up blob URL
      if (blobUrl) URL.revokeObjectURL(blobUrl);

      // Process the segmentation result
      const processedBlob = await this._applyMaskToCanvas(canvas, result, {
        backgroundColor: 'transparent',
        ...options
      });

      this._reportProgress('Complete!', 100);

      return processedBlob;
    } catch (error) {
      console.error('Background removal failed:', error);
      console.error('Error details:', {
        message: error?.message,
        stack: error?.stack,
        name: error?.name,
        toString: error?.toString()
      });
      throw new Error(error?.message || 'Unknown error during background removal');
    }
  }

  /**
   * Remove background and replace with a color
   * @param {File|Blob|string} imageInput - Image file, blob, or data URL
   * @param {string} backgroundColor - CSS color string
   * @param {Object} options - Processing options
   * @returns {Promise<Blob>} Processed image blob
   */
  async replaceBackground(imageInput, backgroundColor, options = {}) {
    return this.removeBackground(imageInput, {
      ...options,
      backgroundColor
    });
  }

  /**
   * Convert RawImage to HTMLImageElement
   * @param {RawImage} rawImage - RawImage object
   * @returns {Promise<HTMLImageElement>}
   * @private
   */
  async _rawImageToImg(rawImage) {
    return new Promise((resolve, reject) => {
      // Convert RawImage to canvas
      const canvas = rawImage.toCanvas();

      // Convert canvas to blob then to object URL
      canvas.toBlob((blob) => {
        if (!blob) {
          reject(new Error('Failed to convert RawImage to blob'));
          return;
        }

        const img = new Image();
        img.onload = () => {
          URL.revokeObjectURL(img.src);
          resolve(img);
        };
        img.onerror = () => {
          URL.revokeObjectURL(img.src);
          reject(new Error('Failed to load image from blob'));
        };
        img.src = URL.createObjectURL(blob);
      }, 'image/png');
    });
  }

  /**
   * Load image from various input types
   * @param {File|Blob|string} input - Image input
   * @returns {Promise<HTMLImageElement>}
   * @private
   */
  async _loadImage(input) {
    return new Promise((resolve, reject) => {
      const img = new Image();

      img.onload = () => resolve(img);
      img.onerror = () => reject(new Error('Failed to load image'));

      if (input instanceof File || input instanceof Blob) {
        img.src = URL.createObjectURL(input);
      } else if (typeof input === 'string') {
        img.src = input;
      } else {
        reject(new Error('Invalid image input type'));
      }
    });
  }

  /**
   * Apply segmentation mask to canvas
   * @param {HTMLCanvasElement} sourceCanvas - Original image canvas
   * @param {Array} result - Segmentation result from model
   * @param {Object} options - Options including backgroundColor
   * @returns {Promise<Blob>}
   * @private
   */
  async _applyMaskToCanvas(sourceCanvas, result, options = {}) {
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');

    canvas.width = sourceCanvas.width;
    canvas.height = sourceCanvas.height;

    // Draw background color if specified
    if (options.backgroundColor && options.backgroundColor !== 'transparent') {
      ctx.fillStyle = options.backgroundColor;
      ctx.fillRect(0, 0, canvas.width, canvas.height);
    }

    // Draw original image from source canvas
    ctx.drawImage(sourceCanvas, 0, 0);

    // Get image data
    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const pixels = imageData.data;

    // Get the mask from the result
    console.log('Segmentation result structure:', result);
    const mask = result[0]?.mask;

    if (!mask) {
      console.error('No mask found. Result structure:', result);
      throw new Error('No mask found in segmentation result');
    }

    console.log('Mask object:', mask);

    // Apply mask to alpha channel
    // The mask is a RawImage where foreground is white (255) and background is black (0)
    const maskCanvas = mask.toCanvas ? mask.toCanvas() : (() => {
      throw new Error('Mask does not have toCanvas method');
    })();

    const maskCtx = maskCanvas.getContext('2d');
    const maskData = maskCtx.getImageData(0, 0, maskCanvas.width, maskCanvas.height);
    const maskPixels = maskData.data;

    console.log('Mask canvas size:', maskCanvas.width, 'x', maskCanvas.height);
    console.log('Image canvas size:', canvas.width, 'x', canvas.height);

    // Apply mask to alpha channel
    for (let i = 0; i < pixels.length; i += 4) {
      // Use the red channel of the mask as alpha
      // Foreground (255) = opaque, Background (0) = transparent
      pixels[i + 3] = maskPixels[i];
    }

    ctx.putImageData(imageData, 0, 0);

    // Convert to blob
    return new Promise((resolve, reject) => {
      canvas.toBlob(
        (blob) => {
          if (blob) {
            resolve(blob);
          } else {
            reject(new Error('Failed to create blob'));
          }
        },
        'image/png',
        options.quality || 0.95
      );
    });
  }

  /**
   * Apply segmentation mask to image
   * @param {HTMLImageElement} img - Original image
   * @param {Array} result - Segmentation result from model
   * @param {Object} options - Options including backgroundColor
   * @returns {Promise<Blob>}
   * @private
   */
  async _applyMask(img, result, options = {}) {
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');

    canvas.width = img.width;
    canvas.height = img.height;

    // Draw background color if specified
    if (options.backgroundColor && options.backgroundColor !== 'transparent') {
      ctx.fillStyle = options.backgroundColor;
      ctx.fillRect(0, 0, canvas.width, canvas.height);
    }

    // Draw original image
    ctx.drawImage(img, 0, 0);

    // Get image data
    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const pixels = imageData.data;

    // Get the mask from the result
    // The model returns an array with segmentation results
    const mask = result[0]?.mask;

    if (!mask) {
      throw new Error('No mask found in segmentation result');
    }

    // Apply mask to alpha channel
    // The mask is a binary image where foreground is white (255) and background is black (0)
    const maskCanvas = document.createElement('canvas');
    const maskCtx = maskCanvas.getContext('2d');
    maskCanvas.width = canvas.width;
    maskCanvas.height = canvas.height;

    // Draw mask to canvas
    const maskImg = new Image();
    await new Promise((resolve, reject) => {
      maskImg.onload = resolve;
      maskImg.onerror = reject;
      maskImg.src = mask.toDataURL();
    });

    maskCtx.drawImage(maskImg, 0, 0, canvas.width, canvas.height);
    const maskData = maskCtx.getImageData(0, 0, canvas.width, canvas.height);
    const maskPixels = maskData.data;

    // Apply mask to alpha channel
    for (let i = 0; i < pixels.length; i += 4) {
      // Use the red channel of the mask as alpha
      // Foreground (255) = opaque, Background (0) = transparent
      pixels[i + 3] = maskPixels[i];
    }

    ctx.putImageData(imageData, 0, 0);

    // Convert to blob
    return new Promise((resolve, reject) => {
      canvas.toBlob(
        (blob) => {
          if (blob) {
            resolve(blob);
          } else {
            reject(new Error('Failed to create blob'));
          }
        },
        'image/png',
        options.quality || 0.95
      );
    });
  }

  /**
   * Report progress to callback
   * @param {string} message - Progress message
   * @param {number} percentage - Progress percentage (0-100)
   * @private
   */
  _reportProgress(message, percentage) {
    if (this.progressCallback) {
      this.progressCallback({
        message,
        percentage: Math.min(Math.max(percentage, 0), 100)
      });
    }
  }

  /**
   * Get model loading status
   * @returns {Object} Status object
   */
  getModelStatus() {
    return {
      loaded: this.modelLoaded,
      device: this.device,
      model: this.model ? 'briaai/RMBG-1.4' : null
    };
  }

  /**
   * Clear cached model from IndexedDB
   * @returns {Promise<void>}
   */
  async clearCache() {
    try {
      // Clear IndexedDB cache used by Transformers.js
      if ('indexedDB' in window) {
        const databases = await window.indexedDB.databases();
        for (const db of databases) {
          if (db.name && db.name.includes('transformers')) {
            await window.indexedDB.deleteDatabase(db.name);
          }
        }
      }

      this.model = null;
      this.modelLoaded = false;

      console.log('Cache cleared successfully');
    } catch (error) {
      console.error('Failed to clear cache:', error);
      throw error;
    }
  }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
  module.exports = BackgroundRemover;
}

// Make available globally for WordPress
if (typeof window !== 'undefined') {
  window.BackgroundRemover = BackgroundRemover;
}

export default BackgroundRemover;
