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

        return $path === null ? null : route('branding', ['asset' => $asset, 'v' => substr(md5($path), 0, 10)], false);
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
            Storage::disk('local')->delete($path);
        }
    }
}
