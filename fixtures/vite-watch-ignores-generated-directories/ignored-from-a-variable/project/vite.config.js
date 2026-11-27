import { defineConfig } from 'vite';
import { generated } from './build/paths.js';

export default defineConfig({
    server: {
        watch: {
            ignored: generated,
        },
    },
});
