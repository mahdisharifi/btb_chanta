<?php

return [
    // Upper bound for PHP memory used while creating responsive slider images.
    'image_memory_limit_mb' => (int) env('SLIDER_IMAGE_MEMORY_LIMIT_MB', 128),
];
