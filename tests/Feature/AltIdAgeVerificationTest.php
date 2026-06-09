<?php

use CBOR\ByteStringObject;
use CBOR\ListObject;
use CBOR\MapObject;
use CBOR\OtherObject\TrueObject;
use CBOR\Tag\CBOREncodingTag;
use CBOR\TextStringObject;
use CBOR\UnsignedIntegerObject;
use Illuminate\Support\Facades\Cache;
use Lenius\LaravelAltid\AltIdAgePresentationValidator;
use Lenius\LaravelAltid\AltIdAgeVerificationResult;

beforeEach(function () {
    Cache::store('array')->flush();
});

it('creates a cached transaction and returns an authorization url on start', function () {
    $response = $this->postJson('/api/altid/age/start');

    $response->assertOk()
        ->assertJsonPath('claim', 'age_over_18')
        ->assertJsonPath('status', 'pending')
        ->assertJsonStructure([
            'transaction_id',
            'authorization_url',
            'test_app_url',
            'authorization_request',
            'qr_code',
            'status_url',
            'expires_at',
        ]);

    $transactionId = $response->json('transaction_id');
    $authorizationUrl = $response->json('authorization_url');
    $testAppUrl = $response->json('test_app_url');

    expect($authorizationUrl)->toStartWith('av://?');
    expect($testAppUrl)->toStartWith('https://app.test.tegnebog.dk/oid4vp?');
    expect($response->json('qr_code'))->toStartWith('data:image/svg+xml;base64,');
    expect($authorizationUrl)->toContain('response_type=vp_token');
    expect($authorizationUrl)->toContain('response_mode=direct_post');
    expect($authorizationUrl)->toContain(rawurlencode('https://altid-test.example.test/api/altid/age/direct-post/'.$transactionId));
    expect($authorizationUrl)->toContain(rawurlencode('age_over_18'));
    expect($authorizationUrl)->toContain(rawurlencode('eu.europa.ec.av.1'));

    $cached = Cache::get("altid:age:{$transactionId}");
    $authorizationRequest = $response->json('authorization_request');

    expect($cached['transaction_id'])->toBe($transactionId);
    expect($cached['status'])->toBe('pending');
    expect($cached['claim'])->toBe('age_over_18');
    expect($cached['response_uri'])->toBe('https://altid-test.example.test/api/altid/age/direct-post/'.$transactionId);
    expect($cached['client_id'])->toBe('redirect_uri:'.$cached['response_uri']);
    expect($authorizationRequest['state'])->toBe($authorizationRequest['dcql_query']['credentials'][0]['id']);
    expect($authorizationRequest['nonce'])->toMatch('/^[0-9a-f-]{36}$/');
    expect($cached['state'])->not->toBeEmpty();
    expect($cached['nonce'])->not->toBeEmpty();
});

it('can request a specific supported age claim', function () {
    $response = $this->postJson('/api/altid/age/start', ['claim' => 'age_over_16']);

    $response->assertOk()
        ->assertJsonPath('claim', 'age_over_16')
        ->assertJsonPath('authorization_request.dcql_query.credentials.0.claims.0.path.1', 'age_over_16');
});

it('falls back to the default claim when an unsupported claim is requested', function () {
    $response = $this->postJson('/api/altid/age/start', ['claim' => 'age_over_99']);

    $response->assertOk()->assertJsonPath('claim', 'age_over_18');
});

it('returns the cached transaction state on status', function () {
    $start = $this->postJson('/api/altid/age/start');

    $this->getJson('/api/altid/age/'.$start->json('transaction_id').'/status')
        ->assertOk()
        ->assertJsonPath('transaction_id', $start->json('transaction_id'))
        ->assertJsonPath('status', 'pending')
        ->assertJsonPath('claim', 'age_over_18');
});

it('returns 404 on status for an unknown transaction', function () {
    $this->getJson('/api/altid/age/unknown-transaction-id/status')
        ->assertNotFound()
        ->assertJsonPath('status', 'expired')
        ->assertJsonPath('verified', false);
});

it('approves a transaction with a structurally valid mdoc when unverified responses are accepted', function () {
    $start = $this->postJson('/api/altid/age/start');
    $transaction = Cache::get('altid:age:'.$start->json('transaction_id'));

    $this->post('/api/altid/age/direct-post/'.$start->json('transaction_id'), [
        'state' => $transaction['state'],
        'vp_token' => vpTokenForTransaction($transaction),
    ])->assertOk()
        ->assertJsonPath('status', 'approved')
        ->assertJsonPath('verified', true)
        ->assertJsonPath('result.validation', 'mdoc_claim_structural_dev')
        ->assertJsonPath('result.cryptographically_verified', false);

    $this->getJson('/api/altid/age/'.$start->json('transaction_id').'/status')
        ->assertOk()
        ->assertJsonPath('status', 'approved')
        ->assertJsonPath('verified', true);
});

it('rejects a structurally valid mdoc when crypto validation is required', function () {
    config(['altid.accept_unverified_responses' => false]);

    $start = $this->postJson('/api/altid/age/start');
    $transaction = Cache::get('altid:age:'.$start->json('transaction_id'));

    $this->post('/api/altid/age/direct-post/'.$start->json('transaction_id'), [
        'state' => $transaction['state'],
        'vp_token' => vpTokenForTransaction($transaction),
    ])->assertStatus(400)
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('verified', false);
});

