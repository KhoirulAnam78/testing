<?php

use App\Exports\ArrayTemplateExport;
use App\Models\DosenPertemuanBlok;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    #[Url(as: 'semester')]
    public string $semester_id = '';

    #[Url(as: 'q')]
    public string $search = '';

    public function mount(): void
    {
        $this->pastikanAkses();
        $this->search = mb_substr($this->search, 0, 100);

        if (! request()->query->has('semester')) {
            $this->semester_id = (string) (Semester::where('is_aktif', true)->value('id_semester') ?? '');
        }
    }

    public function updatedSemesterId(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->search = mb_substr($this->search, 0, 100);
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search');
        $this->semester_id = (string) (Semester::where('is_aktif', true)->value('id_semester') ?? '');
        $this->resetPage();
    }

    public function modePengelola(): bool
    {
        return auth()->user()?->can('rekap-sks-dosen:') === true;
    }

    private function pastikanAkses(): ?int
    {
        $user = auth()->user();

        abort_unless($user?->can('rekap-sks-dosen:') || $user?->can('rekap-sks-saya:'), 403);

        if ($user->can('rekap-sks-dosen:')) {
            return null;
        }

        abort_unless($user->dosen, 403, 'Akun ini belum terhubung ke data dosen.');

        return (int) $user->dosen->id_dosen;
    }

    public function semesterOptions(): Collection
    {
        $dosenId = $this->pastikanAkses();

        return Semester::query()
            ->whereHas('blok.pertemuan_blok.dosen_pertemuan_blok', function (Builder $query) use ($dosenId) {
                $query->when($dosenId !== null, fn (Builder $query) => $query->where('dosen_id', $dosenId));
            })
            ->orderByDesc('tahun')
            ->orderBy('nama')
            ->get(['id_semester', 'nama', 'tahun', 'is_aktif']);
    }

    public function penugasanQuery(): Builder
    {
        $dosenId = $this->pastikanAkses();
        $search = trim($this->search);

        return DosenPertemuanBlok::query()
            ->select('dosen_pertemuan_blok.*')
            ->join('pertemuan_blok as urut_pertemuan', 'urut_pertemuan.id_pertemuan_blok', '=', 'dosen_pertemuan_blok.pertemuan_blok_id')
            ->join('blok as urut_blok', 'urut_blok.id', '=', 'urut_pertemuan.blok_id')
            ->join('aturan_kegiatan_blok as urut_aturan', 'urut_aturan.id', '=', 'urut_pertemuan.aturan_kegiatan_blok_id')
            ->join('jenis_kegiatan as urut_jenis', 'urut_jenis.id', '=', 'urut_aturan.jenis_kegiatan_id')
            ->join('dosen as urut_dosen', 'urut_dosen.id_dosen', '=', 'dosen_pertemuan_blok.dosen_id')
            ->leftJoin('materi_rinci_blok as urut_materi', 'urut_materi.id_materi_rinci_blok', '=', 'urut_pertemuan.materi_rinci_blok_id')
            ->leftJoin('kelompok_blok as urut_kelompok', 'urut_kelompok.id_kelompok_blok', '=', 'urut_pertemuan.kelompok_blok_id')
            ->whereNull('urut_pertemuan.deleted_at')
            ->whereNull('urut_blok.deleted_at')
            ->whereNull('urut_aturan.deleted_at')
            ->whereNull('urut_jenis.deleted_at')
            ->whereNull('urut_dosen.deleted_at')
            ->whereNull('urut_materi.deleted_at')
            ->whereNull('urut_kelompok.deleted_at')
            ->when($dosenId !== null, fn (Builder $query) => $query->where('dosen_pertemuan_blok.dosen_id', $dosenId))
            ->when($this->semester_id !== '', fn (Builder $query) => $query->where('urut_blok.semester_id', (int) $this->semester_id))
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('urut_dosen.nama', 'like', "%{$search}%")
                        ->orWhere('urut_dosen.nidn', 'like', "%{$search}%")
                        ->orWhere('urut_dosen.nip', 'like', "%{$search}%")
                        ->orWhere('urut_blok.nama', 'like', "%{$search}%")
                        ->orWhere('urut_materi.judul', 'like', "%{$search}%")
                        ->orWhere('urut_jenis.nama', 'like', "%{$search}%")
                        ->orWhere('urut_kelompok.kode', 'like', "%{$search}%")
                        ->orWhere('urut_kelompok.nama', 'like', "%{$search}%");
                });
            })
            ->with([
                'dosen:id_dosen,nama,nidn,nip',
                'pertemuan_blok' => fn ($query) => $query
                    ->select('id_pertemuan_blok', 'blok_id', 'aturan_kegiatan_blok_id', 'materi_rinci_blok_id', 'kelompok_blok_id', 'tanggal', 'status')
                    ->with([
                        'blok:id,nama,semester_id',
                        'blok.semester:id_semester,nama,tahun',
                        'aturan_kegiatan_blok' => fn ($query) => $query
                            ->select('id', 'jenis_kegiatan_id', 'bobot_sks')
                            ->withCount('materi_rinci_blok'),
                        'aturan_kegiatan_blok.jenis_kegiatan:id,nama',
                        'materi_rinci_blok:id_materi_rinci_blok,judul,pertemuan_ke',
                        'kelompok_blok:id_kelompok_blok,kode,nama',
                        'dosen_pertemuan_blok:id_dosen_pertemuan_blok,pertemuan_blok_id,dosen_id',
                    ]),
            ])
            ->orderBy('urut_dosen.nama')
            ->orderByDesc('urut_pertemuan.tanggal')
            ->orderBy('urut_blok.nama')
            ->orderBy('dosen_pertemuan_blok.id_dosen_pertemuan_blok');
    }

    public function penugasan(): LengthAwarePaginator
    {
        $dosen = (clone $this->penugasanQuery())
            ->setEagerLoads([])
            ->reorder('urut_dosen.nama')
            ->select('dosen_pertemuan_blok.dosen_id')
            ->groupBy('dosen_pertemuan_blok.dosen_id', 'urut_dosen.nama')
            ->paginate(20);

        $penugasan = $dosen->isEmpty()
            ? collect()
            : $this->penugasanQuery()
                ->whereIn('dosen_pertemuan_blok.dosen_id', $dosen->pluck('dosen_id'))
                ->get();

        return $dosen->setCollection($penugasan);
    }

    public function ringkasan(): Collection
    {
        $dosenId = $this->pastikanAkses();
        $search = trim($this->search);
        $jumlahPengampu = '(SELECT COUNT(*) FROM dosen_pertemuan_blok AS hitung_pengampu WHERE hitung_pengampu.pertemuan_blok_id = pertemuan_ringkas.id_pertemuan_blok)';
        $jumlahPertemuan = '(SELECT COUNT(*) FROM materi_rinci_blok AS hitung_rinci INNER JOIN materi_blok AS hitung_materi ON hitung_materi.id_materi_blok = hitung_rinci.materi_blok_id WHERE hitung_materi.aturan_kegiatan_blok_id = aturan_ringkas.id AND hitung_materi.deleted_at IS NULL AND hitung_rinci.deleted_at IS NULL AND hitung_rinci.status = \'aktif\')';

        return DosenPertemuanBlok::query()
            ->join('dosen as dosen_ringkas', 'dosen_ringkas.id_dosen', '=', 'dosen_pertemuan_blok.dosen_id')
            ->join('pertemuan_blok as pertemuan_ringkas', 'pertemuan_ringkas.id_pertemuan_blok', '=', 'dosen_pertemuan_blok.pertemuan_blok_id')
            ->join('blok as blok_ringkas', 'blok_ringkas.id', '=', 'pertemuan_ringkas.blok_id')
            ->join('aturan_kegiatan_blok as aturan_ringkas', 'aturan_ringkas.id', '=', 'pertemuan_ringkas.aturan_kegiatan_blok_id')
            ->join('jenis_kegiatan as jenis_ringkas', 'jenis_ringkas.id', '=', 'aturan_ringkas.jenis_kegiatan_id')
            ->leftJoin('materi_rinci_blok as materi_ringkas', 'materi_ringkas.id_materi_rinci_blok', '=', 'pertemuan_ringkas.materi_rinci_blok_id')
            ->leftJoin('kelompok_blok as kelompok_ringkas', 'kelompok_ringkas.id_kelompok_blok', '=', 'pertemuan_ringkas.kelompok_blok_id')
            ->whereNull('dosen_ringkas.deleted_at')
            ->whereNull('pertemuan_ringkas.deleted_at')
            ->whereNull('blok_ringkas.deleted_at')
            ->whereNull('aturan_ringkas.deleted_at')
            ->whereNull('jenis_ringkas.deleted_at')
            ->whereNull('materi_ringkas.deleted_at')
            ->whereNull('kelompok_ringkas.deleted_at')
            ->when($dosenId !== null, fn (Builder $query) => $query->where('dosen_pertemuan_blok.dosen_id', $dosenId))
            ->when($this->semester_id !== '', fn (Builder $query) => $query->where('blok_ringkas.semester_id', (int) $this->semester_id))
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('dosen_ringkas.nama', 'like', "%{$search}%")
                        ->orWhere('dosen_ringkas.nidn', 'like', "%{$search}%")
                        ->orWhere('dosen_ringkas.nip', 'like', "%{$search}%")
                        ->orWhere('blok_ringkas.kode', 'like', "%{$search}%")
                        ->orWhere('blok_ringkas.nama', 'like', "%{$search}%")
                        ->orWhere('materi_ringkas.judul', 'like', "%{$search}%")
                        ->orWhere('jenis_ringkas.nama', 'like', "%{$search}%")
                        ->orWhere('kelompok_ringkas.kode', 'like', "%{$search}%")
                        ->orWhere('kelompok_ringkas.nama', 'like', "%{$search}%");
                });
            })
            ->select([
                'dosen_ringkas.id_dosen',
                'dosen_ringkas.nama',
                'dosen_ringkas.nidn',
                'dosen_ringkas.nip',
            ])
            ->selectRaw('COUNT(*) as jumlah_pertemuan')
            ->selectRaw("SUM(aturan_ringkas.bobot_sks / NULLIF($jumlahPertemuan, 0) / NULLIF($jumlahPengampu, 0)) as total_sks")
            ->groupBy('dosen_ringkas.id_dosen', 'dosen_ringkas.nama', 'dosen_ringkas.nidn', 'dosen_ringkas.nip')
            ->orderBy('dosen_ringkas.nama')
            ->get()
            ->map(fn ($item) => (object) [
                'id_dosen' => $item->id_dosen,
                'nama' => $item->nama,
                'identitas' => $item->nidn ?: $item->nip,
                'jumlah_pertemuan' => (int) $item->jumlah_pertemuan,
                'total_sks' => (float) $item->total_sks,
            ]);
    }

    public function exportExcel(): BinaryFileResponse
    {
        $rows = [[
            $this->modePengelola() ? 'REKAP SKS DOSEN' : 'REKAP SKS SAYA',
        ]];
        $groups = [];
        $headings = [
            'Semester',
            'Blok',
            'Pertemuan Ke',
            'Materi Pertemuan',
            'Tanggal',
            'Kegiatan',
            'Kelompok',
            'Bobot Pertemuan',
            'Jumlah Pengampu',
            'Bobot Dosen',
        ];

        foreach ($this->penugasanQuery()->get()->groupBy('dosen_id') as $daftarPertemuan) {
            $dosen = $daftarPertemuan->first()?->dosen;
            $groupRow = count($rows) + 1;
            $rows[] = [
                (string) ($dosen?->nama ?? '')."\nNIDN/NIP: ".($dosen?->nidn ?: ($dosen?->nip ?: '-')),
                '', '', '', '', '',
                $daftarPertemuan->count().' pertemuan',
                '',
                'Total SKS',
                (float) $daftarPertemuan->sum(fn ($item) => $item->bobot_sks),
            ];
            $rows[] = $headings;
            $detailStartRow = count($rows) + 1;

            foreach ($daftarPertemuan as $item) {
                $pertemuan = $item->pertemuan_blok;
                $rows[] = [
                    trim(ucfirst((string) ($pertemuan?->blok?->semester?->nama ?? '')).' '.($pertemuan?->blok?->semester?->tahun ?? '')),
                    (string) ($pertemuan?->blok?->nama ?? ''),
                    (string) ($pertemuan?->materi_rinci_blok?->pertemuan_ke ?? ''),
                    (string) ($pertemuan?->materi_rinci_blok?->judul ?? 'Pertemuan'),
                    $pertemuan?->tanggal?->format('d/m/Y') ?? '',
                    (string) ($pertemuan?->aturan_kegiatan_blok?->jenis_kegiatan?->nama ?? ''),
                    (string) ($pertemuan?->kelompok_blok?->kode ?? ''),
                    $pertemuan?->aturan_kegiatan_blok?->bobotSksPerPertemuan() ?? 0,
                    $pertemuan?->dosen_pertemuan_blok->count() ?? 0,
                    (float) $item->bobot_sks,
                ];
            }

            $groups[] = [
                'group' => $groupRow,
                'heading' => $groupRow + 1,
                'detail_start' => $detailStartRow,
                'detail_end' => count($rows),
            ];
        }

        if ($groups === []) {
            $rows[] = ['Belum ada penugasan sesuai filter.'];
        }

        $namaFile = ($this->modePengelola() ? 'rekap-sks-dosen-' : 'rekap-sks-saya-').now()->format('Ymd-His').'.xlsx';

        $export = new class($rows, $groups) extends ArrayTemplateExport
        {
            public function __construct(array $rows, private readonly array $groups)
            {
                parent::__construct($rows);
            }

            public function styles(Worksheet $sheet): void
            {
                $lastRow = max(1, count($this->array()));

                $sheet->setShowGridlines(false);
                $sheet->setShowSummaryBelow(false);
                $sheet->freezePane('A2');
                $sheet->getParent()?->getDefaultStyle()->getFont()->setName('Aptos')->setSize(11);
                $sheet->mergeCells('A1:J1');
                $sheet->getRowDimension(1)->setRowHeight(30);
                $sheet->getStyle('A1:J1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '17365D']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                if ($this->groups === []) {
                    $sheet->mergeCells('A2:J2');
                    $sheet->getRowDimension(2)->setRowHeight(28);
                    $sheet->getStyle('A2:J2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    return;
                }

                foreach ($this->groups as $group) {
                    $groupRow = $group['group'];
                    $headingRow = $group['heading'];
                    $detailStart = $group['detail_start'];
                    $detailEnd = $group['detail_end'];

                    $sheet->mergeCells("A{$groupRow}:F{$groupRow}");
                    $sheet->mergeCells("G{$groupRow}:H{$groupRow}");
                    $sheet->getRowDimension($groupRow)->setRowHeight(38);
                    $sheet->getStyle("A{$groupRow}:J{$groupRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    ]);
                    $sheet->getStyle("G{$groupRow}:J{$groupRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("J{$groupRow}")->getNumberFormat()->setFormatCode('0.0000');

                    $sheet->getRowDimension($headingRow)->setRowHeight(30);
                    $sheet->getStyle("A{$headingRow}:J{$headingRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '1F4E78']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9EAF7']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    ]);

                    $sheet->getStyle("A{$headingRow}:J{$detailEnd}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9E2F3']]],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getStyle("H{$detailStart}:J{$detailEnd}")->getNumberFormat()->setFormatCode('0.0000');

                    for ($row = $detailStart; $row <= $detailEnd; $row++) {
                        $sheet->getRowDimension($row)->setRowHeight(22)->setOutlineLevel(1);

                        if (($row - $detailStart) % 2 === 1) {
                            $sheet->getStyle("A{$row}:J{$row}")->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setRGB('F4F7FA');
                        }
                    }
                }

                $sheet->getStyle("A1:J{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            }
        };

        return Excel::download($export, $namaFile);
    }
}; ?>

<div>
    <x-full-page-loading target="semester_id,resetFilters" message="Memuat rekap SKS..." />

    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0">{{ $this->modePengelola() ? 'Rekap SKS Dosen' : 'Rekap SKS Saya' }}</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item">{{ $this->modePengelola() ? 'Kelola Blok' : 'Portal Saya' }}</li>
                    <li class="breadcrumb-item active">Rekap SKS</li>
                </ol>
            </div>
        </div>
    </div>

    @php($semesterOptions = $this->semesterOptions())
    @php($penugasan = $this->penugasan())
    @php($ringkasan = $this->ringkasan())
    @php($totalSks = $ringkasan->sum('total_sks'))

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card h-100 border-primary-subtle">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="avatar-md bg-primary-subtle text-primary rounded d-inline-flex align-items-center justify-content-center">
                        <i class="ri-scales-3-line fs-3"></i>
                    </span>
                    <div>
                        <div class="text-muted">{{ $this->modePengelola() ? 'Total SKS seluruh dosen' : 'Total SKS saya' }}</div>
                        <div class="fs-2 fw-semibold lh-sm">{{ number_format($totalSks, 4, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted mb-1">Pertemuan terhitung</div>
                    <div class="fs-3 fw-semibold">{{ number_format($ringkasan->sum('jumlah_pertemuan'), 0, ',', '.') }}</div>
                    <div class="small text-muted">Satu pertemuan dihitung sekali untuk setiap dosen pengampu.</div>
                </div>
            </div>
        </div>
        @if ($this->modePengelola())
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted mb-1">Dosen terplot</div>
                        <div class="fs-3 fw-semibold">{{ number_format($ringkasan->count(), 0, ',', '.') }}</div>
                        <div class="small text-muted">Dosen dengan penugasan sesuai filter.</div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="card">
        <div class="card-header">
            <div class="row align-items-end g-3">
                <div class="col-lg-4 col-md-6">
                    <label class="form-label" for="search-rekap-sks">Pencarian</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="ri-search-line"></i></span>
                        <input
                            id="search-rekap-sks"
                            type="search"
                            class="form-control"
                            placeholder="Dosen, blok, pertemuan, kegiatan..."
                            maxlength="100"
                            wire:model.live.debounce.400ms="search"
                        >
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label" for="filter-semester-sks">Semester</label>
                    <select id="filter-semester-sks" class="form-select" wire:model.live="semester_id">
                        <option value="">Semua semester</option>
                        @foreach ($semesterOptions as $semester)
                            <option value="{{ $semester->id_semester }}">
                                {{ ucfirst($semester->nama) }} {{ $semester->tahun }}{{ $semester->is_aktif ? ' (aktif)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <button
                        type="button"
                        class="btn btn-success w-100"
                        wire:click="exportExcel"
                        wire:loading.attr="disabled"
                        wire:target="exportExcel"
                        @disabled($penugasan->total() === 0)
                    >
                        <span wire:loading.remove wire:target="exportExcel"><i class="ri-file-excel-2-line"></i> Export Excel</span>
                        <span wire:loading wire:target="exportExcel"><span class="spinner-border spinner-border-sm me-1"></span> Mengekspor...</span>
                    </button>
                </div>
                <div class="col-lg-2 col-md-6">
                    <button type="button" class="btn btn-secondary w-100" wire:click="resetFilters">
                        <i class="ri-refresh-line"></i> Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    @if ($this->modePengelola())
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Ringkasan per Dosen</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Dosen</th>
                                <th>Identitas</th>
                                <th class="text-end">Pertemuan</th>
                                <th class="text-end">Total SKS</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ringkasan as $item)
                                <tr wire:key="ringkasan-sks-{{ $item->id_dosen }}">
                                    <td class="fw-semibold">{{ $item->nama }}</td>
                                    <td>{{ $item->identitas ?: '-' }}</td>
                                    <td class="text-end">{{ $item->jumlah_pertemuan }}</td>
                                    <td class="text-end fw-semibold text-primary">{{ number_format($item->total_sks, 4, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">Belum ada penugasan sesuai filter.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-1">Rincian Perhitungan</h5>
            <div class="small text-muted">Bobot SKS pertemuan dibagi rata sesuai jumlah dosen yang diplot pada pertemuan tersebut.</div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Semester / Blok</th>
                            <th>Pertemuan</th>
                            <th>Kegiatan / Kelompok</th>
                            <th class="text-end">Bobot Pertemuan</th>
                            <th class="text-end">Pengampu</th>
                            <th class="text-end">Bobot Dosen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($penugasan->getCollection()->groupBy('dosen_id') as $dosenId => $daftarPertemuan)
                            @php($dosen = $daftarPertemuan->first()?->dosen)
                            <tr wire:key="grup-dosen-sks-{{ $dosenId }}">
                                <td colspan="6" class="bg-primary text-white py-3">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                        <div>
                                            <div class="fw-semibold"><i class="ri-user-star-line me-1"></i>{{ $dosen?->nama }}</div>
                                            <div class="small text-white-50">{{ $dosen?->nidn ?: ($dosen?->nip ?: '-') }}</div>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2">
                                            <span class="badge bg-white text-primary">{{ $daftarPertemuan->count() }} pertemuan</span>
                                            <span class="badge bg-warning text-dark">Total {{ number_format($daftarPertemuan->sum(fn ($item) => $item->bobot_sks), 4, ',', '.') }} SKS</span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @foreach ($daftarPertemuan as $item)
                                @php($pertemuan = $item->pertemuan_blok)
                                @php($jumlahDosen = $pertemuan?->dosen_pertemuan_blok->count() ?? 0)
                                @php($bobotPertemuan = $pertemuan?->aturan_kegiatan_blok?->bobotSksPerPertemuan() ?? 0)
                                <tr wire:key="rincian-sks-{{ $item->id_dosen_pertemuan_blok }}">
                                    <td>
                                        <div class="small text-muted">{{ ucfirst($pertemuan?->blok?->semester?->nama ?? '') }} {{ $pertemuan?->blok?->semester?->tahun }}</div>
                                        <div class="fw-semibold">{{ $pertemuan?->blok?->nama }}</div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $pertemuan?->materi_rinci_blok?->judul ?: 'Pertemuan' }}</div>
                                        <div class="small text-muted">
                                            {{ $pertemuan?->tanggal?->format('d/m/Y') ?: 'Belum dijadwalkan' }}
                                            @if ($pertemuan?->materi_rinci_blok?->pertemuan_ke)
                                                · Pertemuan {{ $pertemuan->materi_rinci_blok->pertemuan_ke }}
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info-subtle text-info">{{ $pertemuan?->aturan_kegiatan_blok?->jenis_kegiatan?->nama }}</span>
                                        <div class="small mt-1">{{ $pertemuan?->kelompok_blok?->kode ?: '-' }}</div>
                                    </td>
                                    <td class="text-end">{{ number_format($bobotPertemuan, 4, ',', '.') }}</td>
                                    <td class="text-end">
                                        <span class="badge bg-light text-dark border">{{ $jumlahDosen }} dosen</span>
                                    </td>
                                    <td class="text-end fw-semibold text-primary">{{ number_format($item->bobot_sks, 4, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    Belum ada penugasan sesuai filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($penugasan->hasPages())
            <div class="card-footer">{{ $penugasan->links() }}</div>
        @endif
    </div>
</div>
