<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Auth;
use Kreait\Firebase\Factory;
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
        if ($this->auth !== null) {
            return $this->auth;
        }

        $config = config('services.firebase');

        $factory = (new Factory)
            ->withServiceAccount([
                'type' => 'service_account',
                'project_id' => $config['project_id'],
                'private_key_id' => $config['private_key_id'],
                'private_key' => $config['private_key'],
                'client_email' => $config['client_email'],
                'client_id' => $config['client_id'],
                'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
                'token_uri' => 'https://oauth2.googleapis.com/token',
                'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
                'client_x509_cert_url' => $config['client_x509_cert_url'],
            ]);

        return $this->auth = $factory->createAuth();
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
