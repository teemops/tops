import { test, expect } from '@playwright/test';
import { AuthHelper } from './helpers/auth';

test.describe('Authentication', () => {
    test('should login successfully', async ({ page }) => {
        const authHelper = new AuthHelper(page);
        
        await authHelper.login();
        
        // Should be on dashboard
        await expect(page).toHaveURL(/.*\/dashboard/);
        
        // Should see dashboard content
        await expect(page.locator('text=Dashboard')).toBeVisible();
    });
});

