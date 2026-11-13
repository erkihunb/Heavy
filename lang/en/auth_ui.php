<?php

/*
 * Text of the sign-in and registration screens (sent to the frontend).
 * Server-side authentication messages live in auth.php.
 */

return [
    'email' => 'Email address',
    'password' => 'Password',
    /*
     * The illustration beside the sign-in form names the system. The Amharic
     * line leads: it is the Authority's own name for the service, and it is
     * the same in both languages.
     */
    'hero' => [
        'title' => 'በሰዓት ገደብ የከባድ ተሽከርካሪዎች መንቀሳቀሻ ፈቃድ ሲስተም',
        'subtitle' => 'Heavy Vehicle Movement Permit System',
    ],
    'panel' => [
        'eyebrow' => 'Addis Ababa City Roads Authority',
        'title' => 'Movement permits for heavy vehicles, from request to issued permit',
        'intro' => 'Heavy vehicles may not move during restricted hours without a permit. The Authority records each request at a branch or the head office, decides on it, takes the payment and issues the permit.',
        'steps' => [
            'apply' => [
                'title' => 'Apply at the office',
                'body' => 'Bring a request letter to a branch. Staff record the organisation, the vehicle and the dates.',
            ],
            'decide' => [
                'title' => 'Review and payment',
                'body' => 'A reviewer checks the request, an approver decides, and the fee is paid at the counter.',
            ],
            'verify' => [
                'title' => 'Issued and checked',
                'body' => 'The permit carries a code a traffic officer can scan and verify at the roadside.',
            ],
        ],
        'no_account' => 'Accounts are opened at the office: nobody signs up here. Staff sign in with the account the Authority gave them.',
    ],
    'login' => [
        'title' => 'Log in to your account',
        'description' => 'Enter your email and password below to log in',
        'forgot_password' => 'Forgot password?',
        'remember' => 'Remember me',
        'submit' => 'Log in',
        'no_account' => "Don't have an account?",
        'sign_up' => 'Sign up',
    ],
    'forgot' => [
        'title' => 'Forgot your password',
        'description' => 'Enter your email address and we will send you a link to set a new password.',
        'submit' => 'Email a reset link',
        'back_prefix' => 'Or, return to',
        'back_link' => 'log in',
    ],
    'reset' => [
        'title' => 'Set a new password',
        'description' => 'Enter the new password for your account below.',
        'new_password' => 'New password',
        'confirm_password' => 'Confirm the new password',
        'submit' => 'Save the new password',
    ],
    'confirm' => [
        'title' => 'Confirm your password',
        'description' => 'This part of the system is protected. Enter your password again to continue.',
        'submit' => 'Confirm password',
    ],
    'verify' => [
        'title' => 'Confirm your email address',
        'description' => 'Open the link we sent to your email address to confirm it.',
        'sent' => 'A new link has been sent to your email address.',
        'resend' => 'Send the link again',
        'logout' => 'Log out',
    ],
    'two_factor' => [
        'title' => 'Authentication code',
        'description' => 'Enter the code from your authenticator app.',
        'recovery_title' => 'Recovery code',
        'recovery_description' => 'Enter one of the recovery codes you saved when two-factor authentication was set up.',
        'recovery_placeholder' => 'Recovery code',
        'use_recovery' => 'use a recovery code instead',
        'use_code' => 'use an authentication code instead',
        'or' => 'Or you can',
        'submit' => 'Continue',
    ],
    'register' => [
        'title' => 'Create an account',
        'description' => 'Enter your details below to create your account',
        'name' => 'Name',
        'name_placeholder' => 'Full name',
        'confirm_password' => 'Confirm password',
        'submit' => 'Create account',
        'have_account' => 'Already have an account?',
        'log_in' => 'Log in',
    ],
];
