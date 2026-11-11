<?php

namespace App\Exceptions;

use App\Enums\PermitStatus;

/**
 * The permit cannot move from its current status to the requested one (spec §3).
 */
final class InvalidPermitTransition extends PermitException
{
    public function __construct(
        public readonly PermitStatus $from,
        public readonly PermitStatus $to,
    ) {
        parent::__construct("A permit cannot move from [{$from->value}] to [{$to->value}].");
    }

    public function userMessage(): string
    {
        return __('permits.errors.invalid_transition', [
            'status' => __("permits.status.{$this->from->value}"),
        ]);
    }
}
