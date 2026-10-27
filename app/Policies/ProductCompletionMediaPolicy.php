<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProductCompletionMedia;
use App\Models\ProductCompletionTask;
use App\Models\User;

class ProductCompletionMediaPolicy
{
    public function view(User $user, ProductCompletionMedia $media): bool
    {
        return $user->can('viewTask', $media->task);
    }

    public function create(User $user, ProductCompletionTask $task): bool
    {
        return $user->can('manageMedia', $task);
    }

    public function update(User $user, ProductCompletionMedia $media): bool
    {
        return $user->can('manageMedia', $media->task)
            && $media->status->isMutable();
    }

    public function delete(User $user, ProductCompletionMedia $media): bool
    {
        return $this->update($user, $media);
    }
}
