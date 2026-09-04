<?php

namespace App\Livewire;

use App\Models\Blok;
use App\Models\Prodi;
use App\Models\Semester;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

final class TableBlok extends Component
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

    public function confirmDeleteBlok(string $id): void
    {
        $this->dispatch('siakad-confirm',
            id: $id,
            confirmEvent: 'delete-blok-confirmed',
            title: 'Hapus blok?',
            text: 'Blok hanya bisa dihapus jika belum dipakai mata kuliah.',
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal',
        );
    }

    #[On('delete-blok-confirmed')]
    public function deleteBlok(string $id): void
    {
        try {
            $decrypted = Crypt::decrypt($id);
        } catch (DecryptException) {
            abort(404);
        }

        $blok = Blok::with(['aturan_kegiatan_blok.materi_blok.materi_rinci_blok'])->findOrFail($decrypted);

        if ($blok->mata_kuliah()->exists()) {
            $this->dispatch('notify', message: [
                'status' => 'error',
                'message' => 'Blok tidak dapat dihapus karena sudah dipakai mata kuliah.',
            ]);

            return;
        }

        if ($blok->peserta_blok()->exists() || $blok->kelompok_blok()->exists() || $blok->pertemuan_blok()->exists()) {
            $this->dispatch('notify', message: [
                'status' => 'error',
                'message' => 'Blok tidak dapat dihapus karena sudah memiliki peserta, kelompok, atau pertemuan.',
            ]);

            return;
        }

        DB::transaction(function () use ($blok) {
            foreach ($blok->aturan_kegiatan_blok as $aturan) {
                foreach ($aturan->materi_blok as $materi) {
                    $materi->materi_rinci_blok()->delete();
                    $materi->delete();
                }

                $aturan->delete();
            }

            $blok->delete();
        });

        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => 'Data berhasil dihapus !',
        ]);
    }

    public function render(): View
    {
        $search = trim($this->search);

        $bloks = Blok::query()
            ->with([
                'prodi:id_prodi,nama',
                'semester:id_semester,nama,tahun',
            ])
            ->withCount(['mata_kuliah', 'aturan_kegiatan_blok', 'materi_blok'])
            ->when($search !== '', fn ($query) => $query->where('nama', 'like', "%{$search}%"))
            ->when($this->prodiId !== '', fn ($query) => $query->where('prodi_id', $this->prodiId))
            ->when($this->semesterId !== '', fn ($query) => $query->where('semester_id', $this->semesterId))
            ->orderByDesc('tanggal_mulai')
            ->orderBy('nama')
            ->paginate(10);

        return view('livewire.table-blok', [
            'bloks' => $bloks,
            'prodis' => Prodi::orderBy('nama')->get(['id_prodi', 'nama']),
            'semesters' => Semester::orderByDesc('tahun')->orderBy('nama')->get(['id_semester', 'nama', 'tahun']),
        ]);
    }
}
