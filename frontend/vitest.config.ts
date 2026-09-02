import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    environment: 'jsdom',
    globals: true,
    include: ['shared/**/*.test.ts', 'shared/**/*.test.tsx', '*/src/**/*.test.{ts,tsx}'],
  },
});
