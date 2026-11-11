<?php

return [
    'none' => [
        'name' => 'No role assigned',
        'summary' => 'Your account has no role yet. Contact an administrator.',
    ],
    'applicant' => [
        'name' => 'Applicant',
        'summary' => 'Manage your organisation and vehicles, apply for permits, upload payment receipts and download issued permits.',
    ],
    'reviewer' => [
        'name' => 'Reviewer',
        'summary' => 'Work through the application queue, request changes and recommend approval or rejection.',
    ],
    'approver' => [
        'name' => 'Approver',
        'summary' => 'Approve or reject applications and revoke issued permits.',
    ],
    'cashier' => [
        'name' => 'Cashier',
        'summary' => 'Verify payments and receipt numbers.',
    ],
    'traffic_officer' => [
        'name' => 'Traffic officer',
        'summary' => 'Scan permits on the road and see their status and history.',
    ],
    'admin' => [
        'name' => 'Administrator',
        'summary' => 'Manage users, vehicle types, fee schedules and settings, view the audit log and run imports.',
    ],
];
