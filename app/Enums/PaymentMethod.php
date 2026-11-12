<?php

namespace App\Enums;

/**
 * How a fee was paid. Online payment is not part of v1 (spec §14, question 3).
 */
enum PaymentMethod: string
{
    case BankDeposit = 'bank_deposit';
    case BankTransfer = 'bank_transfer';
    // Mobile wallets such as telebirr or CBE Birr.
    case MobileMoney = 'mobile_money';
    case Cash = 'cash';
    case Other = 'other';

    /**
     * The methods an applicant can choose when submitting a receipt.
     *
     * @return list<self>
     */
    public static function selectable(): array
    {
        return [self::BankDeposit, self::BankTransfer, self::MobileMoney, self::Cash, self::Other];
    }
}
