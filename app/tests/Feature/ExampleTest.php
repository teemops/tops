<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guests hitting the root are sent to the login page.
     */
    public function test_the_root_route_redirects_guests_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    /**
     * Authenticated users hitting the root are sent to the dashboard.
     */
    public function test_the_root_route_redirects_authenticated_users_to_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect(route('dashboard'));
    }

    /**
     * The Firebase password-reset callback renders the reset page instead of redirecting.
     */
    public function test_the_root_route_renders_reset_password_in_firebase_reset_mode(): void
    {
        $response = $this->get('/?mode=resetPassword&oobCode=abc123&apiKey=test-key');

        $response->assertOk();
    }
}
