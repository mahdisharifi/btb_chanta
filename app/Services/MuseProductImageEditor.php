<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

final class MuseProductImageEditor
{
    private const OUTPUT_SIZE = 1200;
    private const MAX_TEMPORARY_IMAGE_BYTES = 2 * 1024 * 1024;

    public function edit(TemporaryUploadedFile $source, ?string $productName = null): TemporaryUploadedFile
    {
        $settings = $this->settings();
        $sourceBytes = $source->get();
        $sourceMime = $source->getMimeType();

        if (! in_array($sourceMime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $sourceBytes = ImageManager::gd()->read($sourceBytes)->toJpeg(quality: 92)->toString();
            $sourceMime = 'image/jpeg';
        }

        $sourcePath = tempnam(sys_get_temp_dir(), 'muse-product-input-');
        if ($sourcePath === false) {
            throw new RuntimeException('تصویر ورودی برای ارسال به درگاه آماده نشد.');
        }

        try {
            if (file_put_contents($sourcePath, $sourceBytes) === false) {
                throw new RuntimeException('تصویر ورودی برای ارسال به درگاه آماده نشد.');
            }

            require_once base_path('agent_picture/src/AvalAiGateway.php');

            $gateway = new \AvalAiGateway($settings['base_url'], $settings['api_key'], $settings['proxy']);
            $result = $gateway->editImageApi($sourcePath, $sourceMime, $productName);
            Log::info('Muse primary product image response received.', [
                'model' => 'muse-image-1.0',
                'request_id' => $result['requestId'] ?? null,
                'estimated_credits' => 0.0115,
            ]);
            $bytes = $this->extractImageBytes((string) ($result['image'] ?? ''), $settings['proxy']);
            $normalized = $this->normalizeImage($bytes);

            return $this->makeLivewireUpload($normalized);
        } finally {
            if (is_file($sourcePath)) {
                @unlink($sourcePath);
            }
        }
    }

    /** @return array{base_url: string, api_key: string, proxy: string} */
    private function settings(): array
    {
        $settings = app(ProductImageAiSettings::class)->current();

        if (! $settings['ready']) {
            throw new RuntimeException('اتصال سرویس ویرایش عکس به AvalAI تنظیم نشده است.');
        }

        return [
            'base_url' => $settings['base_url'],
            'api_key' => $settings['api_key'],
            'proxy' => $settings['proxy'],
        ];
    }

    private function extractImageBytes(string $image, string $proxy): string
    {
        if (preg_match('~^data:(image/(?:png|jpeg|webp));base64,([A-Za-z0-9+/=\r\n]+)$~i', $image, $matches)) {
            if (strlen($matches[2]) > 28 * 1024 * 1024) {
                throw new RuntimeException('حجم تصویر خروجی بیش از حد مجاز است.');
            }
            $bytes = base64_decode(preg_replace('/\s+/', '', $matches[2]), true);
            if (is_string($bytes) && strlen($bytes) <= 20 * 1024 * 1024) {
                return $bytes;
            }
        }

        if (preg_match('~^https://~i', $image)) {
            return $this->downloadImage($image, $proxy);
        }

        throw new RuntimeException('درگاه تصویر ویرایش‌شدهٔ قابل دریافت برنگرداند.');
    }

    private function downloadImage(string $url, string $proxy): string
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if ($scheme !== 'https' || $host === '' || isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && $parts['port'] !== 443)) {
            throw new RuntimeException('پیوند تصویر برگشتی معتبر نیست.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new RuntimeException('میزبان تصویر برگشتی مجاز نیست.');
            }
        } else {
            $records = dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
            $resolved = array_values(array_filter(array_map(
                static fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null,
                $records,
            )));
            if ($resolved === [] || str_ends_with($host, '.local') || str_ends_with($host, '.localhost')) {
                throw new RuntimeException('میزبان تصویر برگشتی قابل اعتماد نیست.');
            }
            foreach ($resolved as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                    throw new RuntimeException('میزبان تصویر برگشتی مجاز نیست.');
                }
            }
        }

        $options = ['allow_redirects' => false];
        if ($proxy !== '') {
            $options['proxy'] = $proxy;
        } else {
            $options['proxy'] = '';
        }

        /** @var PendingRequest $request */
        $request = Http::timeout(45)
            ->withOptions($options)
            ->accept('image/png, image/jpeg, image/webp');
        $response = $request->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('دریافت تصویر خروجی ناموفق بود.');
        }

        $contentType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        $bytes = $response->body();
        if (! in_array($contentType, ['image/png', 'image/jpeg', 'image/webp'], true) || strlen($bytes) > 20 * 1024 * 1024) {
            throw new RuntimeException('قالب یا اندازهٔ تصویر برگشتی پشتیبانی نمی‌شود.');
        }

        return $bytes;
    }

    private function normalizeImage(string $bytes): string
    {
        $dimensions = @getimagesizefromstring($bytes);
        if (! is_array($dimensions) || ! in_array($dimensions['mime'] ?? '', ['image/png', 'image/jpeg', 'image/webp'], true)) {
            throw new RuntimeException('خروجی یک فایل تصویری معتبر نیست.');
        }

        $image = ImageManager::gd()->read($bytes)
            ->scaleDown(width: self::OUTPUT_SIZE, height: self::OUTPUT_SIZE)
            ->pad(self::OUTPUT_SIZE, self::OUTPUT_SIZE, '#ffffff', 'center');

        foreach ([92, 88, 84, 80] as $quality) {
            $encoded = $image->toWebp(quality: $quality)->toString();
            if (strlen($encoded) <= self::MAX_TEMPORARY_IMAGE_BYTES) {
                return $encoded;
            }
        }

        throw new RuntimeException('حجم تصویر خروجی از محدودیت بارگذاری محصول بیشتر است.');
    }

    private function makeLivewireUpload(string $bytes): TemporaryUploadedFile
    {
        $localPath = tempnam(sys_get_temp_dir(), 'muse-product-output-');
        if ($localPath === false) {
            throw new RuntimeException('ذخیرهٔ موقت تصویر خروجی ناموفق بود.');
        }

        try {
            if (file_put_contents($localPath, $bytes) === false) {
                throw new RuntimeException('ذخیرهٔ موقت تصویر خروجی ناموفق بود.');
            }

            $uploadedFile = new UploadedFile($localPath, 'muse-edited.webp', 'image/webp', null, true);
            $filename = TemporaryUploadedFile::generateHashNameWithOriginalNameEmbedded($uploadedFile);
        } finally {
            if (is_file($localPath)) {
                @unlink($localPath);
            }
        }

        $disk = FileUploadConfiguration::disk();
        $path = FileUploadConfiguration::path($filename);
        if (! Storage::disk($disk)->put($path, $bytes)) {
            Storage::disk($disk)->delete($path);
            throw new RuntimeException('تصویر ویرایش‌شده برای نمایش در فرم آماده نشد.');
        }

        return TemporaryUploadedFile::createFromLivewire($filename);
    }
}
