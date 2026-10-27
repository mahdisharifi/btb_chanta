<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProductCompletionMediaStatus;
use App\Models\ProductCompletionMedia;
use App\Models\ProductCompletionTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductCompletionMediaFactory extends Factory
{
    protected $model = ProductCompletionMedia::class;

    public function definition(): array
    {
        $name = Str::ulid().'.jpg';

        return [
            'task_id' => ProductCompletionTask::factory(),
            'uploaded_by' => User::factory(),
            'disk' => 'local',
            'temporary_path' => 'product-completion/test/originals/'.$name,
            'original_name' => 'product.jpg',
            'safe_name' => $name,
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'file_size' => 100000,
            'width' => 1000,
            'height' => 1000,
            'checksum' => hash('sha256', fake()->uuid()),
            'status' => ProductCompletionMediaStatus::TEMPORARY,
        ];
    }
}
