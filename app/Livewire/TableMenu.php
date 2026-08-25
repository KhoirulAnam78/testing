<?php

namespace App\Livewire;

use App\Models\Menu;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class TableMenu extends PowerGridComponent
{
    public string $tableName = 'tableMenuTable';

    public int $rowNumber = 0;

    public bool $showFilters = true;

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
        return Menu::query()->with(['parent_menu', 'permissions']);
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
            ->add('route')
            ->add('status', fn ($row) => $row->status
                    ? '<span class="badge bg-success">Aktif</span>'
                    : '<span class="badge bg-danger">Nonaktif</span>'
            )
            ->add('is_child_menu', fn ($row) => $row->is_child_menu
                    ? '<span class="badge bg-success">Iya</span>'
                    : '<span class="badge bg-danger">Tidak</span>'
            )
            ->add('parent_menu', function ($row) {
                return $row->parent_menu != null ? $row->parent_menu->name : '-';
            })
            ->add('descriptions', function ($row) {
                return $row->descriptions != null ? $row->descriptions : '-';
            })
            ->add('permissions', function ($row) {
                $html = '';
                foreach ($row->permissions as $p) {
                    $html .= $p->name.'<br>';
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
            Column::make('Nama Menu', 'name')
                ->searchable()
                ->sortable(),

            Column::make('Route', 'route')
                ->searchable()
                ->sortable(),

            Column::make('Status', 'status')
                ->sortable(),

            Column::make('Sub Menu', 'is_child_menu')
                ->sortable(),

            Column::make('Menu Utama', 'parent_menu')
                ->searchable(),

            Column::make('Keterangan', 'descriptions')
                ->bodyAttribute('style', 'white-space:normal !important;width: 300px; min-width:300px;')
                ->searchable(),

            Column::make('Permission', 'permissions'),

            Column::make('Created at', 'created_at')
                ->sortable(),

            Column::make('Updated at', 'updated_at')
                ->sortable(),

            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('name')->operators(['contains', 'contains_not'])->placeholder('Nama Menu'),
            Filter::inputText('route')->operators(['contains', 'contains_not'])->placeholder('Nama Route'),

            Filter::boolean('status')->label('Aktif', 'Non-Aktif'),
        ];
    }

    public function confirmDeleteMenu(string $id): void
    {
        $this->dispatch('siakad-confirm',
            id: $id,
            confirmEvent: 'delete-menu-confirmed',
            title: 'Hapus menu?',
            text: 'Menu yang dihapus akan hilang dari navigasi sistem.',
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal',
        );
    }

    #[On('delete-menu-confirmed')]
    public function deleteMenu($id)
    {
        try {
            $decrypted = Crypt::decrypt($id);
        } catch (DecryptException $e) {
            return abort(404);
        }

        $menu = Menu::find($decrypted);
        $menu->delete();
        // pakai dispatch untuk mengirim pesan alert tanpa redirect atau reload halaman
        $this->dispatch('notify', message: ['status' => 'success', 'message' => 'Data berhasil dihapus !']);

        // pakai session flash ketika pakai redirect
        // session()->flash('success','BERHASIL MENGHASPUSSS');
        // return $this->redirect(route('menu.index'), navigate: true);
    }

    public function relationSearch(): array
    {
        return [
            'parent_menu' => [
                'name',
            ],
        ];
    }

    public function actions($row): array
    {
        return [
            Button::add('edit-menu')
                ->slot('<i class="ri-file-edit-line"></i> Kelola')
                ->class('btn btn-info btn-sm mb-2')
                ->route('menu.add_edit', ['id' => Crypt::encrypt($row->id)])
                ->tooltip('Edit Menu')
                ->attributes([
                    'wire:navigate' => true,
                ]),
            Button::add('delete-menu')
                ->slot('<i class="ri-delete-bin-line"></i> Hapus')
                ->class('btn btn-danger btn-sm mb-2')
                ->tooltip('Hapus Menu')
                ->attributes([
                    'wire:click' => "confirmDeleteMenu('".Crypt::encrypt($row->id)."')",
                ]),
        ];
    }
}
