# OpenKOS Resend mail driver

Standalone Resend integration for OpenKOS mail notifications.

## Install

```sh
composer require openkos/mail-resend
```

Set `RESEND_API_KEY` or enter the key in the OpenKOS mail settings page, then
configure the OpenKOS mail driver as `openkos/resend`.

The package registers both OpenKOS custom mail delivery and Laravel's native
`resend` mailer. Native Laravel notifications use Laravel's built-in Resend
transport; custom OpenKOS notifications use the DTO adapter in this package.

The driver health check calls Resend's read-only domains endpoint to verify the
API connection. It never sends a test message, so the configured key must have
full access for this check.
