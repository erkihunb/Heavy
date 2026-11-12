<?php

namespace App\Domain\Permits;

use Carbon\CarbonInterface;

/**
 * The hours of the day a permit lets its vehicles move, in Addis Ababa time,
 * for example 11:00–14:00 (5:00–8:00 on the Ethiopian clock). A window may
 * run past midnight (20:00–06:00). The start is included, the end is not:
 * a vehicle allowed until 14:00 may not move at 14:00.
 */
final readonly class MovementHours
{
    private function __construct(
        /** HH:MM */
        public string $from,
        /** HH:MM */
        public string $to,
    ) {}

    /**
     * From two HH:MM (or HH:MM:SS) times; null when either is missing (any time of day).
     */
    public static function from(?string $from, ?string $to): ?self
    {
        if ($from === null || $to === null || $from === '' || $to === '') {
            return null;
        }

        return new self(substr($from, 0, 5), substr($to, 0, 5));
    }

    /**
     * Whether a vehicle may move at this moment (taken in Addis Ababa time).
     */
    public function allows(CarbonInterface $at): bool
    {
        $now = $at->copy()->setTimezone((string) config('localization.display_timezone'))->format('H:i');

        return $this->from <= $this->to
            ? $now >= $this->from && $now < $this->to
            : $now >= $this->from || $now < $this->to;
    }

    /**
     * A clock time as the Ethiopian clock reads it: hours counted from
     * 06:00 and 18:00, with the part of the day (ከቀኑ 5:00 is 11:00).
     *
     * @return array{hour: string, period: string}
     */
    public static function ethiopian(string $time): array
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));
        $ethiopian = ($hour + 6) % 12;

        return [
            'hour' => sprintf('%d:%02d', $ethiopian === 0 ? 12 : $ethiopian, $minute),
            'period' => match (true) {
                $hour >= 6 && $hour < 18 => 'day',
                $hour >= 18 => 'evening',
                default => 'night',
            },
        ];
    }

    /**
     * The window in words, in both clocks: "ከቀኑ 5:00 – ከቀኑ 8:00 (11:00–14:00)".
     */
    public function label(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $clock = fn (string $time): string => __('permits.hours.periods.'.self::ethiopian($time)['period'], [
            'time' => self::ethiopian($time)['hour'],
        ], $locale);

        return __('permits.hours.window', [
            'from' => $clock($this->from),
            'to' => $clock($this->to),
            'from_24' => $this->from,
            'to_24' => $this->to,
        ], $locale);
    }

    /**
     * @return array{from: string, to: string, label: string}
     */
    public function present(?string $locale = null): array
    {
        return ['from' => $this->from, 'to' => $this->to, 'label' => $this->label($locale)];
    }
}
