import { test, expect } from '@playwright/test';
import { AuthHelper } from './helpers/auth';

test.describe('Authentication', () => {
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

