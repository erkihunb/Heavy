<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\UserAvatar;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * A person's photo, for anyone signed in (colleagues see each other in lists
 * and the header). The address carries a version, so the browser may keep it.
 */
class UserAvatarController extends Controller
{
    public function __invoke(User $user): BinaryFileResponse
    {
        $path = UserAvatar::path($user);

        abort_if($path === null, 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => str_ends_with($path, '.webp') ? 'image/webp' : 'image/jpeg',
            'Cache-Control' => 'private, max-age=31536000, immutable',
            'Content-Disposition' => 'inline',
        ]);
    }
}
