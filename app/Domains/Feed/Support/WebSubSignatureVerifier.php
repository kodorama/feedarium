<?php

namespace App\Domains\Feed\Support;

/**
 * Pure HMAC-SHA256 verification helper for WebSub push callbacks.
 * No database interaction — stateless.
 */
final class WebSubSignatureVerifier
{
    public static function verify(string $secret, string $body, string $signature): bool
    {
        // Signature format: sha256=<hex>
        if (! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $body, $secret);

        return hash_equals($expected, $signature);
    }
}
