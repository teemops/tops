<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Kreait\Firebase\Auth as FirebaseAuth;
use Kreait\Firebase\Factory;

class FirebaseAuthController extends Controller
{
    protected FirebaseAuth $firebaseAuth;

    public function __construct()
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
    }

    /**
     * Verify Firebase token and create Laravel session
     */
    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
        ]);

        $token = $request->input('token');

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

            // Create default organization if user is new
            if ($user->wasRecentlyCreated) {
                Organization::create([
                    'user_id' => $user->id,
                    'name' => $user->name . "'s Organization",
                    'is_default' => true,
                ]);
            }

            // Log the user in
            Auth::login($user, true);

            $request->session()->regenerate();

            // Show welcome message for new OAuth users
            $message = $user->wasRecentlyCreated 
                ? 'Welcome! Your account has been created and verified.'
                : 'Welcome back!';

            return redirect()->intended(route('dashboard', absolute: false))
                ->with('success', $message);
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
}

