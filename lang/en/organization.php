<?php

return [
    'fields' => [
        'name_am' => 'Name (Amharic)',
        'name_en' => 'Name (English, optional)',
        'tin' => 'TIN (optional)',
        'tin_hint' => '10 digits',
        'phone' => 'Phone number',
        'phone_hint' => 'For example 0911 234 567',
        'email' => 'Email address',
        'address' => 'Address',
    ],
    'errors' => [
        'phone_invalid' => 'Enter a valid Ethiopian phone number.',
        'tin_format' => 'The TIN must be exactly 10 digits.',
    ],
];
