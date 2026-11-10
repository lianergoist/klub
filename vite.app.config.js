import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

// Build til mobilappen: npm run build:app  (API-adresse: KLUB_API=https://klub.rpix.dk npm run build:app)
import { fileURLToPath } from 'node:url';

export default defineConfig({
    root: 'resources/app',
    base: '/',
    publicDir: 'public',
    // Push-pluginets web-del bruges ikke – peg Firebase-web-SDK'et på en lille erstatning
    resolve: { alias: { 'firebase/messaging': fileURLToPath(new URL('./resources/js/stubs/firebase-messaging.js', import.meta.url)) } },
    plugins: [vue({ template: { transformAssetUrls: { base: null, includeAbsolute: false } } })],
    define: {
        __APP__: 'true',
        __API_BASE__: JSON.stringify(process.env.KLUB_API || 'https://klub.rpix.dk'),
    },
    build: { outDir: '../../dist-app', emptyOutDir: true },
});
