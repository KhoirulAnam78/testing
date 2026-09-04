<?php

use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\Menu;

new #[Layout('layouts.app')] class extends Component {
    public $edit_id;
    public $name, $descriptions, $menu;
    public $permissions;
    public $selectedPermissions = [];
    public $selectAll;

    public function mount($id)
    {
        if ($id && $id != 'add') {
            try {
                $decrypted = Crypt::decrypt($id);
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                return abort(404, 'Enkripsi tidak valid !');
            }
            $this->edit_id = $decrypted;
            $role = Role::with('permissions')->find($this->edit_id);

            foreach ($role->permissions as $p) {
                $this->selectedPermissions[] = $p->name;
            }
            $this->name = $role->name;
            $this->descriptions = $role->descriptions;
        }

        // ambil semua menu parent, kemudian ambil child menu
        $this->menu = Menu::with('childs')->join('permissions as b', 'b.menu_id', 'menus.id')->where('b.main_permission', 1)->whereNull('menus.parent_id')->orderBy('position')->select('menus.*', 'b.name as nama_permission')->get();
        $this->permissions = Permission::orderBy('name')->get();

    }

    public function updatedSelectAll()
    {
        if ($this->selectAll) {
            foreach ($this->permissions as $item) {
                array_push($this->selectedPermissions, $item->name);
            }
        } else {
            $this->selectedPermissions = [];
        }
    }

    public function save(){
        $validations=[];

        if ($this->edit_id) {
            $validations['name'] = 'required|unique:roles,name,' . $this->edit_id;
        } else {
            $validations['name'] = 'required|unique:roles,name';
        }

        $messages = [
            'name.required' => 'Nama role wajib diisi.',
            'name.unique' => 'Nama role sudah digunakan, silakan pilih nama lain.',
        ];

        // dd($this->selectedPermissions);

        $this->validate($validations, $messages);

        // create role
        DB::transaction(function () {
            if($this->edit_id){
                $role = Role::find($this->edit_id);
                $update = $role->update([
                    'name' => $this->name,
                    'guard_name' =>'web',
                    'descriptions' => $this->descriptions,
                ]);
            }else{
                $role = Role::create([
                    'name' => $this->name,
                    'guard_name' =>'web',
                    'descriptions' => $this->descriptions,
                ]);
            }

            // give permissions
            $role->syncPermissions($this->selectedPermissions);
        });

        
        session()->flash('success', 'Berhasil mengubah data');
        
        return $this->redirect(route('roles.index'), navigate: true);
    }
};
?>
<form wire:submit='save'>
    <?php echo csrf_field(); ?>
    <div class="row">
        <div class="col-sm-12 col-lg-12">
            <div class="card">
                <div class="card-header">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($edit_id): ?>
                        <h4>Edit Role</h4>
                    <?php else: ?>
                        <h4>Tambah Role</h4>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label>Nama Role</label>
                        <input type="text" class="form-control" required wire:model.live.debounce.500ms='name'>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <div class="text-sm text-danger"><?php echo e($message); ?></div>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea wire:model='descriptions' class="form-control" placeholder="Permission descriptions"></textarea>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['descriptions'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <div class="text-sm text-danger"><?php echo e($message); ?></div>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="mb-3 card p-4 shadow-sm">
                        <h5 class="mb-4 border-bottom pb-2 text-primary">Daftar Permission</h5>

                        <div class="form-check mb-3 p-2 bg-light rounded">
                            <input class="form-check-input ms-1" type="checkbox" id="selectAll" value="all"
                                wire:model.live='selectAll'>
                            <label class="form-check-label fw-bold ms-2" for="selectAll">
                                Pilih Semua Akses
                            </label>
                        </div>

                        <div class="tree-container">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $menu; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <div class="tree-item" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = ''; ?>wire:key='parent-<?php echo e($m->id); ?>'>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                            value="<?php echo e($m->nama_permission); ?>" id="perm<?php echo e($m->id); ?>"
                                            wire:model='selectedPermissions'>
                                        <label class="form-check-label" for="perm<?php echo e($m->id); ?>">
                                            <span
                                                class="badge bg-primary text-uppercase"><?php echo e($m->name); ?></span>
                                            <small class="text-muted ms-2 italic">-- <?php echo e($m->descriptions); ?></small>
                                        </label>
                                    </div>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $m->childs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <div class="tree-item indent-child" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = ''; ?>wire:key='child-<?php echo e($c->id); ?>'>
                                            <div class="fw-bold text-secondary mb-1">
                                                <i class="fas fa-chevron-right small me-1"></i> <?php echo e($c->name); ?>

                                            </div>

                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $c->permissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <div class="tree-item indent-permission"
                                                    <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = ''; ?>wire:key='perm-<?php echo e($cp->id); ?>'>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox"
                                                            value="<?php echo e($cp->name); ?>" id="p-<?php echo e($cp->id); ?>"
                                                            wire:model='selectedPermissions'>
                                                        <label class="form-check-label" for="p-<?php echo e($cp->id); ?>">
                                                            <strong><?php echo e($cp->name); ?></strong>
                                                            <span
                                                                class="text-muted small d-block d-md-inline ms-md-2">[<?php echo e($cp->descriptions); ?>]</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div
        style="
    position: fixed;
    bottom: 50px;
    left: 0;
    width: 100%;
    display: flex;
    justify-content: center;
    z-index: 1050;
">
        <button type="submit" class="btn btn-primary shadow d-flex align-items-center gap-2 fab-save"
            wire:loading.attr="disabled">
            <span wire:loading.remove>💾 SIMPAN</span>
            <span wire:loading>Loading...</span>
        </button>
    </div>
</form>
<?php /**PATH D:\laragon\www\sistem-blok\resources\views\pages\roles\add_edit.blade.php ENDPATH**/ ?>