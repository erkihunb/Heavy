<?php

namespace App\Http\Controllers\Applicant;

use App\Domain\Permits\FeeCalculator;
use App\Domain\Permits\ValidityRules;
use App\Enums\PermitType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Applicant\FeeEstimateRequest;
use App\Models\Vehicle;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/**
 * The live fee estimate shown in the application form: what the permit would
 * cost by today's schedule. The fee actually owed is fixed at approval.
 */
class FeeEstimateController extends Controller
{
    public function __invoke(FeeEstimateRequest $request, FeeCalculator $fees): JsonResponse
    {
        /** @var array{permit_type: string, primary_vehicle_id: string, valid_from: string, valid_to: string} $validated */
        $validated = $request->validated();

        $vehicle = Vehicle::query()->findOrFail($validated['primary_vehicle_id']);
        $from = CarbonImmutable::createFromFormat('!Y-m-d', $validated['valid_from'], 'UTC');
        $to = CarbonImmutable::createFromFormat('!Y-m-d', $validated['valid_to'], 'UTC');
        $days = (int) $from->diffInDays($to) + 1;

        $amount = $fees->amountFor(PermitType::from($validated['permit_type']), $vehicle->vehicle_type_id, $days, ValidityRules::today());

        return response()->json([
            'days' => $days,
            'fee' => $amount === null ? null : Money::present($amount),
        ]);
    }
}
