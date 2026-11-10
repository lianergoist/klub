import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

import { fileURLToPath } from 'node:url';

export default defineConfig({
    // Webudgaven: ikke app, API på samme adresse
    define: { __APP__: 'false', __API_BASE__: JSON.stringify('') },
    // Push-pluginets web-del bruges ikke – peg Firebase-web-SDK'et på en lille erstatning
    resolve: { alias: { 'firebase/messaging': fileURLToPath(new URL('./resources/js/stubs/firebase-messaging.js', import.meta.url)) } },
    plugins: [
        laravel({ input: ['resources/css/app.css', 'resources/js/app.js'], refresh: true }),
        vue({ template: { transformAssetUrls: { base: null, includeAbsolute: false } } }),
    ],
});
