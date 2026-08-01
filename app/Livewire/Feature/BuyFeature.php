<?php

namespace App\Livewire\Feature;

use App\Models\Feature;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class BuyFeature extends Component
{
    public $featureId;
    public $feature;
    public $remainingDays = 0;
    public $calculatedPrice = 0;
    public $activeSubscription = null;
    public $error = null;

    public function mount($featureId)
    {
        $this->featureId = $featureId;
        $this->feature = Feature::findOrFail($featureId);

        $user = Auth::user();

        if ($user->hasFeature($this->feature->slug)) {
            $this->error = "شما این امکان را در حال حاضر فعال دارید.";
            return;
        }

        $this->activeSubscription = $user->getActiveSubscription();

        if (!$this->activeSubscription) {
            $this->error = "برای خرید این امکان، ابتدا باید یک اشتراک فعال داشته باشید.";
            return;
        }

        $endsAt = Carbon::parse($this->activeSubscription->ends_at);
        $this->remainingDays = (int) ceil(now()->diffInHours($endsAt) / 24);

        if ($this->remainingDays <= 0) {
            $this->error = "اشتراک شما منقضی شده است. لطفا ابتدا اشتراک خود را تمدید کنید.";
            return;
        }

        $dailyRate = $this->feature->base_price / $this->feature->base_duration_days;
        $this->calculatedPrice = (int) round($dailyRate * $this->remainingDays);
    }

    public function purchase()
    {
        if ($this->error) {
            return;
        }

        $user = Auth::user();

        $user->purchasedFeatures()->attach($this->feature->id, [
            'paid_amount' => $this->calculatedPrice,
            'purchased_days' => $this->remainingDays,
            'purchased_at' => now(),
            'ends_at' => $this->activeSubscription->ends_at,
            'is_active' => true,
        ]);

        session()->flash('success', 'امکان مورد نظر با موفقیت خریداری و تا پایان اشتراک شما فعال شد.');

        if ($user->isSupplier()) {
            return redirect()->route('supplier.dashboard');
        } elseif ($user->isRetailerPremium()) {
            return redirect()->route('retailer.dashboard');
        }

        return redirect()->route('dashboard');
    }

    public function render()
    {
        $layout = Auth::user()->isSupplier() ? 'components.layouts.supplier' : 'components.layouts.retailer';

        return view('livewire.feature.buy-feature')->layout($layout);
    }
}
