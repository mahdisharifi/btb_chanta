<?php

declare(strict_types=1);

require_once __DIR__ . '/ProductImagePrompt.php';

class OpenAiCompatibleImageGateway
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $proxyEnvironmentVariable = 'AVALAI_AI_PROXY',
        private readonly ?string $proxyUrl = null,
    ) {
    }

    public function editImageApi(string $imagePath, string $mime, ?string $productName): array
    {
        $prompt = ProductImagePrompt::TEXT;
        if ($productName !== null && trim($productName) !== '') {
            $prompt = "نام محصول واردشده برای شناسایی: " . trim($productName) . "\n\n" . $prompt;
        }

        return $this->requestImageApi('edits', [
            'model' => 'muse-image-1.0',
            'prompt' => $prompt,
            'n' => '1',
            'size' => '1024x1024',
            'response_format' => 'b64_json',
            'image' => curl_file_create($imagePath, $mime, basename($imagePath)),
        ], true);
    }

    /** @return array{image: string, mime: string, text: string, usage: array, estimatedCost: array, requestId: string, imageWidth: ?int, imageHeight: ?int} */
    private function requestImageApi(string $endpoint, array $payload, bool $multipart = false): array
    {
        $base = rtrim($this->baseUrl, '/');
        if (!str_ends_with($base, '/v1')) {
            $base .= '/v1';
        }
        $url = $base . '/images/' . $endpoint;
        $requestId = '';
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json',
        ];
        $options = [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 240,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$requestId): int {
                if (preg_match('/^(?:avalai-request-id|x-request-id):\s*([^\r\n]+)/i', $line, $matches)) {
                    $requestId = trim($matches[1]);
                }
                return strlen($line);
            },
        ];
        if ($multipart) {
            $options[CURLOPT_POSTFIELDS] = $payload;
        } else {
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_HTTPHEADER] = $headers;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }
        $proxy = $this->proxyUrl ?? ($this->proxyEnvironmentVariable !== '' ? getenv($this->proxyEnvironmentVariable) : false);
        if ($proxy !== false && $proxy !== '') {
            $options[CURLOPT_PROXY] = $proxy;
        } else {
            // Prevent libcurl from silently selecting a machine-wide proxy.
            // AvalAI is reachable directly in the local platform environment.
            $options[CURLOPT_PROXY] = '';
        }

        $curl = curl_init($url);
        curl_setopt_array($curl, $options);
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($body === false) {
            throw new RuntimeException('ارتباط با آوال‌ای‌آی برقرار نشد: ' . ($curlError ?: 'خطای شبکه'));
        }
        $decoded = json_decode($body, true);
        if ($status < 200 || $status >= 300) {
            $message = is_array($decoded) ? ($decoded['error']['message'] ?? $decoded['message'] ?? null) : null;
            $message = is_string($message) ? mb_substr($message, 0, 600) : 'پاسخ ناموفق از درگاه AI';
            throw new RuntimeException("آوال‌ای‌آی خطای HTTP {$status} برگرداند: {$message}");
        }
        if (!is_array($decoded)) {
            throw new RuntimeException('پاسخ Image API آوال‌ای‌آی JSON معتبر نبود.');
        }
        $image = $this->findImage($decoded);
        if ($image === null) {
            $message = $this->findText($decoded);
            $detail = $message !== '' ? ' متن برگشتی: ' . mb_substr($message, 0, 350) : '';
            throw new RuntimeException('درگاه پاسخ موفق داد اما تصویر خروجی پیدا نشد.' . $detail);
        }
        return [
            'image' => $image['value'],
            'mime' => $image['mime'],
            'text' => $this->findText($decoded),
            'usage' => $this->normalizedUsage($decoded),
            'estimatedCost' => is_array($decoded['estimated_cost'] ?? null) ? $decoded['estimated_cost'] : [],
            'requestId' => $requestId,
            'imageWidth' => $image['width'] ?? null,
            'imageHeight' => $image['height'] ?? null,
        ];
    }

    protected function normalizedUsage(array $response): array
    {
        if (is_array($response['usage'] ?? null)) {
            return $response['usage'];
        }

        $native = is_array($response['usageMetadata'] ?? null) ? $response['usageMetadata'] : [];
        if ($native === []) {
            return [];
        }
        $modalityDetails = static function (mixed $details): array {
            $normalized = ['text_tokens' => null, 'image_tokens' => null, 'cached_tokens' => null];
            if (!is_array($details)) {
                return $normalized;
            }
            foreach ($details as $detail) {
                if (!is_array($detail) || !is_numeric($detail['tokenCount'] ?? null)) {
                    continue;
                }
                $modality = strtoupper((string) ($detail['modality'] ?? ''));
                $tokens = (int) $detail['tokenCount'];
                if ($modality === 'TEXT') {
                    $normalized['text_tokens'] = ($normalized['text_tokens'] ?? 0) + $tokens;
                } elseif ($modality === 'IMAGE') {
                    $normalized['image_tokens'] = ($normalized['image_tokens'] ?? 0) + $tokens;
                } elseif (str_contains($modality, 'CACHED')) {
                    $normalized['cached_tokens'] = ($normalized['cached_tokens'] ?? 0) + $tokens;
                }
            }
            return $normalized;
        };

        return [
            'prompt_tokens' => (int) ($native['promptTokenCount'] ?? 0),
            'completion_tokens' => (int) ($native['candidatesTokenCount'] ?? 0),
            'total_tokens' => (int) ($native['totalTokenCount'] ?? 0),
            'prompt_tokens_details' => $modalityDetails($native['promptTokensDetails'] ?? null),
            'completion_tokens_details' => $modalityDetails($native['candidatesTokensDetails'] ?? null),
        ];
    }

    /** @return array|null AvalAI's exact transaction result, when it is ready. */
    public function lookupExactCost(string $requestId, int $creditToToman): ?array
    {
        if ($requestId === '') {
            return null;
        }

        $parts = parse_url($this->baseUrl);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }
        $origin = strtolower((string) $parts['scheme']) . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }
        $url = $origin . '/user/v1/transactions/lookup';

        // AvalAI says exact transaction records may take up to 30 seconds. Make
        // a few short attempts so the first result page can often show exact cost.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            if ($attempt > 0) {
                usleep(2000000);
            }
            $curl = curl_init($url);
            $curlOptions = [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->apiKey,
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode(['transaction_ids' => [$requestId]], JSON_THROW_ON_ERROR),
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_FOLLOWLOCATION => false,
            ];
            $proxy = $this->proxyUrl ?? ($this->proxyEnvironmentVariable !== '' ? getenv($this->proxyEnvironmentVariable) : false);
            if ($proxy !== false && $proxy !== '') {
                $curlOptions[CURLOPT_PROXY] = $proxy;
            } else {
                $curlOptions[CURLOPT_PROXY] = '';
            }
            curl_setopt_array($curl, $curlOptions);
            $body = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            $decoded = is_string($body) ? json_decode($body, true) : null;
            $transaction = is_array($decoded) ? ($decoded['transactions'][0] ?? null) : null;
            $cost = is_array($transaction) && is_array($transaction['cost'] ?? null) ? $transaction['cost'] : null;
            if ($status >= 200 && $status < 300 && is_array($cost) && is_numeric($cost['unit'] ?? null)) {
                $unit = (float) $cost['unit'];
                return [
                    'unit' => $unit,
                    'currency' => (string) ($cost['currency'] ?? 'UNIT'),
                    'toman' => $unit * max(0, $creditToToman),
                    'paidIrt' => is_numeric($cost['paid_irt'] ?? null) ? (float) $cost['paid_irt'] : null,
                    'source' => (string) ($cost['source'] ?? ''),
                ];
            }
        }

        return null;
    }

    /** @return array{value: string, mime: string}|null */
    protected function findImage(array $response): ?array
    {
        foreach ($response['data'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (isset($item['b64_json']) && is_string($item['b64_json'])) {
                return $this->withDimensions('data:image/png;base64,' . $item['b64_json'], 'image/png');
            }
            if (isset($item['url']) && is_string($item['url']) && $this->isHttpsUrl($item['url'])) {
                return ['value' => $item['url'], 'mime' => 'image/*', 'width' => null, 'height' => null];
            }
        }

        $message = $response['choices'][0]['message'] ?? null;
        if (is_array($message)) {
            foreach (['images', 'content', 'parts', 'output_image', 'generated_image'] as $key) {
                if (!array_key_exists($key, $message)) {
                    continue;
                }
                $found = $this->findImageInValue($message[$key]);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        // Accept native multimodal candidate/parts shapes if the gateway
        // returns them without wrapping them in the usual image response.
        return $this->findImageInValue($response);
    }

    /** @return array{value: string, mime: string, width?: ?int, height?: ?int}|null */
    protected function findImageInValue(mixed $value, ?string $mimeHint = null): ?array
    {
        if (is_string($value)) {
            if (preg_match('~data:(image/[a-zA-Z0-9.+-]+);base64,([A-Za-z0-9+/=\r\n]+)~', $value, $matches)) {
                return $this->withDimensions('data:' . $matches[1] . ';base64,' . preg_replace('/\s+/', '', $matches[2]), $matches[1]);
            }
            if (preg_match('~!\[[^\]]*\]\((https://[^)]+)\)~i', $value, $matches) && $this->isHttpsUrl($matches[1])) {
                return ['value' => $matches[1], 'mime' => 'image/*', 'width' => null, 'height' => null];
            }
            if ($mimeHint !== null && str_starts_with($mimeHint, 'image/') && preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $value)) {
                return $this->withDimensions('data:' . $mimeHint . ';base64,' . preg_replace('/\s+/', '', $value), $mimeHint);
            }
            return null;
        }

        if (!is_array($value)) {
            return null;
        }

        $mime = $value['mime_type'] ?? $value['mimeType'] ?? $value['media_type'] ?? $mimeHint;
        if (isset($value['url']) && is_string($value['url'])) {
            if (str_starts_with($value['url'], 'data:image/')) {
                return $this->findImageInValue($value['url']);
            }
            if ($this->isHttpsUrl($value['url'])) {
                return ['value' => $value['url'], 'mime' => is_string($mime) ? $mime : 'image/*', 'width' => null, 'height' => null];
            }
        }

        foreach (['b64_json', 'image_base64', 'base64', 'data'] as $key) {
            if (isset($value[$key]) && is_string($value[$key])) {
                $hint = is_string($mime) && str_starts_with($mime, 'image/') ? $mime : 'image/png';
                $found = $this->findImageInValue($value[$key], $hint);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        foreach ([
            'image_url', 'inline_data', 'inlineData', 'image', 'images', 'content', 'parts', 'message',
            'candidates', 'choices', 'output', 'output_image', 'generated_image', 'response',
        ] as $key) {
            if (array_key_exists($key, $value)) {
                $found = $this->findImageInValue($value[$key], is_string($mime) ? $mime : null);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        // Multimodal responses may place image parts in lists. Walk list items
        // too; the named-key traversal above handles objects.
        foreach (array_keys($value) as $key) {
            if (!is_int($key)) {
                continue;
            }
            $found = $this->findImageInValue($value[$key], is_string($mime) ? $mime : null);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /** @return array{value: string, mime: string, width: ?int, height: ?int} */
    protected function withDimensions(string $value, string $mime): array
    {
        $width = null;
        $height = null;
        if (preg_match('~^data:image/[^;]+;base64,(.+)$~s', $value, $matches)) {
            $bytes = base64_decode($matches[1], true);
            $dimensions = is_string($bytes) ? @getimagesizefromstring($bytes) : false;
            if (is_array($dimensions)) {
                $width = (int) $dimensions[0];
                $height = (int) $dimensions[1];
            }
        }
        return ['value' => $value, 'mime' => $mime, 'width' => $width, 'height' => $height];
    }

    protected function findText(array $response): string
    {
        $content = $response['choices'][0]['message']['content'] ?? '';
        if (is_string($content) && trim($content) !== '') {
            return trim($content);
        }
        if (is_array($content)) {
            $parts = [];
            foreach ($content as $part) {
                if (is_array($part) && is_string($part['text'] ?? null)) {
                    $parts[] = $part['text'];
                }
            }
            return trim(implode("\n", $parts));
        }

        $parts = $response['candidates'][0]['content']['parts'] ?? [];
        if (is_array($parts)) {
            $text = [];
            foreach ($parts as $part) {
                if (is_array($part) && is_string($part['text'] ?? null)) {
                    $text[] = $part['text'];
                }
            }
            return trim(implode("\n", $text));
        }

        return '';
    }

    protected function isHttpsUrl(string $url): bool
    {
        $parts = parse_url($url);
        return is_array($parts) && strtolower($parts['scheme'] ?? '') === 'https' && isset($parts['host']);
    }
}
