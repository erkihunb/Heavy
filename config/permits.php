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

    /*
    |--------------------------------------------------------------------------
    | Two-Step Approval
    |--------------------------------------------------------------------------
    |
    | When enabled, an Approver can approve or reject an application only after
    | a Reviewer has recorded a recommendation, and the Approver must be a
    | different person from that Reviewer (spec §14, question 5).
    |
    */

    'two_step_approval' => (bool) env('PERMITS_TWO_STEP_APPROVAL', true),

    /*
    |--------------------------------------------------------------------------
    | Validity Limits
    |--------------------------------------------------------------------------
    |
    | The longest period one permit may cover, counting both the first and the
    | last day (spec §4.6), and how many trailers one permit may list next to
    | its primary vehicle (spec §4.3).
    |
    */

    'max_duration_days' => (int) env('PERMITS_MAX_DURATION_DAYS', 90),

    'max_trailers' => (int) env('PERMITS_MAX_TRAILERS', 2),

    /*
    |--------------------------------------------------------------------------
    | Permit Number Prefix
    |--------------------------------------------------------------------------
    |
    | Permit numbers are "<prefix>-<Ethiopian year>-<six digits>", for example
    | TMA-HV-2018-000123 (spec §4.7).
    |
    */

    'number_prefix' => env('PERMITS_NUMBER_PREFIX', 'TMA-HV'),

];
