<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\MfaApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Auth as FirebaseAuth;
use Kreait\Firebase\Factory;

class FirebaseAuthController extends Controller
{
    protected FirebaseAuth $firebaseAuth;

    protected MfaApiService $mfaApi;

    public function __construct(MfaApiService $mfaApi)
    {
        $factory = (new Factory)
            ->withServiceAccount([
                'type' => 'service_account',
                'project_id' => config('services.firebase.project_id'),
                'private_key_id' => config('services.firebase.private_key_id'),
                'private_key' => config('services.firebase.private_key'),
                'client_email' => config('services.firebase.client_email'),
                'client_id' => config('services.firebase.client_id'),
                'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
                'token_uri' => 'https://oauth2.googleapis.com/token',
                'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
                'client_x509_cert_url' => config('services.firebase.client_x509_cert_url'),
            ]);

        $this->firebaseAuth = $factory->createAuth();
        $this->mfaApi = $mfaApi;
    }

    /**
     * Verify Firebase token. Does not create session until verification step is complete.
     * Returns JSON: mfa_required (show TOTP + email fallback) or email_otp_required (show email OTP only).
     */
    public function verify(Request $request): RedirectResponse|JsonResponse
    {

        $token = $request->validate([
            'token' => ['required', 'string'],
        ])['token'];


        try {
            $verifiedToken = $this->firebaseAuth->verifyIdToken($token);
            $uid = $verifiedToken->claims()->get('sub');
            $email = $verifiedToken->claims()->get('email');
            $name = $verifiedToken->claims()->get('name');
            
            // Check if email is verified in Firebase
            // OAuth providers (Google, GitHub, Microsoft) always verify emails, so default to true
            $emailVerified = $verifiedToken->claims()->get('email_verified', true);

            // Find or create user
            $user = User::firstOrCreate(
                ['firebase_uid' => $uid],
                [
                    'email' => $email,
                    'name' => $name ?? 'User',
                    'email_verified_at' => $emailVerified ? now() : null,
                ]
            );

            // Update user info if changed or if email verification status changed
            $updates = [];
            
            if ($user->name !== ($name ?? 'User')) {
                $updates['name'] = $name ?? $user->name;
            }
            
            // If Firebase says email is verified but user isn't verified in our system, verify them
            if ($emailVerified && !$user->hasVerifiedEmail()) {
                $updates['email_verified_at'] = now();
            }
            
            // If email changed, update it
            if ($user->email !== $email) {
                $updates['email'] = $email;
                // If email changed and is verified, set verified_at
                if ($emailVerified) {
                    $updates['email_verified_at'] = now();
                } else {
                    // Email changed but not verified, clear verification
                    $updates['email_verified_at'] = null;
                }
            }
            
            if (!empty($updates)) {
                $user->update($updates);
            }

            // Require verification before session: MFA users → TOTP (or email fallback); others → email OTP.
            if ($this->mfaApi->isConfigured() && $this->mfaApi->isMfaEnabled($token)) {
                if ($request->header('X-Inertia')) {
                    return back()->with(['mfa_required' => true]);
                }
                return response()->json(['mfa_required' => true]);
            }
            if ($request->header('X-Inertia')) {
                return back()->with(['email_otp_required' => true]);
            }
            return response()->json(['email_otp_required' => true]);
        } catch (\Exception $e) {
            Log::error('Firebase token verification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'token_preview' => substr($token, 0, 20) . '...',
            ]);

            // For Inertia requests, return back with errors
            if ($request->header('X-Inertia')) {
                return back()->withErrors([
                    'firebase' => 'Authentication failed: ' . $e->getMessage(),
                ]);
            }

            return redirect()->route('login')
                ->withErrors(['firebase' => 'Authentication failed. Please try again.']);
        }
    }

    /**
     * Verify MFA (TOTP or email OTP) and create Laravel session
     */
    public function verifyMfa(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'otp' => ['required', 'string', 'size:6'],
            'use_email_otp' => ['sometimes', 'boolean'],
        ]);

        $token = $request->input('token');
        $otp = $request->input('otp');
        $useEmailOtp = $request->boolean('use_email_otp');

        try {
            $verifiedToken = $this->firebaseAuth->verifyIdToken($token);
            $uid = $verifiedToken->claims()->get('sub');
            $email = $verifiedToken->claims()->get('email');
            $name = $verifiedToken->claims()->get('name');
            $emailVerified = $verifiedToken->claims()->get('email_verified', true);

            $user = User::firstOrCreate(
                ['firebase_uid' => $uid],
                [
                    'email' => $email,
                    'name' => $name ?? 'User',
                    'email_verified_at' => $emailVerified ? now() : null,
                ]
            );

            // Validate OTP
            $valid = false;
            if ($useEmailOtp) {
                $cacheKey = 'mfa_email_otp:' . $uid;
                $stored = Cache::get($cacheKey);
                if ($stored && $stored['code'] === $otp && $stored['expires_at'] > now()->timestamp) {
                    $valid = true;
                    Cache::forget($cacheKey);
                }
            } else {
                $valid = $this->mfaApi->validateTOTP($token, $otp);
            }

            if (!$valid) {
                if ($request->header('X-Inertia')) {
                    return back()->withErrors(['otp' => 'Invalid or expired verification code.']);
                }
                return response()->json(['error' => 'Invalid or expired verification code.'], 422);
            }

            // Create default organization for new users (same logic as verify() path)
            if ($user->wasRecentlyCreated) {
                $hasInvitations = OrganizationInvitation::where('email', $email)
                    ->whereNull('accepted_at')
                    ->where('expires_at', '>', now())
                    ->exists();
                if (!$hasInvitations) {
                    $organization = Organization::create([
                        'user_id' => $user->id,
                        'name' => $user->name . "'s Organization",
                        'is_default' => true,
                    ]);
                    OrganizationMember::create([
                        'organization_id' => $organization->id,
                        'user_id' => $user->id,
                        'role' => 'owner',
                    ]);
                }
            }

            Auth::login($user, true);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard', absolute: false))
                ->with('success', 'Welcome back!');
        } catch (\Exception $e) {
            Log::error('Firebase MFA verification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->header('X-Inertia')) {
                return back()->withErrors(['otp' => 'Verification failed. Please try again.']);
            }
            return response()->json(['error' => 'Verification failed.'], 422);
        }
    }

    /**
     * Request email OTP for MFA fallback - sends code to user's email
     */
    public function requestEmailOtp(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
        ]);

        $token = $request->input('token');

        try {
            $verifiedToken = $this->firebaseAuth->verifyIdToken($token);
            $uid = $verifiedToken->claims()->get('sub');
            $email = $verifiedToken->claims()->get('email');

            $user = User::where('firebase_uid', $uid)->first();
            if (!$user) {
                return response()->json(['error' => 'User not found.'], 404);
            }

            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $cacheKey = 'mfa_email_otp:' . $uid;
            Cache::put($cacheKey, [
                'code' => $code,
                'expires_at' => now()->addMinutes(10)->timestamp,
            ], now()->addMinutes(11));

            \Illuminate\Support\Facades\Mail::raw(
                "Your Teemops verification code is: {$code}\n\nThis code expires in 10 minutes.\n\nIf you didn't request this, you can safely ignore this email.",
                function ($message) use ($email) {
                    $message->to($email)
                        ->subject('Your Teemops verification code');
                }
            );

            $maskedEmail = $this->maskEmail($email);
            return response()->json([
                'success' => true,
                'masked_email' => $maskedEmail,
            ]);
        } catch (\Exception $e) {
            Log::error('Email OTP request failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to send verification code.'], 500);
        }
    }

    protected function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        $local = $parts[0];
        $domain = $parts[1] ?? '';
        if (strlen($local) <= 2) {
            $masked = $local[0] . '***';
        } else {
            $masked = $local[0] . str_repeat('*', min(strlen($local) - 2, 3)) . substr($local, -1);
        }
        return $masked . '@' . $domain;
    }

    /**
     * Handle Firebase registration (email/password signup)
     * Creates user in Laravel database after Firebase registration
     */
    public function register(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $token = $request->input('token');
        $name = $request->input('name');

        try {
            $verifiedToken = $this->firebaseAuth->verifyIdToken($token);
            $uid = $verifiedToken->claims()->get('sub');
            $email = $verifiedToken->claims()->get('email');
            $emailVerified = $verifiedToken->claims()->get('email_verified', false);
            
            // Check if user already exists
            $existingUser = User::where('firebase_uid', $uid)->orWhere('email', $email)->first();
            
            if ($existingUser) {
                // User already exists, log them in
                Auth::login($existingUser, true);
                $request->session()->regenerate();
                
                return redirect()->intended(route('dashboard', absolute: false))
                    ->with('info', 'Account already exists. You have been logged in.');
            }
            
            // Create new user in Laravel database
            // Note: Password is stored in Firebase, not in Laravel
            $user = User::create([
                'firebase_uid' => $uid,
                'name' => $name,
                'email' => $email,
                'email_verified_at' => $emailVerified ? now() : null,
                // No password field - authentication is handled by Firebase
            ]);

            // Check if user has any pending invitations
            $hasInvitations = OrganizationInvitation::where('email', $email)
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->exists();

            // Only create default organization if user was NOT invited
            if (!$hasInvitations) {
                $organization = Organization::create([
                    'user_id' => $user->id,
                    'name' => $user->name . "'s Organization",
                    'is_default' => true,
                ]);

                // Create owner member record
                OrganizationMember::create([
                    'organization_id' => $organization->id,
                    'user_id' => $user->id,
                    'role' => 'owner',
                ]);
            }

            // Log the user in
            Auth::login($user, true);
            $request->session()->regenerate();

            // If email is not verified, redirect to verification notice
            if (!$emailVerified) {
                return redirect(route('verification.notice', absolute: false))
                    ->with('info', 'Registration successful! Please check your email to verify your account.');
            }

            return redirect()->intended(route('dashboard', absolute: false))
                ->with('success', 'Registration successful! Welcome to Teemops.');
        } catch (\Exception $e) {
            Log::error('Firebase registration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'token_preview' => substr($token, 0, 20) . '...',
            ]);

            // For Inertia requests, return back with errors
            if ($request->header('X-Inertia')) {
                return back()->withErrors([
                    'firebase' => 'Registration failed: ' . $e->getMessage(),
                ]);
            }

            return redirect()->route('register')
                ->withErrors(['firebase' => 'Registration failed. Please try again.']);
        }
    }
}

