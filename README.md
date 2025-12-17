# WP Background Remover

AI-powered background removal for WordPress using the BRIA-RMBG-1.4 model. All processing happens client-side in the user's browser using WebGPU or WASM - no server required!

## Features

### FREE (No Account Required)
✅ Single image background removal
✅ Media library integration
✅ Gutenberg block for content
✅ WebGPU/WASM processing (fast!)
✅ Custom background colors
✅ Download processed images
✅ Save to media library
✅ Before/After comparison

### PREMIUM (Account Required)
🔒 Bulk processing (up to 50 images at once)
🔒 Queue management with pause/resume
🔒 Export results as ZIP
🔒 Advanced settings
🔒 API access for developers
🔒 Priority support

## Requirements

- **WordPress:** 6.0 or higher
- **PHP:** 7.4 or higher
- **Browser:** Modern browser with WebGPU or WASM support
  - Chrome 113+ (WebGPU)
  - Edge 113+ (WebGPU)
  - Safari, Firefox (WASM fallback)

## Installation

1. **Clone or download** this repository to your WordPress plugins directory:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/yourusername/wp-background-remover.git
   ```

2. **Install dependencies:**
   ```bash
   cd wp-background-remover
   npm install
   ```

3. **Build assets:**
   ```bash
   npm run build
   ```

4. **Activate the plugin** in WordPress admin (Plugins → Installed Plugins → Activate)

## Usage

### Single Image Processing

1. Go to **BG Remover** in the WordPress admin menu
2. Click **"Single Image"** tab
3. Upload or drag & drop an image
4. Wait for processing (model loads on first use)
5. Choose background color or keep transparent
6. Download or save to media library

### Media Library Integration

1. Go to **Media Library**
2. Hover over any image
3. Click **"Remove Background"**
4. Process the image in the modal
5. Choose to save as new or replace original

### Gutenberg Block

1. In the block editor, add a new block
2. Search for **"Background Remover"**
3. Upload an image
4. The background is automatically removed
5. Toggle comparison mode to show before/after
6. Customize background color in the sidebar

### Bulk Processing (Premium Only)

1. Create an account and verify via API
2. Go to **BG Remover** → **Bulk Process** tab
3. Upload multiple images (up to 50)
4. Click **"Start Processing"**
5. Pause/resume as needed
6. Download all or save to media library

## Configuration

### Settings Page

Go to **BG Remover → Settings** to configure:

**General Settings:**
- Model Precision (q4, q8, fp16)
- Device Preference (Auto, WebGPU, WASM)
- Default Background Color
- Max Image Dimensions
- Output Quality
- Enable/Disable Features

**Premium Settings** (Premium users only):
- Bulk Processing Limit
- Auto-process on upload
- API access
- Custom watermark

**API Configuration:**
- Set your backend API endpoint for account verification

## Account & Premium Access

### For Users

1. Go to **BG Remover → Get Premium**
2. Enter your email and API token
3. Click **"Verify Account"**
4. Access premium features immediately

### For Developers

The plugin verifies premium accounts via your backend API. Set up your API endpoint in **Settings → API Configuration**.

**API Endpoint:** `/verify` (POST)

**Request:**
```json
{
  "email": "user@example.com"
}
```

**Headers:**
```
Authorization: Bearer {api_token}
Content-Type: application/json
```

**Response:**
```json
{
  "success": true,
  "is_premium": true,
  "status": "active",
  "user_data": {}
}
```

The plugin caches verification for 1 hour. Users must have a valid API token from your service.

## Technical Details

### Client-Side Processing

All image processing happens in the browser using:
- **Transformers.js** - Running ML models in JavaScript
- **BRIA-RMBG-1.4** - State-of-the-art background removal model
- **WebGPU** - Fast GPU acceleration (when available)
- **WASM** - Universal fallback for all browsers
- **IndexedDB** - Model caching for faster subsequent loads

### Architecture

```
wp-background-remover/
├── wp-background-remover.php     # Main plugin file
├── includes/                      # PHP classes
│   ├── class-account.php         # Account management
│   ├── class-admin.php           # Admin interface
│   ├── class-media-integration.php
│   ├── class-rest-api.php        # REST endpoints
│   └── class-settings.php        # Settings management
├── assets/
│   ├── js/                       # JavaScript files
│   │   ├── background-remover.js # Core AI engine
│   │   ├── admin.js              # Admin interface
│   │   ├── media-modal.js        # Media library modal
│   │   └── bulk-processor.js     # Bulk processing (Premium)
│   └── css/                      # Stylesheets
├── blocks/                       # Gutenberg block
│   └── background-remover/
└── build/                        # Compiled assets (gitignored)
```

### REST API Endpoints

- `POST /wp-json/bg-remover/v1/save-media` - Save processed image
- `GET /wp-json/bg-remover/v1/settings` - Get settings
- `POST /wp-json/bg-remover/v1/settings` - Update settings
- `GET /wp-json/bg-remover/v1/account-status` - Get account status
- `GET /wp-json/bg-remover/v1/check-feature/{feature}` - Check feature access
- `POST /wp-json/bg-remover/v1/verify-account` - Verify premium account

## Development

### Build Commands

```bash
# Development mode (watch for changes)
npm run dev

