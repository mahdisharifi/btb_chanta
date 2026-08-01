<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class SeoPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_type',
        'page_identifier',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'og_title',
        'og_description',
        'og_image',
        'canonical_url',
        'robots',
        'schema_markup',
    ];

    protected function casts(): array
    {
        return [
            'schema_markup' => 'array',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeByPage(Builder $query, string $type, string $identifier): Builder
    {
        return $query->where('page_type', $type)
                     ->where('page_identifier', $identifier);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('page_type', $type);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    public static function getForPage(string $type, string $identifier): ?self
    {
        return static::byPage($type, $identifier)->first();
    }

    public static function setForPage(string $type, string $identifier, array $data): self
    {
        return static::updateOrCreate(
            [
                'page_type' => $type,
                'page_identifier' => $identifier,
            ],
            $data
        );
    }

    public function getSchemaJson(): ?string
    {
        if (!$this->schema_markup) {
            return null;
        }
        return json_encode($this->schema_markup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
