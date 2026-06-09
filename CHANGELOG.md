# Changelog

All notable changes to `laravel-altid` will be documented in this file.

## [Unreleased]

## [0.1.0] - 2026-06-09

### Added
- Age verification flow via OID4VP with mdoc/ISO 18013-5 credentials
- `AltIdAgeVerificationService` — start transactions, handle callbacks, poll status
- `MdocAltIdAgePresentationValidator` — cryptographic verification of mdoc vp_token responses
- API routes: `POST /api/altid/age/start`, `POST /api/altid/age/direct-post/{transactionId}`, `GET /api/altid/age/{transactionId}/status`
- Web demo and info pages (`/alderstjek`, `/altid`)
- Config file (`config/altid.php`) with support for multiple age claims (`age_over_13` through `age_over_67`), trust anchor fingerprints, device binding, and transaction TTL
- `LaravelAltidServiceProvider` with automatic route and view registration
- Feature tests for age verification flow and web routes
- `SECURITY.md` and `CONTRIBUTING.md`
