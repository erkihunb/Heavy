<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use App\Support\UserAvatar;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'profile' => [
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'last_name' => $user->last_name,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'phone' => $user->phone,
                'national_id' => $user->national_id,
                'gender' => $user->gender,
                'locale' => $user->locale,
                'timezone' => $user->timezone,
                'theme' => $user->theme,
                'notify_email' => $user->wantsEmailNotifications(),
                'avatar' => UserAvatar::url($user),
            ],
            'options' => [
                'locales' => collect((array) config('localization.supported'))
                    ->map(fn (mixed $label, string $value): array => ['value' => $value, 'label' => (string) $label])
                    ->values()
                    ->all(),
                'timezones' => UserController::TIMEZONES,
                'defaultTimezone' => (string) config('localization.display_timezone'),
                'themes' => ProfileUpdateRequest::THEMES,
            ],
        ]);
    }

    /**
     * Update the user's own profile: who they are, how to reach them and their preferences.
     */
    public function update(ProfileUpdateRequest $request, AuditLog $audit): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($request, $user, $audit): void {
            $fields = [
                'name' => $request->fullName(),
                'email' => $request->string('email')->toString(),
                ...$request->ownProfile(),
            ];
            $old = [];
            $new = [];

            foreach ($fields as $key => $value) {
                // Compared as stored text, so the same choices in another order are no change.
                if (json_encode($user->getAttribute($key)) !== json_encode($value)) {
                    $old[$key] = $user->getAttribute($key);
                    $new[$key] = $value;
                }
            }

            if (array_key_exists('email', $new)) {
                $fields['email_verified_at'] = null;
            }

            $previousAvatar = $user->avatar_path;

            if ($request->avatar() !== null) {
                $fields['avatar_path'] = UserAvatar::store($request->avatar());
                $new['photo'] = 'changed';
            } elseif ($request->boolean('remove_avatar') && $previousAvatar !== null) {
                $fields['avatar_path'] = null;
                $new['photo'] = 'removed';
            }

            if ($new === []) {
                return;
            }

            $user->forceFill($fields)->save();
            $audit->record(AuditEvent::UserUpdated, $user, $user, old: $old, new: $new);

            if (array_key_exists('avatar_path', $fields) && $previousAvatar !== $fields['avatar_path']) {
                DB::afterCommit(fn () => UserAvatar::delete($previousAvatar));
            }
        });

        return to_route('profile.edit');
    }
}
