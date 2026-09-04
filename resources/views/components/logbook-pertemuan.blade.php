<?php

use App\Models\LogbookPertemuanBlok;
use App\Models\PertemuanBlok;
use App\Models\PesertaBlok;
use App\Support\AksesPertemuanBlok;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public int $pertemuan_blok_id;

    public $file;

    public array $catatan = [];

    public function mount(int $pertemuan_blok_id): void
    {
        $this->pertemuan_blok_id = $pertemuan_blok_id;
        abort_unless(AksesPertemuanBlok::logbookAktif($pertemuan_blok_id), 404);
        abort_unless(
            AksesPertemuanBlok::bolehValidasiLogbook(auth()->user(), $pertemuan_blok_id)
            || AksesPertemuanBlok::bolehLihatPertemuan(auth()->user(), $pertemuan_blok_id),
            403
        );
    }

    public function unggah(): void
    {
        $mahasiswaId = (int) (auth()->user()?->mahasiswa?->id_mahasiswa ?? 0);
        abort_unless(AksesPertemuanBlok::bolehUnggahLogbook(auth()->user(), $this->pertemuan_blok_id, $mahasiswaId), 403);

        $this->validate([
            'file' => ['required', 'file', 'mimetypes:application/pdf', 'mimes:pdf', 'max:10240'],
        ], [
            'file.required' => 'File PDF wajib dipilih.',
            'file.mimetypes' => 'File wajib berformat PDF.',
            'file.mimes' => 'File wajib berformat PDF.',
            'file.max' => 'Ukuran PDF maksimal 10 MB.',
        ]);

        $lama = LogbookPertemuanBlok::query()
            ->where('pertemuan_blok_id', $this->pertemuan_blok_id)
            ->where('mahasiswa_id', $mahasiswaId)
            ->first();

        abort_if($lama?->status === 'valid', 422, 'Logbook tervalidasi sudah terkunci.');

        $namaFileAsli = $this->file->getClientOriginalName();
        $ukuranFile = $this->file->getSize();
        $pathBaru = $this->file->store("logbook/{$this->pertemuan_blok_id}", 'local');
        abort_unless($pathBaru, 500, 'File gagal disimpan.');

        try {
            LogbookPertemuanBlok::updateOrCreate(
                ['pertemuan_blok_id' => $this->pertemuan_blok_id, 'mahasiswa_id' => $mahasiswaId],
                [
                    'path_file' => $pathBaru,
                    'nama_file_asli' => $namaFileAsli,
                    'ukuran_file' => $ukuranFile,
                    'status' => 'menunggu',
                    'catatan_validasi' => null,
                    'diunggah_pada' => now(),
                    'divalidasi_pada' => null,
                    'divalidasi_oleh_user_id' => null,
                ]
            );
        } catch (Throwable $e) {
            Storage::disk('local')->delete($pathBaru);
            throw $e;
        }

        if ($lama && $lama->path_file !== $pathBaru) {
            Storage::disk('local')->delete($lama->path_file);
        }

        $this->reset('file');
        $this->dispatch('logbook-tersimpan');
    }

    public function validasi(int $id): void
    {
        $logbook = $this->logbook($id);
        abort_unless(AksesPertemuanBlok::bolehValidasiLogbook(auth()->user(), $this->pertemuan_blok_id), 403);

        $logbook->update([
            'status' => 'valid',
            'catatan_validasi' => null,
            'divalidasi_pada' => now(),
            'divalidasi_oleh_user_id' => auth()->id(),
        ]);
    }

    public function tolak(int $id): void
    {
        $logbook = $this->logbook($id);
        abort_unless(AksesPertemuanBlok::bolehValidasiLogbook(auth()->user(), $this->pertemuan_blok_id), 403);

        $this->validate(["catatan.$id" => ['required', 'string', 'max:2000']], [
            "catatan.$id.required" => 'Catatan penolakan wajib diisi.',
        ]);

        $logbook->update([
            'status' => 'ditolak',
            'catatan_validasi' => trim($this->catatan[$id]),
            'divalidasi_pada' => now(),
            'divalidasi_oleh_user_id' => auth()->id(),
        ]);
    }

    private function logbook(int $id): LogbookPertemuanBlok
    {
        return LogbookPertemuanBlok::query()
            ->where('pertemuan_blok_id', $this->pertemuan_blok_id)
            ->findOrFail($id);
    }

    public function render()
    {
        $pertemuan = PertemuanBlok::findOrFail($this->pertemuan_blok_id);
        $validator = AksesPertemuanBlok::bolehValidasiLogbook(auth()->user(), $this->pertemuan_blok_id);
        $mahasiswaId = (int) (auth()->user()?->mahasiswa?->id_mahasiswa ?? 0);
        $bolehUnggah = AksesPertemuanBlok::bolehUnggahLogbook(auth()->user(), $this->pertemuan_blok_id, $mahasiswaId);

        $peserta = $validator
            ? PesertaBlok::query()
                ->select('peserta_blok.*')
                ->join('anggota_kelompok_blok', 'anggota_kelompok_blok.peserta_blok_id', '=', 'peserta_blok.id_peserta_blok')
                ->where('anggota_kelompok_blok.kelompok_blok_id', $pertemuan->kelompok_blok_id)
                ->whereIn('peserta_blok.status', ['aktif', 'mengulang'])
                ->with('mahasiswa:id_mahasiswa,nim,nama')
                ->get()
            : collect();

        $logbooks = LogbookPertemuanBlok::query()
            ->where('pertemuan_blok_id', $this->pertemuan_blok_id)
            ->when(! $validator, fn ($query) => $query->where('mahasiswa_id', $mahasiswaId))
            ->get()
            ->keyBy('mahasiswa_id');

        return $this->view(compact('validator', 'mahasiswaId', 'bolehUnggah', 'peserta', 'logbooks'));
    }
};
?>

