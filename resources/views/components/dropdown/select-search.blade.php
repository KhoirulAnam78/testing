<?php

use Livewire\Component;

new class extends Component
{
    public $query;
    public $data;
    public $wire_model;
    public $label;
    public $search = '';
    public $colSearch;
    public $colValue;
    public $selected;
    public $selectedValue;
    public $conditions;
    public array $options = [];
    public $optionValue;
    public $optionLabel;
    public bool $usesOptions = false;

    public function mount(
        $query = null,
        $wire_model = null,
        $label = null,
        $colSearch = 'name',
        $colValue = 'id',
        $selected = null,
        $conditions = null,
        $options = [],
        $optionValue = 'value',
        $optionLabel = 'label',
    ): void {
        $this->query = $query;
        $this->wire_model = $wire_model;
        $this->colSearch = $colSearch;
        $this->colValue = $colValue;
        $this->label = $label;
        $this->conditions = $conditions;
        $this->options = is_array($options) ? $options : [];
        $this->optionValue = $optionValue;
        $this->optionLabel = $optionLabel;
        $this->usesOptions = $query === null;
        $this->selectedValue = $selected;
        $this->loadData();
        $this->syncSelectedLabel($selected);
    }

    public function updatedSearch(): void
    {
        $this->loadData();
    }

    public function selectValue($value): void
    {
        if (! $this->syncSelectedLabel($value)) {
            return;
        }

        $this->selectedValue = $value;
        $this->dispatch('select-value', selected: ['model' => $this->wire_model, 'value' => $value]);
    }

    private function loadData(): void
    {
        if ($this->usesOptions) {
            $search = mb_strtolower($this->search);
            $this->data = collect($this->options)
                ->filter(fn ($item) => $search === '' || str_contains(mb_strtolower((string) data_get($item, $this->optionLabel)), $search))
                ->take(10)
                ->values()
                ->all();

            return;
        }

        $query = app($this->query)::query();

        if ($this->search !== '') {
            $query->where($this->colSearch, 'like', '%'.$this->search.'%');
        }

        if ($this->conditions) {
            $query->whereRaw($this->conditions);
        }

        $this->data = $query->limit(10)->orderBy($this->colSearch)->get();
    }

    private function syncSelectedLabel($value): bool
    {
        if ($value === null || $value === '') {
            $this->selected = null;

            return true;
        }

        if ($this->usesOptions) {
            $item = collect($this->options)->first(
                fn ($item) => (string) data_get($item, $this->optionValue) === (string) $value
            );
            $this->selected = $item ? data_get($item, $this->optionLabel) : null;

            return $item !== null;
        }

        $item = app($this->query)::where($this->colValue, $value)->first();
        $this->selected = $item?->{$this->colSearch};

        return $item !== null;
    }

    public function render()
    {
        return $this->view([
            'data' => $this->data,
            'label' => $this->label,
            'wire_model' => $this->wire_model,
        ]);
    }
};
?>

<div>
    <label class="form-label">{{ $label }}</label>
    <div class="dropdown py-2" style="width:100%" data-bs-auto-close="outside">
        <button type="button" class="form-control d-flex justify-content-between align-items-center text-start" id="button_{{ $wire_model }}" data-bs-toggle="dropdown" aria-expanded="false">
            <span>{{ $selected ?? 'Pilih data' }}</span>
            <i class="ri-search-line"></i>
        </button>
        <ul class="dropdown-menu p-2" id="dropdown_{{ $wire_model }}" style="width: 100%; max-height: 360px; overflow-y: auto;" aria-labelledby="button_{{ $wire_model }}" wire:ignore.self>
            <li class="mb-2">
                <input type="text" role="button" data-bs-toggle="dropdown" autofocus wire:model.live.debounce.500ms="search" class="form-control" placeholder="Cari...">
            </li>
            @forelse ($data as $item)
                @php
                    $value = $usesOptions ? data_get($item, $optionValue) : $item->{$colValue};
                    $itemLabel = $usesOptions ? data_get($item, $optionLabel) : $item->{$colSearch};
                @endphp
                <li>
                    <button type="button" class="dropdown-item rounded {{ (string) $selectedValue === (string) $value ? 'fw-bold active' : '' }}" wire:click="selectValue('{{ $value }}')">
                        {{ $itemLabel }}
                    </button>
                </li>
            @empty
                <li><span class="dropdown-item text-muted">Tidak ada data.</span></li>
            @endforelse
        </ul>
    </div>
</div>