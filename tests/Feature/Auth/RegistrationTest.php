<?php

use Illuminate\Support\Facades\Route;

// Applicants do not sign up or apply themselves: a Registrar opens their
// account at the office (tests/Feature/Admin/ApplicantRegistrationTest.php).

test('there is no sign-up page', function () {
    expect(Route::has('register'))->toBeFalse()
        ->and(Route::has('register.store'))->toBeFalse();

    $this->get('/register')->assertNotFound();
});

test('nobody can create their own account', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'correct horse battery staple',
        'password_confirmation' => 'correct horse battery staple',
    ])->assertNotFound();

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
});
