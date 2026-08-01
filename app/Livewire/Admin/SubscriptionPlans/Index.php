<?php

namespace App\Livewire\Admin\SubscriptionPlans;

use App\Models\SubscriptionPlan;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

#[Layout('components.layouts.admin')]
class Index extends Component
{
    use WithPagination;

    public $plan_id;
    public $title;
    public $price;
    public $duration_days;
    public $description;
    public $target_role = 'all'; // مقدار پیش‌فرض
    public $is_active = true;

    public $isOpen = false;
    public $isEditMode = false;

    protected $rules = [
        'title' => 'required|string|max:255',
        'price' => 'required|numeric|min:0',
        'duration_days' => 'required|integer|min:1',
        'description' => 'nullable|string|max:1000',
        'target_role' => 'required|in:supplier,retailer_premium,retailer_basic,end_user,all', // مقادیر مجاز جدید
        'is_active' => 'boolean',
    ];

    public function create()
    {
        $this->resetInputFields();
        $this->isEditMode = false;
        $this->isOpen = true;
    }

    public function edit($id)
    {
        $this->resetInputFields();
        $this->isEditMode = true;

        $plan = SubscriptionPlan::findOrFail($id);
        $this->plan_id = $plan->id;
        $this->title = $plan->title;
        $this->price = $plan->price;
        $this->duration_days = $plan->duration_days;
        $this->description = $plan->description;
        $this->target_role = $plan->target_role;
        $this->is_active = $plan->is_active;

        $this->isOpen = true;
    }

    public function store()
    {
        $this->validate();

        SubscriptionPlan::updateOrCreate(
            ['id' => $this->plan_id],
            [
                'title' => $this->title,
                'price' => $this->price,
                'duration_days' => $this->duration_days,
                'description' => $this->description,
                'target_role' => $this->target_role,
                'is_active' => $this->is_active,
            ]
        );

        session()->flash('message', $this->isEditMode ? 'پلن با موفقیت ویرایش شد.' : 'پلن جدید با موفقیت ایجاد شد.');

        $this->closeModal();
    }

    public function delete($id)
    {
        SubscriptionPlan::findOrFail($id)->delete();
        session()->flash('message', 'پلن با موفقیت حذف شد.');
    }

    public function toggleActive($id)
    {
        $plan = SubscriptionPlan::findOrFail($id);
        $plan->update(['is_active' => !$plan->is_active]);
        session()->flash('message', 'وضعیت پلن با موفقیت تغییر کرد.');
    }

    public function closeModal()
    {
        $this->isOpen = false;
        $this->resetInputFields();
    }

    private function resetInputFields()
    {
        $this->plan_id = null;
        $this->title = '';
        $this->price = null;
        $this->duration_days = null;
        $this->description = '';
        $this->target_role = 'all';
        $this->is_active = true;
        $this->resetValidation();
    }

    public function render()
    {
        $plans = SubscriptionPlan::latest()->paginate(10);
        return view('livewire.admin.subscription-plans.index', compact('plans'));
    }
}
