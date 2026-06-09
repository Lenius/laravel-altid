<?php

namespace Lenius\LaravelAltid;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AltIdAgeVerificationService
{
    public function __construct(
        private readonly AltIdAgePresentationValidator $validator,
    ) {}

    public function start(?string $claim = null): array
    {
        $transactionId = $this->randomToken();
        $state = $this->randomToken();
        $nonce = (string) Str::uuid();
        $claim = $this->normalizeClaim($claim);
        $expiresAt = now()->addMinutes((int) config('altid.transaction_ttl_minutes', 15));
        $responseUri = $this->absoluteUrl("/api/altid/age/direct-post/{$transactionId}");
        $clientId = 'redirect_uri:'.$responseUri;

        $transaction = [
            'transaction_id' => $transactionId,
            'state' => $state,
            'nonce' => $nonce,
            'claim' => $claim,
            'client_id' => $clientId,
            'response_uri' => $responseUri,
            'status' => 'pending',
            'verified' => false,
            'result' => null,
            'error' => null,
            'created_at' => now()->toISOString(),
            'expires_at' => $expiresAt->toISOString(),
        ];

        $this->put($transaction, $expiresAt);

        $authorizationRequest = $this->authorizationRequest($responseUri, $clientId, $state, $nonce, $claim);

        return $transaction + [
            'authorization_request' => $authorizationRequest,
            'authorization_url' => $this->authorizationUrl($authorizationRequest),
            'test_app_url' => $this->authorizationUrl($authorizationRequest, config('altid.test_app_oid4vp_url')),
            'status_url' => $this->absoluteUrl("/api/altid/age/{$transactionId}/status"),
        ];
    }

    public function find(string $transactionId): ?array
    {
        return Cache::get($this->cacheKey($transactionId));
    }

    public function complete(string $transactionId, array $payload): array
    {
        $transaction = $this->find($transactionId);
        $requestMeta = $payload['_request_meta'] ?? [];
        unset($payload['_request_meta']);

        if ($transaction === null) {
            return [
                'transaction_id' => $transactionId,
                'status' => 'failed',
                'verified' => false,
                'error' => 'Transaction was not found or has expired.',
            ];
        }

        $transaction['callback'] = [
            'received_at' => now()->toISOString(),
            'payload_keys' => array_keys($payload),
            'content_type' => $requestMeta['content_type'] ?? null,
            'raw_body_preview' => $requestMeta['raw_body_preview'] ?? null,
        ];

        if (($payload['state'] ?? null) !== $transaction['state']) {
            return $this->fail($transaction, 'State did not match the pending AltID transaction.');
        }

        if (! filled($payload['vp_token'] ?? null)) {
            if (filled($payload['error'] ?? null)) {
                $description = $payload['error_description'] ?? $payload['error'];

                return $this->fail($transaction, 'AltID returned an error: '.$description);
            }

            return $this->fail($transaction, 'AltID response did not include a vp_token.');
        }

        $validation = $this->validator->validate($payload['vp_token'], $transaction, $payload);
        $transaction['callback']['validation'] = $validation->toArray();

        if (! $validation->valid) {
            return $this->fail($transaction, $validation->error ?? 'AltID response could not be validated.');
        }

        if ($validation->claimValue !== true) {
            return $this->fail($transaction, 'AltID age claim was not satisfied.');
        }

        if (! $validation->cryptographicallyVerified && ! config('altid.accept_unverified_responses', false)) {
            return $this->fail($transaction, 'AltID proof was parsed, but cryptographic signature, certificate-chain and session validation is not enabled yet.');
        }

        $transaction['status'] = 'approved';
        $transaction['verified'] = true;
        $transaction['result'] = [
            'claim' => $transaction['claim'],
            'value' => $validation->claimValue,
            'validation' => $this->validationLabel($validation),
            'cryptographically_verified' => $validation->cryptographicallyVerified,
        ];
        $transaction['error'] = null;
        $transaction['verified_at'] = now()->toISOString();

        $this->put($transaction, Carbon::parse($transaction['expires_at']));

        return $transaction;
    }

    private function fail(array $transaction, string $error): array
    {
        $transaction['status'] = 'failed';
        $transaction['verified'] = false;
        $transaction['error'] = $error;
        $transaction['failed_at'] = now()->toISOString();

        $this->put($transaction, Carbon::parse($transaction['expires_at']));

        return $transaction;
    }

    private function put(array $transaction, Carbon $expiresAt): void
    {
        Cache::put($this->cacheKey($transaction['transaction_id']), $transaction, $expiresAt);
    }

    private function validationLabel(AltIdAgeVerificationResult $validation): string
    {
        if (! $validation->cryptographicallyVerified) {
            return 'mdoc_claim_structural_dev';
        }

        if (($validation->details['device_binding_verified'] ?? false) === true) {
            return 'mdoc_crypto_verified';
        }

        return 'mdoc_issuer_crypto_verified';
    }

    private function authorizationRequest(string $responseUri, string $clientId, string $state, string $nonce, string $claim): array
    {
        $dcqlQuery = [
            'credentials' => [
                [
                    'id' => $state,
                    'format' => 'mso_mdoc',
                    'meta' => [
                        'doctype_value' => config('altid.doctype', 'eu.europa.ec.av.1'),
                    ],
                    'claims' => [
                        [
                            'path' => [
                                config('altid.namespace', 'eu.europa.ec.av.1'),
                                $claim,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return [
            'response_type' => 'vp_token',
            'response_mode' => 'direct_post',
            'client_id' => $clientId,
            'response_uri' => $responseUri,
            'dcql_query' => $dcqlQuery,
            'nonce' => $nonce,
            'state' => $state,
        ];
    }

    private function authorizationUrl(array $authorizationRequest, ?string $baseUrl = null): string
    {
        $query = http_build_query([
            ...$authorizationRequest,
            'dcql_query' => json_encode($authorizationRequest['dcql_query'], JSON_UNESCAPED_SLASHES),
        ], '', '&', PHP_QUERY_RFC3986);

        $baseUrl ??= config('altid.scheme', 'av://');

        return rtrim((string) $baseUrl, '?').'?'.$query;
    }

    private function absoluteUrl(string $path): string
    {
        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }

    private function cacheKey(string $transactionId): string
    {
        return "altid:age:{$transactionId}";
    }

    private function randomToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function normalizeClaim(?string $claim): string
    {
        $claim = filled($claim) ? $claim : config('altid.age_claim', 'age_over_18');
        $allowedClaims = config('altid.age_claims', []);

        if (! in_array($claim, $allowedClaims, true)) {
            return config('altid.age_claim', 'age_over_18');
        }

        return $claim;
    }
}
