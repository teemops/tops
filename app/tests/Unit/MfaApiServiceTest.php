<?php

namespace Tests\Unit;

use App\Services\MfaApiService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MfaApiServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.mfa.api_url', 'https://mfa.example.com');
    }

    public function test_is_configured_returns_true_when_api_url_is_set(): void
    {
        $service = new MfaApiService;
        $this->assertTrue($service->isConfigured());
    }

    public function test_is_configured_returns_false_when_api_url_is_empty(): void
    {
        Config::set('services.mfa.api_url', '');
        $service = new MfaApiService;
        $this->assertFalse($service->isConfigured());
    }

    public function test_is_configured_returns_false_when_api_url_is_null(): void
    {
        Config::set('services.mfa.api_url', null);
        $service = new MfaApiService;
        $this->assertFalse($service->isConfigured());
    }

    public function test_is_mfa_enabled_returns_true_when_api_returns_already_verified(): void
    {
        Http::fake([
            'https://mfa.example.com/api/generate' => Http::response([
                'success' => false,
                'message' => 'OTP already verified',
            ], 200),
        ]);

        $service = new MfaApiService;
        $this->assertTrue($service->isMfaEnabled('firebase-token'));
    }

    public function test_is_mfa_enabled_returns_true_when_message_contains_already_verified_case_insensitive(): void
    {
        Http::fake([
            'https://mfa.example.com/api/generate' => Http::response([
                'success' => false,
                'message' => 'User has ALREADY verified MFA',
            ], 200),
        ]);

        $service = new MfaApiService;
        $this->assertTrue($service->isMfaEnabled('firebase-token'));
    }

    public function test_is_mfa_enabled_returns_false_when_api_returns_success_with_base32(): void
    {
        Http::fake([
            'https://mfa.example.com/api/generate' => Http::response([
                'success' => true,
                'base32' => 'JBSWY3DPEHPK3PXP',
            ], 200),
        ]);

        $service = new MfaApiService;
        $this->assertFalse($service->isMfaEnabled('firebase-token'));
    }

    public function test_is_mfa_enabled_returns_false_when_api_returns_unsuccessful(): void
    {
        Http::fake([
            'https://mfa.example.com/api/generate' => Http::response([
                'success' => false,
                'message' => 'Some other error',
            ], 200),
        ]);

        $service = new MfaApiService;
        $this->assertFalse($service->isMfaEnabled('firebase-token'));
    }

    public function test_is_mfa_enabled_returns_false_when_api_returns_non_200(): void
    {
        Http::fake([
            'https://mfa.example.com/api/generate' => Http::response([], 500),
        ]);

        $service = new MfaApiService;
        $this->assertFalse($service->isMfaEnabled('firebase-token'));
    }

    public function test_is_mfa_enabled_returns_false_when_not_configured(): void
    {
        Config::set('services.mfa.api_url', '');
        $service = new MfaApiService;
        $this->assertFalse($service->isMfaEnabled('firebase-token'));
    }

    public function test_validate_totp_returns_true_when_api_returns_success(): void
    {
        Http::fake([
            'https://mfa.example.com/api/validate' => Http::response([
                'success' => true,
            ], 200),
        ]);

        $service = new MfaApiService;
        $this->assertTrue($service->validateTOTP('firebase-token', '123456'));
    }

    public function test_validate_totp_returns_false_when_api_returns_success_false(): void
    {
        Http::fake([
            'https://mfa.example.com/api/validate' => Http::response([
                'success' => false,
            ], 200),
        ]);

        $service = new MfaApiService;
        $this->assertFalse($service->validateTOTP('firebase-token', '123456'));
    }

    public function test_validate_totp_returns_false_when_api_returns_non_200(): void
    {
        Http::fake([
            'https://mfa.example.com/api/validate' => Http::response([], 422),
        ]);

        $service = new MfaApiService;
        $this->assertFalse($service->validateTOTP('firebase-token', '123456'));
    }

    public function test_validate_totp_returns_false_when_not_configured(): void
    {
        Config::set('services.mfa.api_url', '');
        $service = new MfaApiService;
        $this->assertFalse($service->validateTOTP('firebase-token', '123456'));
    }

    public function test_validate_totp_sends_otp_in_request_body(): void
    {
        Http::fake([
            'https://mfa.example.com/api/validate' => function ($request) {
                $body = json_decode($request->body(), true);
                if (($body['otp'] ?? '') === '654321') {
                    return Http::response(['success' => true], 200);
                }
                return Http::response(['success' => false], 200);
            },
        ]);

        $service = new MfaApiService;
        $this->assertTrue($service->validateTOTP('firebase-token', '654321'));
    }

    public function test_is_mfa_enabled_sends_bearer_token(): void
    {
        Http::fake([
            'https://mfa.example.com/api/generate' => function ($request) {
                $auth = $request->header('Authorization');
                $authStr = is_array($auth) ? ($auth[0] ?? '') : $auth;
                if (str_contains($authStr, 'my-custom-token')) {
                    return Http::response([
                        'success' => false,
                        'message' => 'OTP already verified',
                    ], 200);
                }
                return Http::response([], 401);
            },
        ]);

        $service = new MfaApiService;
        $this->assertTrue($service->isMfaEnabled('my-custom-token'));
    }
}
