<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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

            // Find or create user
            $user = User::firstOrCreate(
                ['firebase_uid' => $uid],
                [
                    'email' => $email,
                    'name' => $name ?? 'User',
                    'email_verified_at' => now(),
                ]
            );

            // Update user info if changed
            if ($user->name !== ($name ?? 'User')) {
                $user->update([
                    'name' => $name ?? $user->name,
                ]);
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

            return redirect()->intended(route('dashboard', absolute: false));
        } catch (\Exception $e) {
            Log::error('Firebase token verification failed', [
                'error' => $e->getMessage(),
                'token_preview' => substr($token, 0, 20) . '...',
            ]);

            return redirect()->route('login')
                ->withErrors(['firebase' => 'Authentication failed. Please try again.']);
        }
    }
}

