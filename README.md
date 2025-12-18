# WP Background Remover

AI-powered background removal for your WordPress visitors! Add a simple shortcode `[bg_remover]` to any page and let your visitors remove backgrounds from their images using AI - all processing happens locally in their browser!

## Features

✅ **Bulk Processing** - Process up to 50 images at once
✅ **Client-Side AI** - Uses BRIA-RMBG-1.4 model (runs in browser)
✅ **WebGPU/WASM** - Fast GPU processing with automatic fallback
✅ **Drag & Drop** - Intuitive file upload interface
✅ **Queue Management** - Pause/resume batch operations
✅ **Privacy First** - No uploads to server, all processing local
✅ **Download All** - Get all processed images at once
✅ **Mobile Friendly** - Responsive design for all devices
✅ **No Login Required** - Open for all visitors
✅ **100% Free** - No premium tiers or account walls

## Requirements

- **WordPress:** 6.0+
- **PHP:** 7.4+
- **Modern Browser:**
  - Chrome 113+ / Edge 113+ (WebGPU - fastest ⚡)
  - Safari / Firefox (WASM - compatible ✅)

## Installation

### 1. Upload Plugin
```bash
cd wp-content/plugins/
git clone https://github.com/yourusername/wp-background-remover.git
cd wp-background-remover
```

### 2. Install Dependencies
```bash
npm install
```

### 3. Build Assets
```bash
npm run build
```

### 4. Activate
Go to **WordPress Admin → Plugins** and activate **WP Background Remover**

## Usage

### Add to Any Page

Simply add the shortcode to any page or post:

```
[bg_remover]
```

### Customize

You can customize the title and image limit:

```
[bg_remover title="Remove Backgrounds" max_images="25"]
```

**Shortcode Attributes:**
- `title` - Custom heading (default: "AI Background Remover")
- `max_images` - Maximum images to process (default: 50)

### Example Page

1. Create a new page: **Pages → Add New**
2. Title it: "Background Remover Tool"
3. Add the shortcode: `[bg_remover]`
4. Publish!

Your visitors can now:
1. Visit the page
2. Upload up to 50 images at once
3. Watch the AI process them in real-time
4. Download all processed images

## How It Works

1. **Upload**: Visitors drag & drop or select images
2. **AI Processing**: BRIA-RMBG-1.4 model runs in their browser
3. **Real-time**: See live progress as images are processed
4. **Download**: Get all images with backgrounds removed

### Technical Details

- **Model**: BRIA-RMBG-1.4 (background removal)
- **Library**: Transformers.js
- **Processing**: WebGPU (GPU accelerated) or WASM (CPU fallback)
- **Caching**: Model cached in IndexedDB (loads once)
- **File Size**: ~20MB model (downloaded on first use)

## Performance

**First Load:**
- Model download: ~20MB
- Loading time: 10-30 seconds (caches for future use)

**Subsequent Uses:**
- Model loads from cache: 2-5 seconds
- Processing: 2-10 seconds per image (depends on size/device)

**WebGPU vs WASM:**
- WebGPU: 2-3x faster (Chrome/Edge 113+)
- WASM: Universal compatibility (all browsers)

## Browser Compatibility

| Browser | WebGPU | WASM | Status |
|---------|--------|------|--------|
| Chrome 113+ | ✅ | ✅ | Excellent |
| Edge 113+ | ✅ | ✅ | Excellent |
| Safari 16+ | ❌ | ✅ | Good |
| Firefox 115+ | ❌ | ✅ | Good |
| Mobile Chrome | ❌ | ✅ | Good |
| Mobile Safari | ❌ | ✅ | Good |

## Styling

The shortcode outputs semantic HTML with BEM-style CSS classes. You can customize the appearance in your theme:

```css
/* Target the container */
.wp-bg-remover {
  max-width: 900px;
}

/* Custom button colors */
.wp-bg-remover-button.primary {
  background: #your-color;
}
```

## Development

### Build Commands

```bash
# Development (watch mode)
npm run dev

# Production build
npm run build

# Preview build
npm run preview
```

### File Structure

```
wp-background-remover/
├── wp-background-remover.php    # Main plugin file
├── includes/
│   └── class-rest-api.php       # REST API endpoints
├── assets/
│   ├── js/
│   │   ├── background-remover.js  # Core AI engine
│   │   └── app.js                 # Frontend application
│   └── css/
│       └── frontend.css           # Shortcode styles
├── build/                         # Compiled assets (gitignored)
├── package.json
├── vite.config.js
└── README.md
```

## REST API Endpoints

The plugin provides these REST endpoints:

- `POST /wp-json/bg-remover/v1/save-media` - Save processed image to media library

## License

This plugin is released under GPL v2 or later.

### BRIA-RMBG-1.4 Model License

This plugin uses the **BRIA-RMBG-1.4** model:
- ✅ **FREE for non-commercial use**
- ⚠️ **Commercial use requires license from BRIA AI**

Learn more:
- Model: https://huggingface.co/briaai/RMBG-1.4
- License: https://bria.ai/bria-huggingface-model-license-agreement/

## Troubleshooting

### Model Not Loading
- Check browser console for errors
- Ensure IndexedDB is enabled
- Clear browser cache and try again
- Check network connection

### Slow Processing
- Use WebGPU browser (Chrome/Edge 113+)
- Reduce image sizes before upload
- Check device specifications

### Images Not Processing
- Verify file format (JPG, PNG, WebP only)
- Check file size (max 10MB recommended)
- Try with fewer images
- Check browser console for errors

## Support

- **Issues**: [GitHub Issues](https://github.com/yourusername/wp-background-remover/issues)
- **Documentation**: [GitHub Wiki](https://github.com/yourusername/wp-background-remover/wiki)

## Roadmap

- [ ] ZIP export for batch download
- [ ] Custom background colors
- [ ] Image quality settings
- [ ] Progress persistence (resume after refresh)
- [ ] Additional AI models

## Credits

- **BRIA AI** - RMBG-1.4 model
- **Hugging Face** - Transformers.js library
- **WordPress Community** - Inspiration and support

## Changelog

### 1.0.0 (2025-01-17)
- Initial release
- Shortcode-based public tool
- Bulk processing for all visitors
- WebGPU/WASM support
- Client-side AI processing

---

Made with ❤️ for WordPress
