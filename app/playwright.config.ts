import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright configuration for Laravel + Vue application
 * 
 * See https://playwright.dev/docs/test-configuration
 */
export default defineConfig({
    testDir: './tests/e2e',
    
    /* Run tests in files in parallel */
    fullyParallel: true,
    
    /* Fail the build on CI if you accidentally left test.only in the source code. */
    forbidOnly: !!process.env.CI,
    
    /* Retry on CI only */
    retries: process.env.CI ? 2 : 0,
    
    /* Opt out of parallel tests on CI. */
    workers: process.env.CI ? 1 : undefined,
    
    /* Reporter to use. See https://playwright.dev/docs/test-reporters */
    reporter: [
        ['html'],
        ['list'],
        ...(process.env.CI ? [['github'] as const] : []),
    ],
    
    /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
    use: {
        /* Base URL to use in actions like `await page.goto('/')`. */
        baseURL: process.env.PLAYWRIGHT_TEST_BASE_URL || 'http://localhost:8000',
        
        /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
        /* Change to 'on' to always collect traces for debugging */
        trace: process.env.PWDEBUG ? 'on' : 'on-first-retry',
        
        /* Screenshot on failure */
        screenshot: 'only-on-failure',
        
        /* Video on failure */
        video: 'retain-on-failure',
        
        /* Increase timeout for actions */
        actionTimeout: 10000,
        
        /* Increase navigation timeout */
        navigationTimeout: 30000,
        
        /* Store session state for authentication */
        storageState: undefined, // Can be set to a file path to persist auth state
    },

    /* Configure projects for major browsers */
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
        
        // Uncomment to test in other browsers
        // {
        //     name: 'firefox',
        //     use: { ...devices['Desktop Firefox'] },
        // },
        // {
        //     name: 'webkit',
        //     use: { ...devices['Desktop Safari'] },
        // },
    ],

    /* Run your local dev servers before starting the tests */
    webServer: {
        /* Use a script that starts both servers
         * Only Laravel is health-checked (on port 8000)
         * Vite is started in background - Laravel won't fully load without it
         */
        command: 'bash scripts/start-dev-servers.sh',
        url: 'http://localhost:8000',
        reuseExistingServer: !process.env.CI,
        timeout: 120 * 1000,
        stdout: 'pipe',
        stderr: 'pipe',
        env: {
            ...process.env,
        },
    },
});

