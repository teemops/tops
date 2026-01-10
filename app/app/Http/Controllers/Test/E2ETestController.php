<?php

namespace App\Http\Controllers\Test;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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
}
