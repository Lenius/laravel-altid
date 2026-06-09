<?php

namespace Lenius\LaravelAltid;

interface AltIdAgePresentationValidator
{
    public function validate(mixed $vpToken, array $transaction, array $payload = []): AltIdAgeVerificationResult;
}
