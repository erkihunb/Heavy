<?php

namespace App\Http\Resources;

use App\Models\Permit;
use App\Models\PermitStatusTransition;
use App\Support\EthiopianCalendar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One step in the history of a permit. Applicants see what happened, when and
 * the note that was left for them — never which member of staff did it.
 *
 * @mixin PermitStatusTransition
 */
class PermitTransitionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $at = $this->created_at->setTimezone(config('localization.display_timezone'));

        return [
            'from' => $this->from_status?->value,
            'to' => $this->to_status->value,
            'note' => $this->note,
            'at' => EthiopianCalendar::present($at, app()->getLocale()),
            'time' => $at->format('H:i'),

            ...($request->user()?->can('viewQueue', Permit::class) ? [
                'actor' => $this->whenLoaded('actor', fn (): ?string => $this->actor?->name),
            ] : []),
        ];
    }
}
