<?php

namespace App\Livewire\Admin\Suppliers;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class Create extends Component
{
    public $user_id;
    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('create', Supplier::class);
    }

    protected function rules(): array
    {
        return ['user_id' => ['required', 'integer', Rule::exists('users', 'id'), Rule::unique('suppliers', 'user_id')]];
    }

    protected function messages(): array
    {
        return [
            'user_id.required' => 'لطفاً کاربر را انتخاب کنید.',
            'user_id.integer' => 'کاربر معتبر نیست.',
            'user_id.exists' => 'کاربر وجود ندارد.',
            'user_id.unique' => 'کاربر پروفایل تأمین‌کننده دارد؛ حتی اگر حذف شده باشد.',
        ];
    }

    public function save()
    {
        Gate::authorize('create', Supplier::class);
        $validated = $this->validate();

        return redirect()->route('user.register.supplier.complete', ['user_id' => $validated['user_id'], 'source' => 'admin']);
    }

    public function render()
    {
        $search = trim($this->search);
        $eligible = User::query()->whereNotIn('id', Supplier::withTrashed()->select('user_id')->whereNotNull('user_id'));
        $users = (clone $eligible)->when($search !== '', function ($query) use ($search) {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('mobile', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        })->orderBy('name')->orderBy('id')->limit(30)->get(['id', 'name', 'mobile', 'email']);

        $matchingCount = $users->count();
        $retainedSelection = null;

        if (is_scalar($this->user_id) && ctype_digit((string) $this->user_id) && ! $users->contains('id', $this->user_id)) {
            $selected = (clone $eligible)->find($this->user_id, ['id', 'name', 'mobile', 'email']);
            if ($selected) {
                $users->prepend($selected);
                $retainedSelection = $selected;
            }
        }

        return view('livewire.admin.suppliers.create', compact('users', 'matchingCount', 'retainedSelection'));
    }
}
