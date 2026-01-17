# Xdebug Setup Guide for Laravel Unit Tests

This guide will help you set up Xdebug for debugging Laravel code including unit tests.

## Step 1: Install Xdebug

Since you're on WSL2 with PHP 8.3, install Xdebug using apt:

```bash
sudo apt-get update
sudo apt-get install php8.3-xdebug
```

Alternatively, if the package isn't available, you can install via PECL:

```bash
sudo apt-get install php-pear php8.3-dev
sudo pecl install xdebug
```

## Step 2: Configure Xdebug

Create or edit the Xdebug configuration file:

```bash
sudo nano /etc/php/8.3/cli/conf.d/20-xdebug.ini
```

Add the following configuration:

```ini
zend_extension=xdebug.so
xdebug.mode=debug
xdebug.start_with_request=yes
xdebug.client_port=9003
xdebug.client_host=127.0.0.1
xdebug.log=/tmp/xdebug.log
xdebug.log_level=0
```

**Important settings:**
- `xdebug.mode=debug` - Enables debugging mode
- `xdebug.start_with_request=yes` - Automatically starts debugging on every request
- `xdebug.client_port=9003` - Port for VS Code to listen on
- `xdebug.client_host=127.0.0.1` - Host where VS Code is running

## Step 3: Verify Xdebug Installation

Check if Xdebug is loaded:

```bash
php -m | grep -i xdebug
```

You should see `xdebug` in the output.

Verify Xdebug version and settings:

```bash
php -v
php -i | grep -i xdebug
```

Check the port configuration:

```bash
php -i | grep xdebug.client_port
```

You should see `xdebug.client_port => 9003 => 9003`

## Step 4: Test Xdebug Connection

Create a simple test script to verify Xdebug is working:

```bash
php -r "xdebug_info();" | grep -i "client"
```

Or run this test:

```bash
php -r "var_dump(function_exists('xdebug_break'));"
```

Should output: `bool(true)`

## Step 5: Using Xdebug with VS Code

### Method 1: Listen for Xdebug (Recommended for Unit Tests)

1. **Set breakpoints** in your code (click in the gutter next to line numbers)
2. **Start the debugger**:
   - Press `F5` or go to Run → Start Debugging
   - Select **"Listen for Xdebug"** configuration
   - The debugger will wait for Xdebug to connect
3. **Run your test** in a terminal:
   ```bash
   cd /home/benfellows/my/saas/app
   php artisan test --filter FindingsEngineTest
   ```
4. Execution will pause at your breakpoints

### Method 2: Launch PHPUnit Directly

1. **Open the test file** you want to debug (e.g., `tests/Unit/FindingsEngineTest.php`)
2. **Set breakpoints** in your test or the code being tested
3. **Start debugging**:
   - Press `F5` or go to Run → Start Debugging
   - Select **"Debug PHPUnit (Current Test)"** or **"Debug PHPUnit Test"**
4. Execution will start and pause at breakpoints

### Method 3: Debug Laravel Artisan Commands

For debugging Laravel commands or other PHP scripts:

1. Set breakpoints
2. Use **"Listen for Xdebug"** configuration
3. Run your command:
   ```bash
   php artisan your:command
   ```

## Troubleshooting

### Xdebug not connecting

1. **Check if Xdebug is loaded:**
   ```bash
   php -m | grep xdebug
   ```

2. **Check Xdebug configuration:**
   ```bash
   php -i | grep xdebug
   ```

3. **Verify port 9003 is correct:**
   ```bash
   php -i | grep xdebug.client_port
   ```

4. **Check Xdebug log:**
   ```bash
   tail -f /tmp/xdebug.log
   ```

5. **Test connection manually:**
   ```bash
   php -r "xdebug_info();" | grep -A 5 "client"
   ```

### VS Code not breaking

1. Make sure **"Listen for Xdebug"** is running (check the debug toolbar)
2. Verify breakpoints are set (red dots in the gutter)
3. Check that `xdebug.start_with_request=yes` is set
4. Try using `xdebug_break();` in your code as a manual breakpoint

### Port conflicts

If port 9003 is in use, you can change it:

1. Update `/etc/php/8.3/cli/conf.d/20-xdebug.ini`:
   ```ini
   xdebug.client_port=9004
   ```

2. Update `.vscode/launch.json`:
   ```json
   "port": 9004
   ```

3. Restart VS Code

## Quick Test

To quickly test if Xdebug is working:

1. Open `tests/Unit/FindingsEngineTest.php`
2. Set a breakpoint on line 327 (the echo statement you added)
3. Start **"Listen for Xdebug"** in VS Code
4. Run:
   ```bash
   php artisan test --filter FindingsEngineTest::test_evaluate_scan_handles_access_key_age_calculation
   ```
5. Execution should pause at your breakpoint

## Alternative: Using `xdebug_break()`

If automatic breakpoints don't work, you can add `xdebug_break();` directly in your code:

```php
public function test_evaluate_scan_creates_findings_when_conditions_met(): void
{
    xdebug_break(); // Execution will pause here
    // ... rest of test
}
```

Then use **"Listen for Xdebug"** and run your test normally.
