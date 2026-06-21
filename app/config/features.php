<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase authentication
    |--------------------------------------------------------------------------
    |
    | When false, login/register/password reset use Laravel session auth (Breeze)
    | instead of Firebase. OAuth and Firebase MFA are hidden.
    |
    */
    'firebase_auth' => filter_var(env('FIREBASE_USER_AUTH', true), FILTER_VALIDATE_BOOLEAN),

];
