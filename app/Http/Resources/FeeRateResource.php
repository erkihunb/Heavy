<?php

namespace App\Http\Resources;

use App\Domain\Permits\FeeCalculator;
use App\Support\EthiopianCalendar;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A fee rate a permit can be registered at (FeeCalculator::rates()): the
 * schedule line's id, its band of days, the days a permit at the rate runs,
 * its fee and — once the form has a start date — the last day the permit
 * would be valid, worked out here so the form never computes a date itself.
 *
 * @property array{id: int, min_days: int, max_days: int, days: int, amount_cents: int} $resource
 *
 * @see FeeCalculator::rates()
 */
class FeeRateResource extends JsonResource
{
    private ?CarbonImmutable $validFrom = null;

    /**
     * The rates as the form receives them.
     *
     * @param  list<array{id: int, min_days: int, max_days: int, days: int, amount_cents: int}>  $rates
     * @return list<array<string, mixed>>
     */
    public static function list(array $rates, ?CarbonImmutable $validFrom, Request $request): array
    {
        return array_map(function (array $rate) use ($validFrom, $request): array {
            $resource = new self($rate);
            $resource->validFrom = $validFrom;

            return $resource->resolve($request);
        }, $rates);
    }

    /**
     * @return array{id: int, days: int, min_days: int, max_days: int, fee: array{amount_cents: int, currency: string, formatted: string}, valid_to: array{gregorian: string, ethiopian: string, ethiopian_label: string}|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'days' => $this->resource['days'],
            'min_days' => $this->resource['min_days'],
            'max_days' => $this->resource['max_days'],
            'fee' => Money::present($this->resource['amount_cents']),
            // Both ends count: a permit at a 30-day rate ends 29 days after it starts.
            'valid_to' => EthiopianCalendar::present(
                $this->validFrom?->addDays($this->resource['days'] - 1),
                app()->getLocale(),
            ),
        ];
    }
}
