import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tsconfigPaths from 'vite-tsconfig-paths';
import tailwindcss from "@tailwindcss/vite";
import path from "node:path";


const fullPageRefresh = process.env.VITE_FULL_REFRESH === '1';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.tsx',
                'resources/css/filament/admin/theme.css',
            ],
            refresh: fullPageRefresh,
        }),
        react(),
        tailwindcss(),
        tsconfigPaths(),
    ],
    server: {
        host: '0.0.0.0',
        hmr: {
            host: 'localhost',
            clientPort: 8089,
            protocol: 'wss',
            path: '/vite-hmr',
        },
        port: 5173,
        strictPort: true,
        watch: {
            ignored: [
                '**/storage/**',
                '**/vendor/**',
                '**/node_modules/**',
                '**/.git/**',
            ],
            
            ...(process.env.VITE_USE_POLLING === '1' ? { usePolling: true } : {}),
        },
    },

    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
        },
    },
});
