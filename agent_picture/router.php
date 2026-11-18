<?php

declare(strict_types=1);

require_once __DIR__ . '/src/AvalAiGateway.php';

function loadAgentPictureEnvironment(): void
{
    $path = __DIR__ . '/.env';
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (preg_match('/^[A-Z0-9_]+$/', $key) !== 1 || getenv($key) !== false) {
            continue;
        }
        if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
            $value = substr($value, 1, -1);
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

function jsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function envValue(string $name, string $default = ''): string
{
    $value = getenv($name);
    return $value === false ? $default : trim($value);
}

function configuredAvalAiModels(): array
{
    return ['muse-image-1.0'];
}

function avalAiConfigReady(): bool
{
    $baseUrl = envValue('AVALAI_AI_BASE_URL', 'https://api.avalai.ir');
    $apiKey = envValue('AVALAI_AI_API_KEY');
    $host = parse_url($baseUrl, PHP_URL_HOST);
    return filter_var($baseUrl, FILTER_VALIDATE_URL) !== false
        && strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME)) === 'https'
        && is_string($host)
        && $host !== ''
        && $apiKey !== ''
        && $apiKey !== 'replace-me'
        && configuredAvalAiModels() !== [];
}

function avalAiPricing(): array
{
    return [
        'creditToToman' => max(0, (int) envValue('AVALAI_CREDIT_TOMAN', '268050')),
    ];
}

function museCostSummary(int $creditToToman, ?array $exactCost): array
{
    $modelRate = 0.0115;
    $exactCredits = is_numeric($exactCost['unit'] ?? null) ? (float) $exactCost['unit'] : null;
    $credits = $exactCredits ?? $modelRate;
    return [
        'exact' => $exactCredits !== null,
        'pricingType' => 'per_image',
        'estimatedCredits' => $modelRate,
        'estimatedToman' => $modelRate * $creditToToman,
        'totalCredits' => $credits,
        'totalToman' => $credits * $creditToToman,
        'creditToToman' => $creditToToman,
        'requestId' => is_string($exactCost['requestId'] ?? null) ? $exactCost['requestId'] : '',
        'exactSource' => is_string($exactCost['source'] ?? null) ? $exactCost['source'] : null,
        'description' => '۰٫۰۱ اعتبار پایه + کارمزد ۱۵٪ آوال‌ای‌آی؛ جمع ۰٫۰۱۱۵ اعتبار برای هر تصویر.',
    ];
}

function storeAgentPictureConfig(array $settings): void
{
    $maxUpload = (string) max(1, min(30, (int) envValue('AGENT_PICTURE_MAX_UPLOAD_MB', '15')));
    $quote = static function (string $value): string {
        if (preg_match('/["\r\n\x00]/', $value)) {
            throw new RuntimeException('یکی از مقدارهای تنظیمات دارای نویسهٔ نامعتبر است.');
        }
        return '"' . $value . '"';
    };
    $values = [
        'AVALAI_AI_BASE_URL' => $settings['baseUrl'],
        'AVALAI_AI_API_KEY' => $settings['apiKey'],
        'AVALAI_CREDIT_TOMAN' => (string) $settings['pricing']['creditToToman'],
        'AGENT_PICTURE_MAX_UPLOAD_MB' => $maxUpload,
    ];
    $contentsLines = [];
    foreach ($values as $key => $value) {
        $contentsLines[] = $key . '=' . $quote((string) $value);
    }
    $proxy = getenv('AVALAI_AI_PROXY');
    if ($proxy !== false) {
        $contentsLines[] = 'AVALAI_AI_PROXY=' . $quote($proxy);
    }
    $contentsLines[] = '';
    $contents = implode("\n", $contentsLines);

    $temporaryPath = __DIR__ . '/.env.tmp';
    if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
        throw new RuntimeException('ذخیرهٔ تنظیمات ممکن نشد؛ دسترسی نوشتن پوشه را بررسی کنید.');
    }
    @chmod($temporaryPath, 0600);
    if (!rename($temporaryPath, __DIR__ . '/.env')) {
        @unlink($temporaryPath);
        throw new RuntimeException('جایگزینی فایل تنظیمات ممکن نشد.');
    }
    @chmod(__DIR__ . '/.env', 0600);

    foreach ($values as $key => $value) {
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

loadAgentPictureEnvironment();

if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (!str_starts_with($path, '/api/')) {
    return false;
}

session_name('agent_picture_session');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'path' => '/',
]);
session_start();

if ($path === '/api/session' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    jsonResponse(['csrf' => $_SESSION['csrf']]);
}

if ($path === '/api/models' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    jsonResponse([
        'models' => configuredAvalAiModels(),
        'ready' => avalAiConfigReady(),
        'maxUploadMb' => max(1, min(30, (int) envValue('AGENT_PICTURE_MAX_UPLOAD_MB', '15'))),
    ]);
}

if ($path === '/api/config' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    jsonResponse([
        'avalai' => [
            'baseUrl' => envValue('AVALAI_AI_BASE_URL', 'https://api.avalai.ir'),
            'model' => 'muse-image-1.0',
            'hasApiKey' => envValue('AVALAI_AI_API_KEY') !== '' && envValue('AVALAI_AI_API_KEY') !== 'replace-me',
            'ready' => avalAiConfigReady(),
            'pricing' => avalAiPricing(),
        ],
        'maxUploadMb' => max(1, min(30, (int) envValue('AGENT_PICTURE_MAX_UPLOAD_MB', '15'))),
    ]);
}

