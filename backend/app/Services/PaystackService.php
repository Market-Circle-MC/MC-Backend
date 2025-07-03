<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    protected $secretKey;
    protected $publicKey;
    protected $baseUrl;
    protected $webhookSecret;
    protected $httpClient;

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key');
        $this->publicKey = config('services.paystack.public_key');
        $this->baseUrl = config('services.paystack.payment_url');
        $this->webhookSecret = config('services.paystack.webhook_secret');

        $this->httpClient = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Initialize a Paystack payment.
     * Amount is expected in Ghana Cedis (GHS).
     *
     * @param float $amount Amount in GHS
     * @param string $email Customer email
     * @param string $reference Unique transaction reference
     * @param string $callbackUrl URL to redirect to after payment
     * @return array|null Response data from Paystack or null on failure
     */
    public function initializePayment(float $amount, string $email, string $reference, string $callbackUrl): ?array
    {
        try {
            $response = $this->httpClient->post('/transaction/initialize', [
                'json' => [
                    'amount' => (int)($amount * 100), // Paystack API still expects amount in Kobo/Pesewas even for GHS currency
                    'email' => $email,
                    'reference' => $reference,
                    'callback_url' => $callbackUrl,
                    'currency' => 'GHS', // Explicitly set currency to GHS
                    'metadata' => [
                        'order_reference' => $reference,
                    ],
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            if ($data['status'] === true) {
                Log::info("Paystack payment initialization successful for reference: {$reference}", $data);
                return $data['data'];
            } else {
                Log::error("Paystack payment initialization failed for reference: {$reference}: " . $data['message'], $data);
                return null;
            }
        } catch (RequestException $e) {
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : 'No response body';
            Log::error("Paystack API initialization error for reference: {$reference}: " . $e->getMessage(), [
                'request_error' => $e->getMessage(),
                'response_body' => $responseBody,
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error("General error during Paystack initialization for reference: {$reference}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Verify a Paystack transaction.
     *
     * @param string $reference The transaction reference to verify
     * @return array|null Response data from Paystack or null on failure
     */
    public function verifyPayment(string $reference): ?array
    {
        try {
            $response = $this->httpClient->get("/transaction/verify/{$reference}");
            $data = json_decode($response->getBody(), true);

            if ($data['status'] === true) {
                Log::info("Paystack payment verification successful for reference: {$reference}", $data);
                return $data['data'];
            } else {
                Log::warning("Paystack payment verification failed for reference: {$reference}: " . $data['message'], $data);
                return null;
            }
        } catch (RequestException $e) {
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : 'No response body';
            Log::error("Paystack API verification error for reference: {$reference}: " . $e->getMessage(), [
                'request_error' => $e->getMessage(),
                'response_body' => $responseBody,
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error("General error during Paystack verification for reference: {$reference}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Verify Paystack webhook signature.
     *
     * @param string $payload The raw request body
     * @param string $signature The X-Paystack-Signature header value
     * @return bool
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if (empty($this->webhookSecret)) {
            Log::warning('Paystack Webhook: PAYSTACK_WEBHOOK_SECRET is not configured in .env. Webhook signature WILL NOT be verified.');
            return true;
        }

        $expectedSignature = hash_hmac('sha512', $payload, $this->webhookSecret);
        return hash_equals($expectedSignature, $signature);
    }
}