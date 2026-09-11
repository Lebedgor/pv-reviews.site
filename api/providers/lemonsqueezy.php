<?php
/**
 * Lemon Squeezy payment provider.
 *
 * @package PhotoVideoReviews
 */

declare(strict_types=1);

require_once __DIR__ . '/provider.php';

class LemonSqueezyProvider implements PaymentProvider
{
    private string $apiKey;
    private string $webhookSecret;
    private string $storeId;
    private string $variantId;

    public function __construct()
    {
        $this->apiKey        = defined('LEMONSQUEEZY_API_KEY') ? LEMONSQUEEZY_API_KEY : '';
        $this->webhookSecret = defined('LEMONSQUEEZY_WEBHOOK_SECRET') ? LEMONSQUEEZY_WEBHOOK_SECRET : '';
        $this->storeId       = defined('LEMONSQUEEZY_STORE_ID') ? LEMONSQUEEZY_STORE_ID : '';
        $this->variantId     = defined('LEMONSQUEEZY_VARIANT_ID') ? LEMONSQUEEZY_VARIANT_ID : '';
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->webhookSecret !== '' && $this->storeId !== '' && $this->variantId !== '';
    }

    public function createCheckout(string $token, string $domain, string $email, array $customData = []): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Lemon Squeezy is not configured.');
        }

        $appUrl = defined('APP_URL') ? APP_URL : 'https://pv-reviews.site';

        // Renewal checkouts use the dedicated renewal variant when configured.
        $variantId = $this->variantId;
        if (isset($customData['renew_license_id']) && defined('LEMONSQUEEZY_RENEWAL_VARIANT_ID') && LEMONSQUEEZY_RENEWAL_VARIANT_ID !== '') {
            $variantId = LEMONSQUEEZY_RENEWAL_VARIANT_ID;
        }

        $payload = [
            'data' => [
                'type' => 'checkouts',
                'attributes' => [
                    'product_options' => [
                        'redirect_url'          => $appUrl . '/thank-you?token=' . urlencode($token),
                        'receipt_button_text'   => 'View License Key',
                        'receipt_link_url'      => $appUrl . '/thank-you?token=' . urlencode($token),
                    ],
                    'checkout_data' => [
                        'custom' => array_merge(['session_token' => $token], $customData),
                    ],
                ],
                'relationships' => [
                    'store' => [
                        'data' => [
                            'type' => 'stores',
                            'id'   => $this->storeId,
                        ],
                    ],
                    'product' => [
                        'data' => [
                            'type'       => 'products',
                            'id'         => $variantId,
                        ],
                    ],
                ],
            ],
        ];

        $ch = curl_init('https://api.lemonsqueezy.com/v1/checkouts');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/vnd.api+json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new RuntimeException('Lemon Squeezy API error: ' . $curlError);
        }

        $data = json_decode($response, true);
        if ($httpCode < 200 || $httpCode >= 300 || empty($data['data']['attributes']['url'])) {
            throw new RuntimeException('Lemon Squeezy checkout creation failed: ' . ($data['errors'][0]['detail'] ?? $response));
        }

        return [
            'checkout_url'       => $data['data']['attributes']['url'],
            'provider_session_id' => $data['data']['id'] ?? '',
        ];
    }

    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        $signature = $headers['x-signature']
            ?? $headers['HTTP_X_SIGNATURE']
            ?? $headers['X-Signature']
            ?? '';
        if ($signature === '' || $this->webhookSecret === '') {
            return false;
        }
        $expected = hash_hmac('sha256', $rawBody, $this->webhookSecret);
        return hash_equals($expected, $signature);
    }

    public function parseWebhook(array $payload): array
    {
        $eventName = $payload['meta']['event_name'] ?? '';
        $attrs     = $payload['data']['attributes'] ?? [];
        $dataId    = $payload['data']['id'] ?? null;

        if ($dataId && empty($attrs['id'])) {
            $attrs['id'] = $dataId;
        }

        $lsOrderId = $attrs['id'] ?? null;
        $email     = $attrs['user_email'] ?? '';
        $name      = $attrs['user_name'] ?? '';
        $total     = (int)($attrs['total'] ?? 0);
        $currency  = $attrs['currency'] ?? 'USD';
        $firstItem = $attrs['first_order_item'] ?? [];

        $customData = $payload['meta']['custom_data'] ?? [];
        $sessionToken = $customData['session_token'] ?? null;

        $statusMap = [
            'pending'  => 'pending',
            'paid'     => 'completed',
            'completed' => 'completed',
            'refunded' => 'refunded',
        ];
        $status = $statusMap[$attrs['status'] ?? ''] ?? $attrs['status'] ?? '';

        $eventType = 'order_created';
        if ($eventName === 'order_updated') {
            $eventType = 'order_updated';
        } elseif ($eventName === 'order_refunded' || $status === 'refunded') {
            $eventType = 'order_refunded';
        }

        return [
            'event_type'    => $eventType,
            'order_id'      => $lsOrderId,
            'email'         => $email,
            'name'          => $name,
            'status'        => $status,
            'total'         => $total,
            'currency'      => $currency,
            'domain'        => '',
            'session_token' => $sessionToken,
            'product_name'  => $firstItem['product_name'] ?? '',
            'variant_name'  => $firstItem['variant_name'] ?? '',
            'license_key'   => null,
            'refunded_at'   => $attrs['refunded_at'] ?? null,
        ];
    }
}
