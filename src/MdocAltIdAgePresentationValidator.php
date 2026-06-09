<?php

namespace Lenius\LaravelAltid;

use CBOR\ByteStringObject;
use CBOR\CBORObject;
use CBOR\Decoder;
use CBOR\IndefiniteLengthByteStringObject;
use CBOR\ListObject;
use CBOR\MapObject;
use CBOR\Normalizable;
use CBOR\OtherObject\NullObject;
use CBOR\StringStream;
use CBOR\Tag\CBOREncodingTag;
use CBOR\Tag\GenericTag;
use CBOR\TextStringObject;
use Cose\Key\Ec2Key;
use Cose\Signature\Signature1;
use DateTimeInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use OpenSSLAsymmetricKey;
use Throwable;

class MdocAltIdAgePresentationValidator implements AltIdAgePresentationValidator
{
    private Decoder $decoder;

    public function __construct()
    {
        $this->decoder = Decoder::create();
    }

    public function validate(mixed $vpToken, array $transaction, array $payload = []): AltIdAgeVerificationResult
    {
        try {
            $presentations = $this->presentations($vpToken);
            $credentialId = (string) $transaction['state'];
            $claim = (string) $transaction['claim'];

            if (! array_key_exists($credentialId, $presentations)) {
                return AltIdAgeVerificationResult::fail('AltID vp_token did not contain the requested credential id.', [
                    'expected_credential_id' => $credentialId,
                    'credential_ids' => array_keys($presentations),
                ]);
            }

            $deviceResponses = Arr::wrap($presentations[$credentialId]);

            foreach ($deviceResponses as $deviceResponse) {
                if (! is_string($deviceResponse) || $deviceResponse === '') {
                    continue;
                }

                $result = $this->validateDeviceResponse($deviceResponse, $claim, $transaction, $payload);

                if ($result->valid) {
                    return $result;
                }
            }

            return AltIdAgeVerificationResult::fail('AltID vp_token did not include a valid mdoc DeviceResponse for the requested age claim.');
        } catch (Throwable $exception) {
            return AltIdAgeVerificationResult::fail('AltID vp_token could not be parsed: '.$exception->getMessage());
        }
    }

    private function validateDeviceResponse(string $encodedDeviceResponse, string $claim, array $transaction, array $payload): AltIdAgeVerificationResult
    {
        $deviceResponse = $this->decode($this->base64UrlDecode($encodedDeviceResponse));

        if (! $deviceResponse instanceof MapObject) {
            return AltIdAgeVerificationResult::fail('AltID DeviceResponse was not a CBOR map.');
        }

        $status = (int) $this->normalize($this->mapGet($deviceResponse, 'status'));

        if ($status !== 0) {
            return AltIdAgeVerificationResult::fail('AltID DeviceResponse status was not successful.', [
                'device_response_status' => $status,
            ]);
        }

        $documents = $this->mapGet($deviceResponse, 'documents');

        if (! $documents instanceof ListObject) {
            return AltIdAgeVerificationResult::fail('AltID DeviceResponse did not include documents.');
        }

        foreach ($documents as $document) {
            if (! $document instanceof MapObject) {
                continue;
            }

            $docType = $this->normalize($this->mapGet($document, 'docType'));

            if ($docType !== config('altid.doctype', 'eu.europa.ec.av.1')) {
                continue;
            }

            $claimResult = $this->claimFromDocument($document, $claim);

            if ($claimResult !== null) {
                $issuerResult = $this->verifyIssuerSignedDocument($document, $claimResult, $transaction, $payload);
                $cryptographicallyVerified = $issuerResult['issuer_signature_verified']
                    && $issuerResult['claim_digest_verified']
                    && $issuerResult['issuer_certificate_valid_at_callback'];
                $deviceBindingRequired = (bool) config('altid.require_device_binding', false);
                $deviceBindingSatisfied = ! $deviceBindingRequired || $issuerResult['device_binding_verified'];
                $nonceReplaySatisfied = ! $deviceBindingRequired || $issuerResult['mdoc_generated_nonce_not_replayed'];
                $cryptographicallyVerified = $cryptographicallyVerified
                    && $issuerResult['issuer_certificate_chain_trusted']
                    && $issuerResult['mso_validity_verified']
                    && $issuerResult['mso_doctype_verified']
                    && $deviceBindingSatisfied
                    && $nonceReplaySatisfied;

                return new AltIdAgeVerificationResult(true, $cryptographicallyVerified, $claimResult['value'], null, [
                    'doctype' => $docType,
                    'claim' => $claim,
                    'mdoc_status' => $status,
                    'issuer_signed_item_found' => true,
                    'issuer_auth_present' => $claimResult['issuer_auth_present'],
                    'device_signed_present' => $document->has('deviceSigned'),
                    ...$issuerResult,
                ]);
            }
        }

        return AltIdAgeVerificationResult::fail('AltID DeviceResponse did not contain the requested age claim.', [
            'claim' => $claim,
        ]);
    }

