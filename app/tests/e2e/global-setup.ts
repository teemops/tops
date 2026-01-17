import { request } from '@playwright/test';

/**
 * Global setup for Playwright E2E tests
 * Runs once before all tests to ensure a clean test state
 */
async function globalSetup() {
    const baseURL = process.env.PLAYWRIGHT_TEST_BASE_URL || 'http://localhost:8000';
    
    // Create a request context for API calls
    const requestContext = await request.newContext({
        baseURL,
    });

    console.log('[Global Setup] Clearing rate limiters for test users...');

    // Clear rate limiter for the default test user
    try {
        const response = await requestContext.post('/api/test/reset-test-state', {
            data: { email: 'test@auditaws.cloud' },
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
        });

        if (response.ok()) {
            console.log('[Global Setup] Rate limiter cleared successfully');
        } else {
            console.warn(`[Global Setup] Failed to clear rate limiter: ${response.status()}`);
        }
    } catch (error) {
        console.warn('[Global Setup] Could not clear rate limiter (server may not be running yet):', error);
    }

    await requestContext.dispose();
}

export default globalSetup;
