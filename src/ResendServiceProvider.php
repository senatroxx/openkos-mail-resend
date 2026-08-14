<?php

namespace OpenKOS\MailResend;

use Illuminate\Support\ServiceProvider;
use OpenKOS\Platform\Notification\NotificationDriverRegistration;
use OpenKOS\Platform\OpenKOSManager;

class ResendServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/resend.php', 'resend');
    }

    public function boot(OpenKOSManager $platform): void
    {
        config([
            'mail.mailers.resend' => array_replace(
                config('mail.mailers.resend', []),
                ['transport' => 'resend'],
                ['key' => config('resend.api_key')],
            ),
        ]);

        $platform->notifications()->registerDriver(new NotificationDriverRegistration(
            name: 'openkos/resend',
            channel: 'mail',
            driverClass: ResendDriver::class,
            label: 'Resend',
            laravelMailer: 'resend',
        ));
    }
}
