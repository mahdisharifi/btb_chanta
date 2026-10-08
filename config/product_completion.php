<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Product completion contract
    |--------------------------------------------------------------------------
    |
    | This file is the server-side contract for outsourced product content.
    | Client-side visibility must never be treated as authorization.
    |
    */
    'schema_version' => 1,
    'currency' => 'IRR',

    'defaults' => [
        'revision_limit' => 3,
        'assignment_expiration_hours' => 24,
        'due_days' => 3,
        'review_mode' => 'reviewer',
    ],

    'rate_limits' => [
        'media_uploads_per_minute' => 20,
        'submissions_per_minute' => 10,
    ],

    /*
    | Scope presets are copied to each task. The persisted task scope becomes
    | the authoritative allowlist for that task.
    */
    'scope_presets' => [
        'content' => [
            'title' => true,
            'short_description' => true,
            'full_description' => true,
            'specifications' => true,
            'attributes' => false,
            'gallery' => false,
            'seo' => false,
        ],
        'attributes' => [
            'title' => false,
            'short_description' => false,
            'full_description' => false,
            'specifications' => true,
            'attributes' => true,
            'gallery' => false,
            'seo' => false,
        ],
        'media' => [
            'title' => false,
            'short_description' => false,
            'full_description' => false,
            'specifications' => false,
            'attributes' => false,
            'gallery' => [
                'add' => true,
                'reorder' => true,
                'select_primary' => true,
                'select_seo' => true,
                'propose_removal' => true,
            ],
            'seo' => false,
        ],
        'seo' => [
            'title' => false,
            'short_description' => false,
            'full_description' => false,
            'specifications' => false,
            'attributes' => false,
            'gallery' => false,
            'seo' => [
                'title' => true,
                'description' => true,
                'keywords' => true,
                'canonical_url' => false,
            ],
        ],
        'full_completion' => [
            'title' => true,
            'short_description' => true,
            'full_description' => true,
            'specifications' => true,
            'attributes' => true,
            'gallery' => [
                'add' => true,
                'reorder' => true,
                'select_primary' => true,
                'select_seo' => true,
                'propose_removal' => true,
            ],
            'seo' => [
                'title' => true,
                'description' => true,
                'keywords' => true,
                'canonical_url' => false,
            ],
        ],
        // Correction tasks must receive an explicit field-level scope.
        'correction' => [
            'title' => false,
            'short_description' => false,
            'full_description' => false,
            'specifications' => false,
            'attributes' => false,
            'gallery' => false,
            'seo' => false,
        ],
    ],

    'draft_fields' => [
        'schema_version',
        'title',
        'short_description',
        'full_description',
        'specifications',
        'attributes',
        'gallery',
        'seo',
    ],

    /*
    | These fields remain forbidden even if malformed scope data is stored.
    */
    'never_allowed_fields' => [
        'id',
        'product_id',
        'supplier_id',
        'category_id',
        'slug',
        'sku',
        'status',
        'is_active',
        'is_featured',
        'brand',
        'weight',
        'dimensions',
        'quantity_tiers',
        'packaging_unit_type',
        'quantity_per_packaging',
        'prices',
        'product_prices',
        'retail_prices',
        'supplier_prices',
        'product_supplier',
        'product_suppliers',
        'stock_quantity',
        'cost_price',
        'commission_rate',
        'tier_discounts',
        'price_rounding',
        'price_visibility',
        'check_period_pricing',
        'created_at',
        'updated_at',
        'deleted_at',
        'content_version',
    ],

    'validation' => [
        'title_max' => 255,
        'short_description_max' => 1000,
        'full_description_max' => 100000,
        'specifications_max_items' => 100,
        'specification_key_max' => 100,
        'specification_value_max' => 2000,
        'seo_title_max' => 60,
        'seo_description_max' => 160,
        'seo_keywords_max' => 255,
        'attributes_max' => 100,
        'attribute_values_max' => 50,
    ],

    'media' => [
        'staging_disk' => 'local',
        'published_disk' => 'public',
        'staging_directory' => 'product-completion',
        'published_directory' => 'products/gallery',
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
        'max_file_size_kb' => 2048,
        'max_images' => 10,
        'min_width' => 800,
        'min_height' => 800,
        'max_width' => 8000,
        'max_height' => 8000,
        'max_pixels' => 40000000,
        'rejected_retention_days' => 7,
        'expired_retention_days' => 7,
        'replaced_media_retention_days' => 7,
    ],

    'commission' => [
        'default_calculation_type' => 'per_task',
        'default_revision_policy' => 'scope_change_only',
        'default_correction_task_percentage' => '30.00',
        'decimal_scale' => 2,
        'require_distinct_approver_and_payer' => true,
    ],

    'audit' => [
        'user_agent_max_length' => 500,
        'store_draft_payloads' => false,
        'store_forbidden_values' => false,
    ],
];
