<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ProductImageAiSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class AiImageStudioController extends Controller
{
    public function index(): Response
    {
        return response()->view('admin.ai-image-studio')
            ->header('Cache-Control', 'no-store, private');
    }

    public function studioApp(): Response
    {
        $path = base_path('agent_picture/public/index.html');
        abort_unless(is_file($path), 404);

        $html = file_get_contents($path);
        abort_unless(is_string($html), 500);

        $apiConfigPath = route('admin.ai-image-studio.api.config', [], false);
        $apiBase = Str::beforeLast($apiConfigPath, '/config');
        $assetPath = route('admin.ai-image-studio.asset', ['asset' => 'app.js'], false);
        $assetBase = Str::beforeLast($assetPath, '/app.js');
        $dashboardPath = route('admin.dashboard', [], false);

        $html = str_replace(
            '<html lang="fa" dir="rtl">',
            '<html lang="fa" dir="rtl" data-api-base="' . e($apiBase) . '">',
            $html,
        );
        $html = str_replace('href="/styles.css"', 'href="' . e($assetBase) . '/styles.css"', $html);
        $html = str_replace('src="/app.js"', 'src="' . e($assetBase) . '/app.js"', $html);
        $html = str_replace('href="/" aria-label="BTB Chanta"', 'href="' . e($dashboardPath) . '" aria-label="BTB Chanta"', $html);

        return response($html)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Frame-Options', 'SAMEORIGIN');
    }

    public function asset(string $asset): Response
    {
        abort_unless(in_array($asset, ['app.js', 'styles.css'], true), 404);
        $path = base_path('agent_picture/public/' . $asset);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => $asset === 'app.js' ? 'text/javascript; charset=UTF-8' : 'text/css; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function session(): JsonResponse
    {
        return response()->json(['csrf' => csrf_token()])
            ->header('Cache-Control', 'no-store, private');
    }

    public function configuration(ProductImageAiSettings $settings): JsonResponse
    {
        return response()->json($this->configurationPayload($settings->current()))
            ->header('Cache-Control', 'no-store, private');
    }

    public function saveConfiguration(Request $request, ProductImageAiSettings $settings): JsonResponse
    {
        $validated = $request->validate([
            'avalai.baseUrl' => ['required', 'string', 'url', 'max:255'],
            'avalai.apiKey' => ['nullable', 'string', 'max:2048'],
            'avalai.pricing.creditToToman' => ['required', 'numeric', 'min:0', 'max:1000000000'],
        ]);

        $baseUrl = rtrim(trim((string) data_get($validated, 'avalai.baseUrl')), '/');
        $parts = parse_url($baseUrl);
        if (! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            throw ValidationException::withMessages([
                'avalai.baseUrl' => 'آدرس API باید یک URL معتبر HTTPS باشد.',
            ]);
        }

        $current = $settings->current();
        $apiKey = trim((string) data_get($validated, 'avalai.apiKey', ''));
        if (preg_match('/^(?:apikey|bearer)\s+/i', $apiKey)) {
            $apiKey = preg_replace('/^(?:apikey|bearer)\s+/i', '', $apiKey) ?? $apiKey;
        }
        if ($apiKey === '') {
            $apiKey = $current['api_key'];
        }
        if ($apiKey === '' || $apiKey === 'replace-me' || preg_match('/[\r\n\x00]/', $apiKey)) {
            return response()->json(['error' => 'کلید API معتبر را وارد کنید.'], 422)
                ->header('Cache-Control', 'no-store, private');
        }

        $settings->save(
            $baseUrl,
            $apiKey,
            (int) round((float) data_get($validated, 'avalai.pricing.creditToToman')),
        );

        return response()->json($this->configurationPayload($settings->current()))
            ->header('Cache-Control', 'no-store, private');
    }

    public function edit(Request $request, ProductImageAiSettings $settings): JsonResponse
    {
        $current = $settings->current();
        abort_unless($current['ready'], 503, 'اتصال درگاه تصویر تنظیم نشده است؛ تنظیمات را باز کنید.');

        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:' . ($current['max_upload_mb'] * 1024)],
            'product_name' => ['nullable', 'string', 'max:160'],
        ]);

        $file = $request->file('image');
        if (! $file || ! in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return response()->json(['error' => 'فرمت مجاز تصویر JPG، PNG یا WebP است.'], 422);
        }

        $dimensions = @getimagesize($file->getRealPath());
        if (! is_array($dimensions) || $dimensions[0] > 10000 || $dimensions[1] > 10000) {
            return response()->json(['error' => 'ابعاد تصویر بیش از حد بزرگ است.'], 422);
        }

        try {
            require_once base_path('agent_picture/src/AvalAiGateway.php');

            $gateway = new \AvalAiGateway($current['base_url'], $current['api_key'], $current['proxy'] ?: null);
            $result = $gateway->editImageApi(
                $file->getRealPath(),
                $file->getMimeType(),
                isset($validated['product_name']) ? (string) $validated['product_name'] : null,
            );

            $exactCost = $gateway->lookupExactCost($result['requestId'], $current['credit_to_toman']);
            $estimatedCredits = 0.0115;
            $totalCredits = is_numeric($exactCost['unit'] ?? null)
                ? (float) $exactCost['unit']
                : $estimatedCredits;

            return response()->json([
                'image' => $result['image'],
                'mime' => $result['mime'],
                'note' => $result['text'],
                'usage' => $result['usage'],
                'imageDimensions' => [
                    'width' => $result['imageWidth'],
                    'height' => $result['imageHeight'],
                ],
                'estimatedCost' => $result['estimatedCost'],
                'cost' => [
                    'exact' => $exactCost !== null,
                    'estimatedCredits' => $estimatedCredits,
                    'estimatedToman' => $estimatedCredits * $current['credit_to_toman'],
                    'totalCredits' => $totalCredits,
                    'totalToman' => $exactCost['toman'] ?? ($totalCredits * $current['credit_to_toman']),
                    'creditToToman' => $current['credit_to_toman'],
                    'requestId' => $result['requestId'],
                    'exactSource' => $exactCost['source'] ?? null,
                    'description' => '۰٫۰۱ اعتبار پایه به‌اضافهٔ ۱۵٪ کارمزد درگاه؛ جمع برآوردی ۰٫۰۱۱۵ اعتبار برای هر تصویر.',
                ],
            ])->header('Cache-Control', 'no-store, private');
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'error' => 'ویرایش تصویر انجام نشد: ' . Str::limit($exception->getMessage(), 500),
            ], 502)->header('Cache-Control', 'no-store, private');
        }
    }

    /** @param array{base_url:string,api_key:string,credit_to_toman:int,max_upload_mb:int,ready:bool} $settings */
    private function configurationPayload(array $settings): array
    {
        return [
            'avalai' => [
                'baseUrl' => $settings['base_url'],
                'hasApiKey' => $settings['api_key'] !== '' && $settings['api_key'] !== 'replace-me',
                'ready' => $settings['ready'],
                'pricing' => ['creditToToman' => $settings['credit_to_toman']],
            ],
            'maxUploadMb' => $settings['max_upload_mb'],
        ];
    }
}
