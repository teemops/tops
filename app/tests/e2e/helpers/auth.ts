import { Page } from '@playwright/test';

/**
 * Authentication helpers for Playwright tests
 */
export class AuthHelper {
    constructor(private page: Page) {}

    /**
     * Login with email and password
     */
    async login(email: string, password: string): Promise<void> {
        await this.page.goto('/login');
        await this.page.fill('input[name="email"]', email);
        await this.page.fill('input[name="password"]', password);
        await this.page.click('button[type="submit"]');
        await this.page.waitForURL('**/dashboard', { timeout: 5000 });
    }

    /**
     * Register a new user
     */
    async register(name: string, email: string, password: string): Promise<void> {
        await this.page.goto('/register');
        await this.page.fill('input[name="name"]', name);
        await this.page.fill('input[name="email"]', email);
        await this.page.fill('input[name="password"]', password);
        await this.page.fill('input[name="password_confirmation"]', password);
        await this.page.click('button[type="submit"]');
        // After registration, user is redirected to email verification page
        await this.page.waitForURL('**/verify-email', { timeout: 5000 });
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

