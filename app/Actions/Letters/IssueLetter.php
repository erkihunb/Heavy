<?php

namespace App\Actions\Letters;

use App\Actions\Permits\IssuePaidPermit;
use App\Domain\Notifications\PermitNotifier;
use App\Enums\PermitStatus;
use App\Exceptions\PermitRuleViolation;
use App\Models\Permit;
use App\Models\RequestLetter;
use App\Models\User;
use App\Notifications\LetterNotification;
use Illuminate\Support\Facades\Gate;

/**
 * Issue every paid vehicle of a request letter: each permit gets its own
 * verification code, QR code and certificate (IssuePaidPermit), and the
 * organization hears once for the letter.
 */
final class IssueLetter
{
    public function __construct(
        private readonly LetterPermits $letters,
        private readonly IssuePaidPermit $issuePaidPermit,
        private readonly PermitNotifier $notifier,
    ) {}

    /**
     * @return list<Permit>
     *
     * @throws PermitRuleViolation when no vehicle of the letter is paid and waiting
     */
    public function handle(RequestLetter $letter, User $issuer, ?string $note = null): array
    {
        $permits = $letter->permits()->where('status', PermitStatus::PaymentConfirmed)->with('vehicles')->get()->all();

        if ($permits === []) {
            throw new PermitRuleViolation('letter_nothing_to_issue');
        }

        $issued = $this->letters->each($permits, function (Permit $permit) use ($issuer, $note): Permit {
            Gate::forUser($issuer)->authorize('issue', $permit);

            return $this->issuePaidPermit->handle($permit, $issuer, $note, notify: false);
        });

        $this->notifier->sendToOrganization($letter->organization_id, new LetterNotification($letter, 'issued', count($issued)));

        return $issued;
    }
}
