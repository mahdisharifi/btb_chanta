<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Outsourcing;

use App\Enums\CommissionContentType;
use App\Enums\ProductCompletionTaskType;
use App\Models\CommissionRule;
use App\Services\CommissionRuleService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class CommissionRules extends Component
{
    use WithPagination;

    public string $name = '';
    public string $taskType = 'full_completion';
    public string $revisionPercentage = '30.00';
    public string $currency = 'IRR';

    /** @var array<string, array{enabled: bool, unit_amount: string, max_payable_units: int}> */
    public array $contentRates = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', CommissionRule::class);
        $this->resetContentRates();
    }

    public function updatedTaskType(): void
    {
        $this->resetContentRates();
        $this->resetValidation();
    }

    public function save(CommissionRuleService $service): void
    {
        $items = [];

        foreach (CommissionContentType::cases() as $contentType) {
            $rate = $this->contentRates[$contentType->value] ?? [];
            $items[] = [
                'content_type' => $contentType->value,
                'unit_amount' => (string) ($rate['unit_amount'] ?? '0'),
                'max_payable_units' => (int) ($rate['max_payable_units'] ?? 0),
                'is_enabled' => (bool) ($rate['enabled'] ?? false),
            ];
        }

        $service->createPerContentVersion(auth()->user(), [
            'name' => $this->name,
            'task_type' => $this->taskType,
            'revision_percentage' => $this->revisionPercentage,
            'currency' => $this->currency,
            'items' => $items,
        ]);

        $this->name = '';
        $this->resetContentRates();
        $this->dispatch('notify', message: 'نسخه جدید قانون پورسانت ایجاد شد.', type: 'success');
    }

    public function deactivate(string $publicId, CommissionRuleService $service): void
    {
        $rule = CommissionRule::query()->where('public_id', $publicId)->firstOrFail();
        $service->deactivate(auth()->user(), $rule);
        $this->dispatch('notify', message: 'قانون پورسانت غیرفعال شد.', type: 'success');
    }

    public function render()
    {
        Gate::authorize('viewAny', CommissionRule::class);

        return view('livewire.admin.outsourcing.commission-rules', [
            'rules' => CommissionRule::query()->with('items')->latest('id')->paginate(20),
            'taskTypes' => collect(ProductCompletionTaskType::options())
                ->except(ProductCompletionTaskType::SEO->value)
                ->all(),
            'contentTypes' => $this->applicableContentTypes(),
        ]);
    }

    private function resetContentRates(): void
    {
        $applicable = array_keys($this->applicableContentTypes());
        $defaults = [
            CommissionContentType::IMAGE->value => 6,
            CommissionContentType::VIDEO->value => 0,
            CommissionContentType::ATTRIBUTE->value => 20,
            CommissionContentType::DESCRIPTION->value => 1,
        ];
        $this->contentRates = [];

        foreach (CommissionContentType::cases() as $contentType) {
            $this->contentRates[$contentType->value] = [
                'enabled' => in_array($contentType->value, $applicable, true)
                    && $contentType !== CommissionContentType::VIDEO,
                'unit_amount' => '0',
                'max_payable_units' => $defaults[$contentType->value],
            ];
        }
    }

    /** @return array<string, array{label: string, unit: string, help: string}> */
    private function applicableContentTypes(): array
    {
        $types = match ($this->taskType) {
            ProductCompletionTaskType::CONTENT->value => [CommissionContentType::DESCRIPTION],
            ProductCompletionTaskType::ATTRIBUTES->value => [CommissionContentType::ATTRIBUTE],
            ProductCompletionTaskType::MEDIA->value => [CommissionContentType::IMAGE],
            ProductCompletionTaskType::FULL_COMPLETION->value,
            ProductCompletionTaskType::CORRECTION->value => [
                CommissionContentType::IMAGE,
                CommissionContentType::ATTRIBUTE,
                CommissionContentType::DESCRIPTION,
            ],
            default => [],
        };
        $metadata = [
            CommissionContentType::IMAGE->value => [
                'label' => 'تصاویر',
                'unit' => 'هر تصویر جدید یا حذف تأییدشده = یک واحد',
                'help' => 'تغییر ترتیب، عنوان یا متن جایگزین تصویر پورسانت جدا ندارد.',
            ],
            CommissionContentType::ATTRIBUTE->value => [
                'label' => 'مشخصه‌ها',
                'unit' => 'هر مشخصه = یک واحد',
                'help' => 'تک‌مقداری یا چندمقداری بودن و تعداد مقادیر، تعداد واحد را تغییر نمی‌دهد.',
            ],
            CommissionContentType::DESCRIPTION->value => [
                'label' => 'توضیحات کوتاه و بلند',
                'unit' => 'کل توضیحات = یک واحد',
                'help' => 'تغییر توضیح کوتاه، بلند یا هر دو در مجموع یک واحد محاسبه می‌شود.',
            ],
        ];
        $result = [];

        foreach ($types as $type) {
            $result[$type->value] = $metadata[$type->value];
        }

        return $result;
    }
}
