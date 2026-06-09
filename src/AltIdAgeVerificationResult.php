<?php

namespace Lenius\LaravelAltid;

class AltIdAgeVerificationResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly bool $cryptographicallyVerified,
        public readonly ?bool $claimValue,
        public readonly ?string $error = null,
        public readonly array $details = [],
    ) {}

    public static function fail(string $error, array $details = []): self
    {
        return new self(false, false, null, $error, $details);
    }

    public static function structurallyValid(bool $claimValue, array $details = []): self
    {
        return new self(true, false, $claimValue, null, $details);
    }

    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'cryptographically_verified' => $this->cryptographicallyVerified,
            'claim_value' => $this->claimValue,
            'error' => $this->error,
            'details' => $this->details,
        ];
    }
}