<div>
    <x-full-page-loading message="Memproses operasional blok..." />
    @php($warna = ['menunggu' => 'warning', 'valid' => 'success', 'ditolak' => 'danger'])
    @php($label = ['menunggu' => 'Menunggu Validasi', 'valid' => 'Valid', 'ditolak' => 'Ditolak'])

    @if (! $validator)
        @php($logbook = $logbooks->get($mahasiswaId))
        <form wire:submit="unggah">
            @if ($logbook)
                <span class="badge bg-{{ $warna[$logbook->status] }}-subtle text-{{ $warna[$logbook->status] }}">{{ $label[$logbook->status] }}</span>
                <a class="btn btn-link btn-sm" href="{{ route('logbook.download', $logbook) }}"><i class="ri-download-line"></i> Unduh</a>
                @if ($logbook->status === 'ditolak')
                    <div class="alert alert-danger py-2 mt-2 alert-dismissible fade show" role="alert">
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                        {{ $logbook->catatan_validasi }}</div>
                @endif
            @endif
            @if ((! $logbook || $logbook->status !== 'valid') && $bolehUnggah)
                <input type="file" class="form-control mt-2" wire:model="file" accept="application/pdf">
                @error('file') <div class="text-danger small">{{ $message }}</div> @enderror
                <button class="btn btn-primary btn-sm mt-2" type="submit">Unggah PDF</button>
            @elseif ((! $logbook || $logbook->status !== 'valid') && ! $bolehUnggah)
                <div class="alert alert-info py-2 mt-2 mb-0">
                    Logbook dapat diunggah setelah monitoring pertemuan divalidasi.
                </div>
            @endif
        </form>
    @else
        @php($rekap = ['belum' => 0, 'menunggu' => 0, 'valid' => 0, 'ditolak' => 0])
        @foreach ($peserta as $item)
            @php($rekap[$logbooks->get($item->mahasiswa_id)?->status ?? 'belum']++)
        @endforeach
        <div class="mb-2">
            @foreach ($rekap as $status => $jumlah)
                <span class="badge bg-light text-dark border">{{ ucfirst($status) }}: {{ $jumlah }}</span>
            @endforeach
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>Mahasiswa</th><th>Status</th><th>File</th><th>Validasi</th></tr></thead>
                <tbody>
                @foreach ($peserta as $item)
                    @php($logbook = $logbooks->get($item->mahasiswa_id))
                    <tr>
                        <td>{{ $item->mahasiswa?->nama }}<div class="small text-muted">{{ $item->mahasiswa?->nim }}</div></td>
                        <td>
                            @if ($logbook)
                                <span class="badge bg-{{ $warna[$logbook->status] }}-subtle text-{{ $warna[$logbook->status] }}">{{ $label[$logbook->status] }}</span>
                            @else
                                <span class="badge bg-light text-dark border">Belum Unggah</span>
                            @endif
                        </td>
                        <td>@if ($logbook)<a href="{{ route('logbook.download', $logbook) }}">Unduh PDF</a>@else - @endif</td>
                        <td>
                            @if ($logbook && $logbook->status !== 'valid')
                                <button class="btn btn-success btn-sm" wire:click="validasi({{ $logbook->id }})">Validasi</button>
                                <input class="form-control form-control-sm mt-1" placeholder="Catatan wajib untuk tolak" wire:model="catatan.{{ $logbook->id }}">
                                @error('catatan.'.$logbook->id) <div class="text-danger small">{{ $message }}</div> @enderror
                                <button class="btn btn-danger btn-sm mt-1" wire:click="tolak({{ $logbook->id }})">Tolak</button>
                            @elseif ($logbook?->catatan_validasi)
                                <span class="small text-danger">{{ $logbook->catatan_validasi }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>