<?php

return [
    'title' => 'Organisation profile',
    'intro_create' => 'Tell us about the institution that is applying. You need this before you can register vehicles.',
    'intro_edit' => 'These details appear on applications and issued permits.',
    'read_only' => 'Only the person who created this profile can change it.',
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
    'actions' => [
        'create' => 'Create profile',
        'save' => 'Save changes',
    ],
    'created' => 'Organisation profile created.',
    'updated' => 'Organisation profile updated.',
    'required' => 'Create your organisation profile before registering vehicles.',
    'errors' => [
        'phone_invalid' => 'Enter a valid Ethiopian phone number.',
        'tin_format' => 'The TIN must be exactly 10 digits.',
    ],
];
