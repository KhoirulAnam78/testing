<?php

use Livewire\Component;
use Illuminate\Support\Facades\Route;

new class extends Component
{
    public $data; // menampung option select dari query
    public $wire_model; //nama wire:model nya
    public $search = ''; // ini untuk pencarian
    public $selected; // menampung nilai yang terselect

    public function mount($wire_model, $selected = null)
    {

        $this->wire_model = $wire_model;
        $this->data = collect(Route::getRoutes())
        ->map(fn($route) => $route->getName())
        ->filter(fn($name) => $name && str_contains($name, ''))
        ->values();
        $this->selected = $selected;
    }

    public function updatedSearch()
    {
        $this->data = $this->data = collect(Route::getRoutes())
        ->map(fn($route) => $route->getName())
        ->filter(fn($name) => $name && str_contains($name, $this->search))
        ->values();
    }

    public function selectValue($value)
    {
        // $cek = app($this->query)::where($this->colValue, $value)->first();
        $this->selected = $value;
        $this->dispatch('select-value', selected: ['model' => $this->wire_model, 'value' => $value]);
    }


    public function render()
    {
        return $this->view([
            'data' => $this->data,
            'wire_model' => $this->wire_model,
        ]);
    }
};
?>

<div>
    <label for="selectpicker">Pilih Route</label>
    <div class="dropdown py-2" style="width:100%">
        <span class="form-control" id="button_{{ $wire_model }}" role="button" data-bs-toggle="dropdown"
            aria-expanded="false">
            {{ $selected ?? 'Choose One' }}
            </button>
            <ul class="dropdown-menu" id="dropdown_{{ $wire_model }}" style="width: 100%"
                aria-labelledby="button_{{ $wire_model }}" wire:ignore.self>
                <li style="padding:5px"><input type="text" role="button" data-bs-toggle="dropdown" autofocus
                        wire:model.live.debounce.500ms='search' class="form-control" placeholder="search..."></li>
                @forelse ($data as $item)
                    <li class="dropdown-item">
                        <div style="width: 100%;" wire:click='selectValue("{{ $item }}")'>
                            <span class="{{ $selected == $item ? 'fw-bold' : '' }}">
                                {{ $item }}
                            </span>
                        </div>
                    </li>
                @empty
                    <span class="dropdown-item">No data...</span>
                @endforelse
            </ul>
    </div>
</div>