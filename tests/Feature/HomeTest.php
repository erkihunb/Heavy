<?php

use Inertia\Testing\AssertableInertia as Assert;

it('shows the landing page to guests', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('canRegister', true)
            ->where('auth.user', null));
});

it('shares today in both calendars, using the Addis Ababa date', function () {
    // 22:30 UTC on 7 October is already 8 October (Meskerem 28) in Addis Ababa.
    $this->travelTo('2025-10-07 22:30:00');

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('today', [
            'gregorian' => '2025-10-08',
            'ethiopian' => '28/01/2018',
            'ethiopian_label' => 'መስከረም 28, 2018',
        ]));
});

it('labels the Ethiopian date in the language of the visitor', function () {
    $this->travelTo('2025-10-08 09:00:00');

    $this->withSession(['locale' => 'en'])
        ->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('today.ethiopian_label', 'Meskerem 28, 2018'));
});

it('gives the sample permit a 90-day validity starting today', function () {
    $this->travelTo('2025-10-08 09:00:00');

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sample.validFrom.gregorian', '2025-10-08')
            ->where('sample.validFrom.ethiopian', '28/01/2018')
            ->where('sample.validTo.gregorian', '2026-01-05')
            ->where('sample.validTo.ethiopian', '27/04/2018'));
});

it('exposes nothing about real permits or users on the landing page', function () {
    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sample', fn ($sample) => collect($sample)->keys()->all() === ['validFrom', 'validTo']));
});
