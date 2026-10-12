<?php

declare(strict_types=1);

use App\Http\Controllers\ProductCompletionMediaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/outsourcing/media/{media}/preview', [ProductCompletionMediaController::class, 'show'])
        ->middleware('throttle:60,1')
        ->name('outsourcing.media.preview');

    Route::get('/outsourcing/tasks/{task}/gallery/{gallery}/preview', [ProductCompletionMediaController::class, 'gallery'])
        ->middleware('throttle:60,1')
        ->name('outsourcing.gallery.preview');

    Route::prefix('outsourcer')->name('outsourcer.')->group(function (): void {
        Route::get('/tasks', App\Livewire\Outsourcer\Task\Index::class)
            ->middleware('permission:outsourcing.view_tasks')
            ->name('tasks.index');
        Route::get('/tasks/{task}', App\Livewire\Outsourcer\Task\Show::class)->name('tasks.show');
        Route::get('/tasks/{task}/workspace', App\Livewire\Outsourcer\Task\Workspace::class)->name('tasks.workspace');
        Route::get('/tasks/{task}/submissions', App\Livewire\Outsourcer\Task\Submissions::class)->name('tasks.submissions');
        Route::get('/tasks/{task}/revisions', App\Livewire\Outsourcer\Task\Revisions::class)->name('tasks.revisions');
        Route::get('/commissions', App\Livewire\Outsourcer\Commissions\Index::class)
            ->middleware('permission:outsourcing.view_own_commissions')
            ->name('commissions.index');
    });

    Route::prefix('reviewer/outsourcing')->name('reviewer.outsourcing.')->group(function (): void {
        Route::get('/tasks', App\Livewire\Reviewer\Task\Index::class)
            ->middleware('permission:outsourcing.review_submission')
            ->name('tasks.index');
        Route::get('/tasks/{task}/review', App\Livewire\Reviewer\Task\Review::class)
            ->name('tasks.review');
        Route::get('/commissions', App\Livewire\Reviewer\Commissions\Index::class)
            ->middleware('permission:outsourcing.view_reviewed_commissions')
            ->name('commissions.index');
    });

    Route::prefix('admin/outsourcing')->name('admin.outsourcing.')->group(function (): void {
        Route::get('/dashboard', App\Livewire\Admin\Outsourcing\Dashboard::class)
            ->middleware('permission:outsourcing.view_all_tasks')
            ->name('dashboard');
        Route::get('/tasks', App\Livewire\Admin\Outsourcing\Tasks::class)
            ->middleware('permission:outsourcing.view_all_tasks')
            ->name('tasks.index');
        Route::get('/tasks/{task}', App\Livewire\Admin\Outsourcing\TaskShow::class)
            ->middleware('permission:outsourcing.view_all_tasks')
            ->name('tasks.show');
        Route::get('/commission-rules', App\Livewire\Admin\Outsourcing\CommissionRules::class)
            ->middleware('permission:outsourcing.manage_commission_rules')
            ->name('commission-rules');
        Route::get('/commissions', App\Livewire\Admin\Outsourcing\Commissions::class)
            ->middleware('permission:outsourcing.view_all_commissions')
            ->name('commissions');
    });

    Route::get('/supplier/product-completion-requests', App\Livewire\Supplier\ProductCompletionRequests::class)
        ->middleware('permission:outsourcing.view_supplier_tasks')
        ->name('supplier.product-completion-requests');
});
