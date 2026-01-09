import { test, expect } from '@playwright/test';
import { AuthHelper } from './helpers/auth';
import { OrganizationHelper } from './helpers/organizations';

test.describe('Organizations Feature', () => {
    let authHelper: AuthHelper;
    let orgHelper: OrganizationHelper;

    test.beforeEach(async ({ page }) => {
        authHelper = new AuthHelper(page);
        orgHelper = new OrganizationHelper(page);

        // Login before each test
        // Using a test user - you may need to create this via seeders
        await authHelper.login('test@example.com', 'password');
    });

    test.describe('Organizations List', () => {
        test('should display default organization', async ({ page }) => {
            await orgHelper.gotoOrganizations();

            // Should see default organization
            await expect(page.locator('text=Default')).toBeVisible();
            
            // Should see organization name
            await expect(page.locator('text=My Organization').or(page.locator('text=Organization'))).toBeVisible();
            
            // Should see AWS accounts count
            await expect(page.locator('text=/\\d+ AWS account/')).toBeVisible();
        });

        test('should show add organization button', async ({ page }) => {
            await orgHelper.gotoOrganizations();
            await expect(page.locator('button:has-text("Add Organization")')).toBeVisible();
        });

        test('should navigate from sidebar', async ({ page }) => {
            await page.goto('/dashboard');
            await page.click('text=Organizations');
            await expect(page).toHaveURL(/.*\/organizations/);
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
            
            // Get the default org name (first org in list)
            const defaultOrgCard = page.locator('[class*="bg-white"], [class*="bg-gray-800"]').first();
            const defaultOrgName = await defaultOrgCard.locator('h3').textContent() || '';
            
            // Switch to default organization
            await orgHelper.switchToOrganization(defaultOrgName.trim());
            
            // Verify success notification
            await expect(page.locator(`text=Switched to ${defaultOrgName.trim()}`)).toBeVisible();
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

