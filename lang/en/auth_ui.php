<?php

/*
 * Text of the sign-in and registration screens (sent to the frontend).
 * Server-side authentication messages live in auth.php.
 */

return [
    'email' => 'Email address',
    'password' => 'Password',
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
