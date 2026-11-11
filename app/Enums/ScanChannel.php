<?php

namespace App\Enums;

/**
 * How a permit was looked up: by scanning its QR code, or by typing its
 * number and a plate.
 */
enum ScanChannel: string
{
    case Qr = 'qr';
    case Lookup = 'lookup';
}
