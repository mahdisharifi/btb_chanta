<?php

namespace App\Livewire\Supplier;

use App\Models\Feature;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class BuyFeature extends Component
{
    public $featureId;
    public Feature $feature;
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
            $this->error = "برای خرید این امکان، ابتدا باید یک اشتراک فعال (تامین‌کننده یا کاسب ویژه) داشته باشید.";
            return;
        }

        // استفاده از ends_at بر اساس جدول user_subscriptions شما
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
            'ends_at' => $this->activeSubscription->ends_at, // همگام‌سازی با ends_at اشتراک اصلی
            'is_active' => true,
        ]);

        session()->flash('success', 'امکان مورد نظر با موفقیت خریداری و تا پایان اشتراک شما فعال شد.');

        // ریدایرکت داینامیک بر اساس نقش کاربر
        if ($user->isSupplier()) {
            return redirect()->route('supplier.dashboard');
        } elseif ($user->isRetailerPremium()) {
            return redirect()->route('retailer.dashboard');
        }

        return redirect()->route('dashboard'); // مسیر پیش‌فرض امن
    }

    public function render()
    {
        // انتخاب داینامیک Layout بر اساس نقش
        $layout = Auth::user()->isSupplier() ? 'components.layouts.supplier' : 'components.layouts.retailer';

        return view('livewire.supplier.buy-feature')->layout($layout);
    }
}
