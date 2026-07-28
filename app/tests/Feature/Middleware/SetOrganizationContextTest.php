<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\SetOrganizationContext;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class SetOrganizationContextTest extends TestCase
{
    use RefreshDatabase;

    private SetOrganizationContext $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new SetOrganizationContext();
    }

    /**
     * Push a request through the middleware, returning either the short-circuit
     * response or the request as the middleware left it.
     */
    private function dispatch(Request $request): array
    {
        $passedThrough = null;

        $response = $this->middleware->handle($request, function (Request $r) use (&$passedThrough) {
            $passedThrough = $r;

            return new Response('ok');
        });

        return [$response, $passedThrough];
    }

    private function apiRequest(string $uri = '/api/scans'): Request
    {
        $request = Request::create($uri, 'GET');
        $request->headers->set('Accept', 'application/json');

        return $request;
    }

    public function test_a_guest_api_request_is_rejected(): void
    {
        [$response, $passedThrough] = $this->dispatch($this->apiRequest());

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertNull($passedThrough);
    }

    public function test_a_guest_web_request_passes_through(): void
    {
        [$response, $passedThrough] = $this->dispatch(Request::create('/', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotNull($passedThrough);
    }

    /**
     * The Firebase sign-in endpoints are hit before a session exists, so they must
     * not be blocked by the JSON guard above.
     */
    public function test_a_guest_firebase_auth_request_passes_through(): void
    {
        [$response, $passedThrough] = $this->dispatch($this->apiRequest('/auth/firebase/verify'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotNull($passedThrough);
    }

    public function test_an_unknown_organization_id_is_a_404_for_api_requests(): void
    {
        $this->actingAs(User::factory()->create());

        $request = $this->apiRequest();
        $request->headers->set('X-Organization-Id', 'no-such-org');

        [$response] = $this->dispatch($request);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_an_unknown_organization_id_redirects_web_requests(): void
    {
        $this->actingAs(User::factory()->create());

        $request = Request::create('/dashboard?org_id=no-such-org', 'GET');

        [$response] = $this->dispatch($request);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals(route('organizations.index'), $response->headers->get('Location'));
    }

    public function test_a_non_member_is_denied_access_to_an_organization(): void
    {
        $this->actingAs(User::factory()->create());
        $organization = Organization::factory()->create();

        $request = $this->apiRequest();
        $request->headers->set('X-Organization-Id', $organization->org_id);

        [$response] = $this->dispatch($request);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_the_owner_gets_the_organization_and_owner_role_attached(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $organization = Organization::factory()->create(['user_id' => $user->id]);

        $request = $this->apiRequest();
        $request->headers->set('X-Organization-Id', $organization->org_id);

        [$response, $passedThrough] = $this->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($passedThrough->attributes->get('organization')->is($organization));
        $this->assertEquals('owner', $passedThrough->attributes->get('organization_role'));
    }

    public function test_a_member_gets_their_own_role_attached(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $organization = Organization::factory()->create();
        OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'auditor',
        ]);

        $request = $this->apiRequest();
        $request->headers->set('X-Organization-Id', $organization->org_id);

        [, $passedThrough] = $this->dispatch($request);

        $this->assertEquals('auditor', $passedThrough->attributes->get('organization_role'));
    }

    /**
     * The cookie holds the org the user last selected in the UI and wins over
     * the other sources.
     */
    public function test_the_cookie_takes_precedence_over_the_query_string(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $cookieOrg = Organization::factory()->create(['user_id' => $user->id]);
        $queryOrg = Organization::factory()->create(['user_id' => $user->id]);

        $request = Request::create('/dashboard?org_id='.$queryOrg->org_id, 'GET', [], [
            'current_organization_id' => $cookieOrg->org_id,
        ]);

        [, $passedThrough] = $this->dispatch($request);

        $this->assertTrue($passedThrough->attributes->get('organization')->is($cookieOrg));
    }

    public function test_with_no_org_id_the_default_organization_is_selected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Organization::factory()->create(['user_id' => $user->id]);
        $default = Organization::factory()->default()->create(['user_id' => $user->id]);

        [, $passedThrough] = $this->dispatch(Request::create('/dashboard', 'GET'));

        $this->assertTrue($passedThrough->attributes->get('organization')->is($default));
    }

    public function test_with_no_default_the_first_owned_organization_is_selected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $first = Organization::factory()->create(['user_id' => $user->id]);
        Organization::factory()->create(['user_id' => $user->id]);

        [, $passedThrough] = $this->dispatch(Request::create('/dashboard', 'GET'));

        $this->assertTrue($passedThrough->attributes->get('organization')->is($first));
    }

    public function test_a_user_who_only_belongs_to_others_organizations_gets_that_one(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $organization = Organization::factory()->create();
        OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'viewer',
        ]);

        [, $passedThrough] = $this->dispatch(Request::create('/dashboard', 'GET'));

        $this->assertTrue($passedThrough->attributes->get('organization')->is($organization));
    }

    public function test_a_brand_new_user_gets_a_default_organization_created(): void
    {
        $user = User::factory()->create(['name' => 'Ada']);
        $this->actingAs($user);

        [, $passedThrough] = $this->dispatch(Request::create('/dashboard', 'GET'));

        $organization = $passedThrough->attributes->get('organization');

        $this->assertNotNull($organization);
        $this->assertEquals("Ada's Organization", $organization->name);
        $this->assertTrue($organization->is_default);
        $this->assertDatabaseHas('organization_members', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
    }

    /**
     * An invited user should join the inviting organization rather than silently
     * getting one of their own.
     */
    public function test_a_user_with_a_pending_invitation_does_not_get_a_default_organization(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        OrganizationInvitation::factory()->create(['email' => $user->email]);

        [$response, $passedThrough] = $this->dispatch(Request::create('/dashboard', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull($passedThrough->attributes->get('organization'));
        $this->assertDatabaseCount('organizations', 1); // only the inviter's
    }

    public function test_an_expired_invitation_does_not_block_the_default_organization(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        OrganizationInvitation::factory()->expired()->create(['email' => $user->email]);

        [, $passedThrough] = $this->dispatch(Request::create('/dashboard', 'GET'));

        $this->assertNotNull($passedThrough->attributes->get('organization'));
    }
}
