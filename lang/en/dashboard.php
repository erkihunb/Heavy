<?php

return [
    'greeting' => 'Welcome, :name',
    'role_label' => 'Your role',
    'permits' => [
        'applicant' => [
            'title' => 'Permit applications',
            'body' => 'Apply for a permit for a registered vehicle and follow your applications. Payment and the permit document are being added.',
            'action' => 'Go to permits',
        ],
        'staff' => [
            'title' => 'Review queue',
            'body' => ':count waiting for review.',
            'action' => 'Open the queue',
        ],
    ],
    'account' => [
        'title' => 'Account status',
        'email_verified' => 'Email address verified',
        'email_unverified' => 'Email address not verified',
        'two_factor_on' => 'Two-factor authentication is on',
        'two_factor_off' => 'Two-factor authentication is off',
        'manage' => 'Security settings',
    ],
    'next' => [
        'label' => 'Next step',
        'organization' => [
            'title' => 'Start with your organisation profile',
            'body' => 'You need a profile before you can register vehicles.',
        ],
        'vehicles' => [
            'title' => 'Register your vehicles',
            'body' => ':count registered so far.',
        ],
    ],
];
