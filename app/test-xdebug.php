<?php
/**
 * Simple script to verify Xdebug is working
 * 
 * Usage:
 * 1. Start "Listen for Xdebug" in VS Code
 * 2. Set a breakpoint on line 15 (the echo statement)
 * 3. Run: php test-xdebug.php
 * 4. Execution should pause at the breakpoint
 */

echo "Xdebug Test Script\n";
echo "==================\n\n";

// Check if Xdebug is loaded
if (extension_loaded('xdebug')) {
    echo "✓ Xdebug is loaded\n";
    
    // Get Xdebug version
    $version = phpversion('xdebug');
    echo "✓ Xdebug version: {$version}\n";
    
    // Check Xdebug mode
    $mode = ini_get('xdebug.mode');
    echo "✓ Xdebug mode: " . ($mode ?: 'not set') . "\n";
    
    // Check client port
    $port = ini_get('xdebug.client_port');
    echo "✓ Xdebug client port: " . ($port ?: 'not set') . "\n";
    
    // Check client host
    $host = ini_get('xdebug.client_host');
    echo "✓ Xdebug client host: " . ($host ?: 'not set') . "\n";
    
    echo "\n";
    echo "Setting a breakpoint here - if Xdebug is working, execution will pause:\n";
    echo "Line 15: xdebug_break();\n\n";
    
    // Manual breakpoint - execution will pause here if Xdebug is connected
    if (function_exists('xdebug_break')) {
        xdebug_break();
        echo "✓ Breakpoint hit! Xdebug is working correctly.\n";
    } else {
        echo "✗ xdebug_break() function not available\n";
    }
    
} else {
    echo "✗ Xdebug is NOT loaded\n";
    echo "\n";
    echo "To install Xdebug:\n";
    echo "  sudo apt-get install php8.3-xdebug\n";
    echo "\n";
    echo "Or if that doesn't work:\n";
    echo "  sudo apt-get install php-pear php8.3-dev\n";
    echo "  sudo pecl install xdebug\n";
}

echo "\n";
echo "Test completed.\n";
