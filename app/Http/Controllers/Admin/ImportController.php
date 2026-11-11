<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreImportRequest;
use App\Http\Resources\ImportBatchResource;
use App\Jobs\RunLegacyImport;
use App\Models\ImportBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The legacy import runner (spec §11 Admin): upload the CSV exported from the
 * Google Sheet, run it as a dry run or for real (queued), and download the
 * issues report of each run.
 */
class ImportController extends Controller
{
    private const int PER_PAGE = 20;

    public function index(): Response
    {
        Gate::authorize('viewAny', ImportBatch::class);

        $batches = ImportBatch::query()
            ->with('starter:id,name')
            ->latest('id')
            ->paginate(self::PER_PAGE);

        return Inertia::render('admin/imports/index', [
            'batches' => ImportBatchResource::collection($batches),
        ]);
    }

    public function store(StoreImportRequest $request): RedirectResponse
    {
        $file = $request->file('file');
        $path = Storage::disk(config('permits.documents_disk'))->putFileAs('imports/uploads', $file, Str::random(40).'.csv');

        $batch = new ImportBatch([
            'file_name' => mb_substr((string) $file?->getClientOriginalName(), 0, 255),
            'dry_run' => $request->boolean('dry_run'),
        ]);
        $batch->stored_path = $path ?: null;
        $batch->started_by = $request->user()->id;
        $batch->save();

        RunLegacyImport::dispatch($batch->id);

        return to_route('admin.imports.index')->with('success', __('imports.messages.queued', ['id' => $batch->id]));
    }

    public function issues(ImportBatch $import): StreamedResponse
    {
        Gate::authorize('view', $import);

        $disk = Storage::disk(config('permits.documents_disk'));

        abort_unless($disk->exists($import->issuesPath()), 404);

        return $disk->download($import->issuesPath(), "import-{$import->id}-issues.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