if ($path === '/api/config' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($csrf) || !hash_equals((string) ($_SESSION['csrf'] ?? ''), $csrf)) {
        jsonResponse(['error' => 'نشست تست منقضی شده است؛ صفحه را تازه‌سازی کنید.'], 419);
    }

    $input = json_decode(file_get_contents('php://input') ?: '', true);
    $avalai = is_array($input['avalai'] ?? null) ? $input['avalai'] : [];
    $baseUrl = rtrim(trim((string) ($avalai['baseUrl'] ?? envValue('AVALAI_AI_BASE_URL', 'https://api.avalai.ir'))), '/');
    $parts = parse_url($baseUrl);
    if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false
        || !is_array($parts)
        || strtolower($parts['scheme'] ?? '') !== 'https'
        || empty($parts['host'])
        || isset($parts['user'])
        || isset($parts['pass'])
        || isset($parts['query'])
        || isset($parts['fragment'])
    ) {
        jsonResponse(['error' => 'آدرس آوال‌ای‌آی باید URL معتبر HTTPS باشد.'], 422);
    }

    $apiKey = trim((string) ($avalai['apiKey'] ?? ''));
    if (preg_match('/^(?:apikey|bearer)\s+/i', $apiKey)) {
        $apiKey = preg_replace('/^(?:apikey|bearer)\s+/i', '', $apiKey) ?? $apiKey;
    }
    if ($apiKey === '') {
        $apiKey = envValue('AVALAI_AI_API_KEY');
    }
    if ($apiKey === 'replace-me' || strlen($apiKey) > 2048 || preg_match('/[\r\n\x00]/', $apiKey)) {
        jsonResponse(['error' => 'کلید معتبر آوال‌ای‌آی را وارد کنید.'], 422);
    }

    $pricingInput = is_array($avalai['pricing'] ?? null) ? $avalai['pricing'] : [];
    $creditToToman = $pricingInput['creditToToman'] ?? avalAiPricing()['creditToToman'];
    if (!is_numeric($creditToToman) || (float) $creditToToman < 0 || (float) $creditToToman > 1000000000) {
        jsonResponse(['error' => 'نرخ تبدیل اعتبار به تومان معتبر نیست.'], 422);
    }

    try {
        storeAgentPictureConfig([
            'baseUrl' => $baseUrl,
            'apiKey' => $apiKey,
            'pricing' => ['creditToToman' => (int) round((float) $creditToToman)],
        ]);
        jsonResponse([
            'avalai' => [
                'baseUrl' => $baseUrl,
                'model' => 'muse-image-1.0',
                'hasApiKey' => true,
                'ready' => avalAiConfigReady(),
                'pricing' => avalAiPricing(),
            ],
            'maxUploadMb' => max(1, min(30, (int) envValue('AGENT_PICTURE_MAX_UPLOAD_MB', '15'))),
        ]);
    } catch (Throwable $error) {
        jsonResponse(['error' => $error->getMessage()], 500);
    }
}

if ($path === '/api/edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($csrf) || !hash_equals((string) ($_SESSION['csrf'] ?? ''), $csrf)) {
        jsonResponse(['error' => 'نشست تست منقضی شده است؛ صفحه را تازه‌سازی کنید.'], 419);
    }

    $model = trim((string) ($_POST['model'] ?? ''));
    if ($model !== 'muse-image-1.0') {
        jsonResponse(['error' => 'این سرویس فقط مدل مورد تأیید muse-image-1.0 را فعال دارد.'], 422);
    }
    if (!avalAiConfigReady()) {
        jsonResponse(['error' => 'تنظیمات آوال‌ای‌آی کامل نیست؛ کلید API را در تنظیمات وارد کنید.'], 503);
    }

    $file = $_FILES['image'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        jsonResponse(['error' => 'یک فایل تصویر معتبر انتخاب کنید.'], 422);
    }
    $maxMb = max(1, min(30, (int) envValue('AGENT_PICTURE_MAX_UPLOAD_MB', '15')));
    if ((int) $file['size'] > $maxMb * 1024 * 1024) {
        jsonResponse(['error' => "حجم تصویر باید حداکثر {$maxMb} مگابایت باشد."], 413);
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    $mime = is_array($imageInfo) ? ($imageInfo['mime'] ?? '') : '';
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        jsonResponse(['error' => 'فرمت مجاز تصویر JPG، PNG یا WebP است.'], 422);
    }
    if ((int) ($imageInfo[0] ?? 0) > 10000 || (int) ($imageInfo[1] ?? 0) > 10000) {
        jsonResponse(['error' => 'ابعاد تصویر بیش از حد بزرگ است.'], 422);
    }

    try {
        $gateway = new AvalAiGateway(
            rtrim(envValue('AVALAI_AI_BASE_URL', 'https://api.avalai.ir'), '/'),
            envValue('AVALAI_AI_API_KEY'),
        );
        $result = $gateway->editImageApi(
            $file['tmp_name'],
            $mime,
            isset($_POST['product_name']) ? (string) $_POST['product_name'] : null,
        );
        $pricing = avalAiPricing();
        $exactCost = $gateway->lookupExactCost($result['requestId'], $pricing['creditToToman']);
        $cost = museCostSummary($pricing['creditToToman'], $exactCost);
        $cost['requestId'] = $result['requestId'];
        jsonResponse([
            'image' => $result['image'],
            'mime' => $result['mime'],
            'model' => 'muse-image-1.0',
            'note' => $result['text'],
            'usage' => $result['usage'],
            'cost' => $cost,
            'imageDimensions' => ['width' => $result['imageWidth'], 'height' => $result['imageHeight']],
            'estimatedCost' => $result['estimatedCost'],
        ]);
    } catch (Throwable $error) {
        error_log('[agent_picture] ' . $error->getMessage());
        jsonResponse(['error' => $error->getMessage()], 502);
    }
}

jsonResponse(['error' => 'مسیر API پیدا نشد.'], 404);
