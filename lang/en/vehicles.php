<?php

return [
    'title' => 'Vehicles',
    'register' => 'Register vehicle',
    'register_title' => 'Register a vehicle',
    'fields' => [
        'plate' => 'Plate number',
        'region' => 'Region',
        'code' => 'Code',
        'number' => 'Number',
        'vehicle_type' => 'Vehicle type',
        'owner_name' => 'Vehicle owner',
    ],
    'plate_hint' => 'Choose the region, then enter the code and the number as written on the plate.',
    'plate_preview' => 'Plate as it will be saved',
    'choose_type' => 'Choose a type',
    'actions' => [
        'save' => 'Save vehicle',
        'cancel' => 'Cancel',
    ],
    'registered_message' => 'Vehicle registered.',
    'errors' => [
        'plate_invalid' => 'Enter the plate as region, code and number, for example አአ-03-A45301.',
        'plate_duplicate_own' => 'This vehicle is already in your register.',
        'plate_duplicate_other' => 'This plate number is already registered by another organisation. Contact the Authority if you believe this is a mistake.',
    ],
];
