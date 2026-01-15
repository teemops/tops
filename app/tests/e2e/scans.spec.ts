import { test, expect } from '@playwright/test';
import { AuthHelper } from './helpers/auth';
import { ScanHelper } from './helpers/scans';

test.describe('Scans Feature', () => {
    let authHelper: AuthHelper;
    let scanHelper: ScanHelper;

    test.beforeEach(async ({ page }) => {
        authHelper = new AuthHelper(page);
        scanHelper = new ScanHelper(page);

        // Login before each test using default test user
        // Test user should be seeded: php artisan db:seed --class=TestUserSeeder
        await authHelper.login();
    });

    test.describe('Scans List Page', () => {
        test('should display scans page with header', async ({ page }) => {
            await scanHelper.gotoScans();
            
            // Should see page title
            await expect(page.locator('h1:has-text("Scans")')).toBeVisible();
            
            // Should see description
            await expect(page.locator('text=View and manage your security scans')).toBeVisible();
            
            // Should see New Scan button
            await expect(page.locator('button:has-text("New Scan")')).toBeVisible();
        });

        test('should display filter controls', async ({ page }) => {
            await scanHelper.gotoScans();
            
            // Should see filter dropdowns
            await expect(page.locator('text=Account').first()).toBeVisible();
            await expect(page.locator('text=Status').first()).toBeVisible();
            await expect(page.locator('text=Scan Type').first()).toBeVisible();
            
            // Should see clear filters button
            await expect(page.locator('button:has-text("Clear filters")')).toBeVisible();
        });

        test('should navigate to scans from sidebar', async ({ page }) => {
            await page.goto('/dashboard');
            await page.waitForLoadState('networkidle');
            
            // Click Scans link in sidebar
            await page.click('a:has-text("Scans")');
            
            await expect(page).toHaveURL(/.*\/scans/);
            await expect(page.locator('h1:has-text("Scans")')).toBeVisible();
        });

        test('should show empty state when no scans exist', async ({ page }) => {
            await scanHelper.gotoScans();
            
            // Check for either scans table or empty state
            const hasScans = await page.locator('tbody tr').count() > 0;
            
            if (!hasScans) {
                await expect(page.locator('text=No scans')).toBeVisible();
                await expect(page.locator('text=Get started by running your first security scan')).toBeVisible();
                
                // Empty state should have a New Scan button
                await expect(page.locator('button:has-text("New Scan")')).toHaveCount(2); // Header + empty state
            }
        });
    });

    test.describe('New Scan Modal', () => {
        test('should open new scan modal', async ({ page }) => {
            await scanHelper.gotoScans();
            
            await page.click('button:has-text("New Scan")');
            
            // Modal should be visible
            await expect(page.locator('text=Start New Scan')).toBeVisible();
            await expect(page.locator('text=Select an AWS account and scan type to begin a security scan')).toBeVisible();
        });

        test('should display AWS account dropdown', async ({ page }) => {
            await scanHelper.gotoScans();
            await page.click('button:has-text("New Scan")');
            
            // Should see AWS Account label and dropdown
            await expect(page.locator('label:has-text("AWS Account")')).toBeVisible();
            await expect(page.locator('select#aws_account')).toBeVisible();
        });

        test('should display scan type checkboxes', async ({ page }) => {
            await scanHelper.gotoScans();
            await page.click('button:has-text("New Scan")');
            
            // Should see Scan Types label
            await expect(page.locator('text=Scan Types').first()).toBeVisible();
            
            // Should see scan type options (EC2, IAM, S3, RDS)
            await expect(page.locator('text=EC2')).toBeVisible();
            await expect(page.locator('text=IAM')).toBeVisible();
            await expect(page.locator('text=S3')).toBeVisible();
            await expect(page.locator('text=RDS')).toBeVisible();
        });

        test('should close modal on cancel', async ({ page }) => {
            await scanHelper.gotoScans();
            await page.click('button:has-text("New Scan")');
            
            await expect(page.locator('text=Start New Scan')).toBeVisible();
            
            await page.click('button:has-text("Cancel")');
            
            await expect(page.locator('text=Start New Scan')).not.toBeVisible();
        });

        test('should disable start button when no scan types selected', async ({ page }) => {
            await scanHelper.gotoScans();
            await page.click('button:has-text("New Scan")');
            
            // Start Scan button should be disabled when no scan types are selected
            const startButton = page.locator('button:has-text("Start Scan")');
            await expect(startButton).toBeDisabled();
        });

        test('should enable start button when scan type is selected', async ({ page }) => {
            await scanHelper.gotoScans();
            await page.click('button:has-text("New Scan")');
            
            // Select a scan type
            await page.locator('input[type="checkbox"][value="iam"]').check();
            
            // Check if there are available AWS accounts
            const options = await page.locator('select#aws_account option').count();
            
            if (options > 1) {
                // Select first available account
                await page.selectOption('select#aws_account', { index: 1 });
                
                // Start Scan button should now be enabled
                const startButton = page.locator('button:has-text("Start Scan")');
                await expect(startButton).not.toBeDisabled();
            }
        });

        test('should show warning when no AWS accounts available', async ({ page }) => {
            await scanHelper.gotoScans();
            await page.click('button:has-text("New Scan")');
            
            // Check if warning message is shown (only if no accounts)
            const options = await page.locator('select#aws_account option').count();
            
            if (options <= 1) {
                await expect(page.locator('text=No active AWS accounts available')).toBeVisible();
            }
        });
    });

    test.describe('Scans Table', () => {
        test('should display table headers', async ({ page }) => {
            await scanHelper.gotoScans();
            
            // Check if we have scans (table is visible)
            const hasTable = await page.locator('table').isVisible().catch(() => false);
            
            if (hasTable) {
                await expect(page.locator('th:has-text("Account")')).toBeVisible();
                await expect(page.locator('th:has-text("Status")')).toBeVisible();
                await expect(page.locator('th:has-text("Scan Types")')).toBeVisible();
                await expect(page.locator('th:has-text("Findings")')).toBeVisible();
                await expect(page.locator('th:has-text("Started")')).toBeVisible();
                await expect(page.locator('th:has-text("Actions")')).toBeVisible();
            }
        });

        test('should have sortable column headers', async ({ page }) => {
            await scanHelper.gotoScans();
            
            const hasTable = await page.locator('table').isVisible().catch(() => false);
            
            if (hasTable) {
                // Column headers should have cursor-pointer class (clickable)
                const accountHeader = page.locator('th:has-text("Account")');
                await expect(accountHeader).toHaveClass(/cursor-pointer/);
            }
        });

        test('should display status badges with correct styling', async ({ page }) => {
            await scanHelper.gotoScans();
            
            // Check for various status badges if they exist
            const statuses = ['Completed', 'Running', 'Pending', 'Failed', 'Cancelled'];
            
            for (const status of statuses) {
                const badge = page.locator(`span:has-text("${status}")`).first();
                const isVisible = await badge.isVisible().catch(() => false);
                
                if (isVisible) {
                    // Verify badge has rounded styling
                    await expect(badge).toHaveClass(/rounded-full/);
                }
            }
        });
    });

    test.describe('Scan Filters', () => {
        test('should filter by status', async ({ page }) => {
            await scanHelper.gotoScans();
            
            const hasTable = await page.locator('table').isVisible().catch(() => false);
            
            if (hasTable) {
                // Get initial count
                const initialCount = await page.locator('tbody tr').count();
                
                if (initialCount > 0) {
                    // Filter by completed status
                    await page.selectOption('select:below(:text("Status"))', 'completed');
                    await page.waitForLoadState('networkidle');
                    
                    // All visible rows should have Completed status
                    const rows = page.locator('tbody tr');
                    const rowCount = await rows.count();
                    
                    for (let i = 0; i < rowCount; i++) {
                        const row = rows.nth(i);
                        await expect(row.locator('text=Completed')).toBeVisible();
                    }
                }
            }
        });

        test('should clear filters', async ({ page }) => {
            await scanHelper.gotoScans();
            
            // Apply a filter
            await page.selectOption('select:below(:text("Status"))', 'completed');
            await page.waitForLoadState('networkidle');
            
            // Clear filters
            await page.click('button:has-text("Clear filters")');
            await page.waitForLoadState('networkidle');
            
            // Status filter should be reset to "All statuses"
            const statusSelect = page.locator('select:below(:text("Status"))');
            await expect(statusSelect).toHaveValue('');
        });
    });

    test.describe('Scan Actions', () => {
        test('should have view button for scans', async ({ page }) => {
            await scanHelper.gotoScans();
            
            const hasRows = await page.locator('tbody tr').count() > 0;
            
            if (hasRows) {
                const firstRow = page.locator('tbody tr').first();
                await expect(firstRow.locator('button:has-text("View"), a:has-text("View")')).toBeVisible();
            }
        });

        test('should have cancel button for pending/running scans', async ({ page }) => {
            await scanHelper.gotoScans();
            
            // Look for rows with Pending or Running status
            const pendingRow = page.locator('tbody tr').filter({
                hasText: /Pending|Running/
            }).first();
            
            const hasPendingScans = await pendingRow.isVisible().catch(() => false);
            
            if (hasPendingScans) {
                await expect(pendingRow.locator('button:has-text("Cancel")')).toBeVisible();
            }
        });

        test('should not have cancel button for completed scans', async ({ page }) => {
            await scanHelper.gotoScans();
            
            // Look for rows with Completed status
            const completedRow = page.locator('tbody tr').filter({
                hasText: 'Completed'
            }).first();
            
            const hasCompletedScans = await completedRow.isVisible().catch(() => false);
            
            if (hasCompletedScans) {
                await expect(completedRow.locator('button:has-text("Cancel")')).not.toBeVisible();
            }
        });

        test('should navigate to scan details on view click', async ({ page }) => {
            await scanHelper.gotoScans();
            
            const hasRows = await page.locator('tbody tr').count() > 0;
            
            if (hasRows) {
                const firstRow = page.locator('tbody tr').first();
                await firstRow.locator('button:has-text("View"), a:has-text("View")').click();
                
                // Should navigate to scan details page
                await expect(page).toHaveURL(/.*\/scans\/[a-f0-9-]+/);
            }
        });
    });

    test.describe('Scan Details Page', () => {
        test('should display scan header with account name', async ({ page }) => {
            await scanHelper.gotoScans();
            
            const hasRows = await page.locator('tbody tr').count() > 0;
            
            if (hasRows) {
                // Get account name from first row
                const firstRow = page.locator('tbody tr').first();
                const accountName = await firstRow.locator('td').first().textContent();
                
                // Navigate to scan details
                await firstRow.locator('button:has-text("View"), a:has-text("View")').click();
                await page.waitForLoadState('networkidle');
                
                // Should see account name in header
                if (accountName) {
                    await expect(page.locator(`h1:has-text("${accountName.trim()}")`)).toBeVisible();
                }
            }
        });

        test('should display status badge on details page', async ({ page }) => {
            await scanHelper.gotoScans();
            
            const hasRows = await page.locator('tbody tr').count() > 0;
            
            if (hasRows) {
                await page.locator('tbody tr').first().locator('button:has-text("View"), a:has-text("View")').click();
                await page.waitForLoadState('networkidle');
                
                // Should see status badge
                const statuses = ['Completed', 'Running', 'Pending', 'Failed', 'Cancelled'];
                let foundStatus = false;
                
                for (const status of statuses) {
                    const badge = page.locator(`span:has-text("${status}")`).first();
                    if (await badge.isVisible().catch(() => false)) {
                        foundStatus = true;
                        await expect(badge).toHaveClass(/rounded-full/);
                        break;
                    }
                }
                
                expect(foundStatus).toBe(true);
            }
        });

        test('should display severity summary for completed scans', async ({ page }) => {
            await scanHelper.gotoScans();
            
            // Filter to show only completed scans
            await page.selectOption('select:below(:text("Status"))', 'completed');
            await page.waitForLoadState('networkidle');
            
            const hasCompletedRows = await page.locator('tbody tr').count() > 0;
            
            if (hasCompletedRows) {
                await page.locator('tbody tr').first().locator('button:has-text("View"), a:has-text("View")').click();
                await page.waitForLoadState('networkidle');
                
                // Should see severity summary cards
                await expect(page.locator('text=Critical')).toBeVisible();
                await expect(page.locator('text=High')).toBeVisible();
                await expect(page.locator('text=Medium')).toBeVisible();
                await expect(page.locator('text=Low')).toBeVisible();
            }
        });

        test('should display findings section for completed scans', async ({ page }) => {
            await scanHelper.gotoScans();
            
            // Filter to show only completed scans
            await page.selectOption('select:below(:text("Status"))', 'completed');
            await page.waitForLoadState('networkidle');
            
            const hasCompletedRows = await page.locator('tbody tr').count() > 0;
            
            if (hasCompletedRows) {
                await page.locator('tbody tr').first().locator('button:has-text("View"), a:has-text("View")').click();
                await page.waitForLoadState('networkidle');
                
                // Should see findings section
                await expect(page.locator('text=Findings').first()).toBeVisible();
            }
        });

        test('should have severity filter on details page', async ({ page }) => {
            await scanHelper.gotoScans();
            
            // Filter to show only completed scans
            await page.selectOption('select:below(:text("Status"))', 'completed');
            await page.waitForLoadState('networkidle');
            
            const hasCompletedRows = await page.locator('tbody tr').count() > 0;
            
            if (hasCompletedRows) {
                await page.locator('tbody tr').first().locator('button:has-text("View"), a:has-text("View")').click();
                await page.waitForLoadState('networkidle');
                
                // Should see severity filter dropdown
                await expect(page.locator('select:has-text("All severities")')).toBeVisible();
            }
        });

        test('should show running state for in-progress scans', async ({ page }) => {
            await scanHelper.gotoScans();
            
            // Filter to show only running scans
            await page.selectOption('select:below(:text("Status"))', 'running');
            await page.waitForLoadState('networkidle');
            
            const hasRunningRows = await page.locator('tbody tr').count() > 0;
            
            if (hasRunningRows) {
                await page.locator('tbody tr').first().locator('button:has-text("View"), a:has-text("View")').click();
                await page.waitForLoadState('networkidle');
                
                // Should see running state message
                await expect(page.locator('text=Scan is in progress')).toBeVisible();
            }
        });
    });

    test.describe('Pagination', () => {
        test('should display pagination when multiple pages exist', async ({ page }) => {
            await scanHelper.gotoScans();
            
            // Check if pagination is visible
            const paginationText = page.locator('text=/Showing .* of .* results/');
            const hasPagination = await paginationText.isVisible().catch(() => false);
            
            if (hasPagination) {
                // Should see previous/next buttons
                await expect(page.locator('button:has(:text("Previous")), button[aria-label="Previous"]').first()).toBeVisible();
                await expect(page.locator('button:has(:text("Next")), button[aria-label="Next"]').first()).toBeVisible();
            }
        });

        test('should show results count', async ({ page }) => {
            await scanHelper.gotoScans();
            
            const hasTable = await page.locator('table').isVisible().catch(() => false);
            
            if (hasTable) {
                // Should see results count text
                const paginationText = page.locator('text=/Showing .* to .* of .* results/');
                const hasPagination = await paginationText.isVisible().catch(() => false);
                
                if (hasPagination) {
                    await expect(paginationText).toBeVisible();
                }
            }
        });
    });

    test.describe('Responsive Design', () => {
        test('should work on mobile viewport', async ({ page }) => {
            await page.setViewportSize({ width: 375, height: 667 });
            await scanHelper.gotoScans();
            
            // Should still see page title
            await expect(page.locator('h1:has-text("Scans")')).toBeVisible();
            
            // Should still see New Scan button
            await expect(page.locator('button:has-text("New Scan")')).toBeVisible();
        });

        test('should work on tablet viewport', async ({ page }) => {
            await page.setViewportSize({ width: 768, height: 1024 });
            await scanHelper.gotoScans();
            
            // Should see page title
            await expect(page.locator('h1:has-text("Scans")')).toBeVisible();
            
            // Should see filters
            await expect(page.locator('text=Account').first()).toBeVisible();
        });
    });

    test.describe('Error Handling', () => {
        test('should handle unauthorized access', async ({ page }) => {
            // Logout
            await page.goto('/logout');
            
            // Try to access scans page
            await page.goto('/scans');
            
            // Should be redirected to login
            await expect(page).toHaveURL(/.*\/login/);
        });
    });
});
