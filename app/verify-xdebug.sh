#!/bin/bash
# Quick Xdebug verification script

echo "=== Xdebug Verification ==="
echo ""

# Check if Xdebug extension is loaded
echo "1. Checking if Xdebug is loaded..."
if php -m | grep -qi xdebug; then
    echo "   ✓ Xdebug is loaded"
else
    echo "   ✗ Xdebug is NOT loaded"
    echo ""
    echo "   Install with: sudo apt-get install php8.3-xdebug"
    exit 1
fi

# Check Xdebug version
echo ""
echo "2. Xdebug version:"
php -r "echo '   ' . phpversion('xdebug') . PHP_EOL;"

# Check configuration
echo ""
echo "3. Xdebug configuration:"
echo "   Mode: $(php -r "echo ini_get('xdebug.mode') ?: 'not set';")"
echo "   Client Port: $(php -r "echo ini_get('xdebug.client_port') ?: 'not set';")"
echo "   Client Host: $(php -r "echo ini_get('xdebug.client_host') ?: 'not set';")"
echo "   Start with Request: $(php -r "echo ini_get('xdebug.start_with_request') ?: 'not set';")"

# Check if port 9003 is configured
echo ""
echo "4. Port configuration:"
PORT=$(php -r "echo ini_get('xdebug.client_port') ?: 'not set';")
if [ "$PORT" = "9003" ]; then
    echo "   ✓ Port is set to 9003 (correct)"
else
    echo "   ⚠ Port is set to: $PORT (expected: 9003)"
fi

echo ""
echo "=== Verification Complete ==="
echo ""
echo "To test Xdebug connection:"
echo "  1. Start 'Listen for Xdebug' in VS Code"
echo "  2. Run: php test-xdebug.php"
echo "  3. Execution should pause at breakpoint"
