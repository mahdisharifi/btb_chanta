<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ProductCompletionTaskType;
use App\Services\ProductCompletionScopeDefinitionValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductCompletionScopeTest extends TestCase
{
    public function test_default_scope_never_contains_commercial_fields(): void
    {
        $scope = app(ProductCompletionScopeDefinitionValidator::class)
            ->resolve(ProductCompletionTaskType::FULL_COMPLETION);

        $this->assertArrayNotHasKey('pricing', $scope);
        $this->assertArrayNotHasKey('product_supplier', $scope);
        $this->assertArrayNotHasKey('quantity_tiers', $scope);
        $this->assertTrue($scope['title']);
    }

    public function test_unknown_or_commercial_scope_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(ProductCompletionScopeDefinitionValidator::class)->resolve(
            ProductCompletionTaskType::CONTENT,
            ['pricing' => true]
        );
    }
}
