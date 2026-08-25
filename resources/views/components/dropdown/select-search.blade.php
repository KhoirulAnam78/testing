<?php

use Livewire\Component;

new class extends Component
{
    public $query; // path model App\Models\NamaModel
    public $data; // menampung option select dari query
    public $wire_model; //nama wire:model nya
    public $label; //label untuk select search
    public $search = ''; // ini untuk pencarian
    public $colSearch; // ini untuk nama kolom di tabel database yang ingin dicari
    public $colValue; // ini untuk kolom di tabel database sebagai value apabila terselect
    public $selected; // menampung nilai yang terselect
    public $conditions; //menampung where raw conditions

    public function mount($query, $wire_model, $label, $colSearch = 'name', $colValue, $selected = null,$conditions=null)
    {
        $this->query = $query;
        $this->wire_model = $wire_model;
        $this->colSearch = $colSearch;
        $this->colValue = $colValue;
        $this->label = $label;
        $this->conditions = $conditions;
        $this->data = app($this->query)::limit(10)
            ->orderBy($this->colSearch)
            ->whereRaw($this->conditions)
            ->get();
        $cek = app($this->query)::where($this->colValue, $selected)->first();
        if ($cek) {
            $colName = $this->colSearch;
            $this->selected = $cek->$colName;
        }
    }

    public function updatedSearch()
    {
        $this->data = app($this->query)::where($this->colSearch, 'like', '%' . $this->search . '%')
            ->whereRaw($this->conditions)
            ->limit(10)
            ->orderBy($this->colSearch)
            ->get();
    }

    public function selectValue($value)
    {
        $cek = app($this->query)::where($this->colValue, $value)->first();
        $colName = $this->colSearch;
        $this->selected = $cek->$colName;
        $this->dispatch('select-value', selected: ['model' => $this->wire_model, 'value' => $value]);
    }


    public function render()
    {
        return $this->view([
            'data' => $this->data,
            'label' => $this->label,
            'wire_model' => $this->wire_model,
            'colSearch' => $this->colSearch,
            'colValue' => $this->colValue,
        ]);
    }
};
?>

<div>
    <label for="selectpicker">{{ $label }}</label>
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
                        <div style="width: 100%;" wire:click='selectValue("{{ $item->$colValue }}")'>
                            <span class="{{ $selected == $item->$colValue ? 'fw-bold' : '' }}">
                                {{ $item->$colSearch }}
                            </span>
                        </div>
                    </li>
                @empty
                    <span class="dropdown-item">No data...</span>
                @endforelse
            </ul>
    </div>
</div>