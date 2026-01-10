import { Page, expect } from '@playwright/test';

/**
 * Organization helpers for Playwright tests
 */
export class OrganizationHelper {
    constructor(private page: Page) {}

    /**
     * Navigate to organizations list page
     */
    async gotoOrganizations(): Promise<void> {
        await this.page.goto('/organizations');
        // Wait for page title to ensure page loaded
        await this.page.waitForSelector('text=Organizations', { timeout: 10000 });
        // Wait for API call to complete (if it happens)
        try {
            await this.page.waitForResponse(response => 
                response.url().includes('/api/organizations') && response.status() === 200,
                { timeout: 5000 }
            );
        } catch {
            // API call might not happen - that's okay
        }
        await this.page.waitForLoadState('networkidle');
        // Give Vue time to render
        await this.page.waitForTimeout(1000);
    }

    /**
     * Create a new organization
     */
    async createOrganization(name: string): Promise<void> {
        await this.gotoOrganizations();
        await this.page.click('button:has-text("Add Organization")');
        await this.page.waitForSelector('text=Create Organization');
        await this.page.waitForLoadState('networkidle');
        // Use ID selector
        await this.page.fill('input#name', name);
        await this.page.click('button:has-text("Create")');
        // Wait for success notification
        await this.page.waitForSelector('text=created successfully', { timeout: 10000 });
    }

    /**
     * Get organization card by name
     */
    async getOrganizationCard(name: string) {
        // Find card containing the organization name
        return this.page.locator('[class*="bg-white"], [class*="bg-gray-800"]').filter({
            hasText: new RegExp(name)
        }).first();
    }

    /**
     * Switch to an organization
     */
    async switchToOrganization(name: string): Promise<void> {
        await this.gotoOrganizations();
        await this.page.waitForLoadState('networkidle');
        const card = await this.getOrganizationCard(name);
        await card.locator('button:has-text("Switch to this organization")').click();
        // Wait for success notification
        await this.page.waitForSelector(`text=Switched to ${name}`, { timeout: 10000 });
    }

    /**
     * Open organization settings
     */
    async openSettings(organizationName: string): Promise<void> {
        await this.gotoOrganizations();
        await this.page.waitForLoadState('networkidle');
        const card = await this.getOrganizationCard(organizationName);
        // Click actions menu (three dots) - find button with SVG icon
        await card.locator('button').filter({ has: this.page.locator('svg') }).first().click();
        await this.page.waitForSelector('text=Settings', { state: 'visible' });
        await this.page.click('text=Settings');
        await this.page.waitForURL('**/organizations/*/settings', { timeout: 10000 });
    }

    /**
     * Update organization name
     */
    async updateOrganizationName(newName: string): Promise<void> {
        await this.page.waitForLoadState('networkidle');
        // Use ID selector
        await this.page.fill('input#name', newName);
        await this.page.click('button:has-text("Save changes")');
        await this.page.waitForSelector('text=updated successfully', { timeout: 10000 });
    }

    /**
     * Delete an organization
     */
    async deleteOrganization(organizationName: string): Promise<void> {
        await this.openSettings(organizationName);
        await this.page.click('button:has-text("Delete")');
        // Confirm deletion in modal
        await this.page.waitForSelector('text=Delete Organization');
        await this.page.click('button:has-text("Delete"):not(:has-text("Cancel"))');
        await this.page.waitForSelector('text=deleted successfully', { timeout: 5000 });
    }

    /**
     * Verify organization exists in list
     */
    async expectOrganizationExists(name: string): Promise<void> {
        await expect(this.page.locator(`text=${name}`)).toBeVisible();
    }

    /**
     * Verify organization does not exist in list
     */
    async expectOrganizationNotExists(name: string): Promise<void> {
        await expect(this.page.locator(`text=${name}`)).not.toBeVisible();
    }

    /**
     * Get organization count
     */
    async getOrganizationCount(): Promise<number> {
        const cards = await this.page.locator('[class*="bg-white"], [class*="bg-gray-800"]').filter({
            hasText: /AWS account/
        }).count();
        return cards;
    }
}

