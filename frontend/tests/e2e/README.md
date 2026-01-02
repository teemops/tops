# E2E Testing with Playwright

## Quick Start

### Option 1: Let Playwright manage the server (Recommended for CI)
```bash
npm run test:e2e
```
Playwright will automatically start the dev server if it's not running.

### Option 2: Use existing dev server (Recommended for local development)
1. Start the dev server manually in a separate terminal:
   ```bash
   npm run dev
   ```

2. Run tests (Playwright will detect and reuse the existing server):
   ```bash
   npm run test:e2e
   ```

## Troubleshooting

### Error: "Timed out waiting 120000ms from config.webServer"

This happens when:
- The dev server is not starting properly
- The server is running but not responding on port 3000
- There's a port conflict

**Solution 1: Start server manually first (Recommended)**
```bash
# Terminal 1 - Start the dev server and wait for it to be ready
npm run dev
# Wait until you see "Local: http://localhost:3000"

# Terminal 2 - Run tests (Playwright will detect and reuse the server)
npm run test:e2e
```

**Solution 2: Skip server startup if already running**
```bash
# If server is already running, skip Playwright's server management
SKIP_SERVER=1 npm run test:e2e
# Or use the shortcut:
npm run test:e2e:no-server
```

**Solution 3: Check if server is responding**
```bash
# Check if server is accessible
curl http://localhost:3000

# If it doesn't respond, kill any stuck processes
lsof -ti:3000 | xargs kill -9

# Then start fresh
npm run dev
```

### Error: "WebSocket server error: Port 24678 is already in use"

This is a warning, not an error. It happens when:
- A dev server is already running
- Playwright tries to start another one

**Solution:** This is harmless if `reuseExistingServer` is enabled. The tests should still run. If you see this, it means Playwright detected the existing server and is reusing it.

## Test Commands

- `npm run test:e2e` - Run all E2E tests
- `npm run test:e2e:ui` - Run tests with Playwright UI
- `npm run test:e2e:headed` - Run tests in headed mode (see browser)

## Writing Tests

Tests are located in `tests/e2e/`. See `auth.e2e.spec.ts` for examples.

For more information, see [Playwright Documentation](https://playwright.dev/docs/intro).

