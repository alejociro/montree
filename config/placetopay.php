<?php

declare(strict_types=1);

return [
    'url' => env('P2P_URL', 'https://checkout-test.placetopay.com'),
    'login' => env('P2P_LOGIN'),
    'tran_key' => env('P2P_TRANKEY'),

    'timeout' => (int) env('P2P_TIMEOUT', 15),

    'expiration_minutes' => (int) env('P2P_EXPIRATION_MINUTES', 30),

    'retry' => [
        'attempts' => (int) env('P2P_RETRY_ATTEMPTS', 3),
        'wait' => (int) env('P2P_RETRY_WAIT', 350),
    ],

    'check' => [
        'settle_margin_minutes' => (int) env('P2P_CHECK_MARGIN_MINUTES', 15),
        'lookback_hours' => (int) env('P2P_CHECK_LOOKBACK_HOURS', 72),
    ],
];