# Production build
npm run build

# Preview build
npm run preview
```

### Code Structure

**PHP Classes:**
- Follow WordPress coding standards
- Use PHPDoc comments
- Implement capability checks

**JavaScript:**
- ES2020+ features
- Modular structure
- Async/await for better readability

**CSS:**
- BEM-like naming
- Responsive design
- WordPress admin styles

## Browser Compatibility

| Browser | WebGPU | WASM | Status |
|---------|--------|------|--------|
| Chrome 113+ | ✅ | ✅ | Excellent |
| Edge 113+ | ✅ | ✅ | Excellent |
| Safari 16+ | ❌ | ✅ | Good |
| Firefox 115+ | ❌ | ✅ | Good |
| Mobile Chrome | ❌ | ✅ | Good |
| Mobile Safari | ❌ | ✅ | Good |

The plugin automatically detects the best available option (WebGPU → WASM).

## Performance

**First Load:**
- Model download: ~20MB (cached in IndexedDB)
- Loading time: 10-30 seconds (depends on connection)

**Subsequent Uses:**
- Model loads from cache: 2-5 seconds
- Processing time per image: 2-10 seconds (depends on size and device)

**WebGPU vs WASM:**
- WebGPU: 2-3x faster
- WASM: Universal compatibility

## Limitations

- **Free users:** One image at a time
- **Premium users:** Up to 50 images in bulk
- **Max image size:** 4000x4000px (configurable)
- **Supported formats:** JPG, PNG, WebP
- **Model caching:** Requires IndexedDB support

## License

This plugin is released under GPL v2 or later.

### BRIA-RMBG-1.4 Model License

This plugin uses the **BRIA-RMBG-1.4** model which is:
- ✅ **FREE for non-commercial use**
- ⚠️ **Requires license for commercial use**

Commercial use requires a license from BRIA AI:
- Model: https://huggingface.co/briaai/RMBG-1.4
- License: https://bria.ai/bria-huggingface-model-license-agreement/

## Freemium Model

### Plugin Licensing
- **FREE tier:** Single image processing (no restrictions, no account needed)
- **PREMIUM tier:** Bulk processing and advanced features (requires account)

The account system is designed to integrate with your existing backend/payment system. The subscription/payment handling should be implemented on your backend.

## Troubleshooting

### Model not loading
- Check browser console for errors
- Ensure IndexedDB is enabled
- Try clearing browser cache
- Check network connection

### Processing is slow
- Use WebGPU-compatible browser (Chrome/Edge 113+)
- Reduce image size before processing
- Check device specifications

### Images not saving to library
- Check user has `upload_files` capability
- Verify REST API is accessible
- Check browser console for errors
- Ensure WordPress can write to uploads directory

### Premium features not accessible
- Verify account is active
- Check API endpoint configuration
- Ensure API token is valid
- Check account verification hasn't expired

## Support

For issues, questions, or feature requests:
- **GitHub Issues:** [github.com/yourusername/wp-background-remover/issues](https://github.com/yourusername/wp-background-remover/issues)
- **Documentation:** [github.com/yourusername/wp-background-remover/wiki](https://github.com/yourusername/wp-background-remover/wiki)
- **Premium Support:** Available for premium users

## Roadmap

- [ ] ZIP export for bulk processing
- [ ] WooCommerce integration
- [ ] API access for developers
- [ ] Custom watermarks
- [ ] Batch scheduling
- [ ] Additional models
- [ ] Mobile app integration

## Credits

- **BRIA AI** - RMBG-1.4 model
- **Hugging Face** - Transformers.js library
- **WordPress Community** - Inspiration and support

## Changelog

### 1.0.0 (2025-01-17)
- Initial release
- Single image processing
- Bulk processing for premium users
- Media library integration
- Gutenberg block
- Account management system
- WebGPU/WASM support

---

Made with ❤️ for the WordPress community
