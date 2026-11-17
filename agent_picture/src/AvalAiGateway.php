<?php

declare(strict_types=1);

require_once __DIR__ . '/OpenAiCompatibleImageGateway.php';

final class AvalAiGateway extends OpenAiCompatibleImageGateway
{
    public function __construct(string $baseUrl, string $apiKey, ?string $proxyUrl = null)
    {
        parent::__construct($baseUrl, $apiKey, 'AVALAI_AI_PROXY', $proxyUrl);
    }
}
