import { Page } from '@playwright/test';

/**
 * Authentication helpers for Playwright tests
 */
export class AuthHelper {
    constructor(private page: Page) {}

    /**
     * Login with email and password
     */
    async login(email: string = 'test@auditaws.cloud', password: string = 'password'): Promise<void> {
        await this.page.goto('/login');
        // Wait for page to load and inputs to be visible
        await this.page.waitForSelector('input#email', { state: 'visible' });
        await this.page.waitForSelector('input#password', { state: 'visible' });
        
        // Use ID selectors since inputs don't have name attributes
        await this.page.fill('input#email', email);
        await this.page.fill('input#password', password);
        
        // Click submit button - try multiple selectors
        const submitButton = this.page.locator('button:has-text("Sign in")').or(this.page.locator('button[type="submit"]'));
        await submitButton.click();
        
        // Wait for redirect to dashboard
        await this.page.waitForURL('**/dashboard', { timeout: 15000 });
    }

    /**
     * Register a new user
     */
    async register(name: string, email: string, password: string): Promise<void> {
        await this.page.goto('/register');
        await this.page.waitForLoadState('networkidle');
        // Use ID selectors
        await this.page.fill('input#name', name);
        await this.page.fill('input#email', email);
        await this.page.fill('input#password', password);
        await this.page.fill('input#password_confirmation', password);
        await this.page.click('button[type="submit"]');
        // After registration, user is redirected to email verification page
        await this.page.waitForURL('**/verify-email', { timeout: 10000 });
    }

    /**
     * Logout current user
     */
    async logout(): Promise<void> {
        // Click user menu dropdown
        await this.page.click('button:has-text("JD")');
        // Click logout button
        await this.page.click('button:has-text("Log Out")');
        await this.page.waitForURL('**/login', { timeout: 5000 });
    }

    /**
     * Check if user is logged in
     */
    async isLoggedIn(): Promise<boolean> {
        try {
            await this.page.waitForSelector('text=Dashboard', { timeout: 2000 });
            return true;
        } catch {
            return false;
        }
    }
}

