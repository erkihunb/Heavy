<?php

return [
    'title' => 'Dashboard',
    'greeting' => 'Welcome, :name',
    'intro' => 'Today is :date.',
    'kpis' => [
        'vehicles' => 'Registered vehicles',
        'in_force' => 'Permits in force',
        'in_progress' => 'Applications being reviewed',
        'awaiting_payment' => 'Waiting for payment',
        'waiting_review' => 'Waiting for review',
        'payments_to_verify' => 'Receipts to verify',
        'expiring' => 'Ending in 7 days',
        'scans_today' => 'Checks today',
        'issued_month' => 'Issued in :month',
        'revenue_month' => 'Revenue in :month',
    ],
    'attention' => [
        'title' => 'Needs attention',
        'none' => 'Nothing needs your attention.',
        'open' => 'Open',
        'items' => [
            'email_unverified' => [
                'label' => 'Verify your e-mail address',
                'description' => 'Use the link we sent you, or send it again from your settings.',
            ],
            'two_factor_off' => [
                'label' => 'Turn on two-factor authentication',
                'description' => 'A one-time code from your phone keeps your account safe.',
            ],
            'no_organization' => [
                'label' => 'Create your organisation profile',
                'description' => 'You need a profile before you can register vehicles.',
            ],
            'no_vehicles' => [
                'label' => 'Register your vehicles',
                'description' => 'Permits are issued for registered vehicles.',
            ],
            'changes_requested' => [
                'label' => 'Applications sent back for changes',
                'description' => 'Correct them and submit again.',
            ],
            'awaiting_payment' => [
                'label' => 'Approved and waiting for payment',
                'description' => 'Pay the fee and upload the receipt.',
            ],
            'waiting_review' => [
                'label' => 'Applications waiting for review',
                'description' => 'Oldest first in the review queue.',
            ],
            'payments_to_verify' => [
                'label' => 'Receipts waiting for verification',
                'description' => 'Check them against the bank statement.',
            ],
            'expiring' => [
                'label' => 'Permits ending in the next 7 days',
                'description' => 'Vehicles may need a new permit.',
            ],
        ],
    ],
    'distribution' => [
        'title' => 'Permits by status',
        'total' => ':count permits',
        'empty' => 'No permits yet.',
    ],
    'activity' => [
        'title' => 'Recent activity',
        'empty' => 'Nothing has happened yet.',
        'created' => 'Application started',
        'reissued' => 'Reissued with a new QR code',
        'when' => 'When',
        'what' => 'What happened',
        'permit' => 'Permit',
        'organization' => 'Organisation',
        'by' => 'By',
    ],
    'recent' => [
        'title' => 'My recent applications',
        'empty' => 'You have not applied for a permit yet.',
        'all' => 'All applications',
    ],
    'actions' => [
        'apply' => 'Apply for a permit',
        'register_vehicle' => 'Register a vehicle',
        'queue' => 'Review queue',
        'payments' => 'Verify payments',
        'scan' => 'Check a permit',
        'reports' => 'Reports',
    ],
];