    private function claimFromDocument(MapObject $document, string $claim): ?array
    {
        $issuerSigned = $this->mapGet($document, 'issuerSigned');

        if (! $issuerSigned instanceof MapObject || ! $issuerSigned->has('nameSpaces')) {
            return null;
        }

        $nameSpaces = $this->mapGet($issuerSigned, 'nameSpaces');
        $namespace = config('altid.namespace', 'eu.europa.ec.av.1');

        if (! $nameSpaces instanceof MapObject || ! $nameSpaces->has($namespace)) {
            return null;
        }

        $issuerSignedItems = $this->mapGet($nameSpaces, $namespace);

        if (! $issuerSignedItems instanceof ListObject) {
            return null;
        }

        foreach ($issuerSignedItems as $issuerSignedItemBytes) {
            $issuerSignedItem = $this->decodeIssuerSignedItem($issuerSignedItemBytes);

            if (! $issuerSignedItem instanceof MapObject) {
                continue;
            }

            if ($this->normalize($this->mapGet($issuerSignedItem, 'elementIdentifier')) !== $claim) {
                continue;
            }

            return [
                'value' => $this->normalize($this->mapGet($issuerSignedItem, 'elementValue')) === true,
                'issuer_auth_present' => $issuerSigned->has('issuerAuth'),
                'issuer_signed_item_bytes' => (string) $issuerSignedItemBytes,
                'digest_id' => (int) $this->normalize($this->mapGet($issuerSignedItem, 'digestID')),
            ];
        }

        return null;
    }

    private function decodeIssuerSignedItem(CBORObject $object): ?CBORObject
    {
        if (! $object instanceof CBOREncodingTag && ! $object instanceof GenericTag) {
            return null;
        }

        $encoded = $object->getValue();

        if (! $encoded instanceof ByteStringObject) {
            return null;
        }

        return $this->decode($encoded->getValue());
    }

