<?php

namespace App\Enums;

/**
 * What can be exported (spec §11): permits with their vehicles and fee, or
 * payments with their receipt numbers (finance).
 */
enum ExportDataset: string
{
    case Permits = 'permits';
    case Payments = 'payments';
}
