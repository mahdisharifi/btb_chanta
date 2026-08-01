<?php

namespace App\Livewire\Retailer\Subscriptions;

use Livewire\Component;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Shetabit\Multipay\Invoice;
use Shetabit\Payment\Facade\Payment;
use Illuminate\Support\Facades\DB;

#[Layout('components.layouts.retailer')]
class Index extends Component
{
    public function subscribe($planId)
    {
        $plan = SubscriptionPlan::findOrFail($planId);
        $user = Auth::user();

        // بررسی حداقل مبلغ برای زرین‌پال (حداقل 1000 تومان)
        if ($plan->price < 1000) {
            session()->flash('error', 'مبلغ تراکنش باید حداقل ۱۰۰۰ تومان باشد. مبلغ فعلی: ' . $plan->price);
            return;
        }

        $invoice = (new Invoice)->amount((int) $plan->price);

        // آماده‌سازی جزئیات - فقط در صورت وجود مقدار، کلید را اضافه می‌کنیم
        $details = [
            'description' => 'خرید اشتراک خرده‌فروش - پلن: ' . $plan->name,
        ];

        if (!empty($user->phone)) {
            $details['mobile'] = $user->phone;
        }
        if (!empty($user->email)) {
            $details['email'] = $user->email;
        }

        $invoice->detail($details);

        try {
            $payment = Payment::callbackUrl(route('payment.callback'))
                ->purchase($invoice, function($driver, $transactionId) use ($plan, $user) {

                    DB::transaction(function () use ($plan, $user, $transactionId) {
                        // ۱. ابتدا تراکنش را در جدول مخصوص تراکنش‌ها ثبت می‌کنیم
                        $transaction = Transaction::create([
                            'user_id'     => $user->id,
                            'amount'      => $plan->price,
                            'gateway'     => 'zarinpal',
                            'authority'   => $transactionId, // شناسه آلفانومریک زرین‌پال اینجا می‌نشیند
                            'status'      => 'pending',
                            'description' => 'خرید اشتراک خرده‌فروش - پلن: ' . $plan->name,
                        ]);

                        // ۲. سپس اشتراک را ایجاد کرده و ID عددی تراکنش را به آن می‌دهیم
                        $user->subscriptions()->create([
                            'subscription_plan_id' => $plan->id,
                            'transaction_id'       => $transaction->id, // کلید خارجی صحیح (Integer)
                            'status'               => 'pending', // نیازمند اصلاح در مایگریشن (توضیحات زیر)
                        ]);
                    });

                })->pay();

            return redirect()->away($payment->getAction());

        } catch (\Exception $e) {
            // دیباگ دقیق خطا
            dd([
                'callback'=>route('payment.callback'),
                'error_message' => $e->getMessage(),
                'plan_price'    => $plan->price,
                'merchant_id'   => config('payment.drivers.zarinpal.merchantId'),
                'mode'          => config('payment.drivers.zarinpal.mode'),
                'details'       => $details
            ]);
        }
    }

    public function render()
    {
        $plans = SubscriptionPlan::where('is_active', true)->orderBy('price')->get();

        $currentSubscription = Auth::user()
            ->subscriptions()
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->latest()
            ->first();

        return view('livewire.retailer.subscriptions.index', [
            'plans' => $plans,
            'currentSubscription' => $currentSubscription,
        ]);
    }
}
