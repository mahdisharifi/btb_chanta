<?php

return [
    // Short local window for testing the expiry/resend interface.
    'ttl_seconds' => (int) env('OTP_TTL_SECONDS', env('APP_ENV') === 'local' ? 20 : 120),
    'cooldown_seconds' => (int) env('OTP_COOLDOWN_SECONDS', env('APP_ENV') === 'local' ? 20 : 120),
];
