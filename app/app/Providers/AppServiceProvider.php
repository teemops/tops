<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Kreait\Firebase\Factory;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Resolved lazily so the Firebase service account is only built when a
        // Firebase-backed route is actually hit, and so tests can swap in a double.
        $this->app->singleton(FirebaseAuth::class, function () {
            return (new Factory)
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
                ])
                ->createAuth();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
