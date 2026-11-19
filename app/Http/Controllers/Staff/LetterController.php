<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Letters\DecideLetter;
use App\Actions\Letters\IssueLetter;
use App\Actions\Letters\LetterPermits;
use App\Actions\Letters\RecordLetterPayment;
use App\Actions\Letters\SendLetterForReview;
use App\Actions\Permits\SubmitPermit;
use App\Data\RecommendationData;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Domain\Documents\StoredDocument;
use App\Enums\Permission;
use App\Enums\PermitStatus;
use App\Enums\Recommendation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\RecordLetterPaymentRequest;
use App\Models\Permit;
use App\Models\PermitReview;
use App\Models\PermitReviewRequest;
use App\Models\PermitStatusTransition;
use App\Models\RequestLetter;
use App\Models\User;
use App\Support\EthiopianCalendar;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * A request letter asking for permits for several vehicles: the vehicles
 * and where each stands, and what the office does to them together —
 * recommend, approve some and refuse the rest, take one payment for the
 * approved ones, issue them, and download their certificates. Everything
 * goes through the Actions for one permit, so the same rules apply.
 */
class LetterController extends Controller
{
    public function show(Request $request, RequestLetter $letter): Response
    {
        $first = $this->authorizeView($letter);
        $user = $request->user();
        $letter->load([
            'organization',
            'registrar:id,name',
            'permits.vehicles.vehicleType',
            'permits.applicant',
            'permits.reviews.reviewer:id,name',
            'permits.reviewRequests.reviewer:id,name',
        ]);
        $zone = (string) config('localization.display_timezone');
        $locale = app()->getLocale();

        // Issued vehicles are seen only with `permits.view-issued`.
        $permits = $user instanceof User && ! $user->can(Permission::ViewIssuedPermits->value)
            ? $letter->permits->reject(fn (Permit $permit): bool => in_array($permit->status, Permit::ISSUED_STATUSES, true))->values()
            : $letter->permits;
        $undecided = $permits->filter(fn (Permit $permit): bool => $permit->status->isAwaitingReview());
        $unpaid = $permits->where('status', PermitStatus::AwaitingPayment);
        $paid = $permits->where('status', PermitStatus::PaymentConfirmed);
        $returned = $permits->where('status', PermitStatus::ChangesRequested);
        // The Registrar sends the corrected vehicles again (PermitPolicy::submitFor).
        $resubmittable = $returned->filter(fn (Permit $permit): bool => $user instanceof User
            && $permit->applicant !== null
            && Gate::forUser($user)->inspect('submitFor', [$permit, $permit->applicant])->allowed());
        $issued = $permits->filter(fn (Permit $permit): bool => StoredDocument::isUsable($permit, 'am'));
        $currency = $first->currency;
        $may = fn (string $ability): bool => $user instanceof User && Gate::forUser($user)->inspect($ability, $first)->allowed();
        // The vehicles this person may give an opinion on: those sent to them, or all
        // waiting ones for someone who may decide.
        $reviewable = $undecided
            ->filter(fn (Permit $permit): bool => $user instanceof User && Gate::forUser($user)->inspect('recommend', $permit)->allowed())
            ->map(fn (Permit $permit): string => $permit->id)
            ->values()
            ->all();

        return Inertia::render('staff/letters/show', [
            'letter' => [
                'id' => $letter->id,
                'number' => $letter->letter_number,
                'date' => EthiopianCalendar::present($letter->letter_date, $locale),
                'organization' => $letter->organization->localizedName($locale),
                'registered_by' => $letter->registrar?->name,
                'registered_at' => EthiopianCalendar::present($letter->created_at?->setTimezone($zone), $locale),
                'file_url' => $letter->letter_file_path !== null && Gate::inspect('viewLetter', $first)->allowed() ? route('staff.permits.letter', $first) : null,
                'valid_from' => EthiopianCalendar::present($first->valid_from, $locale),
                'valid_to' => EthiopianCalendar::present($first->valid_to, $locale),
                'permit_type' => $first->permit_type->value,
                'route' => $first->route,
                'reason' => $first->reason,
            ],
            'permits' => $permits->map(fn (Permit $permit): array => LetterPermits::row($permit))->values()->all(),
            'summary' => [
                'total' => $permits->count(),
                'statuses' => $letter->statusCounts(),
                'to_pay' => $unpaid->count(),
                'to_pay_total' => Money::format((int) $unpaid->sum('fee_cents'), $currency),
                'to_pay_money' => [
                    'amount_cents' => (int) $unpaid->sum('fee_cents'),
                    'currency' => $currency,
                    'formatted' => Money::format((int) $unpaid->sum('fee_cents'), $currency),
                ],
                'to_issue' => $paid->count(),
                'returned' => $returned->count(),
                'resubmittable' => $resubmittable->count(),
                // What the person who returned them asked to correct (the latest return).
                'return_note' => $returned->isEmpty() ? null : PermitStatusTransition::query()
                    ->whereIn('permit_id', $returned->pluck('id')->all())
                    ->where('to_status', PermitStatus::ChangesRequested->value)
                    ->latest('id')
                    ->value('note'),
                'issued' => $issued->count(),
            ],
            'can' => [
                'recommend' => $reviewable !== [],
                'decide' => $undecided->isNotEmpty() && $may('approve'),
                'return' => $undecided->isNotEmpty() && $may('requestChanges'),
                'resubmit' => $resubmittable->isNotEmpty(),
                'sendForReview' => $undecided->isNotEmpty() && $may('sendForReview'),
                'recordPayment' => $unpaid->isNotEmpty() && $user?->can(Permission::VerifyPayments->value) === true && $first->isInBranchOf($user),
                'issue' => $paid->isNotEmpty() && $user?->can(Permission::IssuePermits->value) === true && $first->isInBranchOf($user),
                'download' => $issued->contains(fn (Permit $permit): bool => Gate::inspect('download', $permit)->allowed()),
            ],
            'reviewable' => $reviewable,
            'review' => $this->reviewRound($undecided),
            'reviewers' => $user instanceof User && $undecided->isNotEmpty() && $may('sendForReview')
                ? SendLetterForReview::candidates($first, $user)
                    ->map(fn (User $reviewer): array => ['id' => $reviewer->id, 'name' => $reviewer->name])
                    ->all()
                : [],
            'receiptMaxKilobytes' => (int) config('permits.receipt_max_kilobytes'),
            'recommendations' => array_map(fn (Recommendation $recommendation): string => $recommendation->value, Recommendation::cases()),
            'urls' => [
                'sendForReview' => route('letters.send-for-review', $letter),
                'recommend' => route('letters.recommend', $letter),
                'approve' => route('letters.approve', $letter),
                'reject' => route('letters.reject', $letter),
                'return' => route('letters.return', $letter),
                'resubmit' => route('letters.resubmit', $letter),
                'payment' => route('letters.payment', $letter),
                'issue' => route('letters.issue', $letter),
                'documentsAm' => route('letters.documents', [$letter, 'am']),
                'documentsEn' => route('letters.documents', [$letter, 'en']),
            ],
        ]);
    }

