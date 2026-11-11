<?php

namespace App\Http\Controllers;

use App\Models\Permit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The permit's PDF (spec §8). Reached only through a temporary signed URL,
 * and the policy is checked again here: a link that leaked is useless to
 * anyone who may not see the permit.
 */
class PermitDocumentController extends Controller
{
    public function __invoke(Permit $permit): StreamedResponse
    {
        Gate::authorize('download', $permit);

        $disk = Storage::disk(config('permits.documents_disk'));

        abort_if($permit->document_path === null || ! $disk->exists($permit->document_path), 404);

        return $disk->response($permit->document_path, "{$permit->permit_number}.pdf", [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