    private function verifyIssuerSignedDocument(MapObject $document, array $claimResult, array $transaction, array $payload): array
    {
        $base = [
            'issuer_signature_verified' => false,
            'claim_digest_verified' => false,
            'issuer_certificate_valid_at_callback' => false,
            'issuer_certificate_chain_trusted' => false,
            'issuer_certificate_system_trusted' => false,
            'issuer_certificate_configured_trust_anchor_matched' => false,
            'issuer_certificate_subject' => null,
            'issuer_certificate_chain_subjects' => [],
            'issuer_certificate_chain_fingerprints' => [],
            'mso_validity_verified' => false,
            'mso_doctype_verified' => false,
            'device_binding_verified' => false,
            'device_binding_required' => (bool) config('altid.require_device_binding', false),
            'mdoc_generated_nonce_not_replayed' => false,
            'validation_level' => 'mdoc_claim_structural',
            'crypto_note' => 'Full verification requires issuer chain trust, MSO validity and DeviceAuth/session transcript.',
        ];

        try {
            $issuerSigned = $this->mapGet($document, 'issuerSigned');

            if (! $issuerSigned instanceof MapObject || ! $issuerSigned->has('issuerAuth')) {
                return $base + ['issuer_crypto_error' => 'issuerAuth was not present.'];
            }

            $issuerAuth = $this->mapGet($issuerSigned, 'issuerAuth');
            $cose = $this->coseSign1Parts($issuerAuth);
            $certificate = $this->certificateChainFromHeaders($cose['protected_header'], $cose['unprotected_header']);
            $mobileSecurityObject = $this->mobileSecurityObject($cose['payload']);
            $signatureVerified = $this->verifyCoseSignature($cose, $certificate['leaf_pem']);
            $digestVerified = $this->verifyClaimDigest($mobileSecurityObject, $claimResult);
            $msoValidity = $this->verifyMsoValidity($mobileSecurityObject, $certificate);
            $msoDocTypeVerified = $this->normalize($this->mapGet($mobileSecurityObject, 'docType')) === config('altid.doctype', 'eu.europa.ec.av.1');
            $deviceResult = $this->verifyDeviceBinding($document, $mobileSecurityObject, $transaction, $payload, $msoValidity['valid_until']);

            return [
                ...$base,
                'issuer_signature_verified' => $signatureVerified,
                'claim_digest_verified' => $digestVerified,
                'issuer_certificate_valid_at_callback' => $certificate['leaf_valid_now'],
                'issuer_certificate_chain_trusted' => $certificate['chain_trusted'],
                'issuer_certificate_system_trusted' => $certificate['system_trusted'],
                'issuer_certificate_configured_trust_anchor_matched' => $certificate['configured_trust_anchor_matched'],
                'issuer_certificate_subject' => $certificate['leaf_subject'],
                'issuer_certificate_chain_subjects' => $certificate['subjects'],
                'issuer_certificate_chain_fingerprints' => $certificate['fingerprints'],
                'mso_validity_verified' => $msoValidity['verified'],
                'mso_valid_from' => $msoValidity['valid_from']?->format(DATE_ATOM),
                'mso_valid_until' => $msoValidity['valid_until']?->format(DATE_ATOM),
                'mso_signed_at' => $msoValidity['signed_at']?->format(DATE_ATOM),
                'mso_doctype_verified' => $msoDocTypeVerified,
                ...$deviceResult,
                'issuer_digest_algorithm' => $this->normalize($this->mapGet($mobileSecurityObject, 'digestAlgorithm')),
                'validation_level' => $signatureVerified && $digestVerified ? 'mdoc_issuer_crypto' : 'mdoc_claim_structural',
            ];
        } catch (Throwable $exception) {
            return $base + [
                'issuer_crypto_error' => $exception->getMessage(),
            ];
        }
    }

    private function coseSign1Parts(CBORObject $issuerAuth, bool $payloadCanBeNull = false): array
    {
        if ($issuerAuth instanceof GenericTag || $issuerAuth instanceof CBOREncodingTag) {
            $issuerAuth = $issuerAuth->getValue();
        }

        if (! $issuerAuth instanceof ListObject || $issuerAuth->count() !== 4) {
            throw new InvalidArgumentException('issuerAuth was not a COSE_Sign1 array.');
        }

        $protectedHeader = $issuerAuth->get(0);
        $unprotectedHeader = $issuerAuth->get(1);
        $payload = $issuerAuth->get(2);
        $signature = $issuerAuth->get(3);

        if (! $protectedHeader instanceof ByteStringObject) {
            throw new InvalidArgumentException('issuerAuth protected header was not a byte string.');
        }

        if (! $unprotectedHeader instanceof MapObject) {
            throw new InvalidArgumentException('issuerAuth unprotected header was not a map.');
        }

        if (! $payloadCanBeNull && ! $payload instanceof ByteStringObject) {
            throw new InvalidArgumentException('issuerAuth payload was not a byte string.');
        }

        if ($payloadCanBeNull && ! $payload instanceof ByteStringObject && ! $payload instanceof NullObject) {
            throw new InvalidArgumentException('COSE_Sign1 payload was neither byte string nor null.');
        }

        if (! $signature instanceof ByteStringObject) {
            throw new InvalidArgumentException('issuerAuth signature was not a byte string.');
        }

        $protectedHeaderMap = $this->decode($protectedHeader->getValue());

        if (! $protectedHeaderMap instanceof MapObject) {
            throw new InvalidArgumentException('issuerAuth protected header did not decode to a map.');
        }

        return [
            'protected_header_bytes' => $protectedHeader,
            'protected_header' => $protectedHeaderMap,
            'unprotected_header' => $unprotectedHeader,
            'payload' => $payload,
            'signature' => $signature,
        ];
    }

