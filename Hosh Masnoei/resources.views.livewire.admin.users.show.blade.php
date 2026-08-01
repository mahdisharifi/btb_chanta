<div>
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center bg-light">
            <h4 class="mb-0">
                <i class="fas fa-user me-2"></i> User Details
            </h4>
            <div>
                <a href="{{ route('admin.users.edit', $user->id) }}"
                   class="btn btn-warning btn-sm me-2"
                   wire:navigate>
                    <i class="fas fa-edit me-1"></i> Edit
                </a>
                <a href="{{ route('admin.users.index') }}"
                   class="btn btn-secondary btn-sm"
                   wire:navigate>
                    <i class="fas fa-arrow-left me-1"></i> Back to Users
                </a>
            </div>
        </div>

        <div class="card-body">
            {{-- ردیف ۱: نام و ایمیل --}}
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="text-muted small text-uppercase">Name</label>
                    <p class="form-control-plaintext fw-semibold">{{ $user->name }}</p>
                </div>
                <div class="col-md-6">
                    <label class="text-muted small text-uppercase">Email</label>
                    <p class="form-control-plaintext">
                        <a href="mailto:{{ $user->email }}">{{ $user->email }}</a>
                    </p>
                </div>
            </div>

            {{-- ردیف ۲: نقش و وضعیت --}}
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="text-muted small text-uppercase">Role</label>
                    <p class="form-control-plaintext">
                        <span class="badge bg-{{ $roleBadgeColor }} fs-6">
                            {{ $roleLabel }}
                        </span>
                    </p>
                </div>
                <div class="col-md-6">
                    <label class="text-muted small text-uppercase">Status</label>
                    <p class="form-control-plaintext">
                        @if ($user->is_active)
                        <span class="badge bg-success fs-6">
                                <i class="fas fa-check-circle me-1"></i> Active
                            </span>
                        @else
                        <span class="badge bg-danger fs-6">
                                <i class="fas fa-times-circle me-1"></i> Inactive
                            </span>
                        @endif
                    </p>
                </div>
            </div>

            {{-- ردیف ۳: تاریخ‌ها --}}
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="text-muted small text-uppercase">Registered At</label>
                    <p class="form-control-plaintext">
                        <i class="far fa-calendar-alt me-1 text-muted"></i>
                        {{ $user->created_at->format('Y-m-d H:i') }}
                        <small class="text-muted">({{ $user->created_at->diffForHumans() }})</small>
                    </p>
                </div>
                <div class="col-md-6">
                    <label class="text-muted small text-uppercase">Last Updated</label>
                    <p class="form-control-plaintext">
                        <i class="far fa-clock me-1 text-muted"></i>
                        {{ $user->updated_at->format('Y-m-d H:i') }}
                        <small class="text-muted">({{ $user->updated_at->diffForHumans() }})</small>
                    </p>
                </div>
            </div>

            {{-- ردیف ۴: اطلاعات اضافی (در صورت وجود) --}}
            @if ($user->supplier)
            <hr>
            <h5 class="mb-3"><i class="fas fa-truck me-2"></i> Supplier Information</h5>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="text-muted small text-uppercase">Company Name</label>
                    <p class="form-control-plaintext fw-semibold">
                        {{ $user->supplier->company_name ?? '—' }}
                    </p>
                </div>
                <div class="col-md-6">
                    <label class="text-muted small text-uppercase">Phone</label>
                    <p class="form-control-plaintext">
                        {{ $user->supplier->phone ?? '—' }}
                    </p>
                </div>
            </div>
            @endif
        </div>

        {{-- Footer --}}
        <div class="card-footer bg-light d-flex justify-content-end">
            <form wire:submit.prevent="delete" class="d-inline"
                  onsubmit="return confirm('Are you sure you want to delete this user?')">
                @can('delete', $user)
                <a href="{{ route('admin.users.edit', $user->id) }}"
                   class="btn btn-warning me-2"
                   wire:navigate>
                    <i class="fas fa-edit me-1"></i> Edit User
                </a>
                @endcan
            </form>
        </div>
    </div>
</div>
