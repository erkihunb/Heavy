<?php

namespace App\Http\Controllers\Staff;

use App\Enums\ExportDataset;
use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreExportRequest;
use App\Jobs\GenerateExport;
use App\Models\Export;
use App\Support\EthiopianCalendar;
use App\Support\Paginated;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Spreadsheet exports (spec §11): requested here, built on the queue, and
 * downloaded here by the person who requested them.
 */
class ExportController extends Controller
{
    private const int PER_PAGE = 20;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Export::class);

        $locale = app()->getLocale();
        $timezone = config('localization.display_timezone');

        $exports = Export::query()
            ->whereBelongsTo($request->user())
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->through(fn (Export $export): array => [
                'id' => $export->id,
                'dataset' => $export->dataset->value,
                'format' => $export->format->value,
                'filters' => $export->filters,
                'status' => $export->status->value,
                'rows' => $export->rows,
                'created_at' => EthiopianCalendar::present($export->created_at?->setTimezone($timezone), $locale),
                'created_time' => $export->created_at?->setTimezone($timezone)->format('H:i'),
                'can_download' => $export->status === ExportStatus::Completed && $export->path !== null,
            ]);

        return Inertia::render('staff/exports/index', [
            'exports' => Paginated::present($exports),
            'datasets' => array_map(fn (ExportDataset $dataset): string => $dataset->value, ExportDataset::cases()),
            'formats' => array_map(fn (ExportFormat $format): string => $format->value, ExportFormat::cases()),
            'keepDays' => (int) config('permits.exports.keep_days'),
        ]);
    }

    public function store(StoreExportRequest $request): RedirectResponse
    {
        $export = new Export([
            'dataset' => $request->enum('dataset', ExportDataset::class),
            'format' => $request->enum('format', ExportFormat::class),
            // Where the person asking may see is fixed here, never taken from the form.
            'filters' => array_filter([...$request->filters(), 'scope' => $request->user()->branchScope()->toArray()], fn (mixed $value): bool => $value !== null),
        ]);
        $export->user_id = $request->user()->id;
        $export->save();

        GenerateExport::dispatch($export->id);

        return to_route('staff.exports.index')->with('success', __('reports.exports.messages.queued'));
    }

    public function download(Export $export): StreamedResponse
    {
        Gate::authorize('download', $export);

        $disk = Storage::disk(config('permits.documents_disk'));

        abort_if($export->path === null || ! $disk->exists($export->path), 404);

        return $disk->download($export->path, $export->downloadName(), [
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
