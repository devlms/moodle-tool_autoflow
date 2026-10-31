/// <reference types="vitest/config" />
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Builds one self-contained script that the plugin loads with $PAGE->requires->js().
// It exposes window.ToolAutoflowEditor; amd/src/editor.js mounts it. React is bundled
// in so it never touches the copy Moodle 5.2+ ships for its own components.
export default defineConfig({
  plugins: [react()],
  define: { 'process.env.NODE_ENV': JSON.stringify('production') },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    cssCodeSplit: false,
    lib: {
      entry: 'src/main.tsx',
      name: 'ToolAutoflowEditor',
      formats: ['iife'],
      fileName: () => 'editor.js',
      cssFileName: 'editor',
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['test/setup.ts'],
    include: ['test/**/*.test.{ts,tsx}'],
    coverage: {
      provider: 'v8',
      include: ['src/**'],
      exclude: ['src/main.tsx'],
      reporter: ['text', 'html', 'json-summary', 'lcov'],
      reportsDirectory: 'coverage',
    },
  },
});
