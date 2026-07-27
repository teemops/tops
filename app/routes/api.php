<?php

use App\Http\Controllers\Api\OrganizationsController;
use App\Http\Controllers\Api\OrganizationMembersController;
use App\Http\Controllers\Api\AwsAccountsController;
use App\Http\Controllers\Api\ScansController;
use App\Http\Controllers\Api\FindingsController;
use App\Http\Controllers\Test\E2ETestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// API routes support both Firebase token auth (for external clients) and session auth (for web app)
Route::middleware(['api', 'firebase.auth', 'organization.context'])->group(function () {
    // Organizations
    Route::get('/organizations', [OrganizationsController::class, 'index']);
    Route::post('/organizations', [OrganizationsController::class, 'store']);
    Route::get('/organizations/{orgId}', [OrganizationsController::class, 'show']);
    Route::put('/organizations/{orgId}', [OrganizationsController::class, 'update']);
    Route::delete('/organizations/{orgId}', [OrganizationsController::class, 'destroy']);
    Route::get('/organizations/current', [OrganizationsController::class, 'current']);

    // Organization Members & Team Management
    Route::get('/organizations/{orgId}/members', [OrganizationMembersController::class, 'index']);
    Route::post('/organizations/{orgId}/members/invite', [OrganizationMembersController::class, 'invite']);
    Route::put('/organizations/{orgId}/members/{memberId}', [OrganizationMembersController::class, 'update']);
    Route::delete('/organizations/{orgId}/members/{memberId}', [OrganizationMembersController::class, 'destroy']);
    Route::get('/organizations/{orgId}/invitations', [OrganizationMembersController::class, 'listInvitations']);
    Route::delete('/organizations/{orgId}/invitations/{invitationId}', [OrganizationMembersController::class, 'cancelInvitation']);
    Route::post('/organizations/{orgId}/transfer-ownership', [OrganizationMembersController::class, 'transferOwnership']);

    // AWS Accounts
    Route::get('/organizations/{orgId}/aws-accounts', [AwsAccountsController::class, 'index']);
    Route::post('/organizations/{orgId}/aws-accounts/init', [AwsAccountsController::class, 'init']);
    Route::post('/organizations/{orgId}/aws-accounts', [AwsAccountsController::class, 'store']);
    Route::get('/aws-accounts/{accountId}', [AwsAccountsController::class, 'show']);
    Route::put('/aws-accounts/{accountId}', [AwsAccountsController::class, 'update']);
    Route::delete('/aws-accounts/{accountId}', [AwsAccountsController::class, 'destroy']);
    Route::get('/aws-accounts/{accountId}/cloudformation-url', [AwsAccountsController::class, 'getCloudFormationUrl']);

    // Scans
    Route::get('/scan-types', [ScansController::class, 'scanTypes']);
    Route::get('/scan-profiles', [ScansController::class, 'scanProfiles']);
    Route::get('/organizations/{orgId}/scans', [ScansController::class, 'index']);
    Route::post('/organizations/{orgId}/scans', [ScansController::class, 'store']);
    Route::get('/scans/{scanId}', [ScansController::class, 'show']);
    Route::get('/scans/{scanId}/results', [ScansController::class, 'results']);
    Route::post('/scans/{scanId}/cancel', [ScansController::class, 'cancel']);

    // Findings (org-wide)
    Route::get('/organizations/{orgId}/findings', [FindingsController::class, 'index']);
    Route::get('/organizations/{orgId}/findings/by-type/{findingType}', [FindingsController::class, 'byType']);
    Route::get('/recommendations', [FindingsController::class, 'recommendations']);
    Route::get('/results/{findingId}', [FindingsController::class, 'show']);
    Route::put('/results/{findingId}', [FindingsController::class, 'update']);
});

// Accept invitation (requires auth but not organization context)
Route::middleware(['api', 'firebase.auth'])->group(function () {
    Route::post('/organizations/invitations/{token}/accept', [OrganizationMembersController::class, 'acceptInvitation']);
});

// SNS callback (no auth required, uses signature verification)
Route::post('/aws-accounts/sns-callback', [AwsAccountsController::class, 'snsCallback']);

// E2E Test Helper Routes (ONLY available in testing/local environments)
// These routes are automatically disabled in production
if (app()->environment(['testing', 'local'])) {
    Route::prefix('test')->group(function () {
        // Verify email for authenticated user (requires session auth)
        Route::post('/verify-email', [E2ETestController::class, 'verifyEmail'])
            ->middleware(['web', 'auth']); // Use web middleware for session auth
        
        // Verify email by email address (for test users, no auth required in testing)
        Route::post('/verify-email-by-address', [E2ETestController::class, 'verifyEmailByAddress']);

        // Clear login rate limiter (for E2E tests that run multiple logins)
        Route::post('/clear-rate-limiter', [E2ETestController::class, 'clearRateLimiter']);

        // Reset test state (clears rate limiters and other test state)
        Route::post('/reset-test-state', [E2ETestController::class, 'resetTestState']);
    });
}
