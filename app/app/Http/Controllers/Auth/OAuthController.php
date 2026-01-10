<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends Controller
{
    /**
     * Redirect to OAuth provider
     */
    public function redirect(string $provider): RedirectResponse
    {
        $this->validateProvider($provider);

        $driver = Socialite::driver($provider);

        // Special handling for Microsoft
        if ($provider === 'microsoft') {
            $tenant = config('services.microsoft.tenant', 'common');
            $driver->using($tenant);
        }

        return $driver->redirect();
    }

    /**
     * Handle OAuth callback
     */
    public function callback(string $provider): RedirectResponse
    {
        $this->validateProvider($provider);

        try {
            $socialUser = Socialite::driver($provider)->user();

            // Find or create user
            $user = User::firstOrCreate(
                [
                    'email' => $socialUser->getEmail(),
                ],
                [
                    'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
                    'email_verified_at' => now(),
                    // Store provider ID for future reference
                    'firebase_uid' => $socialUser->getId(), // Using firebase_uid field to store OAuth provider ID
                ]
            );

            // Update user info if it changed
            if ($user->name !== ($socialUser->getName() ?? $socialUser->getNickname())) {
                $user->update([
                    'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? $user->name,
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

            return redirect()->intended(route('dashboard', absolute: false));
        } catch (\Exception $e) {
            Log::error('OAuth callback failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('login')
                ->withErrors(['oauth' => 'Authentication failed. Please try again.']);
        }
    }

    /**
     * Validate that the provider is supported
     */
    protected function validateProvider(string $provider): void
    {
        $supportedProviders = ['google', 'github', 'microsoft'];

        if (!in_array($provider, $supportedProviders)) {
            abort(404, 'OAuth provider not supported');
        }
    }
}
