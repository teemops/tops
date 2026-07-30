<?php

namespace Tests\Feature\Auth;

use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Tests\TestCase;

class FirebaseMfaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Firebase is an opt-in path (roadmap D-2) and is off by default, so
        // EnsureFirebaseAuthEnabled 404s these routes unless the flag is on. Set it
        // explicitly rather than inheriting whatever the ambient .env says: these
        // tests are about the opt-in path, and they should pass or fail on their own
        // terms rather than on how the machine happens to be configured.
        config(['features.firebase_auth' => true]);

        // The controller depends on the Firebase client; these tests only exercise
        // request validation, which runs before any Firebase call is made.
        $this->instance(FirebaseAuth::class, $this->createMock(FirebaseAuth::class));
    }

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
