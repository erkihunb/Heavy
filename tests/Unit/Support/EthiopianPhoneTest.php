<?php

use App\Support\EthiopianPhone;

it('normalises Ethiopian phone numbers to international format', function (string $input, string $expected) {
    expect(EthiopianPhone::normalize($input))->toBe($expected);
})->with([
    'international mobile' => ['+251911234567', '+251911234567'],
    'local mobile' => ['0911234567', '+251911234567'],
    'without plus' => ['251911234567', '+251911234567'],
    'without leading zero' => ['911234567', '+251911234567'],
    'safaricom mobile' => ['0712345678', '+251712345678'],
    'addis landline' => ['0115512345', '+251115512345'],
    'spaces' => ['+251 91 123 4567', '+251911234567'],
    'dashes and brackets' => ['(011) 551-2345', '+251115512345'],
    'surrounding whitespace' => ['  0911234567 ', '+251911234567'],
]);

it('rejects values that are not Ethiopian phone numbers', function (string $input) {
    expect(EthiopianPhone::normalize($input))->toBeNull();
})->with([
    'empty' => [''],
    'too short' => ['09112345'],
    'too long' => ['09112345678'],
    'letters' => ['09112345ab'],
    'foreign country code' => ['+14155552671'],
    'leading zero after country code' => ['+2510911234567'],
    'starts with zero zero' => ['0011234567'],
    'free text' => ['call the office'],
]);
