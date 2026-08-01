<?php

namespace App\Livewire\Admin\Flowcharts;

use App\Models\Flowchart;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;

#[Layout('components.layouts.admin')]
class Form extends Component
{
    public ?Flowchart $flowchart = null;

    #[Validate('required|string|max:255', message: 'لطفاً عنوان فلوچارت را وارد کنید.')]
    public $title = '';

    #[Validate('nullable|string')]
    public $description = '';

    #[Validate('required|string', message: 'کد فلوچارت الزامی است.')]
    public $code = "graph TD;\n    A-->B;\n    A-->C;\n    B-->D;\n    C-->D;";

    public function mount(Flowchart $flowchart = null)
    {
        if ($flowchart && $flowchart->exists) {
            $this->authorize('update', $flowchart);
            $this->flowchart = $flowchart;
            $this->title = $flowchart->title;
            $this->description = $flowchart->description;
            $this->code = $flowchart->code;
        }
    }

    public function save()
    {
        $data = $this->validate();

        if ($this->flowchart && $this->flowchart->exists) {
            $this->authorize('update', $this->flowchart);
            $this->flowchart->update($data);
            session()->flash('success', 'فلوچارت با موفقیت بروزرسانی شد.');
        } else {
            $data['user_id'] = Auth::id();
            Flowchart::create($data);
            session()->flash('success', 'فلوچارت جدید با موفقیت ایجاد شد.');
        }

        $this->redirectRoute('admin.flowcharts.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.flowcharts.form');
    }
}
