<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();

        // Registration sends the user to the email-verification notice, not the dashboard.
        $response->assertRedirect(route('verification.notice', absolute: false));
    }

    /**
     * A browser that was used against a previous install still holds a
     * current_organization_id cookie naming an org that no longer exists in the
     * fresh database. Signing up used to complete on the server and then bounce
     * the redirect between /verify-email and /organizations until the browser
     * gave up, surfacing as a network error with no account visible to the user.
     */
    public function test_new_users_can_register_with_a_stale_organization_cookie(): void
    {
        $response = $this->withUnencryptedCookie('current_organization_id', 'org-from-a-previous-install')
            ->post('/register', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));

        // Following that redirect must land on a page, not another redirect.
        $this->withUnencryptedCookie('current_organization_id', 'org-from-a-previous-install')
            ->get(route('verification.notice', absolute: false))
            ->assertStatus(200);
    }

    public function test_registration_requires_a_unique_email(): void
    {
        \App\Models\User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'taken@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_registration_requires_a_confirmed_password(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }
}
