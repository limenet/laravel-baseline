import { defineConfig } from 'vite';
import { devServer } from './build/server.js';

export default defineConfig({
    server: {
        ...devServer,
        port: 5173,
    },
});
