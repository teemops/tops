<?php

use App\Http\Controllers\Api\OrganizationsController;
use App\Http\Controllers\Api\AwsAccountsController;
use App\Http\Controllers\Api\ScansController;
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

Route::middleware(['api', 'firebase.auth', 'organization.context'])->group(function () {
    // Organizations
    Route::get('/organizations', [OrganizationsController::class, 'index']);
    Route::post('/organizations', [OrganizationsController::class, 'store']);
    Route::get('/organizations/{orgId}', [OrganizationsController::class, 'show']);
    Route::put('/organizations/{orgId}', [OrganizationsController::class, 'update']);
    Route::delete('/organizations/{orgId}', [OrganizationsController::class, 'destroy']);
    Route::get('/organizations/current', [OrganizationsController::class, 'current']);

    // AWS Accounts
    Route::get('/organizations/{orgId}/aws-accounts', [AwsAccountsController::class, 'index']);
    Route::post('/organizations/{orgId}/aws-accounts/init', [AwsAccountsController::class, 'init']);
    Route::post('/organizations/{orgId}/aws-accounts', [AwsAccountsController::class, 'store']);
    Route::get('/aws-accounts/{accountId}', [AwsAccountsController::class, 'show']);
    Route::put('/aws-accounts/{accountId}', [AwsAccountsController::class, 'update']);
    Route::delete('/aws-accounts/{accountId}', [AwsAccountsController::class, 'destroy']);

    // Scans
    Route::get('/organizations/{orgId}/scans', [ScansController::class, 'index']);
    Route::post('/organizations/{orgId}/scans', [ScansController::class, 'store']);
    Route::get('/scans/{scanId}', [ScansController::class, 'show']);
    Route::get('/scans/{scanId}/results', [ScansController::class, 'results']);
    Route::post('/scans/{scanId}/cancel', [ScansController::class, 'cancel']);
});

// SNS callback (no auth required, uses signature verification)
Route::post('/aws-accounts/sns-callback', [AwsAccountsController::class, 'snsCallback']);

