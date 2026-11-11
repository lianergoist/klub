import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

import { fileURLToPath } from 'node:url';

// Versionen står ét sted: package.json (vises som 0.1). Byggedato vises også, så man kan se, om webudgaven er ny.
import { readFileSync } from 'node:fs';
const pkg = JSON.parse(readFileSync(new URL('./package.json', import.meta.url)));
const VERSION = { __APP_VERSION__: JSON.stringify(pkg.version.replace(/\.0$/, '')), __BUILD_DATE__: JSON.stringify(new Date().toISOString()) };

export default defineConfig({
    // Webudgaven: ikke app, API på samme adresse
    define: { __APP__: 'false', __API_BASE__: JSON.stringify(''), ...VERSION },
    // Push-pluginets web-del bruges ikke – peg Firebase-web-SDK'et på en lille erstatning
    resolve: { alias: { 'firebase/messaging': fileURLToPath(new URL('./resources/js/stubs/firebase-messaging.js', import.meta.url)) } },
    plugins: [
        laravel({ input: ['resources/css/app.css', 'resources/js/app.js'], refresh: true }),
        vue({ template: { transformAssetUrls: { base: null, includeAbsolute: false } } }),
    ],
});
