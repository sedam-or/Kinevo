import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    reporter: [['list']],
    timeout: 60_000,
    expect: { timeout: 15_000 },
    use: {
        baseURL: process.env.E2E_BASE_URL || 'http://127.0.0.1:8000',
        trace: 'retain-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
