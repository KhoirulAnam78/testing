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
    @csrf
    <div class="row">
        <div class="col-sm-12 col-lg-12">
            <div class="card">
                <div class="card-header">
                    @if ($edit_id)
                        <h4>Edit Role</h4>
                    @else
                        <h4>Tambah Role</h4>
                    @endif
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label>Nama Role</label>
                        <input type="text" class="form-control" required wire:model.live.debounce.500ms='name'>
                        @error('name')
                            <div class="text-sm text-danger">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea wire:model='descriptions' class="form-control" placeholder="Permission descriptions"></textarea>

                        @error('descriptions')
                            <div class="text-sm text-danger">{{ $message }}</div>
                        @enderror
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
                            @foreach ($menu as $m)
                                <div class="tree-item" wire:key='parent-{{ $m->id }}'>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                            value="{{ $m->nama_permission }}" id="perm{{ $m->id }}"
                                            wire:model='selectedPermissions'>
                                        <label class="form-check-label" for="perm{{ $m->id }}">
                                            <span
                                                class="badge bg-primary text-uppercase">{{ $m->name }}</span>
                                            <small class="text-muted ms-2 italic">-- {{ $m->descriptions }}</small>
                                        </label>
                                    </div>

                                    @foreach ($m->childs as $c)
                                        <div class="tree-item indent-child" wire:key='child-{{ $c->id }}'>
                                            <div class="fw-bold text-secondary mb-1">
                                                <i class="fas fa-chevron-right small me-1"></i> {{ $c->name }}
                                            </div>

                                            @foreach ($c->permissions as $cp)
                                                <div class="tree-item indent-permission"
                                                    wire:key='perm-{{ $cp->id }}'>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox"
                                                            value="{{ $cp->name }}" id="p-{{ $cp->id }}"
                                                            wire:model='selectedPermissions'>
                                                        <label class="form-check-label" for="p-{{ $cp->id }}">
                                                            <strong>{{ $cp->name }}</strong>
                                                            <span
                                                                class="text-muted small d-block d-md-inline ms-md-2">[{{ $cp->descriptions }}]</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
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
