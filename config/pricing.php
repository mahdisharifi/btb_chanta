<?php

$exchangeRateRefreshMinutes = max(1, min(60, (int) env('EXCHANGE_RATE_REFRESH_MINUTES', 5)));

return [
    'exchange_rates' => [
        'nerkhsanj_url' => env('NERKHSANJ_RATES_URL', 'https://nerkh.jahankhahan.shop/data/live.json'),
        'refresh_minutes' => $exchangeRateRefreshMinutes,
        'max_age_minutes' => max(
            $exchangeRateRefreshMinutes * 3,
            (int) env('EXCHANGE_RATE_MAX_AGE_MINUTES', 15)
        ),
    ],
];
