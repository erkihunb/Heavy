<?php

namespace App\Enums;

/**
 * What a vehicle is on a permit (spec §4.3): the tractor, or a trailer it pulls.
 */
enum VehicleRole: string
{
    case Primary = 'primary';
    case Trailer = 'trailer';
}
