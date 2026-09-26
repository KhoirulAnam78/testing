<?php

use App\Models\SkalaNilai;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public $edit_id;

    public $nama;

    public $versi = 1;

    public bool $aktif = false;

    public $keterangan;

    public bool $terpakai = false;

    public array $detail = [];

    public function mount($id): void
    {
        abort_unless(auth()->user()?->can('skala-nilai:'), 403);

        if ($id === 'add') {
            $this->isiDefault();

            return;
        }

        try {
            $this->edit_id = Crypt::decrypt($id);
        } catch (DecryptException) {
            abort(404, 'Enkripsi tidak valid!');
        }

        $skala = SkalaNilai::with('detail')->withCount('kurikulum')->findOrFail($this->edit_id);
        $this->nama = $skala->nama;
        $this->versi = $skala->versi;
        $this->aktif = $skala->aktif;
        $this->keterangan = $skala->keterangan;
        $this->terpakai = $skala->kurikulum_count > 0;
        $this->detail = $skala->detail->map(fn ($item) => [
            'id' => $item->id_skala_nilai_detail,
            'nilai_angka_min' => $item->nilai_angka_min,
            'nilai_angka_max' => $item->nilai_angka_max,
            'nilai_huruf' => $item->nilai_huruf,
            'nilai_indeks' => $item->nilai_indeks,
            'lulus' => $item->lulus,
            'boleh_perbaikan' => $item->boleh_perbaikan,
        ])->values()->all();
    }

    public function addDetail(): void
    {
        if (! $this->terpakai) {
            $this->detail[] = ['id' => null, 'nilai_angka_min' => 0, 'nilai_angka_max' => 0, 'nilai_huruf' => '', 'nilai_indeks' => 0, 'lulus' => true, 'boleh_perbaikan' => false];
        }
    }

    public function removeDetail(int $index): void
    {
        if (! $this->terpakai) {
            unset($this->detail[$index]);
            $this->detail = array_values($this->detail);
        }
    }

    public function save()
    {
        abort_unless(auth()->user()?->can('skala-nilai:'), 403);

        $terpakai = $this->edit_id
            ? SkalaNilai::findOrFail($this->edit_id)->kurikulum()->exists()
            : false;

        $payload = $this->validate([
            'nama' => ['required', 'string', 'max:255'],
            'versi' => ['required', 'integer', 'min:1', Rule::unique('skala_nilai', 'versi')->where('nama', $this->nama)->ignore($this->edit_id, 'id_skala_nilai')],
            'aktif' => ['boolean'],
            'keterangan' => ['nullable', 'string'],
            'detail' => ['required', 'array', 'min:1'],
            'detail.*.id' => ['nullable', 'integer'],
            'detail.*.nilai_angka_min' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'detail.*.nilai_angka_max' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'detail.*.nilai_huruf' => ['required', 'string', 'max:10'],
            'detail.*.nilai_indeks' => ['required', 'numeric', 'min:0', 'max:99.99', 'decimal:0,2'],
            'detail.*.lulus' => ['boolean'],
            'detail.*.boleh_perbaikan' => ['boolean'],
        ], [
            'nama.required' => 'Nama skala wajib diisi.',
            'versi.unique' => 'Versi sudah ada untuk nama skala ini.',
            'detail.required' => 'Minimal satu grade wajib diisi.',
            'detail.*.nilai_huruf.required' => 'Huruf wajib diisi.',
        ]);

        $detailValidasi = $terpakai
            ? SkalaNilai::with('detail')->findOrFail($this->edit_id)->detail->toArray()
            : $payload['detail'];

        if (! $this->skalaValid($detailValidasi)) {
            return null;
        }

        if ($payload['aktif'] && SkalaNilai::where('aktif', true)
            ->when($this->edit_id, fn ($query) => $query->whereKeyNot($this->edit_id))
            ->exists()) {
            $this->addError('aktif', 'Hanya satu skala nilai global yang boleh aktif. Nonaktifkan skala aktif lebih dulu.');

            return null;
        }

        DB::transaction(function () use ($payload, $terpakai) {
            if ($terpakai) {
                SkalaNilai::findOrFail($this->edit_id)->update([
                    'aktif' => $payload['aktif'],
                ]);

                return;
            }

            $skala = SkalaNilai::updateOrCreate(['id_skala_nilai' => $this->edit_id], collect($payload)->except('detail')->all());
            $tersimpan = [];

            foreach ($payload['detail'] as $baris) {
                $baris['nilai_huruf'] = Str::upper(trim($baris['nilai_huruf']));
                $detail = $skala->detail()->updateOrCreate(
                    ['id_skala_nilai_detail' => $baris['id'] ?? null],
                    collect($baris)->except('id')->all()
                );
                $tersimpan[] = $detail->id_skala_nilai_detail;
            }

            $skala->detail()->when($tersimpan !== [], fn ($query) => $query->whereNotIn('id_skala_nilai_detail', $tersimpan))->delete();
        });

        session()->flash('success', 'Skala nilai berhasil disimpan.');

        return $this->redirect(route('skala-nilai.index'), navigate: true);
    }

    private function skalaValid(array $detail): bool
    {
        $huruf = collect($detail)->pluck('nilai_huruf')->map(fn ($item) => Str::upper(trim($item)));
        $minimum = collect($detail)->pluck('nilai_angka_min')->map(fn ($item) => number_format((float) $item, 2, '.', ''));

        if ($huruf->duplicates()->isNotEmpty() || $minimum->duplicates()->isNotEmpty()) {
            $this->addError('detail', 'Nilai minimum dan huruf tidak boleh duplikat.');

            return false;
        }

        $detail = collect($detail)->sortBy('nilai_angka_min')->values();

        if ((float) $detail->first()['nilai_angka_min'] !== 0.0 || (float) $detail->last()['nilai_angka_max'] !== 100.0) {
            $this->addError('detail', 'Rentang wajib mencakup 0.00 sampai 100.00.');

            return false;
        }

        foreach ($detail as $index => $baris) {
            if ((float) $baris['nilai_angka_min'] > (float) $baris['nilai_angka_max']) {
                $this->addError("detail.$index.nilai_angka_max", 'Nilai maksimum harus sama atau lebih besar dari minimum.');

                return false;
            }

            if ($index > 0 && round((float) $detail[$index - 1]['nilai_angka_max'] + 0.01, 2) !== round((float) $baris['nilai_angka_min'], 2)) {
                $this->addError('detail', 'Rentang tidak boleh berlubang atau tumpang tindih. Gunakan ketelitian dua desimal.');

                return false;
            }
        }

        return true;
    }

    private function isiDefault(): void
    {
        $this->detail = [
            ['id' => null, 'nilai_angka_min' => 80, 'nilai_angka_max' => 100, 'nilai_huruf' => 'A', 'nilai_indeks' => 4, 'lulus' => true, 'boleh_perbaikan' => false],
            ['id' => null, 'nilai_angka_min' => 70, 'nilai_angka_max' => 79.99, 'nilai_huruf' => 'B', 'nilai_indeks' => 3, 'lulus' => true, 'boleh_perbaikan' => false],
            ['id' => null, 'nilai_angka_min' => 60, 'nilai_angka_max' => 69.99, 'nilai_huruf' => 'C', 'nilai_indeks' => 2, 'lulus' => true, 'boleh_perbaikan' => false],
            ['id' => null, 'nilai_angka_min' => 50, 'nilai_angka_max' => 59.99, 'nilai_huruf' => 'D', 'nilai_indeks' => 1, 'lulus' => false, 'boleh_perbaikan' => true],
            ['id' => null, 'nilai_angka_min' => 0, 'nilai_angka_max' => 49.99, 'nilai_huruf' => 'E', 'nilai_indeks' => 0, 'lulus' => false, 'boleh_perbaikan' => true],
        ];
    }
}; ?>

