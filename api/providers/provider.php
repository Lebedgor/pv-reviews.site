<?php
/**
 * Payment provider interface.
 *
 * Each provider must implement checkout creation and webhook handling.
 *
 * @package PhotoVideoReviews
 */

declare(strict_types=1);

interface PaymentProvider
{
    /**
     * Create a checkout session and return a redirect URL.
     *
     * @param string $token       Internal session token
     * @param string $domain      Normalized domain
     * @param string $email       Customer email (may be empty)
     * @param array  $customData  Additional data to pass through
     * @return array{checkout_url: string, provider_session_id: string}
     */
    public function createCheckout(string $token, string $domain, string $email, array $customData = []): array;

    /**
     * Verify webhook signature authenticity.
     *
     * @param string $rawBody    Raw request body
     * @param array  $headers    Request headers
     * @return bool
     */
    public function verifyWebhook(string $rawBody, array $headers): bool;

    /**
     * Parse a webhook payload into a normalized order structure.
     *
     * Returns:
     *   event_type:  'order_created' | 'order_updated' | 'order_refunded'
     *   order_id:    external order ID
     *   email:       customer email
     *   name:        customer name
     *   status:      'pending' | 'paid' | 'refunded'
     *   total:       amount in cents
     *   currency:    e.g. 'USD'
     *   domain:      website domain from metadata
     *   session_token: internal session token from reference
     *   product_name: product name
     *   variant_name: variant name
     *   license_key:  license key (if provider issues one)
     *   refunded_at:  refund timestamp (if applicable)
     */
    public function parseWebhook(array $payload): array;
}
