#!/bin/bash
# Start both Laravel and Vite dev servers for Playwright tests
# This script starts Vite in the background and runs Laravel in foreground
# Playwright will health-check Laravel, which depends on Vite being ready

# Trap to cleanup background processes on exit
cleanup() {
    if [ ! -z "$VITE_PID" ]; then
        kill $VITE_PID 2>/dev/null
    fi
}
trap cleanup EXIT

# Start Vite in background (if not already running)
if ! lsof -Pi :5173 -sTCP:LISTEN -t >/dev/null 2>&1 ; then
    npm run dev > /dev/null 2>&1 &
    VITE_PID=$!
    # Give Vite a moment to start
    sleep 3
fi

# Start Laravel in foreground (this is what Playwright will health-check)
# When this exits, the trap will cleanup Vite
exec php artisan serve
