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
        <span class="form-control" id="button_<?php echo e($wire_model); ?>" role="button" data-bs-toggle="dropdown"
            aria-expanded="false">
            <?php echo e($selected ?? 'Choose One'); ?>

            </button>
            <ul class="dropdown-menu" id="dropdown_<?php echo e($wire_model); ?>" style="width: 100%"
                aria-labelledby="button_<?php echo e($wire_model); ?>" wire:ignore.self>
                <li style="padding:5px"><input type="text" role="button" data-bs-toggle="dropdown" autofocus
                        wire:model.live.debounce.500ms='search' class="form-control" placeholder="search..."></li>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $data; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <li class="dropdown-item">
                        <div style="width: 100%;" wire:click='selectValue("<?php echo e($item); ?>")'>
                            <span class="<?php echo e($selected == $item ? 'fw-bold' : ''); ?>">
                                <?php echo e($item); ?>

                            </span>
                        </div>
                    </li>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <span class="dropdown-item">No data...</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </ul>
    </div>
</div><?php /**PATH D:\laragon\www\sistem-blok\resources\views\components\dropdown\select-route.blade.php ENDPATH**/ ?>