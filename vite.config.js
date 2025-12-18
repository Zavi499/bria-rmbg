import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
  build: {
    outDir: 'build',
    target: 'es2020',
    rollupOptions: {
      input: {
        'background-remover': resolve(__dirname, 'assets/js/background-remover.js'),
        'app': resolve(__dirname, 'assets/js/app.js'),
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
