<?php

namespace App\Http\Controllers;

use App\Domain\Settings\Branding;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the uploaded logo and browser-tab icon to everyone (they appear on
 * the public pages too). The address carries a version, so the file may be
 * cached for good; with none uploaded the answer is 404.
 */
class BrandingController extends Controller
{
    public function __invoke(string $asset): BinaryFileResponse
    {
        // The logo goes out as its small web copy; the upload is kept for the permit PDF.
        $path = Branding::displayPath($asset);

        abort_if($path === null, 404);

        return response()->file($path, [
            'Content-Type' => Branding::contentType($path),
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Content-Disposition' => 'inline',
        ]);
    }
}
