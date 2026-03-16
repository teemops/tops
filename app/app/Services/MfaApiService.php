<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MfaApiService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.mfa.api_url', ''), '/');
    }

    public function isConfigured(): bool
    {
        return !empty($this->baseUrl);
    }

    /**
     * Check if MFA is enabled for the user (via Teem API generate - returns "already verified" when MFA on)
     */
    public function isMfaEnabled(string $firebaseToken): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $response = Http::withToken($firebaseToken)
                ->timeout(10)
                ->get($this->baseUrl . '/api/generate');

            if (!$response->successful()) {
                return false;
            }

            $data = $response->json();
            $success = $data['success'] ?? true;
            $message = $data['message'] ?? '';

            // MFA is enabled when API returns success: false with "OTP already verified"
            return !$success && stripos($message, 'already verified') !== false;
        } catch (\Throwable $e) {
            Log::warning('MFA API check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Validate TOTP code via Teem API
     */
    public function validateTOTP(string $firebaseToken, string $otp): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $response = Http::withToken($firebaseToken)
                ->timeout(10)
                ->post($this->baseUrl . '/api/validate', [
                    'otp' => $otp,
                ]);

            if (!$response->successful()) {
                return false;
            }

            $data = $response->json();
            return ($data['success'] ?? false) === true;
        } catch (\Throwable $e) {
            Log::warning('MFA API validate failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
