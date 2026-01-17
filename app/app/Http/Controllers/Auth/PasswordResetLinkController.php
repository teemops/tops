<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     * 
     * Note: This endpoint is kept for backward compatibility but is no longer used.
     * Password reset is now handled entirely by Firebase on the frontend.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // Password reset is now handled by Firebase on the frontend
        // This endpoint is kept for backward compatibility but redirects back
        // with a message indicating to use the frontend form
        return back()->with('status', 'Please use the form above to reset your password.');
    }
}
