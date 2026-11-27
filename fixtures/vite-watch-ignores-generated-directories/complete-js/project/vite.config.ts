import { defineConfig } from "vite"

export default defineConfig({
  build: {
    sourcemap: true
  },
  server: {
    watch: {
      ignored: [
        "**/coverage/**",
        "**/playwright-report/**",
        "**/.trivycache/**"
      ]
    }
  }
})
