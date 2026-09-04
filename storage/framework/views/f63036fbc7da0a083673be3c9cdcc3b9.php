<?php

use Livewire\Component;
use App\Models\Menu;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;

new #[Layout('layouts.app')] class extends Component {
    public $edit_id;
    public $name,
        $route,
        $status = 0,
        $is_child_menu = 0,
        $parent_id,
        $position,
        $descriptions,
        $icon,
        $main_permission,
        $permission_name = [], //ini untuk menampung additional permission sementara
        $additional_permissions = []; //permission tambahan yang bisa diubah;

    public function mount($id)
    {
        if ($id && $id != 'add') {
            try {
                $decrypted = Crypt::decrypt($id);
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                return abort(404, 'Enkripsi tidak valid !');
            }
            $this->edit_id = $decrypted;
            $menu = Menu::with('permissions')->find($decrypted);
            $this->name = $menu->name;
            $this->route = $menu->route;
            $this->is_child_menu = $menu->is_child_menu == 0 ? false : true;
            $this->parent_id = $menu->parent_id;
            $this->position = $menu->position;
            $this->descriptions = $menu->descriptions;
            $this->icon = $menu->icon;
            $this->main_permission = Str::slug($menu->name) . ':';
            $this->status = $menu->status == 0 ? false : true;

            foreach ($menu->permissions as $p) {
                if ($p->name != $this->main_permission) {
                    $this->additional_permissions[] = [
                        'nama' => $p->name,
                        'desc' => $p->descriptions,
                    ];
                }
            }
        }
    }

    public function updatedName()
    {
        $oldPrefix = $this->main_permission;
        $this->main_permission = Str::slug($this->name) . ':';

        if ($oldPrefix) {
            $this->additional_permissions = collect($this->additional_permissions)
                ->map(function ($permission) use ($oldPrefix) {
                    if (str_starts_with($permission['nama'], $oldPrefix)) {
                        $permission['nama'] = $this->main_permission . Str::after($permission['nama'], $oldPrefix);
                    }

                    return $permission;
                })
                ->all();
        }

        $this->addDefaultPermissions();
    }

    public function updatedIsChildMenu(): void
    {
        if (! $this->is_child_menu && ! $this->edit_id) {
            $this->additional_permissions = collect($this->additional_permissions)
                ->reject(fn ($permission) => in_array(
                    Str::after($permission['nama'], $this->main_permission),
                    ['tambah', 'edit', 'hapus'],
                    true,
                ))
                ->values()
                ->all();

            return;
        }

        $this->addDefaultPermissions();
    }

    private function addDefaultPermissions(): void
    {
        if (! $this->is_child_menu || $this->edit_id || ! $this->main_permission) {
            return;
        }

        foreach ([
            'tambah' => 'Tambah data',
            'edit' => 'Edit data',
            'hapus' => 'Hapus data',
        ] as $action => $description) {
            $name = $this->main_permission . $action;

            if (! collect($this->additional_permissions)->contains('nama', $name)) {
                $this->additional_permissions[] = [
                    'nama' => $name,
                    'desc' => $description,
                ];
            }
        }
    }

    #[On('select-value')]
    public function selectSearch($selected)
    {
        $model_name = $selected['model'];
        $value = $selected['value'];
        $this->$model_name = $value;
    }

    public function addPermission()
    {
        if ($this->permission_name && $this->permission_name['nama']) {
            $this->permission_name['nama'] = $this->main_permission . $this->permission_name['nama'];
        }
        $this->validate([
            'permission_name.nama' => 'required|string|max:255|unique:permissions,name',
            'permission_name.desc' => 'nullable|string|max:255',
        ]);

        // cek duplikat di array tambahan
        $exists = collect($this->additional_permissions)->pluck('nama')->contains($this->permission_name['nama']);

        if ($exists) {
            $this->addError('permission_name.nama', 'Permission sudah ditambahkan.');
            return;
        }

        $this->additional_permissions[] = [
            'nama' => $this->permission_name['nama'],
            'desc' => $this->permission_name['desc'],
        ];

        $this->permission_name = [];
    }
    public function removePermission($index)
    {
        $cek = $this->additional_permissions[$index];
        $ambil = Permission::where('name', $cek['nama'])->first();
        unset($this->additional_permissions[$index]);
        if ($ambil) {
            $ambil->delete();
        }
        // reindex
        $this->additional_permissions = array_values($this->additional_permissions);
    }

    public function save()
    {
        $validations = [
            'status' => 'required',
            'is_child_menu' => 'required',
            'position' => 'required',
        ];

        if ($this->edit_id) {
            $validations['name'] = 'required|unique:menus,name,' . $this->edit_id;
        } else {
            $validations['name'] = 'required|unique:menus,name';
        }

        if ($this->is_child_menu) {
            $validations['parent_id'] = 'required';
            $validations['route'] = 'required';
        } else {
            $validations['icon'] = 'required';
        }

        $messages = [
            'name.required' => 'Nama menu wajib diisi.',
            'name.unique' => 'Nama menu sudah digunakan, silakan pilih nama lain.',
            'status.required' => 'Status wajib dipilih.',
            'is_child_menu.required' => 'Field ini wajib dipilih.',
            'position.required' => 'Posisi wajib diisi.',
            'icon.required' => 'Icon wajib diisi',
            'parent_id.required' => 'Parent menu wajib dipilih.',
            'route.required' => 'Route wajib diisi.',
        ];
        $this->validate($validations, $messages);
        $this->addDefaultPermissions();

        if ($this->edit_id) {
            DB::transaction(function () {
                // update menu
                Menu::where('id', $this->edit_id)->update([
                    'name' => $this->name,
                    'route' => $this->route,
                    'status' => $this->status,
                    'position' => $this->position,
                    'is_child_menu' => $this->is_child_menu,
                    'icon' => $this->icon,
                    'parent_id' => $this->parent_id,
                    'descriptions' => $this->descriptions,
                ]);

                Permission::updateOrCreate(
                    [
                        'main_permission' => 1,
                        'menu_id' => $this->edit_id,
                    ],
                    [
                        'name' => $this->main_permission,
                        'guard_name' => 'web',
                        'descriptions' => 'akses ' . $this->name,
                    ],
                );

                // update permission
                if ($this->is_child_menu) {
                    foreach ($this->additional_permissions as $key => $value) {
                        Permission::updateOrCreate(
                            [
                                'name' => $value['nama'],
                                'menu_id' => $this->edit_id,
                            ],
                            [
                                'guard_name' => 'web',
                                'descriptions' => $value['desc'],
                            ],
                        );
                    }
                }
            });
            session()->flash('success', 'Berhasil mengubah data');
        } else {
            DB::transaction(function () {
                $new_menu = Menu::create([
                    'name' => $this->name,
                    'route' => $this->route,
                    'status' => $this->status,
                    'position' => $this->position,
                    'is_child_menu' => $this->is_child_menu,
                    'icon' => $this->icon,
                    'parent_id' => $this->parent_id,
                    'descriptions' => $this->descriptions,
                ]);
                Permission::updateOrCreate(
                    [
                        'main_permission' => 1,
                        'menu_id' => $new_menu->id,
                    ],
                    [
                        'name' => $this->main_permission,
                        'guard_name' => 'web',
                        'descriptions' => 'akses ' . $this->name,
                    ],
                );

                foreach ($this->additional_permissions as $key => $value) {
                    Permission::updateOrCreate(
                        [
                            'name' => $value['nama'],
                            'menu_id' => $new_menu->id,
                        ],
                        [
                            'guard_name' => 'web',
                            'descriptions' => $value['desc'],
                        ],
                    );
                }

                // if($this->is_child_menu){
                //     foreach()
                // }
            });

            session()->flash('success', 'Berhasil menambahkan data');
        }
        return $this->redirect(route('menu.index'), navigate: true);
    }
};
?>

