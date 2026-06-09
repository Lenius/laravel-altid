<?php

return [
    'age_claim' => env('ALTID_AGE_CLAIM', 'age_over_18'),

    'age_claims' => [
        'age_over_13',
        'age_over_15',
        'age_over_16',
        'age_over_18',
        'age_over_21',
        'age_over_23',
        'age_over_25',
        'age_over_27',
        'age_over_67',
    ],

    'transaction_ttl_minutes' => (int) env('ALTID_TRANSACTION_TTL_MINUTES', 15),

    'scheme' => env('ALTID_SCHEME', 'av://'),

    'test_app_oid4vp_url' => env('ALTID_TEST_APP_OID4VP_URL', 'https://app.test.tegnebog.dk/oid4vp'),

    'doctype' => env('ALTID_DOCTYPE', 'eu.europa.ec.av.1'),

    'namespace' => env('ALTID_NAMESPACE', 'eu.europa.ec.av.1'),

    'debug' => (bool) env('ALTID_DEBUG', false),

    /*
     * Set to true during development to skip cryptographic proof verification.
     * Must be false in production.
     */
    'accept_unverified_responses' => (bool) env('ALTID_ACCEPT_UNVERIFIED_RESPONSES', false),

    'trust_anchor_fingerprints' => array_values(array_filter(array_map(
        fn (string $fingerprint): string => strtolower(str_replace([':', ' '], '', trim($fingerprint))),
        explode(',', env('ALTID_TRUST_ANCHOR_FINGERPRINTS', '1dc89e870cddac990f5585a0265568522531af678592cc73effd9f8706f55995'))
    ))),

    'require_device_binding' => (bool) env('ALTID_REQUIRE_DEVICE_BINDING', false),

    /*
     * Set to false to disable the built-in web routes (/altid, /alderstjek).
     */
    'register_web_routes' => (bool) env('ALTID_REGISTER_WEB_ROUTES', true),
];
