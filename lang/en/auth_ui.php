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
    'login' => [
        'title' => 'Log in to your account',
        'description' => 'Enter your email and password below to log in',
        'forgot_password' => 'Forgot password?',
        'remember' => 'Remember me',
        'submit' => 'Log in',
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
    'first_password' => [
        'title' => 'Choose your own password',
        'description' => 'You gave the default password. Choose your own password to continue; you will then sign in with it.',
        'submit' => 'Save my password',
        'back' => 'Back to sign in',
        'done' => 'Your password is saved. Sign in with it now.',
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
];
