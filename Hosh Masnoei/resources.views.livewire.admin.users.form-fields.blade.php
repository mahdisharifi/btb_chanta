<div class="row">
    <div class="col-md-6">
        <div class="mb-3">
            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="name" wire:model="name" required>
            @error('name')
            <p class="text-danger small mt-1">{{ $message }}</p>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="mb-3">
            <label for="email" class="form-label">Email address <span class="text-danger">*</span></label>
            <input type="email" class="form-control" id="email" wire:model="email" required>
            @error('email')
            <p class="text-danger small mt-1">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="mb-3">
            <label for="password" class="form-label">
                Password
                @if(isset($editMode) && $editMode)
                <small class="text-muted">(Leave blank to keep current)</small>
                @else
                <span class="text-danger">*</span>
                @endif
            </label>
            <input type="password" class="form-control" id="password" wire:model="password"
                   @if(!isset($editMode) || !$editMode) required @endif>
            @error('password')
            <p class="text-danger small mt-1">{{ $message }}</p>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="mb-3">
            <label for="password_confirmation" class="form-label">
                Confirm Password
                @if(!isset($editMode) || !$editMode)
                <span class="text-danger">*</span>
                @endif
            </label>
            <input type="password" class="form-control" id="password_confirmation" wire:model="password_confirmation"
                   @if(!isset($editMode) || !$editMode) required @endif>
            @error('password_confirmation')
            <p class="text-danger small mt-1">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>

<div class="mb-3">
    <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
    <select class="form-select" id="role" wire:model="role" required>
        <option value="">Select a role</option>
        @foreach ($roles as $value => $label)
        <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
    </select>
    @error('role')
    <p class="text-danger small mt-1">{{ $message }}</p>
    @enderror
</div>

<div class="mb-3 form-check form-switch">
    <input type="checkbox" class="form-check-input" id="is_active" wire:model="is_active" role="switch">
    <label class="form-check-label" for="is_active">Active Account</label>
    @error('is_active')
    <p class="text-danger small mt-1">{{ $message }}</p>
    @enderror
</div>
