<?php

use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Support\Facades\Mail;
use Mockery as m;
use OpenKOS\Core\Data\Mail\MailAddress;
use OpenKOS\Core\Data\Mail\MailAttachment;
use OpenKOS\Core\Data\Mail\MailMessage;
use OpenKOS\MailResend\ResendDriver;
use OpenKOS\Platform\OpenKOSManager;
use Resend\Contracts\Client as ClientContract;

final class ResendClientFake implements ClientContract
{
    public int $propertyAccesses = 0;

    public function __construct(
        private object $service,
        private bool $throwOnPropertyAccess = false,
    ) {}

    public function __get(string $name): object
    {
        $this->propertyAccesses++;

        if ($this->throwOnPropertyAccess) {
            throw new RuntimeException('Unexpected SDK request.');
        }

        return $this->service;
    }
}

it('registers the driver and native Laravel mailer', function () {
    $registration = app(OpenKOSManager::class)->notifications()->get('openkos/resend');

    expect($registration)->not->toBeNull()
        ->and($registration->driverClass)->toBe(ResendDriver::class)
        ->and($registration->laravelMailer)->toBe('resend')
        ->and($registration->config)->toBe([])
        ->and($registration->toArray())->not->toHaveKey('config')
        ->and(config('mail.mailers.resend'))->toMatchArray([
            'transport' => 'resend',
            'key' => null,
        ]);
});

it('exposes an API key field without putting credentials in registration metadata', function () {
    expect((new ResendDriver)->configurationSchema())->toMatchArray([
        'key' => [
            'label' => 'Resend API Key',
            'type' => 'password',
            'required' => true,
            'placeholder' => 're_...',
        ],
    ]);
});

it('uses Laravel native Resend transport for the advertised mailer', function () {
    config(['mail.mailers.resend.key' => 're_test_key']);

    expect(Mail::mailer('resend')->getSymfonyTransport())
        ->toBeInstanceOf(ResendTransport::class);
});

it('checks Resend connectivity without sending mail', function () {
    $domains = m::mock();
    $domains->shouldReceive('list')->once()->with(['limit' => 1])->andReturn(new stdClass);

    $client = new ResendClientFake($domains);

    $missing = new ResendDriver(client: $client);
    $missingHealth = $missing->health();
    $configuredHealth = (new ResendDriver(['key' => 're_test_key'], $client))->health();

    expect($missingHealth->healthy)->toBeFalse()
        ->and($configuredHealth->healthy)->toBeTrue()
        ->and($configuredHealth->message)->toBe('Resend API connection verified.')
        ->and($client->propertyAccesses)->toBe(1);
});

it('reports failed Resend connectivity checks without exposing credentials', function () {
    $domains = m::mock();
    $domains->shouldReceive('list')->once()->with(['limit' => 1])->andThrow(new RuntimeException('secret re_test_key leaked'));

    $health = (new ResendDriver(['key' => 're_test_key'], new ResendClientFake($domains)))->health();

    expect($health->healthy)->toBeFalse()
        ->and($health->message)->toBe('Resend API connection check failed.')
        ->and($health->message)->not->toContain('re_test_key');
});

it('maps an OpenKOS message to the Resend SDK and returns its id', function () {
    $emails = m::mock();
    $emails->shouldReceive('send')->once()->with(m::on(function (array $payload): bool {
        expect($payload)->toBe([
            'from' => 'OpenKOS <sender@example.com>',
            'to' => ['Ada <ada@example.com>'],
            'cc' => ['copy@example.com'],
            'bcc' => ['blind@example.com'],
            'reply_to' => ['reply@example.com'],
            'headers' => ['X-Request-ID' => '123'],
            'subject' => 'Hello',
            'html' => '<p>Hello</p>',
            'text' => 'Hello',
            'attachments' => [[
                'content' => base64_encode('content'),
                'filename' => 'hello.txt',
                'content_type' => 'text/plain',
            ]],
        ]);

        return true;
    }))->andReturn((object) ['id' => 'email_123']);

    $client = new ResendClientFake($emails);

    $result = (new ResendDriver(['api_key' => 're_test_key'], $client))->send(new MailMessage(
        to: [new MailAddress('ada@example.com', 'Ada')],
        subject: 'Hello',
        htmlBody: '<p>Hello</p>',
        plainTextBody: 'Hello',
        from: new MailAddress('sender@example.com', 'OpenKOS'),
        replyTo: new MailAddress('reply@example.com'),
        cc: [new MailAddress('copy@example.com')],
        bcc: [new MailAddress('blind@example.com')],
        headers: ['X-Request-ID' => '123'],
        attachments: [new MailAttachment('content', 'hello.txt', 'text/plain')],
    ));

    expect($result->externalId)->toBe('email_123');
});

it('sanitizes SDK failures', function () {
    $emails = m::mock();
    $emails->shouldReceive('send')->andThrow(new RuntimeException('secret re_test_key leaked'));

    $client = new ResendClientFake($emails);

    expect(fn () => (new ResendDriver(['api_key' => 're_test_key'], $client))->send(new MailMessage(
        to: [new MailAddress('user@example.com')],
        subject: 'Hello',
        htmlBody: '<p>Hello</p>',
        from: new MailAddress('sender@example.com'),
    )))->toThrow(RuntimeException::class, 'Resend mail delivery failed.')
        ->and(fn () => (new ResendDriver(['api_key' => 're_test_key'], $client))->send(new MailMessage(
            to: [new MailAddress('user@example.com')],
            subject: 'Hello',
            htmlBody: '<p>Hello</p>',
            from: new MailAddress('sender@example.com'),
        )))->not->toThrow('re_test_key');
});
