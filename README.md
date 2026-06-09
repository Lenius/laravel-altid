# laravel-altid

[![Latest Version on Packagist](https://img.shields.io/packagist/v/lenius/laravel-altid.svg?style=flat-square)](https://packagist.org/packages/lenius/laravel-altid)
[![GitHub Tests Action Status](https://github.com/lenius/laravel-altid/actions/workflows/run-tests.yml/badge.svg)](https://github.com/lenius/laravel-altid/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://github.com/lenius/laravel-altid/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/lenius/laravel-altid/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/lenius/laravel-altid.svg?style=flat-square)](https://packagist.org/packages/lenius/laravel-altid)

A Laravel package for integrating with [AltID](https://www.digitaliser.dk/altid) — Danmarks officielle app til digitalt ID og aldersverifikation.

AltID is a digital identity wallet built on the [eIDAS2](https://digst.dk/it-loesninger/altid/) regulation, enabling citizens to securely share verified credentials. This package implements the OID4VP (OpenID for Verifiable Presentations) flow with mdoc/ISO 18013-5 credentials to receive age verification responses from the AltID app.

> **Note:** This package currently only supports **age verification**. Support for full digital ID (identification) is planned for a future release.

## Requirements

- PHP 8.4+
- Laravel 11, 12 or 13
- A cache driver (Redis recommended for production)

## Installation

```bash
composer require lenius/laravel-altid
```

Publish the config file:

```bash
php artisan vendor:publish --tag="laravel-altid-config"
```

Optionally publish views and assets:

```bash
php artisan vendor:publish --tag="laravel-altid-views"
php artisan vendor:publish --tag="laravel-altid-assets"
```

## Configuration

Key environment variables:

```env
# The age claim to verify (default: age_over_18)
ALTID_AGE_CLAIM=age_over_18

# Transaction TTL in minutes (default: 15)
ALTID_TRANSACTION_TTL_MINUTES=15

# AltID deep-link scheme (default: av://)
ALTID_SCHEME=av://

# mdoc doctype
ALTID_DOCTYPE=eu.europa.ec.av.1
ALTID_NAMESPACE=eu.europa.ec.av.1

# Trust anchor certificate fingerprint(s), comma-separated
ALTID_TRUST_ANCHOR_FINGERPRINTS=1dc89e870cddac990f5585a0265568522531af678592cc73effd9f8706f55995

# Set true only during development to skip cryptographic verification
ALTID_ACCEPT_UNVERIFIED_RESPONSES=false

# Require device binding in the mdoc proof
ALTID_REQUIRE_DEVICE_BINDING=false

# Disable built-in web demo/info routes (/altid, /alderstjek)
ALTID_REGISTER_WEB_ROUTES=true
```

Supported age claims:

| Claim | Description |
|-------|-------------|
| `age_over_13` | 13+ |
| `age_over_15` | 15+ |
| `age_over_16` | 16+ |
| `age_over_18` | 18+ (default) |
| `age_over_21` | 21+ |
| `age_over_23` | 23+ |
| `age_over_25` | 25+ |
| `age_over_27` | 27+ |
| `age_over_67` | 67+ |

## Routes

The package registers the following routes automatically:

### API routes (`/api`)

| Method | URI | Description |
|--------|-----|-------------|
| `POST` | `/api/altid/age/start` | Start an age verification transaction |
| `POST` | `/api/altid/age/direct-post/{transactionId}` | OID4VP callback from the AltID app |
| `GET`  | `/api/altid/age/{transactionId}/status` | Poll transaction status |

### Web routes

| URI | Description |
|-----|-------------|
| `/altid` | Info page |
| `/alderstjek` | Demo page |

## Usage

### Start an age verification

```php
use Lenius\LaravelAltid\AltIdAgeVerificationService;

$service = app(AltIdAgeVerificationService::class);

// Start with default claim (age_over_18)
$transaction = $service->start();

// Or specify a claim
$transaction = $service->start('age_over_21');
```

The returned array contains:

```php
[
    'transaction_id'        => 'abc123',
    'authorization_url'     => 'av://?response_type=vp_token&...',  // deep-link for AltID app
    'test_app_url'          => 'https://app.test.tegnebog.dk/...',  // for testing
    'status_url'            => 'https://yourapp.com/api/altid/age/abc123/status',
    // ...
]
```

Present the `authorization_url` as a QR code or deep-link button so the user can open it in the AltID app.

### Poll for result

```php
$transaction = $service->find($transactionId);

// $transaction['status']   => 'pending' | 'approved' | 'failed'
// $transaction['verified'] => true | false
```

## Testing

Request test access by emailing [AltID@digst.dk](mailto:AltID@digst.dk) with subject line `Testadgang til AltID`.

The test tool is available at [test-tool.test.tegnebog.dk](https://test-tool.test.tegnebog.dk/).

Set `ALTID_ACCEPT_UNVERIFIED_RESPONSES=true` in your `.env` during development to bypass cryptographic verification.

```bash
composer test
```

## AltID Resources

| Resource | URL |
|----------|-----|
| Official AltID page | [digitaliser.dk/altid](https://www.digitaliser.dk/altid) |
| Digitaliseringsstyrelsen | [digst.dk/it-loesninger/altid](https://digst.dk/it-loesninger/altid/) |
| Technical integration guide (PDF) | [Integrating with AltID v1.0.1](https://www.digitaliser.dk/Media/639160005653021879/Integrating%20with%20AltID%20version%201.0.1.pdf) |
| Recipient design scheme (Figma) | [figma.com/design/O99M3b5pEf0mTn1xRY9pGo](https://www.figma.com/design/O99M3b5pEf0mTn1xRY9pGo/AltID-modtager---Design-Scheme) |
| Recipient registry | [modtager.tegnebog.dk](https://modtager.tegnebog.dk/) |
| Source code (GitLab) | [git.govcloud.dk/digitaliseringsstyrelsen-public/altid-source-code](https://git.govcloud.dk/digitaliseringsstyrelsen-public/altid-source-code/altid-kildekode) |
| Technical support | [AltID@digst.dk](mailto:AltID@digst.dk) |

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Carsten Jonstrup](https://github.com/Lenius-Technologies)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
