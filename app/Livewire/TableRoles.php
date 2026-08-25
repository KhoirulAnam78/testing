<?php

namespace App\Livewire;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use Spatie\Permission\Models\Role;

final class TableRoles extends PowerGridComponent
{
    public string $tableName = 'tableRolesTable';

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
        return Role::query();

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
            ->add('descriptions', function ($row) {
                return $row->descriptions != null ? $row->descriptions : '-';
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
            Column::make('Nama Role', 'name')
                ->searchable()
                ->sortable(),

            Column::make('Keterangan', 'descriptions')
                ->bodyAttribute('style', 'white-space:normal !important;width: 300px; min-width:300px;')
                ->searchable(),
            Column::make('Created at', 'created_at')
                ->sortable(),

            Column::make('Updated at', 'updated_at')
                ->sortable(),

            Column::action('Aksi'),
        ];
    }

    public function confirmDeleteRole(string $id): void
    {
        $this->dispatch('siakad-confirm',
            id: $id,
            confirmEvent: 'delete-role-confirmed',
            title: 'Hapus role?',
            text: 'Role yang dihapus tidak dapat dipakai lagi pada user.',
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal',
        );
    }

    #[On('delete-role-confirmed')]
    public function deleteRole($id)
    {
        try {
            $decrypted = Crypt::decrypt($id);
        } catch (DecryptException $e) {
            return abort(404);
        }

        $role = Role::find($decrypted);
        $role->delete();
        // pakai dispatch untuk mengirim pesan alert tanpa redirect atau reload halaman
        $this->dispatch('notify', message: ['status' => 'success', 'message' => 'Data berhasil dihapus !']);

        // pakai session flash ketika pakai redirect
        // session()->flash('success','BERHASIL MENGHASPUSSS');
        // return $this->redirect(route('roles.index'), navigate: true);
    }

    public function actions($row): array
    {
        return [
            Button::add('edit-role')
                ->slot('<i class="ri-file-edit-line"></i> Kelola')
                ->class('btn btn-info btn-sm mb-2')
                ->route('roles.add_edit', ['id' => Crypt::encrypt($row->id)])
                ->tooltip('Edit Role')
                ->attributes([
                    'wire:navigate' => true,
                ]),

            Button::add('delete-role')
                ->slot('<i class="ri-delete-bin-line"></i> Hapus')
                ->class('btn btn-danger btn-sm mb-2')
                ->tooltip('Hapus Role')
                ->attributes([
                    'wire:click' => "confirmDeleteRole('".Crypt::encrypt($row->id)."')",
                ]),
        ];
    }
}
