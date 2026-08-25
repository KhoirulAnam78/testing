<?php

use App\Models\Prodi;
use App\Models\Semester;
use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component {
    public ?string $prodi_id = null;
    public ?string $semester_id = null;
    public array $prodiOptions = [];
    public array $semesterOptions = [];

    public function mount(): void
    {
        $this->prodiOptions = Prodi::orderBy('nama')
            ->get(['id_prodi', 'nama'])
            ->map(fn (Prodi $prodi) => [
                'id' => (string) $prodi->id_prodi,
                'nama' => $prodi->nama,
            ])
            ->all();

        $this->semesterOptions = Semester::orderByDesc('tahun')
            ->orderByDesc('kode')
            ->get(['id_semester', 'kode', 'nama', 'tahun'])
            ->map(fn (Semester $semester) => [
                'id' => (string) $semester->id_semester,
                'nama' => ucfirst($semester->nama).' '.$semester->tahun.' ('.$semester->kode.')',
            ])
            ->all();
    }
}; ?>

<div>
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0">Kelola Blok</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript: void(0);">Akademik</a></li>
                    <li class="breadcrumb-item active">Blok</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-6"><h5>Daftar Blok</h5></div>
                        <div class="col-6 text-end">
                            <a href="{{ route('blok.add_edit', ['id' => 'add']) }}" wire:navigate class="btn btn-primary btn-sm">
                                <i class="ri-add-box-fill"></i> Tambah
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <livewire:alert/>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="filter_prodi" class="form-label">Prodi</label>
                            <select id="filter_prodi" wire:model.live="prodi_id" class="form-select">
                                <option value="">Semua Prodi</option>
                                @foreach ($prodiOptions as $prodi)
                                    <option value="{{ $prodi['id'] }}">{{ $prodi['nama'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="filter_semester" class="form-label">Semester</label>
                            <select id="filter_semester" wire:model.live="semester_id" class="form-select">
                                <option value="">Semua Semester</option>
                                @foreach ($semesterOptions as $semester)
                                    <option value="{{ $semester['id'] }}">{{ $semester['nama'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <livewire:table-blok
                        :prodi-id="$prodi_id"
                        :semester-id="$semester_id"
                        :key="'table-blok-'.$prodi_id.'-'.$semester_id"
                        lazy
                    />
                </div>
            </div>
        </div>
    </div>
</div>