<form wire:submit='save'>
    <?php echo csrf_field(); ?>
    <div class="row">
        <div class="col-sm-12 col-lg-6">
            <div class="card">
                <div class="card-header">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($edit_id): ?>
                        <h4>Edit Menu</h4>
                    <?php else: ?>
                        <h4>Tambah Menu</h4>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label>Nama Menu</label>
                        <input type="text" class="form-control" wire:model.live.debounce.500ms='name'>
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
                        <label>Posisi Urutan Menu</label>
                        <input type="number" class="form-control" wire:model='position'>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['position'];
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

                    <label class="form-label">Apakah Sub Menu? (Ya/Tidak)</label>
                    <div class="mb-3 mx-2">
                        <div class="form-check form-switch form-switch-right form-switch-md">
                            <input class="form-check-input code-switcher" type="checkbox" value="1"
                                wire:model.live='is_child_menu'>
                        </div>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['is_child_menu'];
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

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($is_child_menu)): ?>
                        <div class="mb-3">
                            <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('dropdown.select-search', ['query' => 'App\Models\Menu','wire_model' => 'parent_id','label' => 'Pilih Parent Menu','colSearch' => 'name','colValue' => 'id','selected' => $parent_id,'conditions' => 'is_child_menu != 1']);

$__keyOuter = $__key ?? null;

