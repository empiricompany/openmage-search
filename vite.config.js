import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
    define: {
        'process.env.NODE_ENV': JSON.stringify('production')
    },
    build: {
        lib: {
            entry: resolve(__dirname, 'skin/frontend/base/default/js/mm_search/src/index.js'),
            name: 'InstantSearchBundle',
            formats: ['iife']
        },
        outDir: resolve(__dirname, 'skin/frontend/base/default/js/mm_search/dist'),
        emptyOutDir: true,
        manifest: true,
        minify: 'terser',
        terserOptions: {
            compress: {
                drop_console: false,
                drop_debugger: true
            },
            format: {
                comments: false
            }
        },
        sourcemap: true,
        rollupOptions: {
            output: {
                extend: true,
                exports: 'none',
                entryFileNames: 'instantsearch-bundle.[hash].js'
            }
        }
    },
    resolve: {
        alias: {
            '@': resolve(__dirname, 'skin/frontend/base/default/js/mm_search/src')
        }
    }
});