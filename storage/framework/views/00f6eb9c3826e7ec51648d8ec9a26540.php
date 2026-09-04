<div
    class="col-12"
    style="text-align: right;"
>
    <label class="col-12 col-sm-8">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(data_get($setUp, 'header.searchInput')): ?>
            <div class="input-group w-100">
                <span class="input-group-text">
                    <svg
                        width="16"
                        height="16"
                        fill="currentColor"
                        class="<?php echo e(theme_style($theme, 'searchBox.iconSearch')); ?>"
                        viewBox="0 0 16 16"
                    >
                        <path
                            d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"
                        ></path>
                    </svg>
                </span>
                <input
                    wire:model.live.debounce.600ms="search"
                    type="text"
                    class="<?php echo e(theme_style($theme, 'searchBox.input')); ?>"
                    placeholder="<?php echo e(trans('livewire-powergrid::datatable.placeholders.search')); ?>"
                >
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </label>
</div>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\components\frameworks\bootstrap5\header\search.blade.php ENDPATH**/ ?>