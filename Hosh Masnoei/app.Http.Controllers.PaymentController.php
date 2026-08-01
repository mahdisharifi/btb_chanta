<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function callback(Request $request)
    {
        $authority = $request->query('Authority');
        $status = $request->query('Status');

        $transaction = Transaction::where('authority', $authority)->first();

        if (!$transaction) {
            return redirect()->route('retailer.subscriptions.index')->with('error', 'تراکنش یافت نشد.');
        }

        if ($status !== 'OK') {
            $transaction->update(['status' => 'failed']);
            return redirect()->route('retailer.subscriptions.index')->with('error', 'پرداخت توسط شما لغو شد یا ناموفق بود.');
        }

        $merchantId = config('services.zarinpal.merchant_id');

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post('https://payment.zarinpal.com/pg/v4/payment/verify.json', [
                'merchant_id' => $merchantId,
                'amount' => $transaction->amount,
                'authority' => $authority,
            ]);

            $result = $response->json();

            if (isset($result['data']['code']) && ($result['data']['code'] == 100 || $result['data']['code'] == 101)) {

                DB::beginTransaction();
                try {
                    $refId = $result['data']['ref_id'];

                    // بروزرسانی تراکنش
                    $transaction->update([
                        'status' => 'successful',
                        'ref_id' => $refId,
                        'gateway_response' => $result['data']
                    ]);

                    // اعمال منطق خرید اشتراک
                    if ($transaction->payable_type === SubscriptionPlan::class) {
                        $plan = SubscriptionPlan::find($transaction->payable_id);
                        $user = $transaction->user;

                        // غیرفعال کردن اشتراک‌های قبلی
                        $user->subscriptions()->update(['is_active' => false]);

                        // ایجاد اشتراک جدید
                        $subscription = UserSubscription::create([
                            'user_id' => $user->id,
                            'subscription_plan_id' => $plan->id,
                            'transaction_id' => $transaction->id,
                            'starts_at' => now(),
                            'ends_at' => now()->addDays($plan->duration_days),
                            'is_active' => true,
                            'payment_tracking_code' => $refId,
                        ]);

                        // ارتقای نقش کاربر
                        $user->update(['role' => UserRole::RETAILER_PREMIUM->value]);
                    }

                    DB::commit();
                    return redirect()->route('retailer.dashboard')->with('success', "پرداخت با موفقیت انجام شد. شماره پیگیری: $refId");

                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Subscription Error: ' . $e->getMessage());
                    return redirect()->route('retailer.subscriptions.index')->with('error', 'خطا در ثبت اشتراک پس از پرداخت.');
                }

            } else {
                $transaction->update(['status' => 'failed', 'gateway_response' => $result]);
                return redirect()->route('retailer.subscriptions.index')->with('error', 'پرداخت ناموفق بود یا قبلاً تایید شده است.');
            }

        } catch (\Exception $e) {
            Log::error('Verify Payment Error: ' . $e->getMessage());
            return redirect()->route('retailer.subscriptions.index')->with('error', 'خطا در ارتباط با سرور زرین‌پال.');
        }
    }
}
