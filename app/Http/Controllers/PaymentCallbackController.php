<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Shetabit\Multipay\Invoice;
use Shetabit\Payment\Facade\Payment;
use Shetabit\Multipay\Exceptions\InvalidPaymentException;
use App\Models\UserSubscription;
use App\Models\Transaction;
use App\Enums\UserRole;
use Illuminate\Support\Facades\DB;

class PaymentCallbackController extends Controller
{
    public function verify(Request $request)
    {
        // دریافت پارامترهای درگاه (زرین‌پال معمولا Authority برمی‌گرداند)
        $authority = $request->query('Authority') ?? $request->query('transaction_id');
        $status = $request->query('Status');

        // ۱. پیدا کردن تراکنش در دیتابیس با استفاده از Authority
        $transaction = Transaction::where('authority', $authority)->first();

        if (!$transaction) {
            return redirect()->route('retailer.dashboard')->with('error', 'تراکنش یافت نشد یا غیرمعتبر است.');
        }

        // ۲. پیدا کردن اشتراک در حال انتظارِ متصل به این تراکنش
        $subscription = UserSubscription::where('transaction_id', $transaction->id)
            ->where('status', 'pending')
            ->first();

        if (!$subscription) {
            return redirect()->route('retailer.dashboard')->with('error', 'اشتراکی برای این تراکنش یافت نشد یا قبلاً تعیین تکلیف شده است.');
        }

        // بررسی وضعیت انصراف کاربر در درگاه
        if ($status === 'NOK' || $status === 'Canceled') {
            $subscription->update(['status' => 'failed']);
            $transaction->update(['status' => 'failed']);

            return redirect()->route('retailer.dashboard')->with('error', 'پرداخت توسط شما لغو شد.');
        }

        try {
            DB::beginTransaction();

            // ۳. تایید پرداخت از طریق پکیج Shetabit
            // دقت کنید که مبلغ باید حتما از دیتابیس خودمان خوانده شود
            $receipt = Payment::amount((int) $transaction->amount)
                ->transactionId($authority)
                ->verify();

            // ۴. بروزرسانی تراکنش
            $transaction->update([
                'status' => 'success',
                'tracking_code' => $receipt->getReferenceId(),
                'paid_at' => now(),
            ]);

            // ۵. بروزرسانی وضعیت اشتراک
            $subscription->update([
                'status' => 'active', // یا paid بر اساس Enum پروژه شما
                'payment_tracking_code' => $receipt->getReferenceId(),
                'starts_at' => now(),
                'ends_at' => now()->addDays($subscription->plan->duration_days),
            ]);

            // ۶. تغییر نقش کاربر در صورت نیاز (مثلا به RETAILER_PREMIUM)
            $user = $subscription->user;
            if ($user->role !== UserRole::RETAILER_PREMIUM->value) {
                $user->update(['role' => UserRole::RETAILER_PREMIUM->value]);
            }

            DB::commit();

            return redirect()->route('retailer.dashboard')->with('success', 'پرداخت با موفقیت انجام شد. کد پیگیری: ' . $receipt->getReferenceId());

        } catch (InvalidPaymentException $exception) {
            DB::rollBack();

            // در صورت خطای درگاه (مثلا مبلغ نامعتبر یا تایید تکراری)
            $subscription->update(['status' => 'failed']);
            $transaction->update(['status' => 'failed']);

            return redirect()->route('retailer.dashboard')->with('error', 'خطا در تایید پرداخت: ' . $exception->getMessage());
        } catch (\Exception $exception) {
            DB::rollBack();

            return redirect()->route('retailer.dashboard')->with('error', 'خطای سیستمی رخ داده است. لطفا با پشتیبانی تماس بگیرید.');
        }
    }
}
