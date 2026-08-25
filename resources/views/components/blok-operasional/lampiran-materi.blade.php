<?php

use App\Models\LampiranMateriBlok;
use App\Models\MateriRinciBlok;
use App\Models\PertemuanBlok;
use App\Support\AksesPertemuanBlok;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Daftar dan form tautan modul / video untuk satu materi.
 *
 * Dua mode, ditentukan oleh `pertemuan_blok_id`:
 * - null  : mengelola lampiran default materi, berlaku untuk semua kelompok (pengelola).
 * - terisi: mengelola lampiran satu pertemuan. Lampiran default tetap ditampilkan
 *           tetapi hanya bisa dibaca, supaya dosen satu kelompok tidak menimpa
 *           materi milik kelompok lain.
 *
 * Halaman mahasiswa tidak memakai komponen ini: daftarnya dirender langsung di Blade
 * karena read-only, sehingga tidak perlu satu komponen Livewire per pertemuan.
 */
new class extends Component
{
    public int $materi_rinci_blok_id;
    public ?int $pertemuan_blok_id = null;

    public string $form_jenis = 'modul';
    public string $form_judul = '';
    public string $form_url = '';
    public string $form_deskripsi = '';
    public ?int $edit_id = null;

    public function mount(int $materi_rinci_blok_id, ?int $pertemuan_blok_id = null): void
    {
        $this->materi_rinci_blok_id = $materi_rinci_blok_id;
        $this->pertemuan_blok_id = $pertemuan_blok_id;

        abort_unless($this->bolehLihat(), 403);

        // Pertemuan yang tidak sesuai materinya menandakan parameter yang tidak konsisten.
        $this->pertemuan();
    }

    public function bolehLihat(): bool
    {
        $user = auth()->user();

        return $this->pertemuan_blok_id
            ? AksesPertemuanBlok::bolehLihatPertemuan($user, $this->pertemuan_blok_id)
            : AksesPertemuanBlok::bolehKelolaLampiranDefault($user);
    }

    public function bolehKelola(): bool
    {
        $user = auth()->user();

        return $this->pertemuan_blok_id
            ? AksesPertemuanBlok::bolehKelolaPertemuan($user, $this->pertemuan_blok_id)
            : AksesPertemuanBlok::bolehKelolaLampiranDefault($user);
    }

    /**
     * Lampiran default materi ini, ditambah lampiran milik pertemuan bila mode pertemuan.
     * Yang nonaktif ikut tampil di sini supaya pengelola dan dosen bisa mengaktifkannya
     * kembali; halaman mahasiswa yang menyaring hanya yang aktif.
     */
    public function daftar()
    {
        $query = $this->pertemuan_blok_id
            ? LampiranMateriBlok::query()->untukPertemuan($this->materi_rinci_blok_id, $this->pertemuan_blok_id)
            : LampiranMateriBlok::query()->defaultMateri($this->materi_rinci_blok_id);

        return $query->get();
    }

    public function simpan(): void
    {
        abort_unless($this->bolehKelola(), 403);

        $data = $this->validate([
            'form_jenis' => ['required', 'in:modul,video'],
            'form_judul' => ['required', 'string', 'max:255'],
            'form_url' => ['required', 'url', 'max:1000', 'starts_with:http://,https://'],
            'form_deskripsi' => ['nullable', 'string', 'max:1000'],
        ], [
            'form_jenis.required' => 'Jenis lampiran wajib dipilih.',
            'form_jenis.in' => 'Jenis lampiran harus modul atau video.',
            'form_judul.required' => 'Judul wajib diisi.',
            'form_judul.max' => 'Judul maksimal 255 karakter.',
            'form_url.required' => 'Tautan wajib diisi.',
            'form_url.url' => 'Tautan tidak valid.',
            'form_url.max' => 'Tautan maksimal 1000 karakter.',
            'form_url.starts_with' => 'Tautan harus dimulai dengan http:// atau https://.',
            'form_deskripsi.max' => 'Keterangan maksimal 1000 karakter.',
        ]);

        $lampiran = $this->edit_id
            ? $this->milikSendiri()->findOrFail($this->edit_id)
            : new LampiranMateriBlok();

        $lampiran->fill([
            'blok_id' => $this->blokId(),
            'materi_rinci_blok_id' => $this->materi_rinci_blok_id,
            'pertemuan_blok_id' => $this->pertemuan_blok_id,
            'jenis' => $data['form_jenis'],
            'judul' => trim($data['form_judul']),
            'url' => trim($data['form_url']),
            'deskripsi' => trim($data['form_deskripsi']) !== '' ? trim($data['form_deskripsi']) : null,
        ]);

        if (! $lampiran->exists) {
            $lampiran->urutan = $this->urutanBerikutnya($data['form_jenis']);
            $lampiran->status = 'aktif';
            $lampiran->dibuat_oleh_user_id = auth()->id();
        }

        $lampiran->save();

        $pesan = $this->edit_id ? 'Tautan berhasil diperbarui.' : 'Tautan berhasil ditambahkan.';

        $this->resetForm();
        $this->dispatch('lampiran-materi-tersimpan');
        $this->dispatch('notify', message: ['status' => 'success', 'message' => $pesan]);
    }

    public function edit(string $id): void
    {
        abort_unless($this->bolehKelola(), 403);

        $lampiran = $this->milikSendiri()->findOrFail((int) $id);

        $this->edit_id = $lampiran->id_lampiran_materi_blok;
        $this->form_jenis = $lampiran->jenis;
        $this->form_judul = (string) $lampiran->judul;
        $this->form_url = (string) $lampiran->url;
        $this->form_deskripsi = (string) $lampiran->deskripsi;
        $this->resetErrorBag();
    }

    public function hapus(string $id): void
    {
        abort_unless($this->bolehKelola(), 403);

        $this->milikSendiri()->findOrFail((int) $id)->delete();

        $this->resetForm();
        $this->dispatch('lampiran-materi-tersimpan');
        $this->dispatch('notify', message: ['status' => 'success', 'message' => 'Tautan berhasil dihapus.']);
    }

    /**
     * Menonaktifkan lebih aman daripada menghapus: tautan hilang dari halaman mahasiswa
     * tetapi riwayatnya tetap ada untuk pengelola dan dosen.
     */
    public function toggleStatus(string $id): void
    {
        abort_unless($this->bolehKelola(), 403);

        $lampiran = $this->milikSendiri()->findOrFail((int) $id);
        $lampiran->status = $lampiran->status === 'aktif' ? 'nonaktif' : 'aktif';
        $lampiran->save();

        $this->dispatch('lampiran-materi-tersimpan');
        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => $lampiran->status === 'aktif' ? 'Tautan diaktifkan.' : 'Tautan dinonaktifkan.',
        ]);
    }

    public function resetForm(): void
    {
        $this->reset(['form_jenis', 'form_judul', 'form_url', 'form_deskripsi', 'edit_id']);
        $this->resetErrorBag();
    }

    #[On('lampiran-materi-tersimpan')]
    public function refreshDaftar(): void
    {
        // Cukup memicu render ulang agar komponen lain pada materi yang sama ikut segar.
    }

    /**
     * Hanya baris yang benar-benar milik cakupan komponen ini. Lampiran default tidak
     * bisa disentuh dari mode pertemuan, dan sebaliknya.
     */
    private function milikSendiri()
    {
        return LampiranMateriBlok::query()
            ->where('materi_rinci_blok_id', $this->materi_rinci_blok_id)
            ->when(
                $this->pertemuan_blok_id,
                fn ($query) => $query->where('pertemuan_blok_id', $this->pertemuan_blok_id),
                fn ($query) => $query->whereNull('pertemuan_blok_id')
            );
    }

    private function pertemuan(): ?PertemuanBlok
    {
        if (! $this->pertemuan_blok_id) {
            return null;
        }

        return PertemuanBlok::query()
            ->where('materi_rinci_blok_id', $this->materi_rinci_blok_id)
            ->findOrFail($this->pertemuan_blok_id);
    }

    private function blokId(): int
    {
        $pertemuan = $this->pertemuan();

        if ($pertemuan) {
            return (int) $pertemuan->blok_id;
        }

        $materi = MateriRinciBlok::with('materi_blok.aturan_kegiatan_blok:id,blok_id')
            ->findOrFail($this->materi_rinci_blok_id);

        $blokId = $materi->materi_blok?->aturan_kegiatan_blok?->blok_id;

        abort_unless($blokId, 404, 'Materi ini tidak terhubung ke blok manapun.');

        return (int) $blokId;
    }

    private function urutanBerikutnya(string $jenis): int
    {
        return (int) $this->milikSendiri()->where('jenis', $jenis)->max('urutan') + 1;
    }

    public function render()
    {
        return $this->view([
            'daftar' => $this->daftar(),
            'bolehKelola' => $this->bolehKelola(),
        ]);
    }
};
?>

