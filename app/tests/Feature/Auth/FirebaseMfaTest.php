<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class FirebaseMfaTest extends TestCase
{
    /**
     * POST as web form (no Accept: application/json) so SetOrganizationContext allows
     * unauthenticated requests through; validation then returns 302 with session errors.
     */
    public function test_verify_mfa_requires_token(): void
    {
        $response = $this->post(route('firebase.verify-mfa'), [
            'otp' => '123456',
            'use_email_otp' => false,
        ]);

        $response->assertSessionHasErrors(['token']);
    }

    public function test_verify_mfa_requires_otp(): void
    {
        $response = $this->post(route('firebase.verify-mfa'), [
            'token' => 'some-firebase-token',
            'use_email_otp' => false,
        ]);

        $response->assertSessionHasErrors(['otp']);
    }

    public function test_verify_mfa_requires_otp_to_be_six_digits(): void
    {
        $response = $this->post(route('firebase.verify-mfa'), [
            'token' => 'some-firebase-token',
            'otp' => '12345',
            'use_email_otp' => false,
        ]);

        $response->assertSessionHasErrors(['otp']);
    }

    public function test_request_email_otp_requires_token(): void
    {
        $response = $this->post(route('firebase.request-email-otp'), []);

        $response->assertSessionHasErrors(['token']);
    }

    public function test_firebase_verify_route_requires_token(): void
    {
        $response = $this->post(route('firebase.verify'), []);

        $response->assertSessionHasErrors(['token']);
    }

    public function test_verify_mfa_route_exists_and_accepts_post(): void
    {
        $response = $this->get(route('firebase.verify-mfa'));
        $response->assertStatus(405);

        $response = $this->post(route('firebase.verify-mfa'), [
            'token' => 'x',
            'otp' => '123456',
        ]);
        $this->assertNotEquals(404, $response->status());
    }

    public function test_request_email_otp_route_exists_and_accepts_post(): void
    {
        $response = $this->get(route('firebase.request-email-otp'));
        $response->assertStatus(405);

        $response = $this->post(route('firebase.request-email-otp'), [
            'token' => 'x',
        ]);
        $this->assertNotEquals(404, $response->status());
    }
}
