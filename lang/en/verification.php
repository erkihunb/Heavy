<?php

return [
    'title' => 'Permit verification',
    'details' => 'Permit details',
    'checked_at' => 'Checked on :date at :time',
    'check_another' => 'Check another permit',
    'results' => [
        'valid' => [
            'title' => 'Valid',
            'body' => 'This permit is in force today.',
        ],
        'not_yet_valid' => [
            'title' => 'Not yet valid',
            'body' => 'This permit has been issued but its first day has not come yet.',
        ],
        'expired' => [
            'title' => 'Expired',
            'body' => 'The period of this permit has ended.',
        ],
        'revoked' => [
            'title' => 'Revoked',
            'body' => 'The Authority has cancelled this permit. It is not valid.',
        ],
        'not_found' => [
            'title' => 'Not found',
            'body' => 'No issued permit matches. The QR code may have been replaced, or the details are wrong.',
        ],
    ],
    'fields' => [
        'permit_number' => 'Permit number',
        'plate' => 'Plate number',
        'permit_type' => 'Permit type',
        'vehicles' => 'Vehicles',
        'organization' => 'Organisation',
        'validity' => 'Valid',
        'rules' => 'Movement hours',
    ],
    'lookup' => [
        'title' => 'Check a permit',
        'intro' => 'Enter the permit number and the plate number of one of its vehicles, exactly as on the permit.',
        'submit' => 'Check',
    ],
    'officer' => [
        'nav' => 'Verify permit',
        'lookup_intro' => 'Scan the QR code with the phone camera, or enter the permit number and a plate.',
        'scan_title' => 'Verification',
        'history' => 'Status history',
        'scans' => 'Recent checks',
        'scan_by_public' => 'Public',
        'channels' => [
            'qr' => 'QR code',
            'lookup' => 'Number and plate',
        ],
        'reports' => 'Officer reports',
        'no_reports' => 'No reports.',
        'reissued' => 'Reissued',
        'report' => [
            'title' => 'Report a violation',
            'hint' => 'Describe what you saw: place, time and what was wrong. The Authority reviews every report.',
            'note' => 'Report',
            'submit' => 'Send report',
            'saved' => 'Report sent.',
        ],
    ],
];
