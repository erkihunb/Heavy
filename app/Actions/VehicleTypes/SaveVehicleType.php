<?php

namespace App\Actions\VehicleTypes;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Support\Facades\DB;

class SaveVehicleType
{
    private const array AUDITED = ['code', 'name_am', 'name_en', 'is_active', 'sort_order'];

    public function __construct(private readonly AuditLog $audit) {}

    /**
     * Add a vehicle type or change one. A type is never deleted: retiring it
     * (inactive) hides it from new vehicles and keeps the existing ones.
     *
     * @param  array{code?: string, name_am: string, name_en: string, is_active: bool, sort_order: int}  $attributes
     */
    public function handle(array $attributes, User $admin, ?VehicleType $type = null): VehicleType
    {
        return DB::transaction(function () use ($attributes, $admin, $type): VehicleType {
            $creating = $type === null;
            $type ??= new VehicleType;
            $old = $creating ? [] : $type->only(self::AUDITED);

            $type->fill($attributes)->save();

            $this->audit->record(
                $creating ? AuditEvent::VehicleTypeCreated : AuditEvent::VehicleTypeUpdated,
                $type,
                $admin,
                old: $old,
                new: $type->only(self::AUDITED),
            );

            return $type;
        });
    }
}
