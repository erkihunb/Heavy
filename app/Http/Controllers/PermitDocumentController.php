<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Domain\Documents\StoredDocument;
use App\Models\Permit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The permit's PDF in Amharic or English (spec §8). Reached only through a temporary signed URL,
 * and the policy is checked again here: a link that leaked is useless to
 * anyone who may not see the permit. Every download or print is in the
 * audit log, with its language; the page's Print button marks its request
 * with the X-Permit-Print header.
 */
class PermitDocumentController extends Controller
{
    public function __invoke(Request $request, AuditLog $audit, Permit $permit, string $language = 'am'): StreamedResponse
    {
        Gate::authorize('download', $permit);

        $disk = Storage::disk(config('permits.documents_disk'));

        // A missing file, or a placeholder nobody can print, is never handed out.
        abort_unless(StoredDocument::isUsable($permit, $language), 404);

        $user = $request->user();
        $audit->record(AuditEvent::DocumentDownloaded, $permit, $user instanceof User ? $user : null, context: [
            'language' => $language,
            'purpose' => $request->headers->has('X-Permit-Print') ? 'print' : 'download',
        ]);

        return $disk->response(StoredDocument::path($permit, $language), "{$permit->permit_number}-{$language}.pdf", [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
