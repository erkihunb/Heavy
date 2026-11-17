<?php

use App\Domain\Audit\AuditEvent;
use App\Enums\Role;
use App\Models\User;
use App\Support\UserAvatar;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/*
 * A staff account carries a photo, phone and preferred language. Photos are cropped to a square, re-encoded, kept on the
 * private disk and shown only to people who are signed in.
 */

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');
    $this->admin = staffUser(Role::Admin);
});

it('opens an account with a photo, phone and language', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), [
            'name' => 'Selam Tesfaye',
            'email' => 'selam@example.test',
            'roles' => ['cashier'],
            'phone' => '0911 234 567',
            'locale' => 'en',
            'avatar' => UploadedFile::fake()->image('selam.jpg', 800, 600),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'selam@example.test')->firstOrFail();
    expect($user->locale)->toBe('en')
        ->and($user->phone)->not->toBeNull()
        ->and($user->avatar_path)->toStartWith(UserAvatar::DIRECTORY.'/');

    Storage::disk('local')->assertExists($user->avatar_path);
    [$width, $height] = getimagesizefromstring(Storage::disk('local')->get($user->avatar_path));
    expect([$width, $height])->toBe([UserAvatar::SIZE, UserAvatar::SIZE]);
});

it('refuses a bad phone and a file that is not a photo', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), [
            'name' => 'Bad Input',
            'email' => 'bad@example.test',
            'roles' => ['cashier'],
            'phone' => '12',
            'avatar' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf'),
        ])
        ->assertSessionHasErrors(['phone', 'avatar']);

    expect(User::query()->where('email', 'bad@example.test')->exists())->toBeFalse();
});

it('changes the details, replaces the photo and removes it, auditing each', function () {
    $user = staffUser(Role::Reviewer);

    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'branch_id' => null,
            'locale' => 'en',
            'avatar' => UploadedFile::fake()->image('a.png', 300, 300),
        ])
        ->assertSessionHasNoErrors();

    $first = $user->refresh()->avatar_path;
    expect($first)->not->toBeNull()->and($user->locale)->toBe('en');

    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $user), ['name' => $user->name, 'branch_id' => null, 'avatar' => UploadedFile::fake()->image('b.png', 300, 300)]);
    $second = $user->refresh()->avatar_path;
    expect($second)->not->toBe($first);
    Storage::disk('local')->assertMissing($first);

    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $user), ['name' => $user->name, 'branch_id' => null, 'remove_avatar' => true]);
    expect($user->refresh()->avatar_path)->toBeNull();
    Storage::disk('local')->assertMissing($second);

    expect(Activity::query()->where('event', AuditEvent::UserUpdated->value)->count())->toBe(3);
});

it('records the national ID without spaces and the gender, and keeps national IDs unique', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), [
            'name' => 'Abebe Kebede',
            'email' => 'abebe@example.test',
            'roles' => ['reviewer'],
            'national_id' => '1234 5678 9012 3456',
            'gender' => 'male',
        ])
        ->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'abebe@example.test')->firstOrFail();
    expect($user->national_id)->toBe('1234567890123456')->and($user->gender)->toBe('male');

    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), [
            'name' => 'Copy', 'email' => 'copy@example.test', 'roles' => ['reviewer'],
            'national_id' => '1234567890123456', 'gender' => 'other',
        ])
        ->assertSessionHasErrors(['national_id' => __('rbac.users.errors.national_id_taken'), 'gender']);

    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), ['name' => 'Short', 'email' => 'short@example.test', 'roles' => ['reviewer'], 'national_id' => '12345'])
        ->assertSessionHasErrors(['national_id' => __('rbac.users.errors.national_id_invalid')]);

    // Saving the person's own ID again is not a clash.
    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $user), ['name' => $user->name, 'branch_id' => null, 'national_id' => '1234567890123456', 'gender' => 'male'])
        ->assertSessionHasNoErrors();
});

it('opens an account deactivated when asked, and sends it no link', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), ['name' => 'Later Start', 'email' => 'later@example.test', 'roles' => ['cashier'], 'active' => '0'])
        ->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'later@example.test')->firstOrFail();
    expect($user->isActive())->toBeFalse()->and($user->deactivated_by)->toBe($this->admin->id);
    Notification::assertNothingSentTo($user);
});

it('shows a photo to signed-in people only, and lists it with the user', function () {
    $user = staffUser(Role::Reviewer);
    $user->forceFill(['avatar_path' => UserAvatar::store(UploadedFile::fake()->image('p.jpg', 200, 200))])->save();

    $this->get(route('users.avatar', $user))->assertRedirect(route('login'));
    $this->actingAs(staffUser(Role::Cashier))->get(route('users.avatar', $user))->assertOk();

    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['search' => $user->email]))
        ->assertInertia(fn (Assert $page) => $page->where('users.data.0.avatar', UserAvatar::url($user)));
});
