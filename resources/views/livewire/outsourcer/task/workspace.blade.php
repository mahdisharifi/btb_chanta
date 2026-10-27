@php
    $workspaceTabs = [];
    if (data_get($task->scope_json, 'attributes')) {
        $workspaceTabs['attributes'] = 'ویژگی‌ها';
    }
    if (data_get($task->scope_json, 'title') || data_get($task->scope_json, 'short_description') || data_get($task->scope_json, 'full_description')) {
        $workspaceTabs['content'] = 'توضیحات';
    }
    if (data_get($task->scope_json, 'gallery')) {
        $workspaceTabs['gallery'] = 'تصاویر';
    }
@endphp
<div x-data="{ tab: '{{ array_key_first($workspaceTabs) ?? 'content' }}' }">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">فضای کار تکمیل محصول</h1>
            <p class="text-sm text-slate-500">{{ $task->status->label() }} · نسخه پیش‌نویس {{ $draftVersion }} · اصلاح باقی‌مانده {{ $task->remainingRevisions() }}</p>
        </div>
        <div class="flex gap-2">
            @can('acceptTask', $task)
                <button wire:click="accept" class="rounded-lg bg-indigo-600 px-4 py-2 text-white">پذیرش</button>
            @endcan
            @can('startTask', $task)
                <button wire:click="start" class="rounded-lg bg-indigo-600 px-4 py-2 text-white">شروع</button>
            @endcan
            @can('resumeTask', $task)
                <button wire:click="resumeRevision" class="rounded-lg bg-amber-600 px-4 py-2 text-white">شروع اصلاح</button>
            @endcan
        </div>
    </div>

    <x-product-completion-guide title="راهنمای فضای کار">
        <p>فقط بخش‌های مجاز این تسک قابل ذخیره‌اند. پیش‌نویس می‌تواند ناقص باشد؛ برای ارسال، فیلدهای الزامی باید تکمیل شوند. مقدارهای جدید ویژگی ابتدا به‌صورت پیشنهاد ذخیره می‌شوند و فقط پس از تأیید نهایی بازبین به فهرست مقادیر سیستم و محصول اضافه خواهند شد.</p>
    </x-product-completion-guide>

    @include('livewire.outsourcer.task.partials.task-brief')
    @if($displaySubmission)
        @include('livewire.outsourcer.task.partials.submitted-content')
    @endif

    @if($openRevision)
        @php
            $revisionFieldLabels = [
                'title' => 'عنوان محصول',
                'short_description' => 'توضیحات کوتاه',
                'full_description' => 'توضیحات کامل',
                'attributes' => 'ویژگی‌های محصول',
                'gallery' => 'تصاویر محصول',
            ];
        @endphp
        <section class="mb-4 rounded-xl border border-amber-300 bg-amber-50 p-5 text-amber-950" aria-labelledby="revision-heading">
            <h2 id="revision-heading" class="text-lg font-bold">درخواست اصلاح بازبین</h2>
            <p class="mt-1 text-sm">{{ $openRevision->requester?->name ?? 'بازبین' }} · {{ $openRevision->classification->label() }}</p>
            <p class="mt-4 text-sm font-semibold">بخش‌های نیازمند اصلاح</p>
            <ul class="mt-2 flex flex-wrap gap-2 text-sm">
                @forelse($openRevision->requested_fields_json ?? [] as $revisionField)
                    <li class="rounded-lg border border-amber-200 bg-white px-3 py-1">{{ $revisionFieldLabels[$revisionField] ?? 'سایر بخش‌های محتوا' }}</li>
                @empty
                    <li>تمام بخش‌های مجاز تسک</li>
                @endforelse
            </ul>
            <p class="mt-4 text-sm font-semibold">توضیح بازبین</p>
            <p class="mt-1 whitespace-pre-wrap break-words text-sm leading-7">{{ $openRevision->reason }}</p>
            <p class="mt-3 text-xs">پس از اعمال اصلاحات خواسته‌شده، محتوا را دوباره برای بررسی ارسال کنید.</p>
        </section>
    @endif

    @if($errors->any())
        <div wire:key="workspace-errors-{{ md5(json_encode($errors->all())) }}"
             x-data="{ open: true, returnFocus: document.activeElement, close() { if (!this.open) return; this.open = false; this.$nextTick(() => { const input = document.getElementById('workspace-image-upload'); const target = {{ $errors->has('upload') ? 'true' : 'false' }} ? input : this.returnFocus; if (target?.isConnected && !target.disabled) target.focus(); }); } }"
             x-show="open"
             x-init="$nextTick(() => $refs.closeErrors && $refs.closeErrors.focus())"
             @keydown.escape.window="if (open) close()"
             @keydown.tab.prevent="$refs.closeErrors.focus()"
             class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 p-4"
             role="alertdialog" aria-modal="true" aria-labelledby="workspace-errors-title" aria-describedby="workspace-errors-list">
            <div class="max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl" @click.outside="close()">
                <div class="flex items-start gap-3">
                    <x-heroicon-o-exclamation-triangle class="h-7 w-7 shrink-0 text-red-600" />
                    <div class="min-w-0 flex-1">
                        <h2 id="workspace-errors-title" class="text-base font-bold text-slate-900">عملیات انجام نشد</h2>
                        <p class="mt-1 text-sm text-slate-700">لطفاً موارد زیر را اصلاح کنید و دوباره تلاش کنید.</p>
                        <ul id="workspace-errors-list" class="mt-3 list-disc space-y-2 pr-5 text-sm leading-7 text-red-800">
                            @foreach($errors->all() as $message)<li class="[overflow-wrap:anywhere]">{{ $message }}</li>@endforeach
                        </ul>
                    </div>
                </div>
                <div class="mt-5 flex justify-end">
                    <button type="button" x-ref="closeErrors" @click="close()" class="min-h-11 rounded-lg bg-slate-800 px-6 py-2 text-sm font-semibold text-white hover:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">متوجه شدم</button>
                </div>
            </div>
        </div>
    @endif

    @if($task->due_at && $task->due_at->isPast() && $task->status->canEditDraft())
        <div class="mb-4 rounded-lg border border-amber-400 bg-amber-100 p-4 text-sm font-medium leading-7 text-amber-950" role="alert">مهلت این تسک پایان یافته است؛ مدیر باید مهلت را تمدید کند تا ذخیره و ارسال ممکن شود.</div>
    @endif

    @if($task->status->canEditDraft())
        <div class="mb-4 flex flex-wrap gap-2 rounded-xl border bg-white p-3">
            @foreach($workspaceTabs as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'" class="rounded-lg px-3 py-2" :class="tab === '{{ $key }}' ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-50'">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <form wire:submit="saveDraft" class="space-y-4">
            <section x-show="tab === 'content'" class="space-y-4 rounded-xl border bg-white p-5">
                @if(data_get($task->scope_json, 'short_description') || data_get($task->scope_json, 'full_description'))
                    @include('livewire.outsourcer.task.partials.ai-prompt')
                @endif
                @if(data_get($task->scope_json, 'title'))
                    <label class="block text-sm font-medium">عنوان
                        <input wire:model="draft.title" class="mt-1 w-full rounded-lg border-slate-300">
                    </label>
                @endif

                @if(data_get($task->scope_json, 'short_description'))
                    <label class="block text-sm font-medium">توضیحات کوتاه (برای ارسال الزامی)
                        <textarea wire:model="draft.short_description" rows="4" class="mt-1 w-full rounded-lg border-slate-300"></textarea>
                    </label>
                @endif

                @if(data_get($task->scope_json, 'full_description'))
                    <label class="block text-sm font-medium">توضیحات کامل
                        <textarea wire:model="draft.full_description" rows="12" class="mt-1 w-full rounded-lg border-slate-300"></textarea>
                    </label>
                @endif

            </section>

            <section x-cloak x-show="tab === 'attributes'" class="space-y-4 rounded-xl border bg-white p-5">
                @forelse($attributes as $attribute)
                    @include('components.attribute-value-selector', ['attribute' => $attribute, 'workspace' => true])
                    @error('newAttributeValues.'.$attribute->id)
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    @error('attributes.*.proposed_values')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                @empty
                    <p class="text-slate-500">این تسک ویژگی قابل تکمیلی ندارد.</p>
                @endforelse
            </section>

            <section x-cloak x-show="tab === 'gallery'" class="space-y-4 rounded-xl border bg-white p-5">
                @if(data_get($task->scope_json, 'gallery'))
                    @include('livewire.outsourcer.task.partials.image-upload-guide')
                    <div
                        x-data="{
                            previewUrl: null,
                            previewReady: false,
                            previewFailed: false,
                            fileType: '',
                            uploadInProgress: false,
                            uploadFinished: false,
                            uploadProgress: 0,
                            fileName: '',
                            transferError: '',
                            resetUpload() {
                                if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
                                this.previewUrl = null;
                                this.previewReady = false;
                                this.previewFailed = false;
                                this.fileType = '';
                                this.uploadInProgress = false;
                                this.uploadFinished = false;
                                this.uploadProgress = 0;
                                this.fileName = '';
                                this.transferError = '';
                                const input = document.getElementById('workspace-image-upload');
                                if (input) input.value = '';
                            },
                            failTransfer(message) {
                                const name = this.fileName;
                                this.resetUpload();
                                this.transferError = name + ': ' + message;
                                this.$nextTick(() => this.$refs.fileInput.focus());
                            },
                            chooseFile(event) {
                                const file = event.target.files?.[0];
                                this.previewReady = false;
                                this.previewFailed = false;
                                this.fileType = file?.type || 'نامشخص';
                                this.uploadFinished = false;
                                this.transferError = '';
                                this.fileName = file?.name || '';
                                if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
                                this.previewUrl = file ? URL.createObjectURL(file) : null;
                            },
                        }"
                        x-on:livewire-upload-start="uploadInProgress = true; uploadFinished = false; uploadProgress = 0"
                        x-on:livewire-upload-progress="uploadProgress = $event.detail.progress"
                        x-on:livewire-upload-finish="uploadInProgress = false; uploadFinished = true; uploadProgress = 100"
                        x-on:livewire-upload-error="failTransfer('انتقال فایل ناموفق بود؛ اتصال را بررسی و فایل را دوباره انتخاب کنید.')"
                        x-on:livewire-upload-cancel="failTransfer('انتقال فایل لغو شد؛ فایل را دوباره انتخاب کنید.')"
                        x-on:workspace-upload-finished.window="resetUpload()"
                        x-on:workspace-upload-failed.window="resetUpload()"
                        class="space-y-3"
                    >
                        <label for="workspace-image-upload" class="mb-2 block text-sm font-medium">انتخاب تصویر محصول</label>
                        <input x-ref="fileInput" id="workspace-image-upload" type="file" wire:model="upload" accept="{{ implode(',', config('product_completion.media.allowed_mime_types', ['image/jpeg', 'image/png', 'image/webp'])) }}" aria-describedby="image-upload-guide{{ $errors->has('upload') ? ' image-upload-error' : '' }}" @if($errors->has('upload')) aria-invalid="true" @endif :aria-describedby="'image-upload-guide' + (previewFailed ? ' image-preview-error' : '') + (transferError ? ' image-transfer-error' : '') + '{{ $errors->has('upload') ? ' image-upload-error' : '' }}'" :aria-invalid="previewFailed || !!transferError || {{ $errors->has('upload') ? 'true' : 'false' }}" class="block min-h-11 w-full text-sm" :disabled="uploadInProgress" wire:loading.attr="disabled" wire:target="uploadMedia" x-on:change="chooseFile($event)">
                        <div wire:loading wire:target="upload" role="status" aria-live="polite" class="rounded-lg border border-indigo-200 bg-indigo-50 p-3 text-sm text-indigo-800">
                            <span class="inline-flex items-center gap-2">
                                <span class="h-4 w-4 animate-spin rounded-full border-2 border-indigo-200 border-t-indigo-700" aria-hidden="true"></span>
                                <span>در حال بارگذاری تصویر…</span>
                            </span>
                        </div>
                        <div x-show="fileName" x-cloak class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm" aria-live="polite">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <span class="min-w-0 max-w-full break-all font-medium" dir="ltr" x-text="fileName"></span>
                                <span x-show="uploadInProgress" class="inline-flex shrink-0 items-center gap-2 text-indigo-700">
                                    <span class="h-4 w-4 animate-spin rounded-full border-2 border-indigo-200 border-t-indigo-700" aria-hidden="true"></span>
                                    <span>در حال آماده‌سازی…</span>
                                </span>
                                <span x-show="!uploadInProgress && previewReady && uploadFinished" x-cloak class="shrink-0 text-emerald-700">تصویر آماده است</span>
                            </div>
                            <div x-show="uploadInProgress" class="mt-2 h-2 overflow-hidden rounded-full bg-indigo-100" role="progressbar" aria-label="پیشرفت بارگذاری تصویر" :aria-valuenow="uploadProgress" aria-valuemin="0" aria-valuemax="100">
                                <div class="h-full rounded-full bg-indigo-600 transition-all" :style="`width: ${uploadProgress}%`"></div>
                            </div>
                            <img x-show="previewUrl" x-cloak :src="previewUrl" alt="پیش‌نمایش تصویر انتخاب‌شده" class="mt-3 max-h-48 w-full rounded-lg object-contain" x-on:load="if (previewUrl) { previewReady = true; previewFailed = false; }" x-on:error="if (previewUrl) { previewReady = false; previewFailed = true; }">
                        </div>
                        <p id="image-preview-error" x-show="previewFailed" x-cloak class="break-words text-sm leading-6 text-red-700" role="alert">فایل «<span dir="ltr" x-text="fileName"></span>» با نوع «<span dir="ltr" x-text="fileType"></span>» در مرورگر قابل خواندن نیست؛ یک تصویر سالم با فرمت JPG، PNG یا WebP انتخاب کنید.</p>
                        <p id="image-transfer-error" x-show="transferError" x-cloak class="break-words text-sm leading-6 text-red-700" role="alert" x-text="transferError"></p>
                        <button type="button" wire:click="uploadMedia" disabled wire:loading.attr="disabled" wire:target="uploadMedia" :disabled="@cannot('manageMedia', $task) true || @endcannot !previewReady || !uploadFinished || uploadInProgress" class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-slate-800 px-4 py-2 text-white enabled:hover:bg-slate-900 disabled:cursor-not-allowed disabled:opacity-50">
                            <span wire:loading wire:target="uploadMedia" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true"></span>
                            <span x-text="uploadInProgress ? 'در حال دریافت تصویر…' : (previewReady && uploadFinished ? 'بارگذاری تصویر' : (uploadFinished && !previewFailed ? 'در حال آماده‌سازی پیش‌نمایش…' : 'ابتدا یک تصویر سالم انتخاب کنید'))"></span>
                        </button>
                        @error('upload')
                            <p id="image-upload-error" class="text-sm leading-6 text-red-700 [overflow-wrap:anywhere]" role="alert" aria-live="assertive">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach(data_get($draft, 'gallery.items', []) as $index => $item)
                            <div wire:key="draft-image-{{ $item['media_public_id'] ?? $item['product_gallery_id'] ?? $index }}" class="rounded-lg border p-3">
                                <p class="truncate text-sm">{{ $item['media_public_id'] ?? 'تصویر موجود #'.($item['product_gallery_id'] ?? '-') }}</p>
                                @if(!empty($item['media_public_id']) && isset($media[$item['media_public_id']]))
                                    <img src="{{ route('outsourcing.media.preview', $media[$item['media_public_id']]) }}" class="mt-2 h-32 w-full rounded object-cover" alt="پیش‌نمایش">
                                @elseif(!empty($item['product_gallery_id']) && isset($existingImages[$item['product_gallery_id']]))
                                    <img src="{{ route('outsourcing.gallery.preview', [$task, $existingImages[$item['product_gallery_id']]]) }}" class="mt-2 h-32 w-full rounded object-contain" alt="تصویر فعلی محصول">
                                @endif
                                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                    @php($uploadedImage = $media[$item['media_public_id'] ?? ''] ?? null)
                                    @if(($item['action'] ?? '') === 'add' && $uploadedImage
                                        && (int) $uploadedImage->uploaded_by === (int) auth()->id()
                                        && $uploadedImage->status === \App\Enums\ProductCompletionMediaStatus::TEMPORARY
                                        && $uploadedImage->published_product_gallery_id === null)
                                        <button type="button" wire:click="removeUploadedImage('{{ $uploadedImage->public_id }}')"
                                            wire:confirm="این تصویر از پیش‌نویس حذف شود؟"
                                            wire:loading.attr="disabled" @cannot('manageMedia', $task) disabled @endcannot
                                            class="min-h-11 rounded border border-red-200 px-3 py-2 text-red-700">حذف تصویر بارگذاری‌شده</button>
                                    @endif
                                    @if(!empty($item['is_primary']))
                                        <span class="font-bold text-emerald-700">تصویر اصلی (خودکار)</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p>تکمیل تصاویر برای این تسک درخواست نشده است.</p>
                @endif
            </section>

            <div class="sticky bottom-3 flex justify-end gap-3 rounded-xl border bg-white/95 p-3 shadow">
                <button type="submit" @cannot('updateDraft', $task) disabled @endcannot wire:loading.attr="disabled" class="rounded-lg bg-slate-800 px-5 py-2 text-white">ذخیره پیش‌نویس</button>
                <button type="button" wire:click="submit" @cannot('submitTask', $task) disabled @endcannot wire:loading.attr="disabled" class="rounded-lg bg-emerald-600 px-5 py-2 text-white">ارسال برای بررسی</button>
            </div>
        </form>
    @elseif(in_array($task->status, [\App\Enums\ProductCompletionTaskStatus::ASSIGNED, \App\Enums\ProductCompletionTaskStatus::ACCEPTED], true))
        <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-900">{{ $task->status === \App\Enums\ProductCompletionTaskStatus::ASSIGNED ? 'پس از پذیرش و شروع تسک، بخش‌های ویرایش در همین صفحه فعال می‌شوند.' : 'با زدن دکمهٔ «شروع» بالای صفحه، بخش‌های ویرایش فعال می‌شوند.' }}</div>
    @else
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-amber-900">در وضعیت فعلی امکان ویرایش پیش‌نویس وجود ندارد.</div>
    @endif
</div>
