# OpenKOS Resend mail driver

Standalone Resend integration for OpenKOS mail notifications.

## Install

```sh
composer require openkos/mail-resend
```

Set `RESEND_API_KEY` and configure the OpenKOS mail driver as `openkos/resend`.

The package registers both OpenKOS custom mail delivery and Laravel's native
`resend` mailer. Native Laravel notifications use Laravel's built-in Resend
transport; custom OpenKOS notifications use the DTO adapter in this package.

The driver health check only reports whether an API key is configured. It never
calls Resend or sends a test message.
