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
        'summary' => 'Examine requests and decide: approve, reject or send back for changes.',
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
    'registrar' => [
        'name' => 'Registrar',
        'summary' => 'Register applicants and their organisation when they come to the office in person.',
    ],
    'issuer' => [
        'name' => 'Issuer',
        'summary' => 'Check paid applications, issue the permit and print it.',
    ],
    'supervisor' => [
        'name' => 'Head-office supervisor',
        'summary' => 'See every branch\'s applications, decisions, permits and revenue, and comment on applications.',
    ],
    'admin' => [
        'name' => 'Administrator',
        'summary' => 'Manage users, vehicle types, fee schedules and settings, register applicants and view the audit log.',
    ],
];