<div>
    <x-full-page-loading message="Memproses operasional blok..." />
    @if ($daftar->isEmpty())
        <div class="text-muted small mb-2">
            <i class="ri-information-line"></i>
            Belum ada tautan modul atau video.
        </div>
    @else
        <div class="list-group list-group-flush mb-2">
            @foreach ($daftar as $item)
                @php($diwarisi = $pertemuan_blok_id && $item->pertemuan_blok_id === null)
                <div class="list-group-item px-0 py-2" wire:key="lampiran-{{ $item->id_lampiran_materi_blok }}">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <div class="flex-grow-1">
                            <div class="small">
                                @if ($item->jenis === 'video')
                                    <span class="badge bg-danger-subtle text-danger"><i class="ri-video-line"></i> Video</span>
                                @else
                                    <span class="badge bg-primary-subtle text-primary"><i class="ri-links-line"></i> Modul</span>
                                @endif

                                @if ($diwarisi)
                                    <span class="badge bg-light text-dark border">dari materi</span>
                                @endif

                                @if ($item->status !== 'aktif')
                                    <span class="badge bg-secondary-subtle text-secondary">nonaktif</span>
                                @endif

                                <span class="fw-semibold">{{ $item->judul }}</span>
                            </div>

                            <div class="small mt-1">
                                <a href="{{ $item->url }}" target="_blank" rel="noopener nofollow">
                                    {{ \Illuminate\Support\Str::limit($item->url, 70) }}
                                    <i class="ri-external-link-line"></i>
                                </a>
                            </div>

                            @if ($item->deskripsi)
                                <div class="text-muted small mt-1">{{ $item->deskripsi }}</div>
                            @endif
                        </div>

                        @if ($bolehKelola && ! $diwarisi)
                            <div class="text-end flex-shrink-0">
                                <button type="button" class="btn btn-light btn-sm"
                                    wire:click="edit('{{ $item->id_lampiran_materi_blok }}')" title="Ubah">
                                    <i class="ri-file-edit-line"></i>
                                </button>
                                <button type="button" class="btn btn-light btn-sm"
                                    wire:click="toggleStatus('{{ $item->id_lampiran_materi_blok }}')"
                                    title="{{ $item->status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' }}">
                                    <i class="{{ $item->status === 'aktif' ? 'ri-eye-off-line' : 'ri-eye-line' }}"></i>
                                </button>
                                <button type="button" class="btn btn-light btn-sm text-danger"
                                    wire:click="hapus('{{ $item->id_lampiran_materi_blok }}')"
                                    wire:confirm="Hapus tautan ini?" title="Hapus">
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($bolehKelola)
        <form wire:submit="simpan" class="border rounded p-2 bg-light">
            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label small mb-1">Jenis</label>
                    <select class="form-select form-select-sm" wire:model="form_jenis">
                        <option value="modul">Modul</option>
                        <option value="video">Video</option>
                    </select>
                    @error('form_jenis') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-9">
                    <label class="form-label small mb-1">Judul</label>
                    <input type="text" class="form-control form-control-sm" wire:model="form_judul"
                        placeholder="Modul Praktikum Anatomi 1">
                    @error('form_judul') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="col-12">
                    <label class="form-label small mb-1">Tautan</label>
                    <input type="url" class="form-control form-control-sm" wire:model="form_url"
                        placeholder="https://drive.google.com/...">
                    @error('form_url') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="col-12">
                    <label class="form-label small mb-1">Keterangan <span class="text-muted">(opsional)</span></label>
                    <input type="text" class="form-control form-control-sm" wire:model="form_deskripsi">
                    @error('form_deskripsi') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="d-flex gap-2 mt-2">
                <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="simpan">
                    <i class="ri-save-line"></i> {{ $edit_id ? 'PERBARUI' : 'TAMBAH' }}
                </button>
                @if ($edit_id)
                    <button type="button" class="btn btn-light btn-sm" wire:click="resetForm">Batal</button>
                @endif
            </div>
        </form>
    @endif
</div>
