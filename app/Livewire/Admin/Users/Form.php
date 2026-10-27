<?php

namespace App\Livewire\Admin\Users;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class Form extends Component
{
    public ?User $user = null;

    public $name = '';
    public $email = '';
    public $mobile = '';
    public $password = '';
    public $password_confirmation = '';
    public bool $allow_without_mobile = false;
    public bool $mark_as_verified = false;
    public bool $is_active = true;

    public $role;

    public function mount(?User $user = null): void
    {
        if (! $user?->exists) {
            Gate::authorize('create', User::class);
            $this->role = UserRole::RETAILER_BASIC->value;

            return;
        }

        Gate::authorize('update', $user);

        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->mobile = $user->mobile ?? '';
        $this->allow_without_mobile = blank($user->mobile);
        $this->mark_as_verified = (bool) $user->is_verified;
        $this->is_active = (bool) $user->is_active;
        $this->role = $user->roles()->value('name') ?? UserRole::RETAILER_BASIC->value;
    }

    protected function rules(): array
    {
        $userId = $this->user?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'mobile' => array_merge(
                ($this->allow_without_mobile || $this->isSystemRole()) ? ['nullable'] : ['required'],
                ['string', 'max:20', Rule::unique('users', 'mobile')->ignore($userId)],
            ),
            'allow_without_mobile' => ['boolean'],
            'mark_as_verified' => ['boolean'],
            'is_active' => ['boolean'],
            'password' => [$this->isEditing() ? 'nullable' : 'required', 'string', 'confirmed', Password::defaults()],
            'role' => ['required', 'string', Rule::in(UserRole::values())],
        ];
    }

    public function save()
    {
        $this->authorizeChanges();
        $this->validate();
        Gate::authorize('assignSensitiveRole', [User::class, $this->role]);

        $editing = $this->isEditing();

        DB::transaction(function () use ($editing) {
            $mobile = trim((string) $this->mobile);
            $mobile = $mobile !== '' ? $mobile : null;

            $user = $editing ? $this->user : new User();
            $mobileChanged = $editing && $user->mobile !== $mobile;

            $user->fill([
                'name' => $this->name,
                'email' => $this->email,
                'mobile' => $mobile,
            ]);

            if (filled($this->password)) {
                $user->password = Hash::make($this->password);
            }

            $user->forceFill([
                'is_verified' => $this->mark_as_verified,
                'is_active' => $this->is_active,
            ]);

            if ($mobileChanged) {
                $user->mobile_verified_at = null;
            }

            $user->save();
            $user->syncRoles([$this->role]);
        });

        session()->flash('success', $editing ? 'اطلاعات کاربر به‌روزرسانی شد.' : 'کاربر با موفقیت ایجاد شد.');

        return $this->redirectRoute('admin.users.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.users.form', [
            'isEditing' => $this->isEditing(),
            'isSystemRole' => $this->isSystemRole(),
            'canUseAdminOverrides' => Gate::allows('createWithoutMobile', User::class),
            'canToggleStatus' => ! $this->isEditing() || Gate::allows('toggleStatus', $this->user),
        ]);
    }

    private function authorizeChanges(): void
    {
        if (! $this->isEditing()) {
            Gate::authorize('create', User::class);
        } else {
            Gate::authorize('update', $this->user);

            if ($this->role !== $this->user->roles()->value('name')) {
                Gate::authorize('changeRole', $this->user);
            }

            if ($this->is_active !== (bool) $this->user->is_active) {
                Gate::authorize('toggleStatus', $this->user);
            }
        }

        if ($this->allow_without_mobile) {
            Gate::authorize('createWithoutMobile', User::class);
        }

        if ($this->mark_as_verified !== (bool) $this->user?->is_verified) {
            Gate::authorize('verifyOnCreate', User::class);
        }
    }

    private function isEditing(): bool
    {
        return (bool) $this->user?->exists;
    }

    private function isSystemRole(): bool
    {
        return in_array($this->role, [
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
            UserRole::OUTSOURCER->value,
            UserRole::REVIEWER->value,
            UserRole::FINANCE_MANAGER->value,
        ], true);
    }
}
