<?php

namespace Lenius\LaravelAltid\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Lenius\LaravelAltid\AltIdAgeVerificationService;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AltIdAgeVerificationController extends Controller
{
    public function __construct(
        private readonly AltIdAgeVerificationService $altIdAgeVerification,
    ) {}

    public function start(Request $request): JsonResponse
    {
        $transaction = $this->altIdAgeVerification->start($request->string('claim')->toString());

        return response()->json($this->publicTransaction($transaction) + [
            'authorization_url' => $transaction['authorization_url'],
            'test_app_url' => $transaction['test_app_url'],
            'authorization_request' => $transaction['authorization_request'],
            'qr_code' => 'data:image/svg+xml;base64,'.base64_encode(
                QrCode::format('svg')->size(360)->margin(1)->generate($transaction['authorization_url'])
            ),
            'status_url' => $transaction['status_url'],
            'expires_at' => $transaction['expires_at'],
        ]);
    }

    public function status(string $transactionId): JsonResponse
    {
        $transaction = $this->altIdAgeVerification->find($transactionId);

        if ($transaction === null) {
            return response()->json([
                'transaction_id' => $transactionId,
                'status' => 'expired',
                'verified' => false,
                'error' => 'Transaction was not found or has expired.',
            ], 404);
        }

        return response()->json($this->publicTransaction($transaction));
    }

    public function directPost(Request $request, string $transactionId): JsonResponse
    {
        $transaction = $this->altIdAgeVerification->complete($transactionId, $this->directPostPayload($request));
        $statusCode = $transaction['status'] === 'approved' ? 200 : 400;

        return response()->json($this->publicTransaction($transaction), $statusCode);
    }

    private function directPostPayload(Request $request): array
    {
        $payload = $request->all();
        $rawBody = $request->getContent();

        if ($payload === [] && $rawBody !== '') {
            parse_str($rawBody, $parsedBody);
            $payload = is_array($parsedBody) ? $parsedBody : [];
        }

        $payload['_request_meta'] = [
            'content_type' => $request->headers->get('content-type'),
            'parsed_keys' => array_values(array_filter(array_keys($payload), fn (string $key): bool => $key !== '_request_meta')),
            'raw_body_preview' => mb_substr($rawBody, 0, 1000),
        ];

        return $payload;
    }

    private function publicTransaction(array $transaction): array
    {
        $public = [
            'transaction_id' => $transaction['transaction_id'],
            'status' => $transaction['status'],
            'verified' => (bool) ($transaction['verified'] ?? false),
            'claim' => $transaction['claim'] ?? null,
            'result' => $transaction['result'] ?? null,
            'error' => $transaction['error'] ?? null,
        ];

        if (config('altid.debug') && isset($transaction['callback'])) {
            $public['callback'] = $transaction['callback'];
        }

        if (isset($transaction['callback']['validation'])) {
            $public['validation'] = $transaction['callback']['validation'];
        }

        return $public;
    }
}
