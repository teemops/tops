# Debugging Playwright Tests

This guide explains how to debug Playwright E2E tests in this project.

## Quick Start

### Method 1: VS Code Debugger (Recommended)

1. **Open the test file** you want to debug (e.g., `tests/e2e/auth.spec.ts`)
2. **Set breakpoints** by clicking in the gutter next to the line numbers
3. **Press F5** or go to Run → Start Debugging
4. **Select a debug configuration**:
   - `Debug Current Playwright Test File` - Debugs the currently open test file
   - `Debug Playwright Tests` - Debugs all tests
   - `Debug Playwright Tests (UI Mode)` - Opens Playwright UI for interactive debugging
   - `Debug Playwright Tests (Headed)` - Runs tests with visible browser

### Method 2: Playwright Debug Mode

Run tests in debug mode from the terminal:

```bash
# Debug all tests
npm run test:e2e:debug

# Debug a specific file
npx playwright test tests/e2e/auth.spec.ts --debug

# Debug a specific test by name
npx playwright test --grep "should login successfully" --debug
```

This opens Playwright Inspector where you can:
- Step through test execution
- See the browser in action
- Inspect page state
- Use the console

### Method 3: Playwright UI Mode (Interactive)

```bash
npm run test:e2e:ui
```

This opens Playwright's UI where you can:
- See all tests
- Run individual tests
- Watch tests execute
- See time-travel debugging
- Inspect network requests

### Method 4: Headed Mode (See Browser)

```bash
npm run test:e2e:headed
```

Runs tests with a visible browser window so you can watch what's happening.

## Debugging Techniques

### 1. Set Breakpoints in VS Code

1. Open your test file (e.g., `tests/e2e/auth.spec.ts`)
2. Click in the gutter (left of line numbers) to set a breakpoint
3. Start debugging (F5)
4. Execution will pause at breakpoints
5. Use the Debug panel to:
   - Inspect variables
   - Step over/into/out
   - Evaluate expressions
   - View call stack

### 2. Use `page.pause()`

Add `await page.pause();` anywhere in your test to pause execution:

```typescript
test('should login successfully', async ({ page }) => {
    const authHelper = new AuthHelper(page);
    await page.pause(); // Execution pauses here
    await authHelper.login();
});
```

### 3. Use `console.log()` and `page.screenshot()`

```typescript
test('should login successfully', async ({ page }) => {
    console.log('Current URL:', page.url());
    await page.screenshot({ path: 'debug-screenshot.png' });
    // Your test code
});
```

### 4. Use Playwright Inspector

When running in debug mode, Playwright Inspector opens automatically. You can:
- **Step**: Execute next action
- **Resume**: Continue execution
- **Pause**: Pause execution
- **Inspect**: Inspect page elements
- **Console**: Run JavaScript in page context

### 5. View Traces

After a test fails, view the trace:

```bash
npm run test:e2e:report
```

Or enable traces always:

```bash
npm run test:e2e:trace
```

Then open the trace viewer to see:
- All actions taken
- Network requests
- Console logs
- Screenshots at each step
- Time-travel debugging

## Debugging Helpers

### Debug Helper Methods

Add these to your test helpers for easier debugging:

```typescript
// In helpers/auth.ts or similar
async debugLog(message: string, page: Page): Promise<void> {
    console.log(`[DEBUG] ${message}`);
    console.log(`[DEBUG] Current URL: ${page.url()}`);
    console.log(`[DEBUG] Page title: ${await page.title()}`);
}

async debugScreenshot(page: Page, name: string): Promise<void> {
    await page.screenshot({ 
        path: `debug-${name}-${Date.now()}.png`,
        fullPage: true 
    });
}
```

### Debug Network Requests

```typescript
// Log all network requests
page.on('request', request => {
    console.log(`→ ${request.method()} ${request.url()}`);
});

// Log all network responses
page.on('response', response => {
    console.log(`← ${response.status()} ${response.url()}`);
});
```

### Debug Console Messages

```typescript
// Listen to browser console
page.on('console', msg => {
    console.log(`[Browser Console] ${msg.type()}: ${msg.text()}`);
});
```

## Common Debugging Scenarios

### Debugging Authentication Issues

```typescript
test('should login successfully', async ({ page }) => {
    // Enable request/response logging
    page.on('request', req => {
        if (req.url().includes('/login')) {
            console.log('Login request:', req.postData());
        }
    });
    
    page.on('response', res => {
        if (res.url().includes('/login')) {
            console.log('Login response:', res.status(), await res.text());
        }
    });
    
    const authHelper = new AuthHelper(page);
    await authHelper.login();
});
```

### Debugging Selector Issues

```typescript
// Check if element exists
const element = page.locator('button:has-text("Sign in")');
console.log('Element count:', await element.count());
console.log('Element visible:', await element.isVisible());

// Take screenshot to see page state
await page.screenshot({ path: 'debug-selector.png' });
```

### Debugging Timing Issues

```typescript
// Add delays to see what's happening
await page.waitForTimeout(2000); // Wait 2 seconds

// Or wait for specific conditions
await page.waitForSelector('text=Dashboard', { timeout: 10000 });
```

## VS Code Debug Configurations

The project includes these debug configurations (in `.vscode/launch.json`):

1. **Debug Playwright Tests** - Debugs all tests
2. **Debug Current Playwright Test File** - Debugs the file you have open
3. **Debug Playwright Tests (UI Mode)** - Opens Playwright UI
4. **Debug Playwright Tests (Headed)** - Runs with visible browser
5. **Debug Specific Test (by name)** - Prompts for test name to debug

## Tips

1. **Use `test.only()`** to run only one test:
   ```typescript
   test.only('should login successfully', async ({ page }) => {
       // Only this test runs
   });
   ```

2. **Use `test.skip()`** to skip tests temporarily:
   ```typescript
   test.skip('should do something', async ({ page }) => {
       // This test is skipped
   });
   ```

3. **Increase timeouts** for debugging:
   ```typescript
   test.setTimeout(60000); // 60 seconds
   ```

4. **Use `page.pause()`** for quick debugging without breakpoints

5. **Check the Playwright report** after failures for screenshots and traces

## Troubleshooting

### Breakpoints not hitting

- Make sure you're using the correct debug configuration
- Check that the test file path is correct
- Try using `page.pause()` instead

### Can't see browser

- Use "Debug Playwright Tests (Headed)" configuration
- Or add `--headed` flag: `npx playwright test --headed`

### Tests run too fast

- Add `await page.waitForTimeout(1000)` to slow down execution
- Use `--headed` mode to see what's happening
- Enable traces to see step-by-step execution

## Resources

- [Playwright Debugging Docs](https://playwright.dev/docs/debug)
- [Playwright Inspector](https://playwright.dev/docs/debug#playwright-inspector)
- [VS Code Playwright Extension](https://marketplace.visualstudio.com/items?itemName=ms-playwright.playwright)