it('rejects a direct post with a mismatched state', function () {
    $start = $this->postJson('/api/altid/age/start');

    $this->post('/api/altid/age/direct-post/'.$start->json('transaction_id'), [
        'state' => 'wrong-state',
        'vp_token' => 'sample-vp-token',
    ])->assertStatus(400)
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('verified', false);

    $this->getJson('/api/altid/age/'.$start->json('transaction_id').'/status')
        ->assertOk()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('verified', false);
});

it('rejects a direct post without a vp_token', function () {
    $start = $this->postJson('/api/altid/age/start');
    $transaction = Cache::get('altid:age:'.$start->json('transaction_id'));

    $this->post('/api/altid/age/direct-post/'.$start->json('transaction_id'), [
        'state' => $transaction['state'],
    ])->assertStatus(400)
        ->assertJsonPath('status', 'failed');
});

it('exposes altid error description from the direct post payload', function () {
    $start = $this->postJson('/api/altid/age/start');
    $transaction = Cache::get('altid:age:'.$start->json('transaction_id'));

    $this->post('/api/altid/age/direct-post/'.$start->json('transaction_id'), [
        'state' => $transaction['state'],
        'error' => 'access_denied',
        'error_description' => 'The user denied the request.',
    ])->assertStatus(400)
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('error', 'AltID returned an error: The user denied the request.');
});

it('does not include callback in status response when debug is disabled', function () {
    $start = $this->postJson('/api/altid/age/start');
    $transaction = Cache::get('altid:age:'.$start->json('transaction_id'));

    $this->post('/api/altid/age/direct-post/'.$start->json('transaction_id'), [
        'state' => $transaction['state'],
        'vp_token' => vpTokenForTransaction($transaction),
    ])->assertOk();

    $response = $this->getJson('/api/altid/age/'.$start->json('transaction_id').'/status')
        ->assertOk()
        ->assertJsonPath('validation.valid', true)
        ->assertJsonPath('validation.details.doctype', 'eu.europa.ec.av.1')
        ->assertJsonPath('validation.details.claim', 'age_over_18');

    expect($response->json())->not->toHaveKey('callback');
});

it('includes callback in status response when debug is enabled', function () {
    config(['altid.debug' => true]);

    $start = $this->postJson('/api/altid/age/start');
    $transaction = Cache::get('altid:age:'.$start->json('transaction_id'));

    $this->post('/api/altid/age/direct-post/'.$start->json('transaction_id'), [
        'state' => $transaction['state'],
        'vp_token' => vpTokenForTransaction($transaction),
    ])->assertOk();

    $response = $this->getJson('/api/altid/age/'.$start->json('transaction_id').'/status')
        ->assertOk()
        ->assertJsonStructure(['callback' => ['payload_keys']]);

    expect($response->json('callback.payload_keys'))->toContain('state');
    expect($response->json('callback.payload_keys'))->toContain('vp_token');
});

it('passes the full callback payload including apu to the validator', function () {
    $captured = [];

    app()->instance(AltIdAgePresentationValidator::class, new class($captured) implements AltIdAgePresentationValidator
    {
        public function __construct(private array &$captured) {}

        public function validate(mixed $vpToken, array $transaction, array $payload = []): AltIdAgeVerificationResult
        {
            $this->captured = $payload;

            return AltIdAgeVerificationResult::structurallyValid(true);
        }
    });

    $start = $this->postJson('/api/altid/age/start');
    $transaction = Cache::get('altid:age:'.$start->json('transaction_id'));

    $this->post('/api/altid/age/direct-post/'.$start->json('transaction_id'), [
        'state' => $transaction['state'],
        'vp_token' => 'token',
        'apu' => base64UrlEncode('wallet-nonce'),
    ])->assertOk();

    expect($captured['vp_token'])->toBe('token');
    expect($captured['apu'])->toBe(base64UrlEncode('wallet-nonce'));
});

// --- helpers ---

function vpTokenForTransaction(array $transaction): string
{
    return json_encode([
        $transaction['state'] => [
            base64UrlEncode((string) deviceResponse($transaction['claim'])),
        ],
    ], JSON_THROW_ON_ERROR);
}

function deviceResponse(string $claim): MapObject
{
    return MapObject::create()
        ->add(TextStringObject::create('version'), TextStringObject::create('1.0'))
        ->add(TextStringObject::create('documents'), ListObject::create([
            MapObject::create()
                ->add(TextStringObject::create('docType'), TextStringObject::create('eu.europa.ec.av.1'))
                ->add(TextStringObject::create('issuerSigned'), MapObject::create()
                    ->add(TextStringObject::create('nameSpaces'), MapObject::create()
                        ->add(TextStringObject::create('eu.europa.ec.av.1'), ListObject::create([
                            CBOREncodingTag::create(ByteStringObject::create((string) issuerSignedItem($claim))),
                        ])))
                    ->add(TextStringObject::create('issuerAuth'), ListObject::create()))
                ->add(TextStringObject::create('deviceSigned'), MapObject::create()),
        ]))
        ->add(TextStringObject::create('status'), UnsignedIntegerObject::create(0));
}

function issuerSignedItem(string $claim): MapObject
{
    return MapObject::create()
        ->add(TextStringObject::create('digestID'), UnsignedIntegerObject::create(1))
        ->add(TextStringObject::create('random'), ByteStringObject::create(random_bytes(16)))
        ->add(TextStringObject::create('elementIdentifier'), TextStringObject::create($claim))
        ->add(TextStringObject::create('elementValue'), TrueObject::create());
}

function base64UrlEncode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}
