<?php

namespace App\Http\Requests\Concerns;

use App\Domain\Permits\MovementHours;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The hours of the day a permit lets its vehicles move: "any time of day",
 * or — when `any_time` is sent as false — a window from–to in Addis Ababa
 * time (24-hour clock; it may run past midnight). A form that sends no hours
 * asks for any time of day.
 *
 * @mixin FormRequest
 */
trait ValidatesMovementHours
{
    /**
     * @param  list<string>  $condition  rules put first, e.g. ['exclude_unless:apply,true']
     * @return array<string, list<string>>
     */
    protected function movementHoursRules(array $condition = []): array
    {
        $limited = [...$condition, 'exclude_unless:any_time,false'];

        return [
            'any_time' => [...$condition, 'boolean'],
            'move_from' => [...$limited, 'required', 'date_format:H:i'],
            'move_to' => [...$limited, 'required', 'date_format:H:i', 'different:move_from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function movementHoursAttributes(): array
    {
        return [
            'any_time' => __('permits.hours.any_time'),
            'move_from' => __('permits.hours.from'),
            'move_to' => __('permits.hours.to'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function movementHoursMessages(): array
    {
        return ['move_to.different' => __('permits.hours.same_times')];
    }

    protected function movementHours(): ?MovementHours
    {
        return $this->has('any_time') && ! $this->boolean('any_time')
            ? MovementHours::from($this->string('move_from')->toString(), $this->string('move_to')->toString())
            : null;
    }
}
