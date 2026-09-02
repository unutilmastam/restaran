import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

// `base` deploy sxemasiga bog'liq (docs/05-PHASE0-PLAN.md §1):
// subdomain'da '/', subdirectory'da '/admin/'. Env orqali beriladi,
// shunda PHASE 16 da kod o'zgartirilmaydi.
export default defineConfig({
  base: process.env.VITE_BASE ?? '/',
  plugins: [react()],
  server: { port: 5174 },
  build: {
    outDir: 'dist',
    // 1 GB disk byudjeti — build artefaktlari nazorat ostida (docs/05 §0).
    chunkSizeWarningLimit: 600,
    sourcemap: false,
  },
});
