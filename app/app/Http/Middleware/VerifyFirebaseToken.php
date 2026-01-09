<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerifyFirebaseToken
{
    protected Auth $auth;

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

        $this->auth = $factory->createAuth();
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?? $request->header('Authorization');

        if (!$token) {
            return response()->json(['error' => 'Unauthorized - No token provided'], 401);
        }

        // Remove 'Bearer ' prefix if present
        $token = str_replace('Bearer ', '', $token);

        try {
            $verifiedToken = $this->auth->verifyIdToken($token);
            $uid = $verifiedToken->claims()->get('sub');
            $email = $verifiedToken->claims()->get('email');
            $name = $verifiedToken->claims()->get('name');

            // Attach user info to request
            $request->merge([
                'firebase_uid' => $uid,
                'firebase_email' => $email,
                'firebase_name' => $name,
            ]);

            // Find or create user in database
            $user = \App\Models\User::firstOrCreate(
                ['firebase_uid' => $uid],
                [
                    'email' => $email,
                    'name' => $name ?? 'User',
                ]
            );

            // Set authenticated user
            auth()->login($user);

            return $next($request);
        } catch (\Exception $e) {
            Log::error('Firebase token verification failed', [
                'error' => $e->getMessage(),
                'token_preview' => substr($token, 0, 20) . '...',
            ]);

            return response()->json(['error' => 'Unauthorized - Invalid token'], 401);
        }
    }
}
