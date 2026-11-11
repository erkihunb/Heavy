<?php

namespace App\Enums;

/**
 * Where one submitted payment stands. A rejected payment stays on record; the
 * applicant submits a new one.
 */
enum PaymentStatus: string
{
    case Submitted = 'submitted';
    case Verified = 'verified';
    case Rejected = 'rejected';
}
