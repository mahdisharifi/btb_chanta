<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Enums\UserRole;
use Illuminate\Support\Facades\DB;
use Shetabit\Multipay\Exceptions\InvalidPaymentException;
use Shetabit\Payment\Facade\Payment;

class PaymentController extends Controller
{
    public function verify(Request $request)
    {
        // دریافت اطلاعات ذخیره شده در سشن قبل از انتقال به درگاه
        $transactionId = session('payment_transaction_id');
        $planId = session('payment_plan_id');

        if (!$transactionId || !$planId) {
            return redirect()->route('retailer.subscriptions.index')
                ->with('error', 'اطلاعات پرداخت یافت نشد.');
        }

        $transaction = Transaction::findOrFail($transactionId);
        $plan = SubscriptionPlan::findOrFail($planId);
        $user = auth()->user();

        try {
            // تایید پرداخت با درگاه
            $receipt = Payment::amount($transaction->amount)
                ->transactionId($request->Authority ?? $request->trackId)
                ->verify();

            DB::beginTransaction();

            // ۱. بروزرسانی تراکنش
            $transaction->update([
                'status' => 'completed',
                'reference_id' => $receipt->getReferenceId(),
            ]);

            // ۲. غیرفعال کردن اشتراک‌های قبلی
            $user->subscriptions()->update(['status' => 'expired']);

            // ۳. ایجاد اشتراک جدید
            UserSubscription::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'starts_at' => now(),
                'ends_at' => now()->addDays($plan->duration_days),
                'status' => 'active',
            ]);

            // ۴. ارتقای نقش کاربر در صورت نیاز
            if (in_array($user->role, [UserRole::RETAILER->value, 'retailer_basic'])) {
                $user->update(['role' => UserRole::RETAILER_PREMIUM->value]);
            }

            DB::commit();

            // پاک کردن سشن‌های موقت
            session()->forget(['payment_transaction_id', 'payment_plan_id']);

            return redirect()->route('retailer.subscriptions.index')
                ->with('message', 'پرداخت با موفقیت انجام شد و اشتراک شما فعال گردید. شماره پیگیری: ' . $receipt->getReferenceId());

        } catch (InvalidPaymentException $exception) {
            // در صورت لغو پرداخت یا خطا
            $transaction->update(['status' => 'failed']);

            return redirect()->route('retailer.subscriptions.index')
                ->with('error', 'پرداخت ناموفق بود یا توسط شما لغو شد: ' . $exception->getMessage());
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('retailer.subscriptions.index')
                ->with('error', 'خطای سیستمی رخ داد: ' . $e->getMessage());
        }
    }
}
