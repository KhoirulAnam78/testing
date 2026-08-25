<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Livewire\Component;
use Spatie\Permission\Models\Role;

new #[Layout('layouts.app')] class extends Component
{
    public $edit_id;

    public string $name = '';

    public string $username = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public $selectedRoles = [];

    public $roles;

    public function mount($id)
    {
        abort_unless(auth()->user()->can(
            $id === 'add' ? 'kelola-user:tambah-user' : 'kelola-user:edit-user'
        ), 403);

        if ($id && $id != 'add') {
            try {
                $decrypted = Crypt::decrypt($id);
            } catch (DecryptException $e) {
                return abort(404, 'Enkripsi tidak valid !');
            }
            $this->edit_id = $decrypted;
            $user = User::with('roles')->find($this->edit_id);
            $this->name = $user->name;
            $this->username = $user->username;
            $this->email = $user->email;
            foreach ($user->roles as $r) {
                $this->selectedRoles[] = $r->name;
            }
        }
        $this->roles = Role::orderBy('name')->get();
    }

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered(($user = User::create($validated))));

        $user->syncRoles($this->selectedRoles);
    }

    public function save()
    {
        abort_unless(auth()->user()->can(
            $this->edit_id ? 'kelola-user:edit-user' : 'kelola-user:tambah-user'
        ), 403);

        if ($this->edit_id) {
            $user = User::findOrFail($this->edit_id);
            if ($this->password) {
                $validated = $this->validate([
                    'name' => ['required', 'string', 'max:255'],
                    'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($this->edit_id)],
                    'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->edit_id)],
                    'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
                ]);
                $validated['password'] = Hash::make($validated['password']);
                $user->update($validated);
                $user->syncRoles($this->selectedRoles);
            } else {
                $validated = $this->validate([
                    'name' => ['required', 'string', 'max:255'],
                    'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($this->edit_id)],
                    'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->edit_id)],
                ]);
                $user->update($validated);
                $user->syncRoles($this->selectedRoles);
            }

            session()->flash('success', 'Berhasil mengubah data');
        } else {
            $this->register();
            session()->flash('success', 'Berhasil menambah data');
        }

        return $this->redirect(route('users.index'), navigate: true);
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
                        <h4>Edit User</h4>
                    @else
                        <h4>Tambah User</h4>
                    @endif
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="name">Name</label>
                        <input type="text" required wire:model="name" class="form-control" id="name"
                            name="username" placeholder="Enter username">
                        @if ($errors->get('name'))
                            <ul class="text-sm text-danger">
                                @foreach ((array) $errors->get('name') as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="username">Username</label>
                        <input type="text" required wire:model="username" class="form-control" id="username"
                            name="username" placeholder="Enter username">
                        @error('username') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" required wire:model="email" class="form-control" id="email"
                            name="email" placeholder="Enter email">
                        @if ($errors->get('email'))
                            <ul class="text-sm text-danger">
                                @foreach ((array) $errors->get('email') as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="userpassword">Password {{ $edit_id != null ? '(isi bila ingin mengganti password)' : '' }}</label>
                        <input type="password" {{ $edit_id == null ? 'required':'' }} wire:model="password" class="form-control" id="userpassword"
                            name="password" placeholder="Enter password">
                        @if ($errors->get('password'))
                            <ul class="text-sm text-danger">
                                @foreach ((array) $errors->get('password') as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="userpasswordconfirmation">Password
                            Confirmation {{ $edit_id != null ? '(isi bila ingin mengganti password)' : '' }}</label>
                        <input type="password" {{ $edit_id == null ? 'required':'' }} wire:model="password_confirmation" class="form-control"
                            id="userpasswordconfirmation" name="password_confirmation"
                            placeholder="Enter Password Confirmation">
                        @if ($errors->get('password_confirmation'))
                            <ul class="text-sm text-danger">
                                @foreach ((array) $errors->get('password_confirmation') as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    <div class="mb-3">
                        <label for="roles">Pilih Role (Hak Akses) User</label>
                        @foreach ($roles as $item)
                            <div class="col-sm-6 col-md-3" wire:key='{{ $item->id }}'>
                                <input class="form-check-input" type="checkbox" value="{{ $item->name }}"
                                    id="role{{ $item->id }}" wire:model='selectedRoles'>
                                <label class="form-check-label" for="role{{ $item->id }}">
                                    {{ $item->name }}
                                </label>
                            </div>
                        @endforeach
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
