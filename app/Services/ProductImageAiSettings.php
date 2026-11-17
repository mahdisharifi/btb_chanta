<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProductImageAiSetting;
use Dotenv\Dotenv;

final class ProductImageAiSettings
{
    /** @return array{base_url:string, api_key:string, proxy:string, credit_to_toman:int, max_upload_mb:int, ready:bool} */
    public function current(): array
    {
        $stored = ProductImageAiSetting::query()->first();
        $fileValues = [];
        $directory = base_path('agent_picture');
        if (is_file($directory . '/.env')) {
            $fileValues = Dotenv::createArrayBacked($directory)->safeLoad();
        }

        $baseUrl = trim((string) ($stored?->base_url ?: config('product_image_ai.base_url') ?: ($fileValues['AVALAI_AI_BASE_URL'] ?? 'https://api.avalai.ir')));
        $apiKey = trim((string) ($stored?->api_key ?: config('product_image_ai.api_key') ?: ($fileValues['AVALAI_AI_API_KEY'] ?? '')));
        $proxy = (string) (config('product_image_ai.proxy') ?: ($fileValues['AVALAI_AI_PROXY'] ?? ''));
        $creditToToman = $stored?->credit_to_toman
            ?? max(0, (int) ($fileValues['AVALAI_CREDIT_TOMAN'] ?? 268050));
        $maxUploadMb = max(1, min(30, (int) ($stored?->max_upload_mb ?? $fileValues['AGENT_PICTURE_MAX_UPLOAD_MB'] ?? 15)));
        $parts = parse_url($baseUrl);

        return [
            'base_url' => rtrim($baseUrl, '/'),
            'api_key' => $apiKey,
            'proxy' => $proxy,
            'credit_to_toman' => $creditToToman,
            'max_upload_mb' => $maxUploadMb,
            'ready' => filter_var($baseUrl, FILTER_VALIDATE_URL) !== false
                && strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME)) === 'https'
                && is_array($parts)
                && ! empty($parts['host'])
                && $apiKey !== ''
                && $apiKey !== 'replace-me',
        ];
    }

    public function save(string $baseUrl, string $apiKey, int $creditToToman): ProductImageAiSetting
    {
        return ProductImageAiSetting::query()->updateOrCreate(
            ['id' => 1],
            [
                'base_url' => rtrim($baseUrl, '/'),
                'api_key' => $apiKey,
                'credit_to_toman' => max(0, $creditToToman),
                'max_upload_mb' => $this->current()['max_upload_mb'],
            ],
        );
    }
}