$__key = null;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1374375659-0', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['parent_id'];
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
                            <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('dropdown.select-route', ['wire_model' => 'route','selected' => $route]);

$__keyOuter = $__key ?? null;

$__key = null;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1374375659-1', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['route'];
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
                    <?php else: ?>
                        <div class="mb-3">
                            <label>Menu Icon (pakai remixicon)</label>
                            <input type="text" class="form-control" wire:model='icon'
                                placeholder="ri-arrow-left-s-line">

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['icon'];
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
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

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

                    <label class="form-label">Status (Aktif/Nonaktif)</label>
                    <div class="mb-3 mx-2">
                        <div class="form-check form-switch form-switch-right form-switch-md">
                            <input class="form-check-input code-switcher" type="checkbox" value="1"
                                wire:model='status'>
                        </div>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['status'];
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
                </div>
            </div>
        </div>
        <div class="col-sm-12 col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h5>Kelola Permission Pada Halaman Ini</h5>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($edit_id): ?>
                        <p>Mengganti nama permission menyebabkan anda harus menyesuaikan kembali dengan view atau
                            controller yang menggunakan permission yang anda ubah</p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <div class="mb-3">
                        <label>Permission Menu (default dibuat otomatis)</label>
                        <input type="text" class="form-control" value="<?php echo e($main_permission); ?>" disabled>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['main_permission'];
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
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($is_child_menu)): ?>
                        <div class="mb-3">
                            <h5>Form tambah permission</h5>
                            <span>semua permission tambahan pada menu/halaman ini akan memiliki prefix
                                <?php echo e($main_permission); ?></span>
                            <br>
                            <br>

                            <div class="mb-2">
                                <label for="permission_name">Nama Permission</label>
                                <input type="text" class="form-control" placeholder="nama permission"
                                    wire:model='permission_name.nama'>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['permission_name.nama'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <small class="text-danger"><?php echo e($message); ?></small>
                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <div class="mb-2">

                                <label for="permission_desc">Keterangan (boleh kosong)</label>
                                <input type="text" class="form-control" placeholder="keterangan"
                                    wire:model='permission_name.desc'>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['permission_name.desc'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <small class="text-danger"><?php echo e($message); ?></small>
                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            </div>

                            <button type="button" class="btn btn-success" wire:click="addPermission"
                                wire:loading.attr="disabled" wire:target="addPermission">
                                Tambah
                            </button>
                            <br>
                            <br>

                            <div class="mb-3">
                                <label>Permission Tambahan (tambahkan permission pada halaman)</label>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>permission</th>
                                                <th>keterangan</th>
                                                <th>aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $additional_permissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $pm): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <tr>
                                                    <td><?php echo e($pm['nama']); ?></td>
                                                    <td><?php echo e($pm['desc']); ?></td>
                                                    <th><button type="button" class="btn btn-danger btn-sm"
                                                            wire:click="removePermission(<?php echo e($i); ?>)"
                                                            wire:confirm="Apakah Anda yakin ingin menghapus permission <?php echo e($pm['nama']); ?>? data akan dihapus juga dari database">
                                                            Hapus
                                                        </button></th>
                                                </tr>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                                <tr>
                                                    <td colspan="3">tidak ada permission tambahan</td>
                                                </tr>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div
        style="
    position: fixed;
    bottom: 20px;
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
<?php /**PATH D:\laragon\www\sistem-blok\resources\views\pages\menu\add_edit.blade.php ENDPATH**/ ?>