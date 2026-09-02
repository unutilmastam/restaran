import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

// SUPER_ADMIN paneli — docs/06-SAAS.md §7: super.itcode.uz
export default defineConfig({
  base: process.env.VITE_BASE ?? '/',
  plugins: [react()],
  server: { port: 5176 },
  build: {
    outDir: 'dist',
    chunkSizeWarningLimit: 600,
    sourcemap: false,
  },
});
