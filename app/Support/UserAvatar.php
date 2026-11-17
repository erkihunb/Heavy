<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * A person's photo. The upload is cropped to a centred square, scaled to
 * SIZE pixels and re-encoded as WebP, which drops anything hidden in the
 * file besides the picture. Photos live on the private local disk and are
 * served to signed-in users only (UserAvatarController).
 */
final class UserAvatar
{
    public const string DIRECTORY = 'avatars';

    public const int SIZE = 256;

    /**
     * The address the photo is shown at, or null when there is none. It
     * carries a version, so a new photo is never served from cache.
     */
    public static function url(User $user): ?string
    {
        $path = self::path($user);

        return $path === null ? null : route('users.avatar', ['user' => $user->id, 'v' => substr(md5($path), 0, 10)]);
    }

    public static function path(User $user): ?string
    {
        $path = $user->avatar_path;

        return is_string($path) && $path !== '' && Storage::disk('local')->exists($path) ? $path : null;
    }

    /**
     * Write the photo and return its path on the local disk.
     */
    public static function store(UploadedFile $file): string
    {
        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($image === false) {
            throw new InvalidArgumentException('The file is not a readable image.');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $side = min($width, $height);
        $square = imagecreatetruecolor(self::SIZE, self::SIZE);

        if ($square === false) {
            throw new InvalidArgumentException('The photo could not be prepared.');
        }

        imagecopyresampled(
            $square, $image, 0, 0,
            (int) floor(($width - $side) / 2), (int) floor(($height - $side) / 2),
            self::SIZE, self::SIZE, $side, $side,
        );

        ob_start();
        function_exists('imagewebp') ? imagewebp($square, null, 85) : imagejpeg($square, null, 85);
        $bytes = (string) ob_get_clean();

        $path = self::DIRECTORY.'/'.Str::uuid7().(function_exists('imagewebp') ? '.webp' : '.jpg');
        Storage::disk('local')->put($path, $bytes);

        return $path;
    }

    public static function delete(?string $path): void
    {
        if (is_string($path) && str_starts_with($path, self::DIRECTORY.'/')) {
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * Upload rules: a photo up to 4 MB.
     *
     * @return list<string>
     */
    public static function rules(): array
    {
        return ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:min_width=64,min_height=64,max_width=6000,max_height=6000'];
    }
}