    private function certificateChainFromHeaders(MapObject $protectedHeader, MapObject $unprotectedHeader): array
    {
        $x5chain = $unprotectedHeader->has(33) ? $this->mapGet($unprotectedHeader, 33) : null;
        $x5chain ??= $protectedHeader->has(33) ? $this->mapGet($protectedHeader, 33) : null;
        $certificateObjects = $x5chain instanceof ListObject ? iterator_to_array($x5chain) : [$x5chain];

        $certificates = array_map(fn (CBORObject $certificate): array => $this->certificateFromDer($this->bytes($certificate)), $certificateObjects);

        if ($certificates === []) {
            throw new InvalidArgumentException('issuerAuth x5chain was empty.');
        }

        $systemTrusted = $this->verifySystemCertificateChain($certificates);
        $configuredTrustAnchorMatched = $this->verifyConfiguredCertificateChain($certificates);

        return [
            'leaf_pem' => $certificates[0]['pem'],
            'leaf_subject' => $certificates[0]['subject'],
            'leaf_valid_now' => $certificates[0]['valid_now'],
            'chain_trusted' => $systemTrusted || $configuredTrustAnchorMatched,
            'system_trusted' => $systemTrusted,
            'configured_trust_anchor_matched' => $configuredTrustAnchorMatched,
            'subjects' => array_column($certificates, 'subject'),
            'fingerprints' => array_column($certificates, 'fingerprint'),
            'valid_from' => $certificates[0]['valid_from'],
            'valid_to' => $certificates[0]['valid_to'],
        ];
    }

    private function certificateFromDer(string $der): array
    {
        $pem = "-----BEGIN CERTIFICATE-----\n"
            .chunk_split(base64_encode($der), 64, "\n")
            ."-----END CERTIFICATE-----\n";
        $parsed = openssl_x509_parse($pem);

        if (! is_array($parsed)) {
            throw new InvalidArgumentException('issuerAuth x5chain certificate could not be parsed.');
        }

        $now = time();
        $subject = $parsed['subject'] ?? [];

        return [
            'pem' => $pem,
            'parsed' => $parsed,
            'valid_now' => ($parsed['validFrom_time_t'] ?? 0) <= $now && ($parsed['validTo_time_t'] ?? 0) >= $now,
            'valid_from' => Carbon::createFromTimestamp($parsed['validFrom_time_t'] ?? 0),
            'valid_to' => Carbon::createFromTimestamp($parsed['validTo_time_t'] ?? 0),
            'fingerprint' => strtolower(str_replace(':', '', openssl_x509_fingerprint($pem, 'sha256') ?: '')),
            'subject' => is_array($subject) ? ($subject['CN'] ?? null) : null,
        ];
    }

    private function verifyConfiguredCertificateChain(array $certificates): bool
    {
        $trustedFingerprints = config('altid.trust_anchor_fingerprints', []);

        if ($trustedFingerprints === []) {
            return false;
        }

        if (count($certificates) === 1) {
            return in_array($certificates[0]['fingerprint'], $trustedFingerprints, true);
        }

        foreach ($certificates as $index => $certificate) {
            $issuer = $certificates[$index + 1] ?? $certificate;
            $publicKey = openssl_pkey_get_public($issuer['pem']);

            if (! $publicKey instanceof OpenSSLAsymmetricKey || openssl_x509_verify($certificate['pem'], $publicKey) !== 1) {
                return false;
            }
        }

        return in_array($certificates[array_key_last($certificates)]['fingerprint'], $trustedFingerprints, true);
    }

