<?php

namespace App\Enums;

/**
 * How a fee was paid. Online payment is not part of v1 (spec §14, question 3).
 */
enum PaymentMethod: string
{
    case BankDeposit = 'bank_deposit';
    case Cash = 'cash';

    /**
     * The methods an applicant can choose when submitting a receipt.
     *
     * @return list<self>
     */
    public static function selectable(): array
    {
        return [self::BankDeposit, self::Cash];
    }
}