    public function sendForReview(Request $request, RequestLetter $letter, SendLetterForReview $send): RedirectResponse
    {
        $this->authorizeView($letter);
        $validated = $request->validate([
            ...$this->choiceRules(),
            'reviewers' => ['required', 'array', 'min:1', 'max:20'],
            'reviewers.*' => ['required', 'integer'],
        ]);

        $done = $send->handle($letter, $validated['permits'], $request->user(), $validated['reviewers']);

        return back()->with('success', __('letters.messages.sent_for_review', ['count' => count($done)]));
    }

    public function recommend(Request $request, RequestLetter $letter, DecideLetter $decide): RedirectResponse
    {
        $this->authorizeView($letter);
        $validated = $request->validate([
            ...$this->choiceRules(),
            'recommendation' => ['required', Rule::enum(Recommendation::class)],
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $done = $decide->recommend($letter, $validated['permits'], $request->user(), new RecommendationData(
            Recommendation::from($validated['recommendation']),
            trim($validated['note']),
        ));

        return back()->with('success', __('letters.messages.recommended', ['count' => count($done)]));
    }

    public function approve(Request $request, RequestLetter $letter, DecideLetter $decide): RedirectResponse
    {
        $this->authorizeView($letter);
        $validated = $request->validate([...$this->choiceRules(), 'note' => ['nullable', 'string', 'max:2000']]);

        $done = $decide->approve($letter, $validated['permits'], $request->user(), $validated['note'] ?? null);

        return back()->with('success', __('letters.messages.approved', ['count' => count($done)]));
    }

    public function reject(Request $request, RequestLetter $letter, DecideLetter $decide): RedirectResponse
    {
        $this->authorizeView($letter);
        $validated = $request->validate([...$this->choiceRules(), 'note' => ['required', 'string', 'max:2000']]);

        $done = $decide->reject($letter, $validated['permits'], $request->user(), trim($validated['note']));

        return back()->with('success', __('letters.messages.rejected', ['count' => count($done)]));
    }

    public function requestChanges(Request $request, RequestLetter $letter, DecideLetter $decide): RedirectResponse
    {
        $this->authorizeView($letter);
        $validated = $request->validate([...$this->choiceRules(), 'note' => ['required', 'string', 'max:2000']]);

        $done = $decide->requestChanges($letter, $validated['permits'], $request->user(), trim($validated['note']));

        return back()->with('success', __('letters.messages.returned', ['count' => count($done)]));
    }

    /**
     * The Registrar sends every corrected vehicle of the letter again, at once.
     */
    public function resubmit(Request $request, RequestLetter $letter, SubmitPermit $submit): RedirectResponse
    {
        $this->authorizeView($letter);
        $user = $request->user();

        $permits = $letter->permits()->with('applicant')->where('status', PermitStatus::ChangesRequested->value)->get()
            ->filter(fn (Permit $permit): bool => $permit->applicant !== null
                && Gate::forUser($user)->inspect('submitFor', [$permit, $permit->applicant])->allowed());

        abort_if($permits->isEmpty(), 403);

        $done = DB::transaction(fn (): int => $permits->each(fn (Permit $permit) => $submit->handle($permit, $user))->count());

        return back()->with('success', __('letters.messages.resubmitted', ['count' => $done]));
    }

    public function payment(RecordLetterPaymentRequest $request, RequestLetter $letter, RecordLetterPayment $record): RedirectResponse
    {
        $done = $record->handle($letter, $request->user(), $request->toData(), $request->note());

        return back()->with('success', __('letters.messages.paid', ['count' => count($done)]));
    }

    public function issue(Request $request, RequestLetter $letter, IssueLetter $issue): RedirectResponse
    {
        $this->authorizeView($letter);
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        $done = $issue->handle($letter, $request->user(), $validated['note'] ?? null);

        return back()->with('success', __('letters.messages.issued', ['count' => count($done)]));
    }

    /**
     * Every issued certificate of the letter in one ZIP file, named by permit
     * number, in one language. Each certificate keeps its own QR code; each
     * is in the audit log as downloaded, as when downloaded on its own.
     */
    public function documents(Request $request, RequestLetter $letter, AuditLog $audit, string $language): BinaryFileResponse
    {
        $this->authorizeView($letter);
        abort_unless(array_key_exists($language, StoredDocument::LANGUAGES), 404);

        $user = $request->user();
        $disk = Storage::disk(config('permits.documents_disk'));
        $permits = $letter->permits()->get()->filter(
            fn (Permit $permit): bool => StoredDocument::isUsable($permit, $language) && Gate::inspect('download', $permit)->allowed(),
        );

        abort_if($permits->isEmpty(), 404);

        $file = tempnam(sys_get_temp_dir(), 'letter');
        $zip = new ZipArchive;
        abort_unless($file !== false && $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 500);

        foreach ($permits as $permit) {
            $zip->addFromString("{$permit->permit_number}-{$language}.pdf", (string) $disk->get((string) StoredDocument::path($permit, $language)));
            $audit->record(AuditEvent::DocumentDownloaded, $permit, $user instanceof User ? $user : null, context: [
                'language' => $language,
                'purpose' => 'download',
                'request_letter' => $letter->id,
            ]);
        }

        $zip->close();
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $letter->letter_number) ?: 'letter';

        return response()->download($file, "{$name}-{$language}.zip", [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend();
    }

    /**
     * The current round of opinions on the vehicles still waiting for a
     * decision: who was asked, and what each Reviewer said. One Reviewer's
     * same opinion on several vehicles is one line, with how many vehicles.
     *
     * @param  Collection<int, Permit>  $undecided
     * @return array{sent: list<array{name: string, answered: bool}>, opinions: list<array{reviewer: string, recommendation: string, note: string, vehicles: int, at: string|null}>}
     */
    private function reviewRound(Collection $undecided): array
    {
        $zone = (string) config('localization.display_timezone');
        $locale = app()->getLocale();
        $opinions = [];
        $sent = [];
        $answered = [];

        foreach ($undecided as $permit) {
            foreach ($permit->reviews->filter(fn (PermitReview $review): bool => $permit->isCurrentReview($review)) as $review) {
                $answered[$review->reviewer_id] = true;
                $key = $review->reviewer_id.'|'.$review->recommendation->value.'|'.$review->assessment;
                $opinions[$key] ??= [
                    'reviewer' => (string) $review->reviewer?->name,
                    'recommendation' => $review->recommendation->value,
                    'note' => $review->assessment,
                    'vehicles' => 0,
                    'at' => EthiopianCalendar::present($review->created_at->setTimezone($zone), $locale)['ethiopian_label'] ?? null,
                ];
                $opinions[$key]['vehicles']++;
            }

            foreach ($permit->reviewRequests as $request) {
                /** @var PermitReviewRequest $request */
                if ($permit->submitted_at === null || $request->created_at->greaterThanOrEqualTo($permit->submitted_at)) {
                    $sent[$request->reviewer_id] = (string) $request->reviewer?->name;
                }
            }
        }

        return [
            'sent' => array_map(
                fn (int $id, string $name): array => ['name' => $name, 'answered' => isset($answered[$id])],
                array_keys($sent),
                $sent,
            ),
            'opinions' => array_values($opinions),
        ];
    }

    /**
     * Whoever may see the letter's permits (or the Registrar of its branch) may see the letter.
     */
    private function authorizeView(RequestLetter $letter): Permit
    {
        /** @var Permit|null $first */
        $first = $letter->permits()->first();

        abort_if($first === null, 404);
        Gate::authorize('viewLetter', $first);

        return $first;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function choiceRules(): array
    {
        return [
            'permits' => ['required', 'array', 'min:1'],
            'permits.*' => ['required', 'string', 'ulid'],
        ];
    }
}
