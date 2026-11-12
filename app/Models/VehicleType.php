<?php

namespace App\Models;

use Database\Factories\VehicleTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $code
 * @property string $name_am
 * @property string $name_en
 * @property bool $is_active
 * @property int $sort_order
 */
class VehicleType extends Model
{
    /** @use HasFactory<VehicleTypeFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name_am',
        'name_en',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public const string CACHE_KEY = 'vehicle-types.v1';

    /** The type whose vehicles are described in words (ሌላ — ይግለጹ). */
    public const string OTHER = 'other';

    /** The type a trailer plate on the office form is registered with. */
    public const string TRAILER = 'trailer';

    protected static function booted(): void
    {
        // The cached list (spec §16) is dropped whenever a type changes.
        static::saved(fn (): bool => Cache::forget(self::CACHE_KEY));
        static::deleted(fn (): bool => Cache::forget(self::CACHE_KEY));
    }

    /**
     * Every vehicle type, in display order, from the cache.
     *
     * @return Collection<int, self>
     */
    public static function cachedOrdered(): Collection
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = Cache::rememberForever(self::CACHE_KEY, fn (): array => self::query()
            ->ordered()
            ->get()
            ->map(fn (self $type): array => $type->getAttributes())
            ->all());

        return self::hydrate($rows);
    }

    /**
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    public function localizedName(string $locale): string
    {
        return $locale === 'en' ? $this->name_en : $this->name_am;
    }
}
