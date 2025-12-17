/**
 * Gutenberg Block: Background Remover
 */

import { registerBlockType } from '@wordpress/blocks';
import { MediaUpload, MediaUploadCheck, InspectorControls, BlockControls } from '@wordpress/block-editor';
import { PanelBody, Button, ToggleControl, ColorPicker, Spinner, Toolbar, ToolbarButton } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import BackgroundRemover from '../../assets/js/background-remover.js';

/**
 * Register block
 */
registerBlockType('wp-bg-remover/background-remover', {
  edit: EditComponent,
  save: SaveComponent
});

/**
 * Edit component
 */
function EditComponent({ attributes, setAttributes }) {
  const { imageUrl, processedImageUrl, backgroundColor, showComparison } = attributes;
  const [isProcessing, setIsProcessing] = useState(false);
  const [progress, setProgress] = useState({ message: '', percentage: 0 });
  const [bgRemover] = useState(() => new BackgroundRemover());

  /**
   * Handle image selection
   */
  const onSelectImage = async (media) => {
    setAttributes({ imageUrl: media.url });

    // Auto-process image
    await processImage(media.url);
  };

  /**
   * Process image
   */
  const processImage = async (url) => {
    setIsProcessing(true);

    try {
      // Initialize model if needed
      if (!bgRemover.getModelStatus().loaded) {
        await bgRemover.initialize((prog) => {
          setProgress(prog);
        });
      }

      // Download image
      setProgress({ message: __('Downloading image...', 'wp-background-remover'), percentage: 10 });
      const response = await fetch(url);
      const blob = await response.blob();

      // Process image
      const processedBlob = await bgRemover.removeBackground(blob);

      // Convert to data URL for display
      const reader = new FileReader();
      reader.onloadend = () => {
        setAttributes({ processedImageUrl: reader.result });
        setIsProcessing(false);
      };
      reader.readAsDataURL(processedBlob);

    } catch (error) {
      console.error('Processing error:', error);
      alert(__('Failed to process image: ', 'wp-background-remover') + error.message);
      setIsProcessing(false);
    }
  };

  /**
   * Re-process with new background color
   */
  const reprocessWithColor = async (color) => {
    if (!imageUrl) return;

    setIsProcessing(true);
    setAttributes({ backgroundColor: color });

    try {
      const response = await fetch(imageUrl);
      const blob = await response.blob();

      const processedBlob = await bgRemover.replaceBackground(blob, color);

      const reader = new FileReader();
      reader.onloadend = () => {
        setAttributes({ processedImageUrl: reader.result });
        setIsProcessing(false);
      };
      reader.readAsDataURL(processedBlob);

    } catch (error) {
      console.error('Error updating background:', error);
      setIsProcessing(false);
    }
  };

  /**
   * Download processed image
   */
  const downloadImage = () => {
    if (!processedImageUrl) return;

    const a = document.createElement('a');
    a.href = processedImageUrl;
    a.download = 'background-removed.png';
    a.click();
  };

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Settings', 'wp-background-remover')}>
          <ToggleControl
            label={__('Show Comparison', 'wp-background-remover')}
            checked={showComparison}
            onChange={(value) => setAttributes({ showComparison: value })}
          />

          {processedImageUrl && (
            <>
              <h3>{__('Background Color', 'wp-background-remover')}</h3>
              <ColorPicker
                color={backgroundColor === 'transparent' ? '#ffffff' : backgroundColor}
                onChangeComplete={(color) => reprocessWithColor(color.hex)}
              />
              <Button
                isSecondary
                onClick={() => reprocessWithColor('transparent')}
                style={{ marginTop: '10px' }}
              >
                {__('Make Transparent', 'wp-background-remover')}
              </Button>
            </>
          )}
        </PanelBody>
      </InspectorControls>

      {processedImageUrl && (
        <BlockControls>
          <Toolbar>
            <ToolbarButton
              icon="download"
              label={__('Download', 'wp-background-remover')}
              onClick={downloadImage}
            />
            <ToolbarButton
              icon="image-rotate"
              label={__('Change Image', 'wp-background-remover')}
              onClick={() => setAttributes({ imageUrl: '', processedImageUrl: '' })}
            />
          </Toolbar>
        </BlockControls>
      )}

      <div className="wp-block-wp-bg-remover-background-remover">
        {!imageUrl ? (
          <MediaUploadCheck>
            <MediaUpload
              onSelect={onSelectImage}
              allowedTypes={['image']}
              render={({ open }) => (
                <div className="wp-bg-remover-placeholder">
                  <Button isPrimary onClick={open}>
                    {__('Select Image', 'wp-background-remover')}
                  </Button>
                  <p>{__('Choose an image to remove its background', 'wp-background-remover')}</p>
                </div>
              )}
            />
          </MediaUploadCheck>
        ) : isProcessing ? (
          <div className="wp-bg-remover-processing">
            <Spinner />
            <p>{progress.message}</p>
            <div className="progress-bar">
              <div
                className="progress-fill"
                style={{ width: `${progress.percentage}%` }}
              />
            </div>
          </div>
        ) : processedImageUrl ? (
          showComparison ? (
            <div className="wp-bg-remover-comparison">
              <div className="wp-bg-remover-comparison-item">
                <span className="wp-bg-remover-comparison-label">
                  {__('Original', 'wp-background-remover')}
                </span>
                <img src={imageUrl} alt={__('Original', 'wp-background-remover')} />
              </div>
              <div className="wp-bg-remover-comparison-item">
                <span className="wp-bg-remover-comparison-label">
                  {__('Processed', 'wp-background-remover')}
                </span>
                <img src={processedImageUrl} alt={__('Processed', 'wp-background-remover')} />
              </div>
            </div>
          ) : (
            <img
              src={processedImageUrl}
              alt={__('Background Removed', 'wp-background-remover')}
              className="wp-bg-remover-image"
            />
          )
        ) : (
          <img src={imageUrl} alt={__('Selected Image', 'wp-background-remover')} />
        )}
      </div>
    </>
  );
}

/**
 * Save component (render on frontend)
 */
function SaveComponent({ attributes }) {
  const { processedImageUrl, imageUrl, showComparison } = attributes;

  if (!processedImageUrl && !imageUrl) {
    return null;
  }

  return (
    <div className="wp-block-wp-bg-remover-background-remover">
      {showComparison && imageUrl && processedImageUrl ? (
        <div className="wp-bg-remover-comparison">
          <div className="wp-bg-remover-comparison-item">
            <span className="wp-bg-remover-comparison-label">
              {__('Original', 'wp-background-remover')}
            </span>
            <img src={imageUrl} alt={__('Original', 'wp-background-remover')} />
          </div>
          <div className="wp-bg-remover-comparison-item">
            <span className="wp-bg-remover-comparison-label">
              {__('Processed', 'wp-background-remover')}
            </span>
            <img src={processedImageUrl} alt={__('Processed', 'wp-background-remover')} />
          </div>
        </div>
      ) : (
        <img
          src={processedImageUrl || imageUrl}
          alt={__('Background Removed', 'wp-background-remover')}
          className="wp-bg-remover-image"
        />
      )}
    </div>
  );
}
