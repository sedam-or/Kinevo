import { defineConfig, mergeConfig } from 'vitest/config';
import viteConfig from './vite.config.ts';

export default mergeConfig(
    viteConfig,
    defineConfig({
        test: {
            environment: 'happy-dom',
            include: ['resources/js/**/*.{test,spec}.ts'],
            globals: false,
        },
    }),
);