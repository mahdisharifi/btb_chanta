<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BranchSwitchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $user = $request->user();
        $primary = $user->supplier()->firstOrFail();
        $branchId = (int) $validated['branch_id'];

        if ($branchId === (int) $primary->id) {
            $request->session()->forget('supplier.active_branch_id');
        } else {
            Supplier::query()
                ->whereKey($branchId)
                ->where('user_id', $user->id)
                ->where('parent_supplier_id', $primary->id)
                ->where('is_active', true)
                ->firstOrFail();

            $request->session()->put('supplier.active_branch_id', $branchId);
        }

        $returnTo = $validated['return_to'] ?? null;
        if ($returnTo) {
            $parts = parse_url($returnTo);
            $path = is_array($parts) ? ($parts['path'] ?? '') : '';
            $isRelativeInternalPath = is_array($parts)
                && !isset($parts['scheme'])
                && !isset($parts['host'])
                && !isset($parts['user'])
                && !isset($parts['pass'])
                && !Str::startsWith($returnTo, '//')
                && Str::startsWith($path, '/supplier/');

            if ($isRelativeInternalPath) {
                return redirect()->to($returnTo);
            }
        }

        return redirect()->route('supplier.dashboard');
    }
}
