<?php

namespace Lenius\LaravelAltid\Facades;

use Illuminate\Support\Facades\Facade;
use Lenius\LaravelAltid\AltIdAgeVerificationService;

/**
 * @method static array start(?string $claim = null)
 * @method static array|null find(string $transactionId)
 * @method static array complete(string $transactionId, array $payload)
 *
 * @see AltIdAgeVerificationService
 */
class LaravelAltid extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AltIdAgeVerificationService::class;
    }
}
