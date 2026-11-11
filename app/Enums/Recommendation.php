<?php

namespace App\Enums;

/**
 * What a Reviewer advises the Approver to do with an application under review.
 */
enum Recommendation: string
{
    case Approve = 'approve';
    case Reject = 'reject';
}
