import { defineConfig } from 'vitest/config'

export default defineConfig({
    test: {
        include: ['js/tests/**/*.test.ts'],
        environment: 'node',
    },
})
