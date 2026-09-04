<?php

namespace App\Livewire;

use App\Models\Blok;
use App\Models\Prodi;
use App\Models\Semester;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

final class TableBlokOperasional extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'prodi')]
    public string $prodiId = '';

    #[Url(as: 'semester')]
    public string $semesterId = '';

    protected string $paginationTheme = 'bootstrap';

    public function mount(): void
    {
        if (! request()->query->has('semester')) {
            $this->semesterId = $this->semesterAktifId();
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedProdiId(): void
    {
        $this->resetPage();
    }

    public function updatedSemesterId(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'prodiId');
        $this->semesterId = $this->semesterAktifId();
        $this->resetPage();
    }

    private function semesterAktifId(): string
    {
        return (string) (Semester::where('is_aktif', true)->value('id_semester') ?? '');
    }

    public function placeholder(): string
    {
        return <<<'HTML'
            <div class="d-flex justify-content-center align-items-center" style="height: 300px;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <div class="ms-3">Memuat daftar blok...</div>
            </div>
        HTML;
    }

    public function render(): View
    {
        $search = trim($this->search);

        $bloks = Blok::query()
            ->dapatDikelolaOleh(auth()->user())
            ->with([
                'prodi:id_prodi,nama',
                'semester:id_semester,nama,tahun',
                'mata_kuliah:id,blok_id,kode',
                'koordinator:id_dosen,nama',
                'asisten_koordinator:id_dosen,nama',
                'pengelola_blok.dosen:id_dosen,nama',
            ])
            ->withCount(['peserta_blok', 'kelompok_blok', 'pertemuan_blok'])
            ->when($search !== '', fn ($query) => $query->where(
                fn ($query) => $query
                    ->where('kode', 'like', "%{$search}%")
                    ->orWhere('nama', 'like', "%{$search}%")
            ))
            ->when($this->prodiId !== '', fn ($query) => $query->where('prodi_id', $this->prodiId))
            ->when($this->semesterId !== '', fn ($query) => $query->where('semester_id', $this->semesterId))
            ->orderByDesc('tanggal_mulai')
            ->orderBy('nama')
            ->paginate(10);

        return view('livewire.table-blok-operasional', [
            'bloks' => $bloks,
            'prodis' => Prodi::orderBy('nama')->get(['id_prodi', 'nama']),
            'semesters' => Semester::orderByDesc('tahun')->orderBy('nama')->get(['id_semester', 'nama', 'tahun']),
        ]);
    }
}
