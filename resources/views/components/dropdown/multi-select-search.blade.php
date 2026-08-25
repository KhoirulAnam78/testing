<?php

use Livewire\Component;

new class extends Component
{
    public $query;
    public $wire_model;
    public $label;
    public $colSearch;
    public $colValue;
    public $colSubtitle;
    public $conditions;
    public $selected = [];
    public $search = '';
    public $data;
    public $limit = 10;
    public $currentValue;

    public function mount(
        $query,
        $wire_model,
        $label,
        $colSearch = 'name',
        $colValue = 'id',
        $selected = [],
        $conditions = null,
        $colSubtitle = null,
        $limit = 10,
        $currentValue = null,
    ): void {
        $this->query = $query;
        $this->wire_model = $wire_model;
        $this->label = $label;
        $this->colSearch = $colSearch;
        $this->colValue = $colValue;
        $this->selected = collect($selected)->map(fn ($value) => (string) $value)->values()->all();
        $this->conditions = $conditions;
        $this->colSubtitle = $colSubtitle;
        $this->limit = $limit;
        $this->currentValue = $currentValue;
        $this->loadData();
    }

    public function updatedSearch(): void
    {
        $this->loadData();
    }

    public function toggleValue($value): void
    {
        $value = (string) $value;

        if (in_array($value, $this->selected, true)) {
            $this->selected = array_values(array_diff($this->selected, [$value]));
        } else {
            $this->selected[] = $value;
        }

        $this->dispatch('multi-select-value', selected: [
            'model' => $this->wire_model,
            'value' => $this->selected,
        ]);

        $this->loadData();
    }

    public function removeValue($value): void
    {
        $value = (string) $value;
        $this->selected = array_values(array_diff($this->selected, [$value]));

        $this->dispatch('multi-select-value', selected: [
            'model' => $this->wire_model,
            'value' => $this->selected,
        ]);

        $this->loadData();
    }

    private function baseQuery()
    {
        $query = app($this->query)::query();

        if ($this->conditions) {
            $query->whereRaw($this->conditions);
        }

        return $query;
    }

    private function loadData(): void
    {
        $query = $this->baseQuery();

        if ($this->search !== '') {
            $search = $this->search;
            $query->where(function ($query) use ($search) {
                $query->where($this->colSearch, 'like', "%{$search}%");

                if ($this->colSubtitle) {
                    $query->orWhere($this->colSubtitle, 'like', "%{$search}%");
                }
            });
        }

        $this->data = $query
            ->limit($this->limit)
            ->orderBy($this->colSearch)
            ->get();
    }

    public function selectedItems()
    {
        if (empty($this->selected)) {
            return collect();
        }

        return app($this->query)::query()
            ->whereIn($this->colValue, $this->selected)
            ->orderBy($this->colSearch)
            ->get();
    }

    public function render()
    {
        return $this->view([
            'data' => $this->data,
            'selectedItems' => $this->selectedItems(),
        ]);
    }
};
?>

<div>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <label class="form-label mb-0">{{ $label }}</label>
        <span class="badge bg-primary-subtle text-primary">{{ count($selected) }} dipilih</span>
    </div>

    @if ($selectedItems->isNotEmpty())
        <div class="d-flex flex-wrap gap-2 mb-2">
            @foreach ($selectedItems as $item)
                <span class="badge bg-light text-body border d-inline-flex align-items-center gap-1">
                    {{ $item->{$colSearch} }}{{ $colSubtitle ? ' - '.$item->{$colSubtitle} : '' }}
                    <button type="button" class="btn btn-link btn-sm p-0 text-danger lh-1" wire:click="removeValue('{{ $item->{$colValue} }}')">
                        <i class="ri-close-line"></i>
                    </button>
                </span>
            @endforeach
        </div>
    @endif

    <div class="dropdown py-2" style="width:100%" data-bs-auto-close="outside">
        <span class="form-control d-flex justify-content-between align-items-center" id="button_{{ $wire_model }}" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span>{{ count($selected) ? count($selected).' data dipilih' : 'Pilih data' }}</span>
            <i class="ri-search-line"></i>
        </span>

        <ul class="dropdown-menu p-2" id="dropdown_{{ $wire_model }}" style="width: 100%; max-height: 360px; overflow-y: auto;" aria-labelledby="button_{{ $wire_model }}" wire:ignore.self>
            <li class="mb-2">
                <input type="text" role="button" data-bs-toggle="dropdown" autofocus wire:model.live.debounce.500ms="search" class="form-control" placeholder="Cari...">
            </li>

            @forelse ($data as $item)
                @php
                    $value = (string) $item->{$colValue};
                    $isSelected = in_array($value, $selected, true);
                    $isUsedByOther = isset($item->blok_id) && $item->blok_id && (int) $item->blok_id !== (int) $currentValue;
                @endphp
                <li>
                    <button type="button" class="dropdown-item d-flex gap-2 align-items-start rounded py-2" wire:click="toggleValue('{{ $item->{$colValue} }}')">
                        <input type="checkbox" class="form-check-input mt-1 pe-none" @checked($isSelected) tabindex="-1">
                        <span class="d-block">
                            <span class="fw-semibold d-block">{{ $item->{$colSearch} }}{{ $colSubtitle ? ' - '.$item->{$colSubtitle} : '' }}</span>
                            @if (isset($item->sks))
                                <span class="text-muted small">{{ $item->sks }} SKS</span>
                            @endif
                            @if ($isUsedByOther)
                                <span class="badge bg-warning-subtle text-warning ms-1">Dipakai blok lain</span>
                            @endif
                        </span>
                    </button>
                </li>
            @empty
                <li><span class="dropdown-item text-muted">Tidak ada data.</span></li>
            @endforelse
        </ul>
    </div>
</div>
