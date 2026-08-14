<?php

namespace OpenKOS\MailResend;

use OpenKOS\Core\Contracts\MailDriver;
use OpenKOS\Core\Data\Mail\DriverHealthResult;
use OpenKOS\Core\Data\Mail\MailAddress;
use OpenKOS\Core\Data\Mail\MailMessage;
use OpenKOS\Core\Data\Mail\MailSendResult;
use Resend;
use Resend\Contracts\Client;
use RuntimeException;
use Throwable;

class ResendDriver implements MailDriver
{
    public function __construct(
        private array $config = [],
        private ?Client $client = null,
    ) {}

    public function send(MailMessage $message): MailSendResult
    {
        try {
            $result = $this->client()->emails->send($this->payload($message));
        } catch (Throwable $exception) {
            throw new RuntimeException('Resend mail delivery failed.', 0, $exception);
        }

        return new MailSendResult($result->id ?? null, 'Sent via Resend.');
    }

    public function health(): DriverHealthResult
    {
        if ($this->apiKey() === null) {
            return new DriverHealthResult(false, 'Resend API key is not configured.');
        }

        try {
            $this->client()->domains->list(['limit' => 1]);
        } catch (Throwable) {
            return new DriverHealthResult(false, 'Resend API connection check failed.');
        }

        return new DriverHealthResult(true, 'Resend API connection verified.');
    }

    public function configurationSchema(): array
    {
        return [
            'key' => [
                'label' => 'Resend API Key',
                'type' => 'password',
                'required' => true,
                'placeholder' => 're_...',
            ],
        ];
    }

    private function client(): Client
    {
        if ($this->client) {
            return $this->client;
        }

        $apiKey = $this->apiKey();

        if ($apiKey === null) {
            throw new RuntimeException('Resend API key is not configured.');
        }

        return $this->client = Resend::client($apiKey);
    }

    private function apiKey(): ?string
    {
        $apiKey = $this->config['key'] ?? $this->config['api_key'] ?? config('resend.api_key');

        return is_string($apiKey) && trim($apiKey) !== '' ? $apiKey : null;
    }

    private function payload(MailMessage $message): array
    {
        $from = $message->from ?? $this->configuredFrom();

        if (! $from) {
            throw new RuntimeException('Resend sender address is not configured.');
        }

        return array_filter([
            'from' => $this->formatAddress($from),
            'to' => array_map(fn (MailAddress $address) => $this->formatAddress($address), $message->to),
            'cc' => $this->formatAddresses($message->cc),
            'bcc' => $this->formatAddresses($message->bcc),
            'reply_to' => $message->replyTo ? [$this->formatAddress($message->replyTo)] : null,
            'headers' => $message->headers,
            'subject' => $message->subject,
            'html' => $message->htmlBody,
            'text' => $message->plainTextBody,
            'attachments' => array_map(static fn ($attachment) => [
                'content' => base64_encode($attachment->content),
                'filename' => $attachment->filename,
                'content_type' => $attachment->mimeType,
            ], $message->attachments),
        ], static fn ($value) => $value !== null && $value !== []);
    }

    private function configuredFrom(): ?MailAddress
    {
        $from = $this->config['from'] ?? [];
        $address = $from['address'] ?? null;

        return is_string($address) && trim($address) !== ''
            ? new MailAddress($address, $from['name'] ?? null)
            : null;
    }

    /** @param list<MailAddress> $addresses */
    private function formatAddresses(array $addresses): ?array
    {
        return $addresses === []
            ? null
            : array_map(fn (MailAddress $address) => $this->formatAddress($address), $addresses);
    }

    private function formatAddress(MailAddress $address): string
    {
        return $address->name ? "{$address->name} <{$address->address}>" : $address->address;
    }
}
