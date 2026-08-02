<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'private_key_id' => env('FIREBASE_PRIVATE_KEY_ID'),
        'private_key' => env('FIREBASE_PRIVATE_KEY'),
        'client_email' => env('FIREBASE_CLIENT_EMAIL'),
        'client_id' => env('FIREBASE_CLIENT_ID'),
        'client_x509_cert_url' => env('FIREBASE_CLIENT_X509_CERT_URL'),
    ],

    'aws' => [
        'parent_account_id' => env('AWS_PARENT_ACCOUNT_ID'),
        'cloudformation_template_url' => env('TOPS_CFN_TEMPLATE_URL'),
        'deployment_region' => env('TOPS_DEPLOYMENT_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
        // How long a pending AWS account stays linkable after it is created (N-11).
        // A pending record is a live target for anyone who can publish to the SNS
        // topic, so the window is bounded; re-connecting from the UI mints a fresh
        // one. Set to 0 to disable expiry.
        'account_link_window_hours' => (int) env('TOPS_ACCOUNT_LINK_WINDOW_HOURS', 24),
        'sqs_name' => env('TOPS_SQS_NAME'),
        'sqs_arn' => env('TOPS_SQS_ARN'),
        // Dead-letter queue for teemops_main. Messages land here after
        // maxReceiveCount (5) failed deliveries; aws:redrive-dlq moves them back.
        'sqs_dlq_name' => env('TOPS_SQS_DLQ_NAME', env('TOPS_SQS_NAME') ? env('TOPS_SQS_NAME').'_dlq' : null),
        'sqs_dlq_arn' => env('TOPS_SQS_DLQ_ARN'),
        'sns_arn' => env('TOPS_SNS_ARN'),
        // Install-scoped filter secret (N-11). Minted once by the installer and
        // written to generated/teemops.env; the parent topic's subscription only
        // forwards child-account pings that carry it.
        'install_id' => env('TOPS_INSTALL_ID'),
        'audit_sqs_name' => env('TOPS_AUDIT_SQS_NAME', 'teemops_audit'),
        'audit_sqs_arn' => env('TOPS_AUDIT_SQS_ARN'),
        'audit_region_sqs_name' => env('TOPS_AUDIT_REGION_SQS_NAME', 'teemops_audit_region'),
        'audit_region_sqs_arn' => env('TOPS_AUDIT_REGION_SQS_ARN'),
        'region' => env('TOPS_DEPLOYMENT_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL') . '/oauth/google/callback'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI', env('APP_URL') . '/oauth/github/callback'),
    ],

    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI', env('APP_URL') . '/oauth/microsoft/callback'),
        'tenant' => env('MICROSOFT_TENANT_ID', 'common'),
    ],

    'mfa' => [
        'api_url' => env('MFA_AUTH_API', env('VITE_MFA_AUTH_API', 'http://127.0.0.1:8787')),
    ],

];
