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
     * The POST halves of the login and register routes are unnamed, so matching
     * them by route name silently missed them and any client accepting any
     * content type got a 401 instead of being able to sign up.
     */
    public function test_a_guest_can_post_to_login_and_register_expecting_json(): void
    {
        foreach (['login', 'register'] as $path) {
            $request = Request::create('/'.$path, 'POST');
            $request->headers->set('X-Requested-With', 'XMLHttpRequest');
            $request->headers->set('Accept', '*/*');

            [$response, $passedThrough] = $this->dispatch($request);

            $this->assertEquals(200, $response->getStatusCode(), "POST /{$path} was blocked");
            $this->assertNotNull($passedThrough, "POST /{$path} did not reach the controller");
        }
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

    /**
     * Selecting an organization must not change who is logged in. This used to
     * swap the request's user for the Organization, so everything reading
     * $request->user() - the sidebar's user menu included - showed the org's
     * name and a null email in place of the person's.
     */
    public function test_selecting_an_organization_leaves_the_authenticated_user_alone(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $organization = Organization::factory()->create(['user_id' => $user->id]);

        $request = Request::create('/dashboard', 'GET', [], [
            'current_organization_id' => $organization->org_id,
        ]);
        $request->setUserResolver(fn () => $user);

        [, $passedThrough] = $this->dispatch($request);

        $this->assertInstanceOf(User::class, $passedThrough->user());
        $this->assertTrue($passedThrough->user()->is($user));
        $this->assertSame($user->email, $passedThrough->user()->email);
    }

    /**
     * The cookie rides along on every request including API calls, so a dead one
     * must not 404 the sidebar's organization list - that showed the user "No
     * organizations" when they in fact had one.
     */
    public function test_a_stale_organization_cookie_does_not_fail_api_requests(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $default = Organization::factory()->default()->create(['user_id' => $user->id]);

        $request = Request::create('/api/organizations', 'GET', [], [
            'current_organization_id' => 'org-from-a-previous-install',
        ]);
        $request->headers->set('Accept', 'application/json');

        [$response, $passedThrough] = $this->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($passedThrough->attributes->get('organization')->is($default));
    }

    /**
     * Healing stops at access: a cookie naming an org the user is not in must
     * still be refused on the API, so a write cannot be quietly redirected into
     * a different tenant.
     */
    public function test_a_cookie_for_an_inaccessible_org_is_still_denied_on_the_api(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Organization::factory()->default()->create(['user_id' => $user->id]);
        $someoneElses = Organization::factory()->create();

        $request = Request::create('/api/aws-accounts', 'POST', [], [
            'current_organization_id' => $someoneElses->org_id,
        ]);
        $request->headers->set('Accept', 'application/json');

        [$response, $passedThrough] = $this->dispatch($request);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertNull($passedThrough);
    }

    /**
     * A web request must never be redirected for a bad org id. This middleware
     * runs on the whole web group, so the redirect target would hit it again and
     * bounce forever - ERR_TOO_MANY_REDIRECTS on every page.
     */
    public function test_an_unknown_organization_id_does_not_redirect_web_requests(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $default = Organization::factory()->default()->create(['user_id' => $user->id]);

        $request = Request::create('/dashboard?org_id=no-such-org', 'GET');

        [$response, $passedThrough] = $this->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($passedThrough->attributes->get('organization')->is($default));
    }

    /**
     * The regression that broke signup on a fresh install: a cookie left over
     * from a previous install names an org that no longer exists.
     */
    public function test_a_stale_organization_cookie_falls_back_to_the_default_org(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $default = Organization::factory()->default()->create(['user_id' => $user->id]);

        $request = Request::create('/verify-email', 'GET', [], [
            'current_organization_id' => 'org-from-a-previous-install',
        ]);

        [$response, $passedThrough] = $this->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($passedThrough->attributes->get('organization')->is($default));
    }

    public function test_a_stale_organization_cookie_is_cleared_from_the_browser(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Organization::factory()->default()->create(['user_id' => $user->id]);

        $request = Request::create('/dashboard', 'GET', [], [
            'current_organization_id' => 'org-from-a-previous-install',
        ]);

        [$response] = $this->dispatch($request);

        $cleared = collect($response->headers->getCookies())
            ->firstWhere(fn ($cookie) => $cookie->getName() === 'current_organization_id');

        $this->assertNotNull($cleared, 'Expected the stale selection cookie to be cleared.');
        $this->assertEmpty($cleared->getValue());
    }

    /**
     * A brand new user signing up has no organization yet, so the stale cookie
     * must not stop one being created for them.
     */
    public function test_a_stale_cookie_still_lets_a_new_user_get_a_default_org(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = Request::create('/verify-email', 'GET', [], [
            'current_organization_id' => 'org-from-a-previous-install',
        ]);

        [$response, $passedThrough] = $this->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotNull($passedThrough->attributes->get('organization'));
        $this->assertDatabaseHas('organizations', [
            'user_id' => $user->id,
            'is_default' => true,
        ]);
    }

    /**
     * Losing access to the selected org must not lock the user out either.
     */
    public function test_a_web_request_for_an_inaccessible_org_falls_back_to_the_default_org(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $default = Organization::factory()->default()->create(['user_id' => $user->id]);
        $someoneElses = Organization::factory()->create();

        $request = Request::create('/dashboard', 'GET', [], [
            'current_organization_id' => $someoneElses->org_id,
        ]);

        [$response, $passedThrough] = $this->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($passedThrough->attributes->get('organization')->is($default));
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
