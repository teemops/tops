import { test, expect, request } from '@playwright/test';
import { AuthHelper } from './helpers/auth';

test.describe('Authentication', () => {
    // Clear rate limiter before all tests in this file
    test.beforeAll(async () => {
        const baseURL = process.env.PLAYWRIGHT_TEST_BASE_URL || 'http://localhost:8000';
        const requestContext = await request.newContext({ baseURL });
        
        try {
            // Clear rate limiter for test users
            await requestContext.post('/api/test/clear-rate-limiter', {
                data: { email: 'test@auditaws.cloud' },
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            });
        } catch (error) {
            console.warn('[Auth Tests] Could not clear rate limiter:', error);
        }
        
        await requestContext.dispose();
    });

    // Use serial mode to ensure tests run in order
    test.describe.serial('User Registration and Login', () => {
        test('should register a new user', async ({ page }) => {
            const authHelper = new AuthHelper(page);
            
            // Register test user
            await authHelper.register(
                'Test User',
                'test@auditaws.cloud',
                'password'
            );
            
            // Should be on email verification page
            await expect(page).toHaveURL(/.*\/verify-email/);
            
            // Verify email using the test API endpoint
            // This uses /api/test/verify-email-by-address which is only available in testing/local
            await authHelper.verifyEmailByAddress('test@auditaws.cloud');
        });
        
        test('should login successfully with registered user', async ({ page }) => {
            const authHelper = new AuthHelper(page);
            
            // Login with the registered user
            await authHelper.login('test@auditaws.cloud', 'password');
            
            // Should be on dashboard
            await expect(page).toHaveURL(/.*\/dashboard/);
            
            // Should see dashboard content
            await expect(page.locator('text=Dashboard')).toBeVisible();
        });
    });
});

