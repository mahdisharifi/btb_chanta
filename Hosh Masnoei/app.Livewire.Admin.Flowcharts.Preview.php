<?php
namespace App\Livewire\Admin\Flowcharts;

use Livewire\Attributes\Layout;
use Livewire\Component, Flowchart;
#[Layout('components.layouts.admin')]

class Preview extends Component {
    public $code;
    protected $listeners=['previewFlowchart'];
    public function previewFlowchart($id){
        $f=Flowchart::findOrFail($id);
        $this->authorize('view',$f);
        $this->code=$f->code;
        $this->dispatchBrowserEvent('open-modal', ['detail' => 'flowchartPreview']);
    }
    public function render(){ return view('livewire.admin.flowcharts.preview'); }
}
