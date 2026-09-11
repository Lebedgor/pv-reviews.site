<?php
/**
 * Payment provider factory.
 *
 * Returns the active provider based on PAYMENT_PROVIDER env var.
 *
 * @package PhotoVideoReviews
 */

declare(strict_types=1);

require_once __DIR__ . '/lemonsqueezy.php';
require_once __DIR__ . '/getly.php';

function getPaymentProvider(): PaymentProvider
{
    $providerName = defined('PAYMENT_PROVIDER') ? strtolower(trim(PAYMENT_PROVIDER)) : 'lemonsqueezy';

    switch ($providerName) {
        case 'getly':
            $provider = new GetlyProvider();
            if (!$provider->isConfigured()) {
                error_log('PVR: Getly selected but not configured, falling back to Lemon Squeezy.');
                return new LemonSqueezyProvider();
            }
            return $provider;

        case 'lemonsqueezy':
        default:
            $provider = new LemonSqueezyProvider();
            if (!$provider->isConfigured()) {
                error_log('PVR: Lemon Squeezy selected but not configured.');
            }
            return $provider;
    }
}
