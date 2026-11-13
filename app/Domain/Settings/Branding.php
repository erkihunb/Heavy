<?php

namespace App\Domain\Settings;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The logo and browser-tab icon an Admin uploads (System settings → General,
 * as in EUISIS), plus the support contacts and sign-in message shown to
 * everyone. Files live on the private local disk and are served by the
 * `branding` route, so they work without a storage link and under the
 * Content-Security-Policy ('self').
 *
 * Raster uploads are decoded and written again (PNG, or WebP for photos),
 * which drops anything hidden in the file besides the picture. The stored
 * name is generated, never the uploader's.
 */
final class Branding
{
    public const array ASSETS = ['logo', 'favicon'];

    private const string DIRECTORY = 'branding';

    /** The largest the web pages draw the logo is 48 CSS pixels; three times that, rounded up. */
    private const int DISPLAY_SIZE = 160;

    private const array TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
    ];

    /**
     * The stored file of an asset, when one is set and still on the disk.
     */
    public static function path(string $asset): ?string
    {
        $path = config("permits.branding.{$asset}");

        return is_string($path) && $path !== '' && Storage::disk('local')->exists($path) ? $path : null;
    }

    /**
     * The address of an asset; the file name in the query string makes a new
     * upload a new address, so browsers may cache each one for good.
     */
    public static function url(string $asset): ?string
    {
        $path = self::path($asset);

        // "display": the logo's address changed when it began to be served as a
        // smaller WebP copy, so browsers that cached the old file fetch the new one.
        $version = $asset === 'logo' ? "{$path}|display" : $path;

        return $path === null ? null : route('branding', ['asset' => $asset, 'v' => substr(md5($version), 0, 10)], false);
    }

    public static function absolutePath(string $asset): ?string
    {
        $path = self::path($asset);

        return $path === null ? null : Storage::disk('local')->path($path);
    }

    public static function contentType(string $path): string
    {
        return self::TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    }

    /**
     * The logo as the web pages show it: at most DISPLAY_SIZE pixels on its
     * longer side (three times the largest place it appears, for sharp phone
     * screens) and as WebP, made once from the upload and kept beside it. The
     * upload itself stays as it is for the permit PDF. When no smaller copy
     * can be made (an icon file, no image support, or the copy would not be
     * smaller), the upload is served.
     */
    public static function displayPath(string $asset): ?string
    {
        $path = self::path($asset);

        if ($path === null || $asset !== 'logo') {
            return $path === null ? null : Storage::disk('local')->path($path);
        }

        $disk = Storage::disk('local');

        foreach (self::displayCopies($path) as $copy) {
            if ($disk->exists($copy)) {
                return $disk->path($copy);
            }
        }

        $original = (string) $disk->get($path);
        $bytes = self::displayBytes($original);

        // Not worth a copy: keep the upload's bytes under the copy's name, so
        // making one is not tried again on every request.
        $copy = $bytes !== null && strlen($bytes) < strlen($original)
            ? self::displayStem($path).'.webp'
            : self::displayStem($path).'.'.strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $disk->put($copy, $bytes !== null && strlen($bytes) < strlen($original) ? $bytes : $original);

        return $disk->path($copy);
    }

    private static function displayStem(string $path): string
    {
        return (string) preg_replace('/\.[a-z0-9]+$/i', '', $path).'.display';
    }

    /**
     * Where the display copy of an upload may be stored.
     *
     * @return list<string>
     */
    private static function displayCopies(string $path): array
    {
        $stem = self::displayStem($path);

        return array_values(array_unique([
            "{$stem}.webp",
            "{$stem}.".strtolower(pathinfo($path, PATHINFO_EXTENSION)),
        ]));
    }

    /**
     * The picture scaled down to fit DISPLAY_SIZE (never up), with its
     * transparency, as WebP; null when it cannot be decoded or written.
     */
    private static function displayBytes(string $original): ?string
    {
        if (! function_exists('imagewebp') || $original === '') {
            return null;
        }

        $image = @imagecreatefromstring($original);

        if ($image === false) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::DISPLAY_SIZE / max($width, $height, 1));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $copy = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($copy === false) {
            return null;
        }

        imagealphablending($copy, false);
        imagesavealpha($copy, true);
        imagefill($copy, 0, 0, (int) imagecolorallocatealpha($copy, 0, 0, 0, 127));
        imagecopyresampled($copy, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        $written = imagewebp($copy, null, 90);
        $bytes = (string) ob_get_clean();

        return $written && $bytes !== '' ? $bytes : null;
    }

    /**
     * The logo for the permit document: the file named in .env when there is
     * one, otherwise the uploaded logo.
     */
    public static function documentLogoPath(): ?string
    {
        $configured = config('permits.document.logo_path');

        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        return self::absolutePath('logo');
    }

    /**
     * The browser-tab icon tag for the Blade layouts.
     */
    public static function faviconTag(): string
    {
        $url = self::url('favicon');

        return $url === null
            ? '<link rel="icon" href="/favicon.svg" type="image/svg+xml">'
            : sprintf('<link rel="icon" href="%s" type="%s">', e($url), e(self::contentType((string) self::path('favicon'))));
    }

    /**
     * What every page needs (shared with the frontend).
     *
     * @return array{logo: ?string, favicon: ?string, supportEmail: ?string, supportPhone: ?string, loginMessage: ?string}
     */
    public static function shared(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $message = config("permits.branding.login_message.{$locale}");
        $email = config('permits.branding.support_email');
        $phone = config('permits.branding.support_phone');

        return [
            'logo' => self::url('logo'),
            'favicon' => self::url('favicon'),
            'supportEmail' => is_string($email) && $email !== '' ? $email : null,
            'supportPhone' => is_string($phone) && $phone !== '' ? $phone : null,
            'loginMessage' => is_string($message) && trim($message) !== '' ? trim($message) : null,
        ];
    }

    /**
     * Write an uploaded file and return its path on the local disk. A raster
     * file is re-encoded; an icon (.ico) is kept as it is.
     */
    public static function store(string $asset, UploadedFile $file): string
    {
        $extension = strtolower((string) $file->guessExtension());
        $name = self::DIRECTORY.'/'.$asset.'-'.Str::uuid7();

        if ($extension === 'ico') {
            Storage::disk('local')->put($name.'.ico', (string) file_get_contents($file->getRealPath()));

            return $name.'.ico';
        }

        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($image === false) {
            throw new \InvalidArgumentException('The file is not a readable image.');
        }

        // Photos stay small as WebP; everything else becomes a PNG with its transparency.
        $photo = in_array($extension, ['jpg', 'jpeg'], true);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        $photo ? imagewebp($image, null, 85) : imagepng($image, null, 9);
        $bytes = (string) ob_get_clean();

        $path = $name.($photo ? '.webp' : '.png');
        Storage::disk('local')->put($path, $bytes);

        return $path;
    }

    public static function delete(?string $path): void
    {
        if (is_string($path) && str_starts_with($path, self::DIRECTORY.'/')) {
            Storage::disk('local')->delete([$path, ...self::displayCopies($path)]);
        }
    }
}
