<?php

declare(strict_types=1);

namespace App\Payment;

/**
 * Vérifie l'en-tête Stripe-Signature (schéma "t=<timestamp>,v1=<hmac>").
 * STRIDE: Spoofing / Tampering — un webhook non signé, mal signé ou trop ancien (rejeu) est refusé.
 */
final class WebhookSignatureVerifier
{
    private const TOLERANCE_SECONDS = 300;

    public function __construct(private readonly string $secret)
    {
        if (strlen($this->secret) < 16) {
            throw new \LogicException('PAYMENT_WEBHOOK_SECRET is missing or too short.');
        }
    }

    public function isValid(string $payload, string $header, ?int $now = null): bool
    {
        $now ??= time();
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }
        if ($timestamp === null || $signatures === [] || abs($now - $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $this->secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    public function sign(string $payload, int $timestamp): string
    {
        return 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $payload, $this->secret);
    }
}
