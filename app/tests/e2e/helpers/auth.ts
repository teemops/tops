import { Page } from '@playwright/test';

/**
 * Authentication helpers for Playwright tests
 */
export class AuthHelper {
    private debugMode: boolean;

    constructor(private page: Page, debugMode: boolean = false) {
        // Enable debug mode to see detailed logs and screenshots
        this.debugMode = debugMode;
        
        if (this.debugMode) {
            // Log network requests
            this.page.on('request', request => {
                if (request.url().includes('/login') || request.url().includes('/register')) {
                    console.log(`[Auth Debug] → ${request.method()} ${request.url()}`);
                }
            });
            
            // Log network responses
            this.page.on('response', response => {
                if (response.url().includes('/login') || response.url().includes('/register')) {
                    console.log(`[Auth Debug] ← ${response.status()} ${response.url()}`);
                }
            });
        }
    }
    
    /**
     * Debug helper: Log current page state
     */
    private async debugLog(message: string): Promise<void> {
        if (this.debugMode) {
            console.log(`[Auth Debug] ${message}`);
            console.log(`[Auth Debug] Current URL: ${this.page.url()}`);
            console.log(`[Auth Debug] Page title: ${await this.page.title()}`);
        }
    }
    
    /**
     * Debug helper: Take screenshot
     */
    private async debugScreenshot(name: string): Promise<void> {
        if (this.debugMode) {
            await this.page.screenshot({ 
                path: `debug-auth-${name}-${Date.now()}.png`,
                fullPage: true 
            });
        }
    }

    /**
     * Clear the rate limiter for an email address
     * This prevents "Too many failed login attempts" errors during testing
     */
    async clearRateLimiter(email: string): Promise<void> {
        try {
            const response = await this.page.request.post('http://localhost:8000/api/test/clear-rate-limiter', {
                data: { email },
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            });
            
            if (!response.ok()) {
                console.warn(`[Auth] Failed to clear rate limiter: ${response.status()}`);
            }
        } catch (error) {
            // Silently ignore - rate limiter clearing is best effort
            console.warn('[Auth] Could not clear rate limiter:', error);
        }
    }

    /**
     * Login with email and password
     */
    async login(email: string = 'test@auditaws.cloud', password: string = 'password'): Promise<void> {
        await this.debugLog('Starting login');
        
        // Clear rate limiter before attempting login to prevent throttling during tests
        await this.clearRateLimiter(email);
        
        await this.page.goto('/login');
        // Wait for page to load and inputs to be visible
        await this.page.waitForSelector('input#email', { state: 'visible' });
        await this.page.waitForSelector('input#password', { state: 'visible' });
        await this.debugScreenshot('login-page-loaded');
        
        // Use ID selectors since inputs don't have name attributes
        await this.page.fill('input#email', email);
        await this.page.fill('input#password', password);
        
        // Click submit button and wait for navigation
        // Don't wait for specific response - Inertia handles redirects client-side
        await this.page.locator('button:has-text("Sign in")').or(this.page.locator('button[type="submit"]')).click();
        
        // Wait for navigation to dashboard or for error to appear
        try {
            await Promise.race([
                this.page.waitForURL('**/dashboard', { timeout: 15000 }),
                this.page.waitForSelector('text=Dashboard', { state: 'visible', timeout: 15000 }),
            ]);
        } catch (error) {
            // If navigation failed, check if we're still on login page (might have errors)
            const currentUrl = this.page.url();
            if (currentUrl.includes('/login')) {
                // Wait a moment for error messages to render
                await this.page.waitForTimeout(500);
                const errorElement = this.page.locator('.text-red-600, .text-red-400, [role="alert"], .InputError').first();
                const errorMessage = await errorElement.textContent().catch(() => null);
                throw new Error(`Login failed - still on login page${errorMessage ? `: ${errorMessage.trim()}` : ''}`);
            }
            throw error;
        }
        
        // Ensure page is fully loaded
        await this.page.waitForLoadState('networkidle');
    }

