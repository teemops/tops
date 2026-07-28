<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerifyFirebaseToken
{
    protected ?Auth $auth = null;

    /**
     * Handle an incoming request.
     * Supports both Firebase token authentication and session-based authentication.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractBearerToken($request);

        if ($token === null || ! config('features.firebase_auth')) {
            return $this->authenticateViaSession($request, $next);
        }

        return $this->authenticateViaFirebaseToken($request, $next, $token);
    }

    protected function authenticateViaSession(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            return $next($request);
        }

        Log::debug('API request without token or session', [
            'url' => $request->url(),
            'has_session' => $request->hasSession(),
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
        ]);

        return response()->json(['error' => 'Unauthorized - No token provided'], 401);
    }

    protected function authenticateViaFirebaseToken(Request $request, Closure $next, string $token): Response
    {
        try {
            $verifiedToken = $this->firebaseAuth()->verifyIdToken($token);
            $uid = $verifiedToken->claims()->get('sub');
            $email = $verifiedToken->claims()->get('email');
            $name = $verifiedToken->claims()->get('name');

            $request->merge([
                'firebase_uid' => $uid,
                'firebase_email' => $email,
                'firebase_name' => $name,
            ]);

            $user = \App\Models\User::firstOrCreate(
                ['firebase_uid' => $uid],
                [
                    'email' => $email,
                    'name' => $name ?? 'User',
                ]
            );

            auth()->login($user);

            return $next($request);
        } catch (\Exception $e) {
            Log::error('Firebase token verification failed', [
                'error' => $e->getMessage(),
                'token_preview' => substr($token, 0, 20).'...',
            ]);

            return response()->json(['error' => 'Unauthorized - Invalid token'], 401);
        }
    }

    protected function firebaseAuth(): Auth
    {
        // Resolved on demand (and only when a bearer token is present) from the
        // binding registered in AppServiceProvider, so tests can supply a double.
        return $this->auth ??= app(Auth::class);
    }

    protected function extractBearerToken(Request $request): ?string
    {
        $token = $request->bearerToken() ?? $request->header('Authorization');

        if (! $token) {
            return null;
        }

        $token = str_replace('Bearer ', '', $token);

        return $token !== '' ? $token : null;
    }
}
