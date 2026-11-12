<?php

use App\Domain\Sms\LogSmsGateway;
use App\Domain\Sms\SmsGateway;
use App\Models\Permit;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\PermitRevoked;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/*
 * Text messages go through the SmsGateway contract (spec §11); until a
 * provider is chosen the log driver writes them with the number masked.
 */

it('sends the notification\'s text to the routed number through the gateway', function () {
    $sent = [];
    app()->instance(SmsGateway::class, new class($sent) implements SmsGateway
    {
        /** @param  list<array{string, string}>  $sent */
        public function __construct(private array &$sent) {}

        public function send(string $to, string $message): void
        {
            $this->sent[] = [$to, $message];
        }
    });

    $permit = Permit::factory()->revoked()->create(['permit_number' => 'AACRA-2019-000001']);

    Notification::route('sms', '0911223344')->notifyNow(new PermitRevoked($permit, 'x'));

    expect($sent)->toHaveCount(1)
        ->and($sent[0][0])->toBe('0911223344')
        ->and($sent[0][1])->toContain('AACRA-2019-000001');
});

it('logs messages with the phone number masked', function () {
    Log::spy();

    (new LogSmsGateway)->send('+251 911 223 344', 'TMA: permit AACRA-2019-000001 revoked.');

    Log::shouldHaveReceived('info')->once()->withArgs(fn (string $message, array $context): bool => $context['to'] === '*********344'
        && ! str_contains(json_encode($context), '911223')
        && $context['message'] === 'TMA: permit AACRA-2019-000001 revoked.');
});

it('masks short and oddly written numbers', function (string $phone, string $masked) {
    expect(LogSmsGateway::mask($phone))->toBe($masked);
})->with([
    ['0911223344', '*******344'],
    ['091-122-3344', '*******344'],
    ['12', '12'],
    ['', ''],
]);

it('skips a route without a number', function () {
    $gateway = Mockery::mock(SmsGateway::class);
    $gateway->shouldNotReceive('send');

    (new SmsChannel($gateway))->send(Notification::route('sms', ''), new PermitRevoked(Permit::factory()->make()));
});

it('refuses an unknown SMS driver', function () {
    config(['services.sms.driver' => 'carrier-pigeon']);

    expect(fn () => app(SmsGateway::class))->toThrow(InvalidArgumentException::class, 'carrier-pigeon');
});

it('uses the log driver by default', function () {
    expect(app(SmsGateway::class))->toBeInstanceOf(LogSmsGateway::class);
});
