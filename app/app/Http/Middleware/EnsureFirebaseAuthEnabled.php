<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFirebaseAuthEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('features.firebase_auth')) {
            abort(404);
        }

        return $next($request);
    }
}
