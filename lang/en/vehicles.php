<?php

return [
    'title' => 'Vehicles',
    'intro' => 'The vehicles your organisation can apply for permits for.',
    'register' => 'Register vehicle',
    'register_title' => 'Register a vehicle',
    'edit_title' => 'Edit vehicle',
    'edit' => 'Edit',
    'count' => ':count registered',
    'empty' => [
        'title' => 'No vehicles yet',
        'body' => 'Register the vehicles you need permits for.',
    ],
    'no_results' => 'No vehicles match your search.',
    'fields' => [
        'plate' => 'Plate number',
        'region' => 'Region',
        'code' => 'Code',
        'number' => 'Number',
        'vehicle_type' => 'Vehicle type',
        'owner_name' => 'Vehicle owner',
        'registered' => 'Registered',
    ],
    'plate_hint' => 'Choose the region, then enter the code and the number as written on the plate.',
    'plate_preview' => 'Plate as it will be saved',
    'choose_type' => 'Choose a type',
    'filters' => [
        'search' => 'Search by plate',
        'all_types' => 'All types',
        'apply' => 'Search',
        'clear' => 'Clear',
    ],
    'actions' => [
        'save' => 'Save vehicle',
        'cancel' => 'Cancel',
    ],
    'registered_message' => 'Vehicle registered.',
    'updated_message' => 'Vehicle updated.',
    'errors' => [
        'plate_invalid' => 'Enter the plate as region, code and number, for example አአ-03-A45301.',
        'plate_duplicate_own' => 'This vehicle is already in your register.',
        'plate_duplicate_other' => 'This plate number is already registered by another organisation. Contact the Authority if you believe this is a mistake.',
    ],
];
