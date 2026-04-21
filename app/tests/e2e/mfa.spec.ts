import { test, expect } from '@playwright/test';

/**
 * MFA (Multi-Factor Authentication) login flow E2E tests.
 * Uses /login?e2e_mfa=1 to show the verification step without going through Firebase.
 */
test.describe('MFA Login', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/login?e2e_mfa=1');
        await page.waitForLoadState('networkidle');
    });

    test('shows verification step when e2e_mfa=1', async ({ page }) => {
        await expect(page.getByRole('heading', { name: 'Verify your identity' })).toBeVisible();
        await expect(page.getByText('Enter the 6-digit code from your authenticator app')).toBeVisible();
        await expect(page.locator('input#otp')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Verify & sign in' })).toBeVisible();
    });

    test('Verify & sign in is disabled until 6 digits are entered', async ({ page }) => {
        const verifyBtn = page.getByRole('button', { name: 'Verify & sign in' });
        await expect(verifyBtn).toBeDisabled();

        await page.locator('input#otp').fill('12345');
        await expect(verifyBtn).toBeDisabled();

        await page.locator('input#otp').fill('123456');
        await expect(verifyBtn).toBeEnabled();
    });

    test('Use different account returns to password form', async ({ page }) => {
        await expect(page.getByRole('heading', { name: 'Verify your identity' })).toBeVisible();

        await page.getByRole('button', { name: /Use different account/ }).click();
        await page.waitForTimeout(300);

        await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
        await expect(page.locator('input#email')).toBeVisible();
        await expect(page.locator('input#password')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Sign in' })).toBeVisible();
    });

    test('Email me a code instead shows email OTP section', async ({ page }) => {
        await page.getByRole('button', { name: 'Email me a code instead' }).click();
        await page.waitForTimeout(500);

        // Either the "Send code to my email" button or the code input (if request succeeded)
        const sendCodeBtn = page.getByRole('button', { name: 'Send code to my email' });
        const codeInput = page.locator('input#email-otp');
        await expect(sendCodeBtn.or(codeInput)).toBeVisible();
    });

    test('Use authenticator app instead returns to authenticator form', async ({ page }) => {
        await page.getByRole('button', { name: 'Email me a code instead' }).click();
        await page.waitForTimeout(500);

        await page.getByRole('button', { name: 'Use authenticator app instead' }).click();
        await page.waitForTimeout(300);

        await expect(page.getByText('Enter the 6-digit code from your authenticator app')).toBeVisible();
        await expect(page.locator('input#otp')).toBeVisible();
    });

});

test.describe('MFA Login - without e2e hook', () => {
    test('login page without e2e_mfa shows password form', async ({ page }) => {
        await page.goto('/login');
        await page.waitForLoadState('networkidle');

        await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
        await expect(page.locator('input#email')).toBeVisible();
        await expect(page.locator('input#password')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Sign in' })).toBeVisible();
    });
});
