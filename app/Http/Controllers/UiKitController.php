<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The UI kit demo page: sample rows for the data table (filtered and paged on
 * the server, as a real list is) and a sample form whose validation errors
 * show under their fields. Development only (routes/ui-kit.php); nothing is
 * stored.
 */
class UiKitController extends Controller
{
    private const array STATUSES = ['draft', 'submitted', 'under_review', 'awaiting_payment', 'issued', 'rejected', 'expired'];

    private const array TYPES = ['Truck', 'Trailer', 'Crane', 'Tanker', 'Low-bed'];

    private const int PER_PAGE = 6;

    public function show(Request $request): Response
    {
        $search = is_string($request->query('search')) ? trim($request->query('search')) : '';
        $status = in_array($request->query('status'), self::STATUSES, true) ? $request->query('status') : '';

        $rows = collect($this->sampleRows())
            ->when($search !== '', fn ($rows) => $rows->filter(
                fn (array $row): bool => str_contains(mb_strtolower($row['plate'].' '.$row['owner']), mb_strtolower($search)),
            ))
            ->when($status !== '', fn ($rows) => $rows->where('status', $status))
            ->values();

        $page = max(1, (int) $request->query('page', 1));
        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, self::PER_PAGE)->values(),
            $rows->count(),
            self::PER_PAGE,
            $page,
        );

        return Inertia::render('ui-kit', [
            'rows' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'filters' => ['search' => $search, 'status' => $status],
            'statuses' => self::STATUSES,
            'types' => self::TYPES,
            'tab' => in_array($request->query('tab'), ['overview', 'forms', 'data'], true) ? $request->query('tab') : 'overview',
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'regex:/^\+?[0-9 ]{9,15}$/'],
            'type' => ['required', Rule::in(self::TYPES)],
            'axles' => ['required', 'integer', 'min:2', 'max:12'],
            'notes' => ['nullable', 'string', 'max:500'],
            'document' => ['nullable', 'file', 'mimes:pdf,png,jpg', 'max:2048'],
        ], attributes: collect(['name', 'email', 'phone', 'type', 'axles', 'notes', 'document'])
            ->mapWithKeys(fn (string $field): array => [$field => __("shell.demo.form.{$field}")])
            ->all());

        return back()->with('success', __('shell.demo.saved'));
    }

    /**
     * @return list<array{id: int, plate: string, owner: string, type: string, status: string, amount: int, submitted_at: string}>
     */
    private function sampleRows(): array
    {
        $owners = ['Abebe Kebede', 'Tigist Haile', 'Dawit Alemu', 'Selam Tesfaye', 'Mekdes Girma', 'Yonas Bekele', 'Hirut Desta', 'Samuel Getachew'];

        return array_map(fn (int $i): array => [
            'id' => $i,
            'plate' => sprintf('AA 3-%05d', 12000 + $i * 397),
            'owner' => $owners[$i % count($owners)],
            'type' => self::TYPES[$i % count(self::TYPES)],
            'status' => self::STATUSES[$i % count(self::STATUSES)],
            'amount' => 1500 + ($i * 275) % 4000,
            'submitted_at' => Carbon::create(2026, 11, 1)->addDays($i)->toDateString(),
        ], range(1, 23));
    }
}
