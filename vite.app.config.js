import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

// Build til mobilappen: npm run build:app  (API-adresse: KLUB_API=https://klub.rpix.dk npm run build:app)
export default defineConfig({
    root: 'resources/app',
    base: '/',
    publicDir: 'public',
    plugins: [vue({ template: { transformAssetUrls: { base: null, includeAbsolute: false } } })],
    define: {
        __APP__: 'true',
        __API_BASE__: JSON.stringify(process.env.KLUB_API || 'https://test.klub.rpix.dk'),
    },
    build: { outDir: '../../dist-app', emptyOutDir: true },
});
