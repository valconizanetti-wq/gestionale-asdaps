import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/editor.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    // L'editor dei verbali (Jodit) pesa ~220 kB compresso: si carica solo dove serve.
    build: { chunkSizeWarningLimit: 800 },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
