import { defineConfig } from 'vite';

export default defineConfig({
    server: {
        host: '0.0.0.0',
        hmr: { host: 'theme.ddev.site' }, // the DDEV router
    },
    build: {
        outDir: 'dist',
    },
});
