<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Outsourcing;

use App\Enums\ProductCompletionTaskStatus;
use App\Models\ProductCompletionTask;
use App\Models\User;
use App\Services\ProductCompletionConflictResolutionService;
use App\Services\ProductCompletionTaskService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;

#[Layout('components.layouts.admin')]
class TaskShow extends Component
{
    #[Locked]
    public string $taskPublicId;

    public ?int $assignedTo = null;
    public ?int $reviewerId = null;
    public string $cancelReason = '';
    public string $newDueAt = '';
    public string $conflictResolutionReason = '';

    public function mount(ProductCompletionTask $task): void
    {
        Gate::authorize('viewTask', $task);
        $this->taskPublicId = $task->public_id;
        $this->assignedTo = $task->assigned_to;
        $this->reviewerId = $task->reviewer_id;
        $this->newDueAt = $task->due_at
            ? jdate($task->due_at->copy()->timezone(config('app.timezone')))->format('Y/m/d H:i')
            : '';
    }

    public function publish(ProductCompletionTaskService $service): void
    {
        $published = DB::transaction(function () use ($service): bool {
            Gate::authorize('outsourcing.publish_task');
            $task = ProductCompletionTask::query()->where('public_id', $this->taskPublicId)->lockForUpdate()->firstOrFail();
            Gate::authorize('viewTask', $task);
            if ($task->status !== ProductCompletionTaskStatus::DRAFT || $task->published_at !== null || $task->assigned_to !== null) {
                return false;
            }
            $service->publish(auth()->user(), $task);

            return true;
        });
        if (! $published) {
            $this->dispatch('notify', message: 'وضعیت تسک به‌روز شد؛ این تسک قابل انتشار مجدد نیست.', type: 'info');

            return;
        }
        $this->dispatch('notify', message: 'تسک منتشر شد و اکنون برای پذیرش کارشناسان در دسترس است.', type: 'success');
    }

    public function assign(ProductCompletionTaskService $service): void
    {
        $task = $this->task();
        $this->validate([
            'assignedTo' => ['required', 'integer', 'exists:users,id'],
            'reviewerId' => ['nullable', 'integer', 'exists:users,id'],
        ], [
            'assignedTo.required' => 'انتخاب پیمانکار الزامی است.',
        ]);
        $service->assign(
            auth()->user(),
            $task,
            User::query()->findOrFail($this->assignedTo),
            $this->reviewerId ? User::query()->findOrFail($this->reviewerId) : null,
        );
        $this->dispatch('notify', message: 'تخصیص تسک بروزرسانی شد.', type: 'success');
    }

    public function retryConflict(
        int $expectedLockVersion,
        ProductCompletionConflictResolutionService $service,
    ): void {
        $service->retry(auth()->user(), $this->task(), $expectedLockVersion);
        $this->dispatch('notify', message: 'تداخل رفع شد و نهایی‌سازی تسک دوباره آغاز شد.', type: 'success');
    }

    public function returnConflictForRevision(
        int $expectedLockVersion,
        ProductCompletionConflictResolutionService $service,
    ): void {
        $this->validate([
            'conflictResolutionReason' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'conflictResolutionReason.required' => 'توضیح موردنیاز برای اصلاح الزامی است.',
            'conflictResolutionReason.min' => 'توضیح اصلاح باید حداقل پنج نویسه باشد.',
            'conflictResolutionReason.max' => 'توضیح اصلاح نمی‌تواند بیشتر از دو هزار نویسه باشد.',
        ]);

        $service->requestRevision(
            auth()->user(),
            $this->task(),
            $expectedLockVersion,
            $this->conflictResolutionReason,
        );
        $this->conflictResolutionReason = '';
        $this->dispatch('notify', message: 'تسک برای رفع تداخل به پیمانکار بازگردانده شد.', type: 'success');
    }

    public function retryFinalization(
        int $expectedLockVersion,
        ProductCompletionConflictResolutionService $service,
    ): void {
        $service->retryFinalization(auth()->user(), $this->task(), $expectedLockVersion);
        $this->dispatch('notify', message: 'درخواست نهایی‌سازی ثبت شد؛ اگر پردازش قبلی در صف باشد، همان اجرا ادامه می‌یابد.', type: 'success');
    }

    public function extendDeadline(ProductCompletionTaskService $service): void
    {
        $task = $this->task();
        Gate::authorize('extendDeadline', $task);
        $this->newDueAt = strtr(trim($this->newDueAt), array_combine(
            preg_split('//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY),
            str_split('01234567890123456789'),
        ));
        $this->validate([
            'newDueAt' => ['required', 'regex:/^[1-2][0-9]{3}\/[0-9]{2}\/[0-9]{2} (?:[01][0-9]|2[0-3]):[0-5][0-9]$/'],
        ], [
            'newDueAt.required' => 'مهلت جدید را انتخاب کنید.',
            'newDueAt.regex' => 'تاریخ و ساعت شمسی را با قالب ۱۴۰۵/۰۷/۲۰ ۱۸:۰۰ وارد کنید.',
        ]);
        [$year, $month, $day] = array_map('intval', explode('/', substr($this->newDueAt, 0, 10)));
        if (! CalendarUtils::checkDate($year, $month, $day)) {
            throw ValidationException::withMessages(['newDueAt' => 'تاریخ شمسی معتبر نیست.']);
        }

        $dueAt = Jalalian::fromFormat(
            'Y/m/d H:i', $this->newDueAt, new \DateTimeZone(config('app.timezone')),
        )->toCarbon()->startOfMinute();
        if (! $dueAt->isFuture()) {
            throw ValidationException::withMessages(['newDueAt' => 'مهلت جدید باید در آینده باشد.']);
        }

        $service->extendDeadline(auth()->user(), $task, $dueAt);
        $this->dispatch('notify', message: 'مهلت تسک به‌روزرسانی شد.', type: 'success');
    }

    public function cancel(ProductCompletionTaskService $service): void
    {
        $service->cancel(auth()->user(), $this->task(), $this->cancelReason);
        $this->dispatch('notify', message: 'تسک لغو شد.', type: 'success');
    }

    public function render()
    {
        $task = $this->task()->load([
            'product:id,title,category_id',
            'assignee:id,name',
            'reviewer:id,name',
            'submissions:id,task_id,public_id,version_no,status,submitted_at',
            'revisions:id,task_id,revision_no,status,reason,requested_at',
        ]);

        return view('livewire.admin.outsourcing.task-show', [
            'task' => $task,
            'outsourcers' => User::role('outsourcer')->select(['id', 'name'])->get(),
            'reviewers' => User::role('reviewer')->select(['id', 'name'])->get(),
        ]);
    }

    private function task(): ProductCompletionTask
    {
        $task = ProductCompletionTask::query()->where('public_id', $this->taskPublicId)->firstOrFail();
        Gate::authorize('viewTask', $task);

        return $task;
    }
}
