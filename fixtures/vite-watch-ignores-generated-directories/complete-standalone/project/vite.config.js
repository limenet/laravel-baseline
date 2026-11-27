import { defineConfig } from 'vite';

export default defineConfig({
    server: {
        watch: {
            ignored: [
                '**/coverage/**',
                '**/playwright-report/**',
                '**/.trivycache/**',
                '**/vendor/**',
                '**/.cache/**',
                '**/.phpunit.cache/**',
                '**/.ddev/**',
            ],
        },
    },
});
