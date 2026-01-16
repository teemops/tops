<?php

namespace App\Http\Controllers\Test;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * E2E Test Helper Controller
 * 
 * This controller provides test-only endpoints for Playwright E2E tests.
 * These endpoints are ONLY available in testing environments.
 */
class E2ETestController extends Controller
{
    /**
     * Verify email for the authenticated user
     * Only available in testing environment
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        // Only allow in testing environment
        if (!app()->environment(['testing', 'local'])) {
            abort(404);
        }

        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'error' => 'User not authenticated',
            ], 401);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified',
                'verified' => true,
            ]);
        }

        $user->markEmailAsVerified();

        return response()->json([
            'message' => 'Email verified successfully',
            'verified' => true,
        ]);
    }

    /**
     * Verify email by email address (for test users)
     * Only available in testing environment
     */
    public function verifyEmailByAddress(Request $request): JsonResponse
    {
        // Only allow in testing environment
        if (!app()->environment(['testing', 'local'])) {
            abort(404);
        }

        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'error' => 'User not found',
            ], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified',
                'verified' => true,
            ]);
        }

        $user->markEmailAsVerified();

        return response()->json([
            'message' => 'Email verified successfully',
            'verified' => true,
        ]);
    }

    /**
     * Clear login rate limiter for testing
     * Only available in testing environment
     */
    public function clearRateLimiter(Request $request): JsonResponse
    {
        // Only allow in testing environment
        if (!app()->environment(['testing', 'local'])) {
            abort(404);
        }

        $request->validate([
            'email' => 'required|email',
        ]);

        // Clear rate limiter using the same key format as LoginRequest
        $email = Str::transliterate(Str::lower($request->email));
        $ip = $request->ip();
        $throttleKey = $email . '|' . $ip;

        RateLimiter::clear($throttleKey);

        return response()->json([
            'message' => 'Rate limiter cleared successfully',
            'cleared' => true,
        ]);
    }

    /**
     * Reset all test data and rate limiters
     * Only available in testing environment
     */
    public function resetTestState(Request $request): JsonResponse
    {
        // Only allow in testing environment
        if (!app()->environment(['testing', 'local'])) {
            abort(404);
        }

        // Clear rate limiter for the test user if email provided
        if ($request->has('email')) {
            $email = Str::transliterate(Str::lower($request->email));
            $ip = $request->ip();
            $throttleKey = $email . '|' . $ip;
            RateLimiter::clear($throttleKey);
        }

        return response()->json([
            'message' => 'Test state reset successfully',
            'reset' => true,
        ]);
    }
}