    /**
     * Register a new user
     * Note: After registration, user is redirected to email verification page
     * For test users, you may need to verify email separately or use a test endpoint
     */
    async register(name: string, email: string, password: string): Promise<void> {
        await this.page.goto('/register');
        await this.page.waitForLoadState('networkidle');
        
        // Wait for form inputs to be visible
        await this.page.waitForSelector('input#name', { state: 'visible' });
        await this.page.waitForSelector('input#email', { state: 'visible' });
        
        // Fill registration form
        await this.page.fill('input#name', name);
        await this.page.fill('input#email', email);
        await this.page.fill('input#password', password);
        await this.page.fill('input#password_confirmation', password);
        await this.page.fill('checkbox#terms', 'on');
        // Submit registration form
        // Inertia.js does client-side navigation, so we wait for navigation rather than response
        // We'll also try to catch the response, but navigation is the primary indicator
        const navigationPromise = this.page.waitForURL('**/verify-email', { timeout: 15000 });
        const responsePromise = this.page.waitForResponse(
            response => {
                const url = response.url();
                const method = response.request().method();
                // Match Inertia POST requests to /register
                const isRegisterEndpoint = url.includes('/register') && method === 'POST';
                return isRegisterEndpoint;
            },
            { timeout: 15000 }
        ).catch(() => null); // Don't fail if response isn't caught
        
        // Click submit button
        await this.page.click('button#register-submit');
        
        // Wait for navigation (primary success indicator)
        try {
            await navigationPromise;
        } catch (error) {
            // If navigation failed, check for errors
            await this.page.waitForTimeout(1000);
            const errorElement = this.page.locator('.text-red-600, .text-red-400, [role="alert"], .InputError').first();
            const errorMessage = await errorElement.textContent().catch(() => null);
            if (errorMessage) {
                throw new Error(`Registration failed: ${errorMessage.trim()}`);
            }
            const currentUrl = this.page.url();
            throw new Error(`Registration failed - expected redirect to /verify-email but got ${currentUrl}`);
        }
        
        // Optionally check response if we caught it
        const response = await responsePromise;
        if (response && response.status() !== 200 && response.status() !== 302) {
            const errorElement = this.page.locator('.text-red-600, .text-red-400, [role="alert"], .InputError').first();
            const errorMessage = await errorElement.textContent().catch(() => null);
            if (errorMessage) {
                throw new Error(`Registration failed: ${errorMessage.trim()}`);
            }
        }
        
        await this.page.waitForLoadState('networkidle');
    }
    
    /**
     * Verify email for the currently logged-in user
     * Uses the test API endpoint /api/test/verify-email
     * Note: This requires the user to be logged in (after registration)
     */
    async verifyEmail(): Promise<void> {
        // Use the test API endpoint to verify email
        const response = await this.page.request.post('http://localhost:8000/api/test/verify-email', {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
        });

        if (!response.ok()) {
            const error = await response.json().catch(() => ({ error: 'Unknown error' }));
            throw new Error(`Email verification failed: ${error.error || response.statusText()}`);
        }

        const result = await response.json();
        
        if (!result.verified) {
            throw new Error('Email verification failed: User email was not verified');
        }

        // After verification, navigate to dashboard
        await this.page.goto('/dashboard');
        await this.page.waitForURL('**/dashboard', { timeout: 10000 });
    }
    
    /**
     * Verify email by email address (for test users)
     * Uses the test API endpoint /api/test/verify-email-by-address
     * This doesn't require authentication - useful for E2E tests
     */
    async verifyEmailByAddress(email: string): Promise<void> {
        const response = await this.page.request.post('http://localhost:8000/api/test/verify-email-by-address', {
            data: { email },
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
        });

        if (!response.ok()) {
            const error = await response.json().catch(() => ({ error: 'Unknown error' }));
            throw new Error(`Email verification failed: ${error.error || response.statusText()}`);
        }

        const result = await response.json();
        
        if (!result.verified) {
            throw new Error('Email verification failed: User email was not verified');
        }
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

