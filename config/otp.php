<?php

return [
    'ttl_seconds' => (int) env('OTP_TTL_SECONDS', 120),
    'cooldown_seconds' => (int) env('OTP_COOLDOWN_SECONDS', 120),
];
