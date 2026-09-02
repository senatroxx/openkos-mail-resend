<?php

namespace OpenKOS\MailResend;

use OpenKOS\Platform\OpenKOSManager;
use OpenKOS\Platform\Plugin\Plugin;
use OpenKOS\Platform\Plugin\PluginManifest;

final class ResendPlugin extends Plugin
{
    public function manifest(): PluginManifest
    {
        return new PluginManifest(
            id: 'openkos/mail-resend',
            name: 'Resend Mail',
            version: '0.2.1',
            description: 'Resend mail integration for OpenKOS notifications.',
            coreVersion: '^0.2',
        );
    }

    public function register(OpenKOSManager $platform): void
    {
        $provider = new ResendServiceProvider(app());
        $provider->register();
        $provider->boot($platform);
    }
}
