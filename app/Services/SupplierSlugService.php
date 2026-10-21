<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\SupplierSlugRedirect;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupplierSlugService
{
    public function change(Supplier $supplier, string $requestedSlug): Supplier
    {
        Gate::authorize('updateSlug', $supplier);
        $newSlug = Str::slug($requestedSlug);

        if ($newSlug === '' || strlen($newSlug) > 191) {
            throw ValidationException::withMessages(['slug' => 'اسلاگ نامعتبر یا رزروشده است.']);
        }

        try {
            return DB::transaction(function () use ($supplier, $newSlug): Supplier {
                $lockedSupplier = Supplier::query()->lockForUpdate()->findOrFail($supplier->getKey());
                $oldSlug = $lockedSupplier->slug;

                if ($newSlug === $oldSlug) {
                    return $lockedSupplier;
                }

                $slugIsUsed = Supplier::withTrashed()
                    ->where('slug', $newSlug)
                    ->whereKeyNot($lockedSupplier->getKey())
                    ->lockForUpdate()->first();
                $slugWasUsed = SupplierSlugRedirect::where('old_slug', $newSlug)->lockForUpdate()->first();

                if ($slugIsUsed || $slugWasUsed) {
                    throw ValidationException::withMessages(['slug' => 'اسلاگ نامعتبر یا رزروشده است.']);
                }

                if (filled($oldSlug)) {
                    SupplierSlugRedirect::create([
                        'old_slug' => $oldSlug,
                        'new_slug' => $newSlug,
                        'supplier_id' => $lockedSupplier->getKey(),
                        'created_by' => auth()->id(),
                    ]);
                }

                $lockedSupplier->update(['slug' => $newSlug]);

                return $lockedSupplier->refresh();
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['slug' => 'اسلاگ نامعتبر یا رزروشده است.']);
        }
    }
}
