<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class TableUsers extends PowerGridComponent
{
    public string $tableName = 'tableUsersTable';

    public int $rowNumber = 0;

    public function setUp(): array
    {
        return [
            PowerGrid::header()
                ->showSearchInput(),
            PowerGrid::footer()
                ->showPerPage(10)
                ->showRecordCount('min'),
        ];
    }

    public function datasource(): ?Builder
    {
        $this->rowNumber = 0;

        // return DB::table('menus as a')
        // ->leftjoin('menus as b','a.parent_id','b.id')
        // ->when($this->search, function($q){
        //     $q->where('a.name','LIKE','%'.$this->search)
        //     ->orWhere('a.route','LIKE','%'.$this->search)
        //     ->orWhere('a.descriptions','LIKE','%'.$this->search)
        //     ->orWhere('b.name','LIKE','%'.$this->search);
        // })
        // ->select('a.id as id','a.name as name','a.status as status','a.route as route','a.is_child_menu as is_child_menu','a.parent_id as parent_id','a.descriptions as descriptions','a.created_at as created_at','a.updated_at as updated_at','b.name as parent_menu');
        // $data  = User::with('roles')->get();
        // dd($data);
        return User::with('roles');

    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('no', function ($row) {
                $page = $this->paginators['page'] ?? 1;
                $footer = $this->setUp['footer'];

                if (is_array($footer) && array_key_exists('perPage', $footer)) {
                    $perPage = $footer['perPage'];
                } else {
                    $perPage = $footer->perPage;
                }

                return ($page - 1) * $perPage + (++$this->rowNumber);
            })
            ->add('name')
            ->add('username')
            ->add('email')
            ->add('roles', function ($row) {
                $html = '';
                foreach ($row->getRoleNames() as $r) {
                    $html .= $r.'<br>';
                }

                return $html;
            })
            ->add('created_at')
            ->add('updated_at');
    }

    public function placeholder()
    {
        return <<<'HTML'
            <div class="d-flex justify-content-center align-items-center" style="height: 300px;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <div class="ms-3">Memuat data tabel...</div>
            </div>
        HTML;
    }

    public function columns(): array
    {
        return [
            Column::make('No', 'no'),
            Column::make('Nama User', 'name')
                ->searchable()
                ->sortable(),
            Column::make('Username', 'username')
                ->searchable()
                ->sortable(),
            Column::make('Email', 'email')->searchable()
                ->sortable(),
            Column::make('Roles', 'roles'),
            Column::make('Created at', 'created_at')
                ->sortable(),

            Column::make('Updated at', 'updated_at')
                ->sortable(),

            Column::action('Aksi'),
        ];
    }

    public function confirmDeleteUser(string $id): void
    {
        abort_unless(auth()->user()->can('kelola-user:hapus-user'), 403);

        $this->dispatch('siakad-confirm',
            id: $id,
            confirmEvent: 'delete-user-confirmed',
            title: 'Hapus user?',
            text: 'User yang dihapus tidak dapat digunakan lagi untuk login.',
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal',
        );
    }

    public function confirmLoginAs(string $id): void
    {
        abort_unless(auth()->user()->can('kelola-user:'), 403);
        abort_if(session()->has('login_as_original_user_id'), 403);

        $this->dispatch('siakad-confirm',
            id: $id,
            confirmEvent: 'login-as-confirmed',
            title: 'Login sebagai user ini?',
            text: 'Sesi Anda akan beralih ke user terpilih.',
            confirmButtonText: 'Ya, login',
            cancelButtonText: 'Batal',
        );
    }

    #[On('login-as-confirmed')]
    public function loginAs(string $id): void
    {
        abort_unless(auth()->user()->can('kelola-user:'), 403);
        abort_if(session()->has('login_as_original_user_id'), 403);

        try {
            $targetId = Crypt::decrypt($id);
        } catch (DecryptException $e) {
            abort(404);
        }

        abort_if((int) $targetId === (int) auth()->id(), 422);

        $originalUserId = auth()->id();
        $target = User::findOrFail($targetId);

        session()->put('login_as_original_user_id', $originalUserId);
        Auth::login($target);
        request()->session()->regenerate();

        $this->redirectRoute('dashboard', navigate: false);
    }

    #[On('delete-user-confirmed')]
    public function deleteUser($id)
    {
        abort_unless(auth()->user()->can('kelola-user:hapus-user'), 403);

        try {
            $decrypted = Crypt::decrypt($id);
        } catch (DecryptException $e) {
            return abort(404);
        }

        $user = User::find($decrypted);
        $user->delete();
        // pakai dispatch untuk mengirim pesan alert tanpa redirect atau reload halaman
        $this->dispatch('notify', message: ['status' => 'success', 'message' => 'Data berhasil dihapus !']);

        // pakai session flash ketika pakai redirect
        // session()->flash('success','BERHASIL MENGHASPUSSS');
        // return $this->redirect(route('roles.index'), navigate: true);
    }

    public function actions($row): array
    {
        $actions = [];

        if (auth()->user()->can('kelola-user:edit-user')) {
            $actions[] = Button::add('edit-user')
                ->slot('<i class="ri-file-edit-line"></i> Kelola')
                ->class('btn btn-info btn-sm mb-2')
                ->route('users.add_edit', ['id' => Crypt::encrypt($row->id)])
                ->tooltip('Edit User')
                ->attributes([
                    'wire:navigate' => true,
                ]);
        }

        if (
            auth()->user()->can('kelola-user:')
            && (int) $row->id !== (int) auth()->id()
            && ! session()->has('login_as_original_user_id')
        ) {
            $actions[] = Button::add('login-as')
                ->slot('<i class="ri-login-box-line"></i> Login As')
                ->class('btn btn-warning btn-sm mb-2')
                ->tooltip('Login sebagai user ini')
                ->attributes([
                    'wire:click' => "confirmLoginAs('".Crypt::encrypt($row->id)."')",
                ]);
        }

        if (auth()->user()->can('kelola-user:hapus-user')) {
            $actions[] = Button::add('delete-user')
                ->slot('<i class="ri-delete-bin-line"></i> Hapus')
                ->class('btn btn-danger btn-sm mb-2')
                ->tooltip('Hapus User')
                ->attributes([
                    'wire:click' => "confirmDeleteUser('".Crypt::encrypt($row->id)."')",
                ]);
        }

        return $actions;
    }
}
