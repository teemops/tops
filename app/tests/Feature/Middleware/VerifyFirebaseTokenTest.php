<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\VerifyFirebaseToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Lcobucci\JWT\UnencryptedToken;
use Tests\TestCase;

class VerifyFirebaseTokenTest extends TestCase
{
    use RefreshDatabase;

    private VerifyFirebaseToken $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        // Firebase is an opt-in path (roadmap D-2) and off by default, so the
        // middleware short-circuits unless the flag is on. Set it explicitly rather
        // than inheriting the ambient .env — one test below deliberately turns it
        // back off to assert the disabled behaviour.
        config(['features.firebase_auth' => true]);

        $this->middleware = new VerifyFirebaseToken();
    }

    private function dispatch(Request $request): array
    {
        $passedThrough = null;

        $response = $this->middleware->handle($request, function (Request $r) use (&$passedThrough) {
            $passedThrough = $r;

            return new Response('ok');
        });

        return [$response, $passedThrough];
    }

    /**
     * Bind a Firebase client that returns a token with the given claims.
     */
    private function fakeFirebaseToken(array $claims): void
    {
        $token = $this->createMock(UnencryptedToken::class);
        $dataSet = new \Lcobucci\JWT\Token\DataSet($claims, '');
        $token->method('claims')->willReturn($dataSet);

        $auth = $this->createMock(FirebaseAuth::class);
        $auth->method('verifyIdToken')->willReturn($token);

        $this->instance(FirebaseAuth::class, $auth);
    }

    public function test_a_request_without_a_token_or_session_is_rejected(): void
    {
        [$response, $passedThrough] = $this->dispatch(Request::create('/api/scans', 'GET'));

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('No token provided', $response->getContent());
        $this->assertNull($passedThrough);
    }

    public function test_a_session_authenticated_request_passes_through(): void
    {
        $this->actingAs(User::factory()->create());

        [$response, $passedThrough] = $this->dispatch(Request::create('/api/scans', 'GET'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotNull($passedThrough);
    }

    /**
     * With the Firebase feature off, a bearer token is ignored and the session
     * is the only thing that counts.
     */
    public function test_a_bearer_token_is_ignored_when_firebase_auth_is_disabled(): void
    {
        config(['features.firebase_auth' => false]);

        $request = Request::create('/api/scans', 'GET');
        $request->headers->set('Authorization', 'Bearer some-token');

        [$response] = $this->dispatch($request);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_an_invalid_firebase_token_is_rejected(): void
    {
        $auth = $this->createMock(FirebaseAuth::class);
        $auth->method('verifyIdToken')->willThrowException(new \RuntimeException('expired'));
        $this->instance(FirebaseAuth::class, $auth);

        $request = Request::create('/api/scans', 'GET');
        $request->headers->set('Authorization', 'Bearer bad-token');

        [$response, $passedThrough] = $this->dispatch($request);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('Invalid token', $response->getContent());
        $this->assertNull($passedThrough);
    }

    public function test_a_valid_token_logs_in_an_existing_user(): void
    {
        $user = User::factory()->create(['firebase_uid' => 'uid-existing']);

        $this->fakeFirebaseToken([
            'sub' => 'uid-existing',
            'email' => $user->email,
            'name' => $user->name,
        ]);

        $request = Request::create('/api/scans', 'GET');
        $request->headers->set('Authorization', 'Bearer good-token');

        [$response] = $this->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue(auth()->check());
        $this->assertEquals($user->id, auth()->id());
        $this->assertDatabaseCount('users', 1);
    }

    public function test_a_valid_token_provisions_an_unknown_user(): void
    {
        $this->fakeFirebaseToken([
            'sub' => 'uid-new',
            'email' => 'new@example.com',
            'name' => 'New Person',
        ]);

        $request = Request::create('/api/scans', 'GET');
        $request->headers->set('Authorization', 'Bearer good-token');

        [$response, $passedThrough] = $this->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('users', [
            'firebase_uid' => 'uid-new',
            'email' => 'new@example.com',
            'name' => 'New Person',
        ]);
        $this->assertEquals('uid-new', $passedThrough->input('firebase_uid'));
        $this->assertEquals('new@example.com', $passedThrough->input('firebase_email'));
    }

    /**
     * Firebase accounts created via some providers carry no display name.
     */
    public function test_a_token_without_a_name_falls_back_to_user(): void
    {
        $this->fakeFirebaseToken([
            'sub' => 'uid-nameless',
            'email' => 'nameless@example.com',
            'name' => null,
        ]);

        $request = Request::create('/api/scans', 'GET');
        $request->headers->set('Authorization', 'Bearer good-token');

        $this->dispatch($request);

        $this->assertDatabaseHas('users', [
            'firebase_uid' => 'uid-nameless',
            'name' => 'User',
        ]);
    }

    /**
     * Some clients send the raw token in the Authorization header without the
     * "Bearer " prefix.
     */
    public function test_a_token_without_the_bearer_prefix_is_accepted(): void
    {
        $this->fakeFirebaseToken([
            'sub' => 'uid-raw',
            'email' => 'raw@example.com',
            'name' => 'Raw',
        ]);

        $request = Request::create('/api/scans', 'GET');
        $request->headers->set('Authorization', 'raw-token-value');

        [$response] = $this->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('users', ['firebase_uid' => 'uid-raw']);
    }

    public function test_an_empty_bearer_token_is_treated_as_no_token(): void
    {
        $request = Request::create('/api/scans', 'GET');
        $request->headers->set('Authorization', 'Bearer ');

        [$response] = $this->dispatch($request);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('No token provided', $response->getContent());
    }
}
