<?php

/*
 * E-mail and SMS wording. Placeholders: :number (permit number), :type,
 * :from / :to (Ethiopian dates), :fee. SMS texts stay short and never carry
 * a fee, a receipt number, a name or a link with a token.
 */

return [
    'greeting' => 'Hello,',
    'note' => 'Note from the Authority: :note',
    'salutation' => ':authority',

    'approved' => [
        'subject' => 'Permit :number approved — please pay the fee',
        'body' => 'Your application for a :type permit from :from to :to has been approved as permit :number. Pay :fee at the bank or the Authority\'s cashier, then bring the receipt to the office where you applied.',
        'sms' => 'AACRA: permit :number approved. Pay the fee and bring the receipt to the office.',
    ],
    'issued' => [
        'subject' => 'Permit :number issued',
        'body' => 'Your payment has been verified and permit :number is issued, valid from :from to :to. Collect the printed permit at the office; its QR code shows its status when scanned.',
        'sms' => 'AACRA: permit :number issued, valid :from – :to. Collect it at the office.',
    ],
    'revoked' => [
        'subject' => 'Permit :number revoked',
        'body' => 'Permit :number has been revoked by the Authority and is no longer valid. Its QR code now shows REVOKED.',
        'sms' => 'AACRA: permit :number has been REVOKED and is no longer valid.',
    ],
    'expiring_soon' => [
        'subject' => 'Permit :number expires on :to',
        'body' => 'Permit :number is valid until :to. If the vehicle still needs to move during restricted hours after that day, apply for a new permit at the office in time.',
        'sms' => 'AACRA: permit :number expires on :to. Apply in time if you need a new one.',
    ],
    'changes_requested' => [
        'subject' => 'Your permit application needs changes',
        'body' => 'The Authority has sent your application for a :type permit from :from to :to back for changes. Contact the office to correct it.',
        'sms' => 'AACRA: your permit application needs changes. Please contact the office.',
    ],
    'rejected' => [
        'subject' => 'Your permit application was rejected',
        'body' => 'The Authority has rejected your application for a :type permit from :from to :to.',
        'sms' => 'AACRA: your permit application was rejected. Contact the office for the reason.',
    ],
    'payment_rejected' => [
        'subject' => 'Receipt for permit :number not accepted',
        'body' => 'The cashier could not accept the receipt you submitted for permit :number. Bring a correct receipt for :fee to the office.',
        'sms' => 'AACRA: the receipt for permit :number was not accepted. Please contact the office.',
    ],
];
