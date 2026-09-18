import { defineConfig } from '@playwright/test';
export default defineConfig({
    testDir: './tests/browser', testMatch: '**/*.spec.js', workers: 1, timeout: 60000,
    use: { baseURL: 'http://127.0.0.1:8123', channel: 'chrome', headless: true, viewport: { width: 1500, height: 1000 }, trace: 'retain-on-failure' },
    webServer: { command: 'node scripts/browser-server.mjs', url: 'http://127.0.0.1:8123/login', reuseExistingServer: false, timeout: 30000 },
});
