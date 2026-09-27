<?php

use App\Models\Mahasiswa;
use App\Models\Semester;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function mount(): void
    {
        $this->mahasiswa();
    }

    private function mahasiswa(): Mahasiswa
    {
        abort_unless(auth()->user()?->can('status-registrasi-saya:'), 403);
        $mahasiswa = auth()->user()?->mahasiswa;
        abort_unless($mahasiswa, 403, 'Akun ini belum terhubung ke data mahasiswa.');

        return $mahasiswa;
    }

    public function with(): array
    {
        $mahasiswa = $this->mahasiswa();
        $semester = Semester::query()
            ->with(['status_registrasi_mahasiswa' => fn ($query) => $query
                ->where('mahasiswa_id', $mahasiswa->id_mahasiswa)
                ->select('id_status_registrasi_mahasiswa', 'semester_id', 'status')])
            ->where('tahun', '>=', $mahasiswa->angkatan)
            ->orderByDesc('tahun')
            ->orderByDesc('kode')
            ->get();

        return compact('mahasiswa', 'semester');
    }
}; ?>

<div>
    <div class="mb-3">
        <h4 class="mb-1">Status Registrasi Saya</h4>
        <div class="text-muted">{{ $mahasiswa->nim }} · {{ $mahasiswa->nama }} · Angkatan {{ $mahasiswa->angkatan }}</div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="text-center">No</th>
                            <th>Kode Semester</th>
                            <th>Semester</th>
                            <th class="text-center">Periode</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($semester as $item)
                            @php($status = $item->status_registrasi_mahasiswa->first()?->status)
                            <tr wire:key="status-registrasi-saya-{{ $item->id_semester }}">
                                <td class="text-center">{{ $loop->iteration }}</td>
                                <td>{{ $item->kode }}</td>
                                <td>{{ ucfirst($item->nama) }} {{ $item->tahun }}</td>
                                <td class="text-center">
                                    @if ($item->is_aktif)
                                        <span class="badge bg-primary-subtle text-primary">Semester Aktif</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($status === 'aktif')
                                        <span class="badge bg-success-subtle text-success">Aktif</span>
                                    @elseif ($status === 'cuti')
                                        <span class="badge bg-warning-subtle text-warning">Cuti</span>
                                    @elseif ($status === 'nonaktif')
                                        <span class="badge bg-danger-subtle text-danger">Nonaktif</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary">Belum diatur</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Belum ada semester sejak tahun angkatan mahasiswa.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>