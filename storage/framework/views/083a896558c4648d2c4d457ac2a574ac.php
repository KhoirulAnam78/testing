<?php use PowerComponents\LivewirePowerGrid\Providers\SupportLivewireVersions; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(SupportLivewireVersions::isV4()): ?>
        <?php
        $__scriptKey = '2223209777-0';
        ob_start();
    ?>
        <script>
            this.$js('pgRowTemplates', (rowTemplates) => {
                window['pgRowTemplates_' + $wire.id] = JSON.parse(rowTemplates);
            })
            this.$js('pgResourceIcons', (icons) => {
                window.pgResourceIcons = JSON.parse(icons);
            })
            this.$js('pgActions', (actions) => {
                window['pgActions_' + $wire.id] = JSON.parse(actions);
            })
            this.$js('pgActionsHeader', (actions) => {
                window['pgActionsHeader_' + $wire.id] = JSON.parse(actions);
            })
        </script>
        <?php
        $__output = ob_get_clean();

        \Livewire\store($this)->push('scripts', $__output, $__scriptKey)
    ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\components\support-livewire-v4.blade.php ENDPATH**/ ?>