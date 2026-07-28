<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\EnsureFirebaseAuthEnabled;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class EnsureFirebaseAuthEnabledTest extends TestCase
{
    public function test_the_request_passes_through_when_firebase_auth_is_enabled(): void
    {
        config(['features.firebase_auth' => true]);

        $response = (new EnsureFirebaseAuthEnabled())->handle(
            Request::create('/auth/firebase/verify', 'POST'),
            fn () => new Response('ok')
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * With Firebase off the routes should look like they do not exist, rather
     * than returning a 403 that advertises them.
     */
    public function test_the_route_is_hidden_when_firebase_auth_is_disabled(): void
    {
        config(['features.firebase_auth' => false]);

        $this->expectException(NotFoundHttpException::class);

        (new EnsureFirebaseAuthEnabled())->handle(
            Request::create('/auth/firebase/verify', 'POST'),
            fn () => new Response('ok')
        );
    }
}
