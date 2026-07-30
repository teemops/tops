<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase authentication
    |--------------------------------------------------------------------------
    |
    | Off by default: the self-hosted path uses Laravel session auth (Breeze) for
    | login/register/password reset and needs no Firebase project. OAuth and
    | Firebase MFA are hidden when this is false.
    |
    | Firebase is only enabled when the flag is explicitly turned on AND the
    | server-side service-account credentials are present. This guard means an
    | install that sets FIREBASE_USER_AUTH=true but leaves the credentials blank
    | still falls back to Breeze — signup uses email/password and the Firebase
    | routes 404, instead of throwing mid-signup.
    |
    */
    'firebase_auth' => filter_var(env('FIREBASE_USER_AUTH', false), FILTER_VALIDATE_BOOLEAN)
        && filled(env('FIREBASE_PROJECT_ID'))
        && filled(env('FIREBASE_PRIVATE_KEY'))
        && filled(env('FIREBASE_CLIENT_EMAIL')),

];
