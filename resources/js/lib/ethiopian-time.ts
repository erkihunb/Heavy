/**
 * A clock time (HH:MM, 24-hour) as the Ethiopian clock reads it: hours are
 * counted from 06:00 and 18:00, so 11:00 is 5:00 in the day. Mirrors
 * App\Domain\Permits\MovementHours::ethiopian().
 */
export function ethiopianTime(time: string): {
    hour: string;
    period: 'day' | 'evening' | 'night';
} | null {
    const match = /^(\d{1,2}):(\d{2})/.exec(time);

    if (!match) {
        return null;
    }

    const hours = Number(match[1]);
    const ethiopian = (hours + 6) % 12;

    return {
        hour: `${ethiopian === 0 ? 12 : ethiopian}:${match[2]}`,
        period:
            hours >= 6 && hours < 18
                ? 'day'
                : hours >= 18
                  ? 'evening'
                  : 'night',
    };
}
