import { Page, expect } from '@playwright/test';

/**
 * Scan helpers for Playwright tests
 */
export class ScanHelper {
    constructor(private page: Page) {}

    /**
     * Navigate to scans list page
     */
    async gotoScans(): Promise<void> {
        await this.page.goto('/scans');
        // Wait for page title to ensure page loaded
        await this.page.waitForSelector('h1:has-text("Scans")', { timeout: 10000 });
        await this.page.waitForLoadState('networkidle');
    }

    /**
     * Open the new scan modal
     */
    async openNewScanModal(): Promise<void> {
        await this.page.click('button:has-text("New Scan")');
        await this.page.waitForSelector('text=Start New Scan', { timeout: 5000 });
    }

    /**
     * Close the new scan modal
     */
    async closeNewScanModal(): Promise<void> {
        await this.page.click('button:has-text("Cancel")');
        await this.page.waitForSelector('text=Start New Scan', { state: 'hidden', timeout: 5000 });
    }

    /**
     * Create a new scan with specified options
     * @param awsAccountName - The name of the AWS account to scan
     * @param scanTypes - Array of scan types to select (e.g., ['iam', 's3', 'ec2'])
     */
    async createScan(awsAccountName: string, scanTypes: string[]): Promise<void> {
        await this.openNewScanModal();

        // Select AWS account
        await this.page.selectOption('select#aws_account', { label: new RegExp(awsAccountName) });

        // Select scan types
        for (const scanType of scanTypes) {
            const checkbox = this.page.locator(`input[type="checkbox"][value="${scanType}"]`);
            await checkbox.check();
        }

        // Submit form
        await this.page.click('button:has-text("Start Scan")');
        
        // Wait for success notification
        await this.page.waitForSelector('text=Scan started successfully', { timeout: 10000 });
    }

    /**
     * Get scan row by account name
     */
    getScanRowByAccount(accountName: string) {
        return this.page.locator('tr').filter({
            hasText: accountName
        }).first();
    }

    /**
     * Get all scan rows
     */
    getScanRows() {
        return this.page.locator('tbody tr');
    }

    /**
     * View scan details
     */
    async viewScan(accountName: string): Promise<void> {
        const row = this.getScanRowByAccount(accountName);
        await row.locator('button:has-text("View"), a:has-text("View")').click();
        await this.page.waitForURL(/.*\/scans\/[a-f0-9-]+/, { timeout: 10000 });
    }

    /**
     * Cancel a scan
     */
    async cancelScan(accountName: string): Promise<void> {
        const row = this.getScanRowByAccount(accountName);
        
        // Set up dialog handler before clicking
        this.page.once('dialog', async dialog => {
            await dialog.accept();
        });
        
        await row.locator('button:has-text("Cancel")').click();
        
        // Wait for success notification
        await this.page.waitForSelector('text=Scan cancelled successfully', { timeout: 10000 });
    }

    /**
     * Filter scans by status
     */
    async filterByStatus(status: string): Promise<void> {
        await this.page.selectOption('select:below(:text("Status"))', status);
        await this.page.waitForLoadState('networkidle');
    }

    /**
     * Filter scans by AWS account
     */
    async filterByAccount(accountName: string): Promise<void> {
        await this.page.selectOption('select:below(:text("Account"))', { label: new RegExp(accountName) });
        await this.page.waitForLoadState('networkidle');
    }

    /**
     * Filter scans by scan type
     */
    async filterByScanType(scanType: string): Promise<void> {
        await this.page.selectOption('select:below(:text("Scan Type"))', scanType);
        await this.page.waitForLoadState('networkidle');
    }

    /**
     * Clear all filters
     */
    async clearFilters(): Promise<void> {
        await this.page.click('button:has-text("Clear filters")');
        await this.page.waitForLoadState('networkidle');
    }

    /**
     * Click on a sortable column header
     */
    async sortBy(column: string): Promise<void> {
        await this.page.click(`th:has-text("${column}")`);
        await this.page.waitForLoadState('networkidle');
    }

    /**
     * Navigate to next page
     */
    async nextPage(): Promise<void> {
        await this.page.click('button[aria-label="Next"], button:has(:text("Next"))');
        await this.page.waitForLoadState('networkidle');
    }

    /**
     * Navigate to previous page
     */
    async previousPage(): Promise<void> {
        await this.page.click('button[aria-label="Previous"], button:has(:text("Previous"))');
        await this.page.waitForLoadState('networkidle');
    }

    /**
     * Check if scan has a specific status badge
     */
    async expectScanStatus(accountName: string, status: string): Promise<void> {
        const row = this.getScanRowByAccount(accountName);
        await expect(row.locator(`text=${status}`)).toBeVisible();
    }

    /**
     * Check if empty state is shown
     */
    async expectEmptyState(): Promise<void> {
        await expect(this.page.locator('text=No scans')).toBeVisible();
        await expect(this.page.locator('text=Get started by running your first security scan')).toBeVisible();
    }

    /**
     * Check if loading state is shown
     */
    async expectLoading(): Promise<void> {
        await expect(this.page.locator('text=Loading scans')).toBeVisible();
    }

    /**
     * Get scan count from pagination info
     */
    async getScanCount(): Promise<number> {
        const paginationText = await this.page.locator('text=/of \\d+ results/').textContent();
        if (!paginationText) return 0;
        const match = paginationText.match(/of (\d+) results/);
        return match ? parseInt(match[1], 10) : 0;
    }

    /**
     * Navigate to scan details page
     */
    async gotoScanDetails(scanId: string): Promise<void> {
        await this.page.goto(`/scans/${scanId}`);
        await this.page.waitForSelector('text=Scan', { timeout: 10000 });
        await this.page.waitForLoadState('networkidle');
    }

    /**
     * Check severity summary on scan details page
     */
    async expectSeveritySummary(): Promise<void> {
        await expect(this.page.locator('text=Critical')).toBeVisible();
        await expect(this.page.locator('text=High')).toBeVisible();
        await expect(this.page.locator('text=Medium')).toBeVisible();
        await expect(this.page.locator('text=Low')).toBeVisible();
    }

    /**
     * Filter findings by severity on details page
     */
    async filterFindingsBySeverity(severity: string): Promise<void> {
        await this.page.selectOption('select:has-text("All severities")', severity);
        await this.page.waitForLoadState('networkidle');
    }

    /**
     * Check if findings list is visible
     */
    async expectFindingsSection(): Promise<void> {
        await expect(this.page.locator('text=Findings')).toBeVisible();
    }

    /**
     * Check if no findings message is shown
     */
    async expectNoFindings(): Promise<void> {
        await expect(this.page.locator('text=No findings')).toBeVisible();
    }
}
