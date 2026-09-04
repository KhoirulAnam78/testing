<div class="d-flex flex-column flex-lg-row align-items-sm-center justify-content-between">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paginator->count() > 0): ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recordCount === 'full'): ?>
            <small class="text-muted d-block mb-2 my-md-2 me-1">
                <?php echo e(trans('livewire-powergrid::datatable.pagination.showing')); ?>

                <span class="font-semibold"><?php echo e($paginator->firstItem()); ?></span>
                <?php echo e(trans('livewire-powergrid::datatable.pagination.to')); ?>

                <span class="font-semibold"><?php echo e($paginator->lastItem()); ?></span>
                <?php echo e(trans('livewire-powergrid::datatable.pagination.of')); ?>

                <span class="font-semibold"><?php echo e($paginator->total()); ?></span>
                <?php echo e(trans('livewire-powergrid::datatable.pagination.results')); ?>

            </small>
        <?php elseif($recordCount === 'short'): ?>
            <small class="text-muted d-block mb-2 my-md-2 me-1">
                <span class="font-semibold"> <?php echo e($paginator->firstItem()); ?></span>
                -
                <span class="font-semibold"> <?php echo e($paginator->lastItem()); ?></span>
                |
                <span class="font-semibold"> <?php echo e($paginator->total()); ?></span>
            </small>
        <?php elseif($recordCount === 'min'): ?>
            <small class="text-muted d-block mb-2 my-md-2 me-1">
                <span class="font-semibold"> <?php echo e($paginator->firstItem()); ?></span>
                -
                <span class="font-semibold"> <?php echo e($paginator->lastItem()); ?></span>
            </small>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paginator->hasPages() && $recordCount != 'min'): ?>
            <nav>
                <ul class="pagination mb-0 ms-0 ms-sm-1">
                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paginator->onFirstPage()): ?>
                        <li
                            class="page-item disabled"
                            aria-disabled="true"
                            aria-label="<?php echo app('translator')->get('pagination.previous'); ?>"
                        >
                            <span
                                class="page-link"
                                aria-hidden="true"
                            >&lsaquo;</span>
                        </li>
                    <?php else: ?>
                        <li class="page-item">
                            <button
                                type="button"
                                class="page-link"
                                wire:click="previousPage('<?php echo e($paginator->getPageName()); ?>')"
                                wire:loading.attr="disabled"
                                rel="prev"
                                aria-label="<?php echo app('translator')->get('pagination.previous'); ?>"
                            >
                                &lsaquo;
                            </button>
                        </li>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $elements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $element): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(is_string($element)): ?>
                            <li
                                class="page-item disabled"
                                aria-disabled="true"
                            ><span class="page-link"><?php echo e($element); ?></span></li>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(is_array($element)): ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $element; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $page => $url): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($page == $paginator->currentPage()): ?>
                                    <li
                                        class="page-item active"
                                        <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'paginator-page-'.e($page).''; ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'paginator-page-'.e($page).''; ?>wire:key="paginator-page-<?php echo e($page); ?>"
                                        aria-current="page"
                                    >
                                        <span class="page-link"><?php echo e($page); ?></span>
                                    </li>
                                <?php else: ?>
                                    <li
                                        class="page-item"
                                        <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'paginator-page-'.e($page).''; ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'paginator-page-'.e($page).''; ?>wire:key="paginator-page-<?php echo e($page); ?>"
                                    >
                                        <button
                                            type="button"
                                            class="page-link"
                                            wire:click="gotoPage(<?php echo e($page); ?>, '<?php echo e($paginator->getPageName()); ?>')"
                                        ><?php echo e($page); ?></button>
                                    </li>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paginator->hasMorePages()): ?>
                        <li class="page-item">
                            <button
                                type="button"
                                class="page-link"
                                wire:click="nextPage('<?php echo e($paginator->getPageName()); ?>')"
                                wire:loading.attr="disabled"
                                rel="next"
                                aria-label="<?php echo app('translator')->get('pagination.next'); ?>"
                            >&rsaquo;
                            </button>
                        </li>
                    <?php else: ?>
                        <li
                            class="page-item disabled"
                            aria-disabled="true"
                            aria-label="<?php echo app('translator')->get('pagination.next'); ?>"
                        >
                            <span
                                class="page-link"
                                aria-hidden="true"
                            >&rsaquo;</span>
                        </li>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paginator->hasPages() && $recordCount == 'min'): ?>
            <nav>
                <ul class="pagination mb-0 ms-0 ms-sm-1">
                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paginator->onFirstPage()): ?>
                        <li class="page-item disabled">
                            <button
                                type="button"
                                class="page-link"
                                rel="prev"
                                aria-label="<?php echo app('translator')->get('pagination.previous'); ?>"
                            >
                                &lsaquo;
                            </button>
                        </li>
                    <?php else: ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(method_exists($paginator, 'getCursorName')): ?>
                            <li class="page-item">
                                <button
                                    type="button"
                                    class="page-link"
                                    wire:click="setPage('<?php echo e($paginator->previousCursor()->encode()); ?>','<?php echo e($paginator->getCursorName()); ?>')"
                                    wire:loading.attr="disabled"
                                    rel="prev"
                                    aria-label="<?php echo app('translator')->get('pagination.previous'); ?>"
                                >
                                    &lsaquo;
                                </button>
                            </li>
                        <?php else: ?>
                            <li class="page-item">
                                <button
                                    type="button"
                                    class="page-link"
                                    wire:click="previousPage('<?php echo e($paginator->getPageName()); ?>')"
                                    wire:loading.attr="disabled"
                                    rel="prev"
                                    aria-label="<?php echo app('translator')->get('pagination.previous'); ?>"
                                >
                                    &lsaquo;
                                </button>
                            </li>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paginator->hasMorePages()): ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(method_exists($paginator, 'getCursorName')): ?>
                            <li class="page-item">
                                <button
                                    type="button"
                                    class="page-link"
                                    wire:click="setPage('<?php echo e($paginator->nextCursor()->encode()); ?>','<?php echo e($paginator->getCursorName()); ?>')"
                                    wire:loading.attr="disabled"
                                    rel="next"
                                    aria-label="<?php echo app('translator')->get('pagination.next'); ?>"
                                >&rsaquo;
                                </button>
                            </li>
                        <?php else: ?>
                            <li class="page-item">
                                <button
                                    type="button"
                                    class="page-link"
                                    wire:click="nextPage('<?php echo e($paginator->getPageName()); ?>')"
                                    wire:loading.attr="disabled"
                                    rel="next"
                                    aria-label="<?php echo app('translator')->get('pagination.next'); ?>"
                                >&rsaquo;
                                </button>
                            </li>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <button
                                type="button"
                                class="page-link"
                                rel="next"
                                aria-label="<?php echo app('translator')->get('pagination.next'); ?>"
                            >&rsaquo;
                            </button>
                        </li>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH D:\laragon\www\sistem-blok\resources\views/vendor/livewire-powergrid/components/frameworks/bootstrap5/pagination.blade.php ENDPATH**/ ?>