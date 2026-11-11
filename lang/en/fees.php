<?php

return [
    'title' => 'Fee schedule',
    'intro' => 'What a permit costs, by permit type, vehicle type and number of days. A change never affects permits that were already approved.',
    'placeholder_notice' => 'The amounts seeded with the system are placeholders. Enter the Authority\'s official fees before going live.',
    'new' => 'Add fee',
    'create_title' => 'Add a fee',
    'edit_title' => 'Change a fee',
    'all_vehicle_types' => 'All vehicle types',
    'all_types_hint' => 'Applies to every vehicle type that has no fee of its own.',
    'days_range' => ':min–:max days',
    'open_ended' => 'until further notice',
    'current' => 'In force',
    'not_current' => 'Not in force today',
    'empty' => 'No fees have been entered.',
    'fields' => [
        'permit_type' => 'Permit type',
        'vehicle_type' => 'Vehicle type',
        'min_days' => 'From (days)',
        'max_days' => 'To (days)',
        'days' => 'Duration',
        'amount' => 'Amount (ETB)',
        'effective_from' => 'In force from',
        'effective_to' => 'In force until',
        'effective' => 'In force',
    ],
    'form' => [
        'days_hint' => 'Both numbers are included: 1 to 30 covers permits of 1 up to 30 days.',
        'effective_to_hint' => 'Leave empty to keep the fee until it is replaced. To retire a fee, give it an end date.',
        'save' => 'Save',
        'cancel' => 'Cancel',
    ],
    'messages' => [
        'saved' => 'Fee saved.',
    ],
    'errors' => [
        'overlap' => 'Another fee already covers some of these days for the same permit type and vehicle type during this period.',
    ],
];