<form wire:submit="save">
    <div class="page-title-box"><h4 class="mb-sm-0">{{ $edit_id ? 'Kelola' : 'Tambah' }} Skala Nilai</h4></div>
    @error('immutable') <div class="alert alert-warning">{{ $message }}</div> @enderror
    @if ($terpakai) <div class="alert alert-info">Skala sudah dipakai kurikulum. Data dikunci; buat versi baru untuk perubahan.</div> @endif
    <div class="card"><div class="card-header"><h5 class="mb-0">Identitas Versi</h5></div><div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">Nama</label><input class="form-control" wire:model="nama" @disabled($terpakai)>@error('nama') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                <div class="col-md-2 mb-3"><label class="form-label">Versi</label><input type="number" min="1" class="form-control" wire:model="versi" @disabled($terpakai)>@error('versi') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                <div class="col-md-4 mb-3"><label class="form-label">Status</label><select class="form-select" wire:model="aktif"><option value="0">Nonaktif</option><option value="1">Aktif</option></select>@error('aktif') <div class="text-danger small">{{ $message }}</div> @enderror</div>
            </div>
            <label class="form-label">Keterangan</label><textarea class="form-control" rows="2" wire:model="keterangan" @disabled($terpakai)></textarea>
        </div></div>
    <fieldset @disabled($terpakai)>
        <div class="card"><div class="card-header d-flex justify-content-between align-items-center"><div><h5 class="mb-0">Grade</h5><small class="text-muted">Rentang dua desimal, lengkap 0.00–100.00.</small></div><button type="button" class="btn btn-soft-primary btn-sm" wire:click="addDetail">Tambah Grade</button></div>
            <div class="card-body table-responsive">@error('detail') <div class="alert alert-danger">{{ $message }}</div> @enderror
                <table class="table align-middle"><thead><tr><th>Min</th><th>Maks</th><th>Huruf</th><th>Indeks</th><th>Lulus</th><th>Boleh Perbaikan</th><th></th></tr></thead><tbody>
                @foreach ($detail as $index => $baris)
                    <tr wire:key="grade-{{ $index }}">
                        <td><input type="number" step="0.01" class="form-control" wire:model="detail.{{ $index }}.nilai_angka_min">@error("detail.$index.nilai_angka_min") <small class="text-danger">{{ $message }}</small> @enderror</td>
                        <td><input type="number" step="0.01" class="form-control" wire:model="detail.{{ $index }}.nilai_angka_max">@error("detail.$index.nilai_angka_max") <small class="text-danger">{{ $message }}</small> @enderror</td>
                        <td><input class="form-control text-uppercase" wire:model="detail.{{ $index }}.nilai_huruf"></td>
                        <td><input type="number" step="0.01" class="form-control" wire:model="detail.{{ $index }}.nilai_indeks"></td>
                        <td><input type="checkbox" class="form-check-input" wire:model="detail.{{ $index }}.lulus"></td>
                        <td><input type="checkbox" class="form-check-input" wire:model="detail.{{ $index }}.boleh_perbaikan"></td>
                        <td><button type="button" class="btn btn-danger btn-sm" wire:click="removeDetail({{ $index }})"><i class="ri-delete-bin-line"></i></button></td>
                    </tr>
                @endforeach
                </tbody></table>
            </div>
        </div>
    </fieldset>
    <div class="d-flex gap-2 justify-content-end mb-5"><a href="{{ route('skala-nilai.index') }}" wire:navigate class="btn btn-light">Kembali</a><button class="btn btn-primary" wire:loading.attr="disabled"><i class="ri-save-line"></i> Simpan</button></div>
</form>