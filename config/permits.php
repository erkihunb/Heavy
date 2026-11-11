<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mandatory Two-Factor Authentication For Staff
    |--------------------------------------------------------------------------
    |
    | When enabled, any user holding a staff role (reviewer, approver, cashier,
    | traffic officer, admin) is confined to the two-factor setup screens until
    | they have confirmed two-factor authentication. Disable only for local
    | development; it must stay on in production (spec §2, §15).
    |
    */

    'staff_two_factor_required' => (bool) env('PERMITS_STAFF_TWO_FACTOR_REQUIRED', true),

];
