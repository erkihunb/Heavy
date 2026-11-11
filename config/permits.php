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

    /*
    |--------------------------------------------------------------------------
    | Separation Of Duties
    |--------------------------------------------------------------------------
    |
    | When enabled, the person who approved a permit cannot be the one who
    | verifies its payment (spec §2).
    |
    */

    'separation_of_duties' => (bool) env('PERMITS_SEPARATION_OF_DUTIES', true),

    /*
    |--------------------------------------------------------------------------
    | Private Documents
    |--------------------------------------------------------------------------
    |
    | The filesystem disk payment receipts (and, later, permit documents) are
    | stored on. It must never be publicly reachable: files are served only
    | through controllers that authorise the request (spec §15). The largest
    | receipt file accepted, in kilobytes (spec §11: 5 MB).
    |
    */

    'documents_disk' => env('PERMITS_DOCUMENTS_DISK', 'documents'),

    'receipt_max_kilobytes' => (int) env('PERMITS_RECEIPT_MAX_KB', 5120),

];
