<?php
/**
 * Getly.store payment provider.
 *
 * @package PhotoVideoReviews
 */

declare(strict_types=1);

require_once __DIR__ . '/provider.php';

class GetlyProvider implements PaymentProvider
{
    private string $apiKey;
    private string $webhookSecret;
    private string $productId;

    public function __construct()
    {
        $this->apiKey        = defined('GETLY_API_KEY') ? GETLY_API_KEY : '';
        $this->webhookSecret = defined('GETLY_WEBHOOK_SECRET') ? GETLY_WEBHOOK_SECRET : '';
        $this->productId     = defined('GETLY_PRODUCT_ID') ? GETLY_PRODUCT_ID : '';
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->webhookSecret !== '';
    }

    public function createCheckout(string $token, string $domain, string $email, array $customData = []): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Getly is not configured.');
        }

        $payload = [
            'productId'       => $this->productId,
            'reference'       => $token,
            'metadata'        => array_merge(['domain' => $domain, 'email' => $email], $customData),
            'expiresInHours'  => 24,
        ];

        $ch = curl_init('https://www.getly.store/api/v1/checkout-links');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'Idempotency-Key: checkout-' . $token,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new RuntimeException('Getly API error: ' . $curlError);
        }

        $data = json_decode($response, true);
        if ($httpCode < 200 || $httpCode >= 300 || empty($data['data']['url'])) {
            throw new RuntimeException('Getly checkout creation failed: ' . ($data['error'] ?? $response));
        }

        return [
            'checkout_url'       => $data['data']['url'],
            'provider_session_id' => $data['data']['id'] ?? '',
        ];
    }

    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        $signatureV2 = $headers['x-getly-signature-v2']
            ?? $headers['HTTP_X_GETLY_SIGNATURE_V2']
            ?? $headers['X-Getly-Signature-V2']
            ?? '';
        if ($signatureV2 === '' || $this->webhookSecret === '') {
            return false;
        }

        if (!preg_match('/^t=(\d+),v1=([a-f0-9]+)$/', $signatureV2, $m)) {
            return false;
        }

        $timestamp = (int)$m[1];
        $expectedHmac = $m[2];

        if (abs(time() - $timestamp) > 300) {
            return false;
        }

        $payloadToVerify = $timestamp . '.' . $rawBody;
        $calculated = hash_hmac('sha256', $payloadToVerify, $this->webhookSecret);
        return hash_equals($calculated, $expectedHmac);
    }

    public function parseWebhook(array $payload): array
    {
        $eventName = $payload['event'] ?? '';
        $data      = $payload['data'] ?? [];

        $orderId      = $data['orderId'] ?? $data['id'] ?? '';
        $email        = $data['metadata']['email'] ?? $data['buyerEmail'] ?? $data['email'] ?? '';
        $name         = $data['buyerName'] ?? $data['name'] ?? '';
        $total        = (int)($data['amountCents'] ?? $data['total'] ?? 0);
        $currency     = $data['currency'] ?? 'USD';
        $sessionToken = $data['reference'] ?? $data['metadata']['session_token'] ?? '';
        $domain       = $data['metadata']['domain'] ?? '';
        $licenseKey   = $data['licenseKey'] ?? $data['license']['key'] ?? null;
        $productName  = $data['productName'] ?? $data['product']['name'] ?? '';
        $variantName  = '';

        $statusMap = [
            'sale.completed'       => 'completed',
            'checkout_link.completed' => 'completed',
            'order.refunded'       => 'refunded',
            'refund.created'       => 'refunded',
            'refund.completed'     => 'refunded',
        ];
        $status = $statusMap[$eventName] ?? 'paid';

        $eventType = 'order_created';
        if ($status === 'refunded') {
            $eventType = 'order_refunded';
        }

        return [
            'event_type'    => $eventType,
            'order_id'      => (string)$orderId,
            'email'         => $email,
            'name'          => $name,
            'status'        => $status,
            'total'         => $total,
            'currency'      => $currency,
            'domain'        => $domain,
            'session_token' => $sessionToken,
            'product_name'  => $productName,
            'variant_name'  => $variantName,
            'license_key'   => $licenseKey,
            'refunded_at'   => $data['refundedAt'] ?? null,
        ];
    }
}
