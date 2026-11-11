<?php

namespace App\Enums;

/**
 * The two kinds of permit the Authority issues (spec §1): ሙሉ ቀን and ይለፍ.
 */
enum PermitType: string
{
    case FullDay = 'full_day';
    case Pass = 'pass';
}
