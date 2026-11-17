<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ProductImageAiSetting extends Model
{
    protected $fillable = [
        'base_url',
        'api_key',
        'credit_to_toman',
        'max_upload_mb',
    ];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'credit_to_toman' => 'integer',
            'max_upload_mb' => 'integer',
        ];
    }
}
