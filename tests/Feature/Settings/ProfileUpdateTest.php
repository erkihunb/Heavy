<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    config(['permits.security.email_verification' => true]);

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('a changed email address counts as confirmed while verification is off', function () {
    config(['permits.security.email_verification' => false]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Test User', 'email' => 'new@example.com'])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('an account cannot be deleted by its owner', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete('/settings/profile', ['password' => 'password'])
        ->assertMethodNotAllowed();

    expect($user->fresh())->not->toBeNull()
        ->and(Route::has('profile.destroy'))->toBeFalse();
});

test('a person can update their own personal details and preferences', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'first_name' => 'Abebe',
            'middle_name' => 'Kebede',
            'last_name' => 'Alemu',
            'email' => $user->email,
            'username' => 'Abebe.K',
            'gender' => 'male',
            'phone' => '0911223344',
            'national_id' => '1234 5678 9012 3456',
            'locale' => 'en',
            'timezone' => 'Africa/Addis_Ababa',
            'theme' => 'dark',
            'notify_email' => 0,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Abebe Kebede Alemu')
        ->and($user->first_name)->toBe('Abebe')
        ->and($user->username)->toBe('abebe.k')
        ->and($user->gender)->toBe('male')
        ->and($user->phone)->toBe('+251911223344')
        ->and($user->national_id)->toBe('1234567890123456')
        ->and($user->locale)->toBe('en')
        ->and($user->theme)->toBe('dark')
        ->and($user->wantsEmailNotifications())->toBeFalse();
});

test('a person cannot change their own employment record from their profile', function () {
    $user = User::factory()->create([
        'job_title' => 'Permit officer',
        'department' => 'Permits',
        'employee_number' => 'EMP-001',
        'employment_status' => 'permanent',
    ]);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'first_name' => 'Abebe',
            'middle_name' => 'Kebede',
            'email' => $user->email,
            'job_title' => 'Director',
            'department' => 'Finance',
            'employee_number' => 'EMP-999',
            'employment_status' => 'contract',
            'branch_id' => 99,
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->job_title)->toBe('Permit officer')
        ->and($user->department)->toBe('Permits')
        ->and($user->employee_number)->toBe('EMP-001')
        ->and($user->employment_status)->toBe('permanent')
        ->and($user->branch_id)->toBeNull();
});

test('a username or national ID that belongs to someone else is refused', function () {
    User::factory()->create(['username' => 'taken', 'national_id' => '123456789012']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'first_name' => 'Abebe',
            'middle_name' => 'Kebede',
            'email' => $user->email,
            'username' => 'taken',
            'national_id' => '123456789012',
        ])
        ->assertSessionHasErrors(['username', 'national_id']);
});
