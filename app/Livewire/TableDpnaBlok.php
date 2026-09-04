<?php

namespace App\Livewire;

use App\Models\Blok;
use App\Models\Prodi;
use App\Models\Semester;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

final class TableDpnaBlok extends Component
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

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'prodiId', 'semesterId'], true)) {
            $this->resetPage();
        }
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

    public function render(): View
    {
        $search = trim($this->search);

        return view('livewire.table-dpna-blok', [
            'bloks' => Blok::query()
                ->dapatDikelolaOleh(auth()->user())
                ->with(['prodi:id_prodi,nama', 'semester:id_semester,nama,tahun'])
                ->withCount(['peserta_blok', 'pertemuan_blok'])
                ->withSum([
                    'aturan_kegiatan_blok as total_bobot_kegiatan_dpna' => fn ($query) => $query->where('nilai_masuk_dpna', true),
                ], 'bobot_nilai_dpna')
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('kode', 'like', "%{$search}%")
                    ->orWhere('nama', 'like', "%{$search}%")))
                ->when($this->prodiId !== '', fn ($query) => $query->where('prodi_id', $this->prodiId))
                ->when($this->semesterId !== '', fn ($query) => $query->where('semester_id', $this->semesterId))
                ->orderByDesc('tanggal_mulai')
                ->orderBy('nama')
                ->paginate(10),
            'prodis' => Prodi::orderBy('nama')->get(['id_prodi', 'nama']),
            'semesters' => Semester::orderByDesc('tahun')->orderBy('nama')->get(['id_semester', 'nama', 'tahun']),
        ]);
    }
}