    private function verifySystemCertificateChain(array $certificates): bool
    {
        if (count($certificates) !== 1) {
            return false;
        }

        return openssl_x509_checkpurpose($certificates[0]['pem'], X509_PURPOSE_ANY) === true;
    }

    private function mobileSecurityObject(ByteStringObject $payload): MapObject
    {
        $decoded = $this->decode($payload->getValue());

        if ($decoded instanceof CBOREncodingTag || $decoded instanceof GenericTag) {
            $encoded = $decoded->getValue();

            if (! $encoded instanceof ByteStringObject) {
                throw new InvalidArgumentException('MobileSecurityObject tag did not contain bytes.');
            }

            $decoded = $this->decode($encoded->getValue());
        }

        if (! $decoded instanceof MapObject) {
            throw new InvalidArgumentException('MobileSecurityObject did not decode to a map.');
        }

        return $decoded;
    }

    private function verifyCoseSignature(array $cose, string $certificatePem): bool
    {
        $alg = (int) $this->normalize($this->mapGet($cose['protected_header'], 1));

        if ($alg !== -7) {
            throw new InvalidArgumentException("Unsupported issuerAuth COSE alg {$alg}.");
        }

        $publicKey = openssl_pkey_get_public($certificatePem);

        if ($publicKey === false) {
            throw new InvalidArgumentException('Could not read public key from issuerAuth certificate.');
        }

        $signatureStructure = (string) Signature1::create($cose['protected_header_bytes'], $cose['payload']);
        $signature = $this->ecdsaRawToDer($cose['signature']->getValue());

        return openssl_verify($signatureStructure, $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    private function verifyClaimDigest(MapObject $mobileSecurityObject, array $claimResult): bool
    {
        $digestAlgorithm = strtolower(str_replace('-', '', (string) $this->normalize($this->mapGet($mobileSecurityObject, 'digestAlgorithm'))));

        if (! in_array($digestAlgorithm, hash_algos(), true)) {
            throw new InvalidArgumentException("Unsupported MSO digest algorithm {$digestAlgorithm}.");
        }

        $valueDigests = $this->mapGet($mobileSecurityObject, 'valueDigests');
        $namespace = config('altid.namespace', 'eu.europa.ec.av.1');

        if (! $valueDigests instanceof MapObject || ! $valueDigests->has($namespace)) {
            throw new InvalidArgumentException('MSO valueDigests did not contain the AltID namespace.');
        }

        $namespaceDigests = $this->mapGet($valueDigests, $namespace);

        if (! $namespaceDigests instanceof MapObject || ! $namespaceDigests->has($claimResult['digest_id'])) {
            throw new InvalidArgumentException('MSO valueDigests did not contain the claim digest id.');
        }

        $expectedDigest = $this->bytes($this->mapGet($namespaceDigests, $claimResult['digest_id']));
        $actualDigest = hash($digestAlgorithm, $claimResult['issuer_signed_item_bytes'], true);

        return hash_equals($expectedDigest, $actualDigest);
    }

    private function verifyMsoValidity(MapObject $mobileSecurityObject, array $certificate): array
    {
        $validityInfo = $this->mapGet($mobileSecurityObject, 'validityInfo');

        if (! $validityInfo instanceof MapObject) {
            throw new InvalidArgumentException('MSO validityInfo was not a map.');
        }

        $signedAt = $this->dateTime($this->mapGet($validityInfo, 'signed'));
        $validFrom = $this->dateTime($this->mapGet($validityInfo, 'validFrom'));
        $validUntil = $this->dateTime($this->mapGet($validityInfo, 'validUntil'));
        $now = now();

        return [
            'verified' => $signedAt >= $certificate['valid_from']
                && $signedAt <= $certificate['valid_to']
                && $validFrom <= $now
                && $validUntil >= $now,
            'signed_at' => $signedAt,
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
        ];
    }

    private function verifyDeviceBinding(MapObject $document, MapObject $mobileSecurityObject, array $transaction, array $payload, ?DateTimeInterface $validUntil): array
    {
        $base = [
            'device_binding_verified' => false,
            'mdoc_generated_nonce_not_replayed' => false,
            'mdoc_generated_nonce_hash' => null,
        ];

        if (! filled($payload['apu'] ?? null)) {
            return $base + ['device_binding_error' => 'Callback did not include apu, so mdocGeneratedNonce is unavailable.'];
        }

        try {
            $mdocGeneratedNonce = $this->base64UrlDecode((string) $payload['apu']);
            $deviceSigned = $this->mapGet($document, 'deviceSigned');

            if (! $deviceSigned instanceof MapObject) {
                throw new InvalidArgumentException('deviceSigned was not a map.');
            }

            $deviceAuth = $this->mapGet($deviceSigned, 'deviceAuth');

            if (! $deviceAuth instanceof MapObject || ! $deviceAuth->has('deviceSignature')) {
                throw new InvalidArgumentException('deviceAuth.deviceSignature was not present.');
            }

            $deviceSignature = $this->coseSign1Parts($this->mapGet($deviceAuth, 'deviceSignature'), true);
            $deviceAuthenticationBytes = $this->deviceAuthenticationBytes($document, $deviceSigned, $transaction, $mdocGeneratedNonce);
            $deviceKey = $this->deviceKey($mobileSecurityObject);
            $deviceBindingVerified = $this->verifyDetachedCoseSignature($deviceSignature, $deviceAuthenticationBytes, $deviceKey);
            $nonceNotReplayed = $deviceBindingVerified && $this->rememberMdocGeneratedNonce($mdocGeneratedNonce, $validUntil);

            return [
                ...$base,
                'device_binding_verified' => $deviceBindingVerified,
                'mdoc_generated_nonce_not_replayed' => $nonceNotReplayed,
                'mdoc_generated_nonce_hash' => hash('sha256', $mdocGeneratedNonce),
            ];
        } catch (Throwable $exception) {
            return $base + [
                'device_binding_error' => $exception->getMessage(),
            ];
        }
    }

    private function deviceAuthenticationBytes(MapObject $document, MapObject $deviceSigned, array $transaction, string $mdocGeneratedNonce): string
    {
        $clientIdToHash = ListObject::create([
            TextStringObject::create((string) $transaction['client_id']),
            TextStringObject::create($mdocGeneratedNonce),
        ]);
        $responseUriToHash = ListObject::create([
            TextStringObject::create((string) $transaction['response_uri']),
            TextStringObject::create($mdocGeneratedNonce),
        ]);
        $handover = ListObject::create([
            ByteStringObject::create(hash('sha256', (string) $clientIdToHash, true)),
            ByteStringObject::create(hash('sha256', (string) $responseUriToHash, true)),
            TextStringObject::create((string) $transaction['nonce']),
        ]);
        $sessionTranscript = ListObject::create([
            NullObject::create(),
            NullObject::create(),
            $handover,
        ]);
        $deviceNameSpacesBytes = $deviceSigned->has('nameSpaces')
            ? $this->mapGet($deviceSigned, 'nameSpaces')
            : CBOREncodingTag::create(ByteStringObject::create((string) MapObject::create()));

        $deviceAuthentication = ListObject::create([
            TextStringObject::create('DeviceAuthentication'),
            $sessionTranscript,
            $this->mapGet($document, 'docType'),
            $deviceNameSpacesBytes,
        ]);

        return (string) CBOREncodingTag::create(ByteStringObject::create((string) $deviceAuthentication));
    }

    private function deviceKey(MapObject $mobileSecurityObject): Ec2Key
    {
        $deviceKeyInfo = $this->mapGet($mobileSecurityObject, 'deviceKeyInfo');

        if (! $deviceKeyInfo instanceof MapObject) {
            throw new InvalidArgumentException('MSO deviceKeyInfo was not a map.');
        }

        $deviceKey = $this->mapGet($deviceKeyInfo, 'deviceKey');

        if (! $deviceKey instanceof MapObject) {
            throw new InvalidArgumentException('MSO deviceKeyInfo.deviceKey was not a map.');
        }

        return Ec2Key::create($this->coseKeyData($deviceKey));
    }

    private function verifyDetachedCoseSignature(array $cose, string $payload, Ec2Key $key): bool
    {
        $alg = (int) $this->normalize($this->mapGet($cose['protected_header'], 1));

        if ($alg !== -7) {
            throw new InvalidArgumentException("Unsupported DeviceAuth COSE alg {$alg}.");
        }

        $signatureStructure = (string) Signature1::create($cose['protected_header_bytes'], ByteStringObject::create($payload));
        $signature = $this->ecdsaRawToDer($cose['signature']->getValue());

        return openssl_verify($signatureStructure, $signature, $key->toPublic()->asPEM(), OPENSSL_ALGO_SHA256) === 1;
    }

    private function rememberMdocGeneratedNonce(string $mdocGeneratedNonce, ?DateTimeInterface $validUntil): bool
    {
        $expiresAt = $validUntil instanceof DateTimeInterface ? Carbon::instance($validUntil) : now()->addMinutes(15);

        return Cache::add('altid:mdoc-generated-nonce:'.hash('sha256', $mdocGeneratedNonce), true, $expiresAt);
    }

    private function coseKeyData(MapObject $key): array
    {
        $data = [];

        foreach ($key as $item) {
            $mapKey = (int) $this->normalize($item->getKey());
            $value = $this->normalize($item->getValue());
            $data[$mapKey] = is_numeric($value) ? (int) $value : $value;
        }

        return $data;
    }

    private function dateTime(CBORObject $object): Carbon
    {
        $dateTime = $this->normalize($object);

        if (! $dateTime instanceof DateTimeInterface) {
            throw new InvalidArgumentException('Expected CBOR tdate value.');
        }

        return Carbon::instance($dateTime);
    }

    private function presentations(mixed $vpToken): array
    {
        if (is_array($vpToken)) {
            return $vpToken;
        }

        if (! is_string($vpToken) || $vpToken === '') {
            return [];
        }

        $decoded = json_decode($vpToken, true);

        if (! is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    private function decode(string $bytes): CBORObject
    {
        return $this->decoder->decode(new StringStream($bytes));
    }

    private function mapGet(MapObject $map, string|int $key): CBORObject
    {
        return $map->get($key);
    }

    private function normalize(CBORObject $object): mixed
    {
        return $object instanceof Normalizable ? $object->normalize() : $object;
    }

    private function bytes(?CBORObject $object): string
    {
        if ($object instanceof ByteStringObject || $object instanceof IndefiniteLengthByteStringObject) {
            return $object->getValue();
        }

        throw new InvalidArgumentException('Expected a CBOR byte string.');
    }

    private function ecdsaRawToDer(string $signature): string
    {
        if (strlen($signature) !== 64) {
            throw new InvalidArgumentException('ES256 signature must be 64 raw bytes.');
        }

        return $this->derSequence(
            $this->derInteger(substr($signature, 0, 32)).
            $this->derInteger(substr($signature, 32, 32))
        );
    }

    private function derInteger(string $integer): string
    {
        $integer = ltrim($integer, "\x00");
        $integer = $integer === '' ? "\x00" : $integer;

        if ((ord($integer[0]) & 0x80) !== 0) {
            $integer = "\x00".$integer;
        }

        return "\x02".$this->derLength(strlen($integer)).$integer;
    }

    private function derSequence(string $contents): string
    {
        return "\x30".$this->derLength(strlen($contents)).$contents;
    }

    private function derLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $bytes = '';

        while ($length > 0) {
            $bytes = chr($length & 0xFF).$bytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($bytes)).$bytes;
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;

        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new InvalidArgumentException('Invalid base64url data.');
        }

        return $decoded;
    }
}
