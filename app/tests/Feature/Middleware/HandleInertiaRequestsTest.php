<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class HandleInertiaRequestsTest extends TestCase
{
    use RefreshDatabase;

    private HandleInertiaRequests $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new HandleInertiaRequests();
    }

    /**
     * Build a request with a session, as the shared props read flash messages.
     */
    private function request(?User $user = null): Request
    {
        $request = Request::create('/dashboard', 'GET');
        $request->setLaravelSession($this->app['session']->driver());

        if ($user) {
            $request->setUserResolver(fn () => $user);
        }

        return $request;
    }

    public function test_a_guest_shares_a_null_user(): void
    {
        $shared = $this->middleware->share($this->request());

        $this->assertNull($shared['auth']['user']);
        $this->assertNull($shared['organization_role']);
    }

    public function test_an_authenticated_user_shares_only_safe_fields(): void
    {
        $user = User::factory()->create();

        $shared = $this->middleware->share($this->request($user));

        $this->assertEqualsCanonicalizing(
            ['id', 'name', 'email', 'email_verified_at'],
            array_keys($shared['auth']['user'])
        );
        $this->assertEquals($user->email, $shared['auth']['user']['email']);
    }

    public function test_the_role_set_by_the_organization_middleware_is_shared_as_is(): void
    {
        $user = User::factory()->create();
        $request = $this->request($user);
        $request->attributes->set('organization_role', 'auditor');

        $shared = $this->middleware->share($request);

        $this->assertEquals('auditor', $shared['organization_role']);
    }

    /**
     * When the role was not pre-computed, it is derived from the organization on
     * the request.
     */
    public function test_the_role_is_derived_from_the_request_organization(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'administrator',
        ]);

        $request = $this->request($user);
        $request->attributes->set('organization', $organization);

        $shared = $this->middleware->share($request);

        $this->assertEquals('administrator', $shared['organization_role']);
    }

    public function test_flash_messages_are_shared(): void
    {
        $request = $this->request();
        $request->session()->flash('success', 'Saved.');
        $request->session()->flash('error', 'Nope.');

        $shared = $this->middleware->share($request);

        $this->assertEquals('Saved.', $shared['flash']['success']);
        $this->assertEquals('Nope.', $shared['flash']['error']);
        $this->assertNull($shared['flash']['warning']);
        $this->assertNull($shared['flash']['info']);
    }

    public function test_the_firebase_feature_flag_is_shared(): void
    {
        config(['features.firebase_auth' => false]);

        $this->assertFalse($this->middleware->share($this->request())['features']['firebase_auth']);

        config(['features.firebase_auth' => true]);

        $this->assertTrue($this->middleware->share($this->request())['features']['firebase_auth']);
    }
}
