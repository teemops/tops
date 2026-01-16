import { test, expect, request } from '@playwright/test';
import { AuthHelper } from './helpers/auth';
import { OrganizationHelper } from './helpers/organizations';

test.describe('Organizations Feature', () => {
    let authHelper: AuthHelper;
    let orgHelper: OrganizationHelper;

    // Clear rate limiter before all tests in this file
    test.beforeAll(async () => {
        const baseURL = process.env.PLAYWRIGHT_TEST_BASE_URL || 'http://localhost:8000';
        const requestContext = await request.newContext({ baseURL });
        
        try {
            await requestContext.post('/api/test/clear-rate-limiter', {
                data: { email: 'test@auditaws.cloud' },
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            });
        } catch (error) {
            console.warn('[Organizations Tests] Could not clear rate limiter:', error);
        }
        
        await requestContext.dispose();
    });

    test.beforeEach(async ({ page }) => {
        authHelper = new AuthHelper(page);
        orgHelper = new OrganizationHelper(page);

        // Login before each test using default test user
        // Test user should be seeded: php artisan db:seed --class=TestUserSeeder
        await authHelper.login();
    });

    test.describe('Organizations List', () => {
        test('should display default organization', async ({ page }) => {
            await orgHelper.gotoOrganizations();
            
            // Wait for API call to complete (if it happens) or just wait for content
            try {
                await page.waitForResponse(response => 
                    response.url().includes('/api/organizations') && response.status() === 200,
                    { timeout: 5000 }
                );
            } catch {
                // API call might not happen if data is cached or loaded differently
            }
            
            // Wait for organizations to render - look for the page title (heading)
            await expect(page.locator('h1:has-text("Organizations")')).toBeVisible();
            
            // Wait for at least one organization card to appear
            // Look for cards that contain organization info
            await page.waitForSelector('h3', { timeout: 10000 });
            
            // Should see organization name
            const orgName = page.locator('h3').first();
            await expect(orgName).toBeVisible();
            
            // Should see AWS accounts count (might be "0 AWS accounts")
            await expect(page.locator('text=/AWS account/')).toBeVisible({ timeout: 5000 });
        });

        test('should show add organization button', async ({ page }) => {
            await orgHelper.gotoOrganizations();
            await expect(page.locator('button:has-text("Add Organization")')).toBeVisible();
        });

        test('should navigate from sidebar', async ({ page }) => {
            await page.goto('/dashboard');
            await page.waitForLoadState('networkidle');
            await page.click('text=Organizations');
            await expect(page).toHaveURL(/.*\/organizations/);
            await page.waitForLoadState('networkidle');
        });
    });

    test.describe('Create Organization', () => {
        test('should create a new organization', async ({ page }) => {
            const orgName = `Test Org ${Date.now()}`;
            
            await orgHelper.createOrganization(orgName);
            
            // Verify organization appears in list
            await orgHelper.expectOrganizationExists(orgName);
            
            // Verify success notification
            await expect(page.locator('text=created successfully')).toBeVisible();
        });

        test('should show validation error for empty name', async ({ page }) => {
            await orgHelper.gotoOrganizations();
            await page.click('button:has-text("Add Organization")');
            await page.waitForSelector('text=Create Organization');
            
            // Try to submit without name
            await page.click('button:has-text("Create")');
            
            // Should show validation error
            await expect(page.locator('input[name="name"]:invalid')).toBeVisible();
        });

        test('should auto-switch to new organization', async ({ page }) => {
            const orgName = `Auto Switch Org ${Date.now()}`;
            
            await orgHelper.createOrganization(orgName);
            
            // Should show "Current organization" on the new org
            const card = await orgHelper.getOrganizationCard(orgName);
            await expect(card.locator('text=Current organization')).toBeVisible();
        });
    });

    test.describe('Switch Organization', () => {
        test('should switch between organizations', async ({ page }) => {
            // Create a second organization
            const orgName = `Switch Test Org ${Date.now()}`;
            await orgHelper.createOrganization(orgName);
            
            // Wait for page to update
            await page.waitForLoadState('networkidle');
            
            // Get the default org name (first org in list that's not the one we just created)
            const orgCards = page.locator('[class*="bg-white"], [class*="bg-gray-800"]').filter({
                hasText: /AWS account/
            });
            const defaultOrgCard = orgCards.filter({ hasNotText: orgName }).first();
            const defaultOrgName = await defaultOrgCard.locator('h3').textContent() || '';
            
            if (defaultOrgName.trim()) {
                // Switch to default organization
                await orgHelper.switchToOrganization(defaultOrgName.trim());
                
                // Verify success notification
                await expect(page.locator(`text=Switched to ${defaultOrgName.trim()}`)).toBeVisible({ timeout: 10000 });
            }
        });
    });

    test.describe('Organization Settings', () => {
        test('should open settings page', async ({ page }) => {
            await orgHelper.gotoOrganizations();
            
            // Get first organization name
            const firstOrgCard = page.locator('[class*="bg-white"], [class*="bg-gray-800"]').first();
            const orgName = await firstOrgCard.locator('h3').textContent() || '';
            
            await orgHelper.openSettings(orgName.trim());
            
            // Should be on settings page
            await expect(page).toHaveURL(/.*\/organizations\/.*\/settings/);
            await expect(page.locator('text=Organization Settings')).toBeVisible();
        });

        test('should update organization name', async ({ page }) => {
            // Create test organization
            const originalName = `Update Test ${Date.now()}`;
            await orgHelper.createOrganization(originalName);
            
            // Open settings
            await orgHelper.openSettings(originalName);
            
            // Update name
            const newName = `Updated ${Date.now()}`;
            await orgHelper.updateOrganizationName(newName);
            
            // Verify success notification
            await expect(page.locator('text=updated successfully')).toBeVisible();
            
            // Go back to list and verify name changed
            await orgHelper.gotoOrganizations();
            await orgHelper.expectOrganizationExists(newName);
        });
    });

    test.describe('Delete Organization', () => {
        test('should delete organization without AWS accounts', async ({ page }) => {
            // Create test organization
            const orgName = `Delete Test ${Date.now()}`;
            await orgHelper.createOrganization(orgName);
            
            // Delete it
            await orgHelper.deleteOrganization(orgName);
            
            // Verify it's gone
            await orgHelper.expectOrganizationNotExists(orgName);
        });

        test('should not allow deleting default organization', async ({ page }) => {
            await orgHelper.gotoOrganizations();
            
            // Find default organization
            const defaultOrgCard = page.locator('text=Default').locator('..').locator('..').locator('..');
            
            // Open settings
            await defaultOrgCard.locator('button').filter({ hasText: '' }).first().click();
            await page.click('text=Settings');
            
            // Should not see delete button in danger zone
            await expect(page.locator('text=Danger Zone')).toBeVisible();
            await expect(page.locator('button:has-text("Delete")')).not.toBeVisible();
            
            // Should see warning message
            await expect(page.locator('text=cannot be deleted')).toBeVisible();
        });
    });

    test.describe('Responsive Design', () => {
        test('should work on mobile viewport', async ({ page }) => {
            await page.setViewportSize({ width: 375, height: 667 });
            await orgHelper.gotoOrganizations();
            
            // Should still see organizations
            await expect(page.locator('text=Organizations')).toBeVisible();
            
            // Should see add button
            await expect(page.locator('button:has-text("Add Organization")')).toBeVisible();
        });

        test('should work on tablet viewport', async ({ page }) => {
            await page.setViewportSize({ width: 768, height: 1024 });
            await orgHelper.gotoOrganizations();
            
            // Should see organizations in grid
            await expect(page.locator('text=Organizations')).toBeVisible();
        });
    });
});

