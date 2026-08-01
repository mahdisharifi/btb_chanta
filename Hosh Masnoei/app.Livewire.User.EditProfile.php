<?php

namespace App\Livewire\User;

use Livewire\Attributes\Layout;
use Livewire\Component;

class EditProfile extends Component
{
    #[Layout('components.layouts.blank')]


    public function render()
    {
        return view('livewire.user.edit-profile');
    }
}
