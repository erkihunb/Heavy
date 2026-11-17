<?php

namespace App\Enums;

/**
 * What a Reviewer advises the Approver to do with an application under review.
 */
enum Recommendation: string
{
    case Approve = 'approve';
    // The applicant should correct or complete the application first.
    case Changes = 'changes';
    case Reject = 'reject';
}
