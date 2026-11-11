<?php

namespace App\Exceptions;

/**
 * A business rule other than the state machine or the overlap rule refused an
 * Action. FormRequests check the same rules first, so this is normally only
 * reached by a race or by calling an Action directly.
 */
final class PermitRuleViolation extends PermitException
{
    /**
     * @param  string  $rule  key under `permits.errors` in the language files
     * @param  array<string, string|int>  $replacements
     */
    public function __construct(
        public readonly string $rule,
        public readonly array $replacements = [],
    ) {
        parent::__construct("Permit rule [{$rule}] was violated.");
    }

    public function userMessage(): string
    {
        return __("permits.errors.{$this->rule}", $this->replacements);
    }
}
