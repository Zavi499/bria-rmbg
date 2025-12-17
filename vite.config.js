import { defineConfig } from 'vite';
import { resolve } from 'path';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  build: {
    outDir: 'build',
    target: 'es2020',
    rollupOptions: {
      input: {
        'admin': resolve(__dirname, 'assets/js/admin.js'),
        'background-remover': resolve(__dirname, 'assets/js/background-remover.js'),
        'bulk-processor': resolve(__dirname, 'assets/js/bulk-processor.js'),
        'media-modal': resolve(__dirname, 'assets/js/media-modal.js'),
        'gutenberg-block': resolve(__dirname, 'blocks/background-remover/index.jsx'),
      },
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: 'assets/[name][extname]'
      }
    },
    chunkSizeWarningLimit: 2000,
    commonjsOptions: {
      transformMixedEsModules: true
    }
  },
  optimizeDeps: {
    exclude: ['@huggingface/transformers']
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, './assets/js')
    }
  }
});
