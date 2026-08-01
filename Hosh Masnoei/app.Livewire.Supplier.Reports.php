<?php

namespace App\Livewire\Supplier;

use Livewire\Attributes\Layout;
use Livewire\Component;
#[Layout('components.layouts.supplier')]

class Reports extends Component
{
    public function render()
    {
        return view('livewire.supplier.reports');
    }
}
