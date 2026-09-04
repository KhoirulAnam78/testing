<div
    class="items-center justify-between sm:flex gap-2"
    wire:loading.class="blur-[2px]"
    wire:target="loadMore"
>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paginator->count() > 0): ?>
        <div class="items-center justify-between w-full sm:flex-1 sm:flex">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recordCount === 'full'): ?>
                <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['mr-3' => $paginator->hasPages()]); ?>">
                    <div class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                        'mr-2' => $paginator->hasPages(),
                        'leading-5 text-center sm:text-right',
                    ]); ?>">
                        <?php echo e(trans('livewire-powergrid::datatable.pagination.showing')); ?>

                        <span class="font-semibold firstItem"><?php echo e($paginator->firstItem()); ?></span>
                        <?php echo e(trans('livewire-powergrid::datatable.pagination.to')); ?>

                        <span class="font-semibold lastItem"><?php echo e($paginator->lastItem()); ?></span>
                        <?php echo e(trans('livewire-powergrid::datatable.pagination.of')); ?>

                        <span class="font-semibold total"><?php echo e($paginator->total()); ?></span>
                        <?php echo e(trans('livewire-powergrid::datatable.pagination.results')); ?>

                    </div>
                </div>
            <?php elseif($recordCount === 'short'): ?>
                <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['mr-3' => $paginator->hasPages()]); ?>">
                    <p class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                        'mr-2' => $paginator->hasPages(),
                        'leading-5 text-center sm:text-right',
                    ]); ?>">
                        <span class="font-semibold firstItem"> <?php echo e($paginator->firstItem()); ?></span>
                        -
                        <span class="font-semibold lastItem"> <?php echo e($paginator->lastItem()); ?></span>
                        |
                        <span class="font-semibold total"> <?php echo e($paginator->total()); ?></span>
                    </p>
                </div>
            <?php elseif($recordCount === 'min'): ?>
                <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['mr-3' => $paginator->hasPages()]); ?>">
                    <p class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                        'mr-2' => $paginator->hasPages(),
                        'leading-5 text-center sm:text-right',
                    ]); ?>">
                        <span class="font-semibold firstItem"> <?php echo e($paginator->firstItem()); ?></span>
                        -
                        <span class="font-semibold lastItem"> <?php echo e($paginator->lastItem()); ?></span>
                    </p>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paginator->hasPages() && !in_array($recordCount, ['min', 'short'])): ?>
                <nav
                    role="navigation"
                    aria-label="Pagination Navigation"
                    class="items-center justify-between sm:flex"
                >
                    <div class="flex join justify-center mt-2 md:flex-none md:justify-end sm:mt-0">

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$paginator->onFirstPage()): ?>
                            <a
                                class="btn join-item"
                                wire:click="gotoPage(1, '<?php echo e($paginator->getPageName()); ?>')"
                            >
                                <svg
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                    class="w-5 h-5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="m18.75 4.5-7.5 7.5 7.5 7.5m-6-15L5.25 12l7.5 7.5"
                                    />
                                </svg>
                            </a>

                            <a
                                class="btn join-item flex items-center"
                                wire:click="previousPage('<?php echo e($paginator->getPageName()); ?>')"
                                rel="next"
                            >
                                <svg
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                    class="w-5 h-5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M15.75 19.5 8.25 12l7.5-7.5"
                                    />
                                </svg>

                            </a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $elements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $element): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(is_array($element)): ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $element; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $page => $url): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($page == $paginator->currentPage()): ?>
                                        <span class="btn join-item btn-primary"><?php echo e($page); ?></span>
                                    <?php elseif(
                                        $page === $paginator->currentPage() + 1 ||
                                            $page === $paginator->currentPage() + 2 ||
                                            $page === $paginator->currentPage() - 1 ||
                                            $page === $paginator->currentPage() - 2): ?>
                                        <a
                                            class="btn join-item"
                                            wire:click="gotoPage(<?php echo e($page); ?>, '<?php echo e($paginator->getPageName()); ?>')"
                                        ><?php echo e($page); ?></a>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paginator->hasMorePages()): ?>
                            <a
                                class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                                    'block' => $paginator->lastPage() - $paginator->currentPage() >= 2,
                                    'hidden' => $paginator->lastPage() - $paginator->currentPage() < 2,
                                    'btn join-item flex',
                                ]); ?>"
                                wire:click="nextPage('<?php echo e($paginator->getPageName()); ?>')"
                                rel="next"
                            >
                                <svg
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                    class="w-5 h-5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="m8.25 4.5 7.5 7.5-7.5 7.5"
                                    />
                                </svg>
                            </a>
                            <a
                                class="btn join-item"
                                wire:click="gotoPage(<?php echo e($paginator->lastPage()); ?>, '<?php echo e($paginator->getPageName()); ?>')"
                            >
                                <svg
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                    class="w-5 h-5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="m5.25 4.5 7.5 7.5-7.5 7.5m6-15 7.5 7.5-7.5 7.5"
                                    />
                                </svg>
                            </a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </nav>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paginator->hasPages() && in_array($recordCount, ['min', 'short'])): ?>
                    <nav
                        role="navigation"
                        aria-label="Pagination Navigation"
                        class="items-center justify-between sm:flex"
                    >
                        <div class="flex justify-center gap-2 md:flex-none md:justify-end sm:mt-0">
                            <span>
                                
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paginator->onFirstPage()): ?>
                                    <button
                                        disabled
                                        class="btn join-item"
                                    >
                                        <?php echo app('translator')->get('Previous'); ?>
                                    </button>
                                <?php else: ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(method_exists($paginator, 'getCursorName')): ?>
                                        <button
                                            wire:click="setPage('<?php echo e($paginator->previousCursor()->encode()); ?>','<?php echo e($paginator->getCursorName()); ?>')"
                                            wire:loading.attr="disabled"
                                            class="btn join-item"
                                        >
                                            <svg
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke-width="1.5"
                                                stroke="currentColor"
                                                class="w-5 h-5"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="m18.75 4.5-7.5 7.5 7.5 7.5m-6-15L5.25 12l7.5 7.5"
                                                />
                                            </svg>

                                        </button>
                                    <?php else: ?>
                                        <button
                                            wire:click="previousPage('<?php echo e($paginator->getPageName()); ?>')"
                                            wire:loading.attr="disabled"
                                            class="select-none btn join-item"
                                        >
                                            <?php echo app('translator')->get('Previous'); ?>
                                        </button>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </span>

                            <span>
                                
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paginator->hasMorePages()): ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(method_exists($paginator, 'getCursorName')): ?>
                                        <button
                                            wire:click="setPage('<?php echo e($paginator->nextCursor()->encode()); ?>','<?php echo e($paginator->getCursorName()); ?>')"
                                            wire:loading.attr="disabled"
                                            class="btn join-item"
                                        >
                                            <svg
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke-width="1.5"
                                                stroke="currentColor"
                                                class="w-5 h-5"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="m18.75 4.5-7.5 7.5 7.5 7.5m-6-15L5.25 12l7.5 7.5"
                                                />
                                            </svg>

                                        </button>
                                    <?php else: ?>
                                        <button
                                            wire:click="nextPage('<?php echo e($paginator->getPageName()); ?>')"
                                            wire:loading.attr="disabled"
                                            class="btn join-item"
                                        >
                                            <?php echo app('translator')->get('Next'); ?>
                                        </button>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php else: ?>
                                    <button
                                        disabled
                                        class="btn join-item"
                                    >
                                        <?php echo app('translator')->get('Next'); ?>
                                    </button>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </span>
                        </div>
                    </nav>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\components\frameworks\daisyui\pagination.blade.php ENDPATH**/ ?>