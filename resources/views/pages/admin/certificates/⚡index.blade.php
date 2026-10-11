<?php

use App\Models\Certificate;
use App\Models\LearningPath;
use App\Models\LearningPathEnrollment;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Kelola Sertifikat & Kelulusan - Admin')] class extends Component {
    #[Url]
    public string $search = '';

    #[Url]
    public ?int $selectedPathId = null;

    #[Url]
    public string $statusFilter = 'all'; // all, certified, uncertified

    public bool $showIssueModal = false;
    public bool $showPreviewModal = false;
    public ?int $selectedCertificateId = null;

    // Issue/Edit Form fields
    public ?int $editingCertificateId = null;
    public ?int $formUserId = null;
    public ?int $formPathId = null;
    public string $certificate_number = '';
    public string $title = '';
    public string $grade = 'Lulus dengan Sangat Baik (A)';
    public string $notes = '';
    public string $certificate_url = '';
    public ?string $issued_at = null;

    /**
     * All learning paths for filter and forms.
     */
    #[Computed]
    public function learningPaths()
    {
        return LearningPath::orderBy('title')->get();
    }

    /**
     * Get enrollments list with certificate and user progress.
     */
    #[Computed]
    public function enrollments()
    {
        $query = LearningPathEnrollment::with([
            'user.completedMaterials',
            'learningPath.materials',
            'user.certificates',
        ]);

        if (! empty($this->search)) {
            $query->whereHas('user', function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%')
                    ->orWhere('major', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->selectedPathId) {
            $query->where('learning_path_id', $this->selectedPathId);
        }

        if ($this->statusFilter === 'certified') {
            $query->whereHas('user.certificates', function ($q) {
                $q->whereColumn('certificates.learning_path_id', 'learning_path_enrollments.learning_path_id')
                    ->where('status', 'issued');
            });
        } elseif ($this->statusFilter === 'uncertified') {
            $query->whereDoesntHave('user.certificates', function ($q) {
                $q->whereColumn('certificates.learning_path_id', 'learning_path_enrollments.learning_path_id')
                    ->where('status', 'issued');
            });
        }

        return $query->latest('enrolled_at')->get();
    }

    /**
     * Metrics overview.
     */
    #[Computed]
    public function metrics(): array
    {
        $totalEnrollments = LearningPathEnrollment::count();
        $totalIssuedCertificates = Certificate::where('status', 'issued')->count();
        $pendingCertificates = max(0, $totalEnrollments - $totalIssuedCertificates);

        return [
            'totalEnrollments' => $totalEnrollments,
            'totalIssued' => $totalIssuedCertificates,
            'pending' => $pendingCertificates,
        ];
    }

    /**
     * Open modal to issue a certificate for an enrolled student.
     */
    public function openIssueModal(int $userId, int $pathId): void
    {
        $user = User::findOrFail($userId);
        $path = LearningPath::findOrFail($pathId);

        $existing = Certificate::where('user_id', $user->id)
            ->where('learning_path_id', $path->id)
            ->first();

        $this->formUserId = $user->id;
        $this->formPathId = $path->id;

        if ($existing) {
            $this->editingCertificateId = $existing->id;
            $this->certificate_number = $existing->certificate_number;
            $this->title = $existing->title;
            $this->grade = $existing->grade ?? 'Lulus dengan Sangat Baik (A)';
            $this->notes = $existing->notes ?? '';
            $this->certificate_url = $existing->certificate_url ?? '';
            $this->issued_at = $existing->issued_at->format('Y-m-d\TH:i');
        } else {
            $this->editingCertificateId = null;
            $slugCode = strtoupper(Str::slug(Str::limit($path->slug, 6, '')));
            $this->certificate_number = 'CIT-CERT/'.now()->year.'/'.$slugCode.'/'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT);
            $this->title = 'Sertifikat Kelulusan - '.$path->title;
            $this->grade = 'Lulus dengan Sangat Baik (A)';
            $this->notes = 'Telah menyelesaikan seluruh rangkaian materi dan evaluasi akhir program '.$path->title.'.';
            $this->certificate_url = '';
            $this->issued_at = now()->format('Y-m-d\TH:i');
        }

        $this->showIssueModal = true;
    }

    /**
     * Save issued certificate.
     */
    public function saveCertificate(): void
    {
        $validated = $this->validate([
            'certificate_number' => 'required|string|max:100',
            'title' => 'required|string|max:255',
            'grade' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'certificate_url' => 'nullable|url|max:255',
            'issued_at' => 'required|date',
        ]);

        Certificate::updateOrCreate(
            [
                'user_id' => $this->formUserId,
                'learning_path_id' => $this->formPathId,
            ],
            [
                'certificate_number' => $validated['certificate_number'],
                'title' => $validated['title'],
                'status' => 'issued',
                'grade' => $validated['grade'],
                'notes' => $validated['notes'],
                'certificate_url' => $validated['certificate_url'],
                'issued_at' => Carbon::parse($validated['issued_at']),
                'issued_by' => Auth::id(),
            ]
        );

        $this->showIssueModal = false;
        Flux::toast(variant: 'success', text: __('Sertifikat kelulusan berhasil diterbitkan!'));
    }

    /**
     * Revoke / Delete certificate.
     */
    public function revokeCertificate(int $certificateId): void
    {
        $cert = Certificate::findOrFail($certificateId);
        $cert->delete();

        Flux::toast(variant: 'warning', text: __('Sertifikat telah dicabut/dihapus.'));
    }

    /**
     * Preview certificate.
     */
    public function previewCertificate(int $certificateId): void
    {
        $this->selectedCertificateId = $certificateId;
        unset($this->activePreviewCertificate);
        $this->showPreviewModal = true;
    }

    /**
     * Get certificate for preview.
     */
    #[Computed]
    public function activePreviewCertificate(): ?Certificate
    {
        if (! $this->selectedCertificateId) {
            return null;
        }

        return Certificate::with(['user', 'learningPath', 'issuer'])->find($this->selectedCertificateId);
    }
};
?>

<div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
        <div>
            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-400/10 text-yellow-600 dark:text-yellow-400 mb-2 border border-yellow-400/20">
                <flux:icon name="shield-check" class="size-3.5" />
                <span>Admin Panel</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                Kelola Sertifikat & Kelulusan
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                Verifikasi progres belajar mahasiswa per kelas dan berikan sertifikat kelulusan resmi.
            </p>
        </div>
    </div>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="p-4 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Total Pendaftaran Kelas</p>
                <p class="text-2xl font-black text-zinc-900 dark:text-zinc-100 mt-1">{{ $this->metrics['totalEnrollments'] }} Peserta</p>
            </div>
            <span class="p-2.5 rounded-xl bg-yellow-400/10 text-yellow-600 dark:text-yellow-400">
                <flux:icon name="users" class="size-5" />
            </span>
        </div>

        <div class="p-4 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Sertifikat Diterbitkan</p>
                <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ $this->metrics['totalIssued'] }} Lulus</p>
            </div>
            <span class="p-2.5 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                <flux:icon name="trophy" class="size-5" />
            </span>
        </div>

        <div class="p-4 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Dalam Proses Belajar</p>
                <p class="text-2xl font-black text-amber-500 mt-1">{{ $this->metrics['pending'] }} Peserta</p>
            </div>
            <span class="p-2.5 rounded-xl bg-amber-500/10 text-amber-500">
                <flux:icon name="clock" class="size-5" />
            </span>
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="flex flex-col sm:flex-row gap-4 items-center justify-between">
        <div class="w-full sm:w-72">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Cari nama, email mahasiswa..."
                icon="magnifying-glass"
                size="sm"
                clearable
            />
        </div>

        <div class="flex items-center gap-3 w-full sm:w-auto">
            <div class="w-48">
                <flux:select wire:model.live="selectedPathId" size="sm">
                    <option value="">Semua Path Learning</option>
                    @foreach ($this->learningPaths as $lp)
                        <option value="{{ $lp->id }}">{{ $lp->title }}</option>
                    @endforeach
                </flux:select>
            </div>

            <div class="w-44">
                <flux:select wire:model.live="statusFilter" size="sm">
                    <option value="all">Semua Status</option>
                    <option value="certified">Sudah Lulus / Bersertifikat</option>
                    <option value="uncertified">Belum Bersertifikat</option>
                </flux:select>
            </div>
        </div>
    </div>

    <!-- Table of Enrollments & Certificates -->
    <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-zinc-50 dark:bg-zinc-800/60 text-zinc-600 dark:text-zinc-400 uppercase font-semibold border-b border-zinc-200 dark:border-zinc-800">
                    <tr>
                        <th class="px-5 py-3.5">Mahasiswa</th>
                        <th class="px-5 py-3.5">Path Learning</th>
                        <th class="px-5 py-3.5">Progres Modul</th>
                        <th class="px-5 py-3.5">Status Sertifikasi</th>
                        <th class="px-5 py-3.5">Detail Sertifikat</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->enrollments as $enrollment)
                        @php
                            $user = $enrollment->user;
                            $path = $enrollment->learningPath;
                            $totalMats = $path->materials->count();
                            $completedMats = $user->completedMaterials->where('learning_path_id', $path->id)->count();
                            $matPercent = $totalMats > 0 ? (int) round(($completedMats / $totalMats) * 100) : 0;
                            $cert = $user->certificates->firstWhere('learning_path_id', $path->id);
                        @endphp
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-colors">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="size-8 rounded-full bg-yellow-400/20 text-yellow-700 dark:text-yellow-300 font-bold flex items-center justify-center text-xs shrink-0">
                                        {{ $user->initials() }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-zinc-900 dark:text-zinc-100 text-sm">{{ $user->name }}</p>
                                        <p class="text-zinc-400 text-[11px]">{{ $user->email }} • {{ $user->major ?? 'Mahasiswa' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-yellow-400/15 text-yellow-700 dark:text-yellow-400 border border-yellow-400/30">
                                    <flux:icon name="{{ $path->icon ?: 'academic-cap' }}" class="size-3.5" />
                                    {{ $path->title }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="space-y-1 max-w-[120px]">
                                    <div class="flex items-center justify-between text-[11px]">
                                        <span class="font-bold text-zinc-800 dark:text-zinc-200">{{ $completedMats }} / {{ $totalMats }}</span>
                                        <span class="text-zinc-400">{{ $matPercent }}%</span>
                                    </div>
                                    <div class="w-full h-1.5 rounded-full bg-zinc-200 dark:bg-zinc-700 overflow-hidden">
                                        <div class="h-full rounded-full {{ $matPercent === 100 ? 'bg-emerald-500' : 'bg-yellow-400' }}" style="width: {{ $matPercent }}%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                @if ($cert)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                        <flux:icon name="trophy" class="size-3" />
                                        Lulus & Bersertifikat
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-medium bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                        <flux:icon name="clock" class="size-3" />
                                        Belum Diterbitkan
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                @if ($cert)
                                    <div class="space-y-0.5">
                                        <p class="font-mono text-[11px] text-zinc-900 dark:text-zinc-100 font-bold">{{ $cert->certificate_number }}</p>
                                        <p class="text-[10px] text-zinc-400">
                                            {{ $cert->issued_at->locale('id')->isoFormat('D MMMM Y') }} @if ($cert->grade) • <span class="text-yellow-600 dark:text-yellow-400 font-semibold">{{ $cert->grade }}</span> @endif
                                        </p>
                                    </div>
                                @else
                                    <span class="text-zinc-400 italic text-[11px]">-</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    @if ($cert)
                                        <flux:button wire:click="previewCertificate({{ $cert->id }})" variant="subtle" size="xs" icon="eye">
                                            Lihat
                                        </flux:button>
                                        <flux:button wire:click="openIssueModal({{ $user->id }}, {{ $path->id }})" variant="subtle" size="xs" icon="pencil-square">
                                            Edit
                                        </flux:button>
                                        <flux:button wire:click="revokeCertificate({{ $cert->id }})" wire:confirm="Yakin ingin mencabut/menghapus sertifikat ini?" variant="danger" size="xs" icon="trash">
                                            Cabut
                                        </flux:button>
                                    @else
                                        <flux:button wire:click="openIssueModal({{ $user->id }}, {{ $path->id }})" variant="primary" size="xs" icon="trophy">
                                            Berikan Sertifikat
                                        </flux:button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-zinc-400 italic">
                                Belum ada data mahasiswa yang terdaftar di kelas dengan filter saat ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Issue / Edit Certificate Modal -->
    <flux:modal wire:model="showIssueModal" class="md:w-[620px]">
        <form wire:submit="saveCertificate" class="space-y-4">
            <div>
                <flux:heading size="lg">
                    {{ $editingCertificateId ? 'Edit Sertifikat Kelulusan' : 'Terbitkan Sertifikat Kelulusan' }}
                </flux:heading>
                <flux:text class="text-xs">
                    Berikan sertifikat resmi kepada mahasiswa yang telah berhasil menyelesaikan alur pembelajaran kelas ini.
                </flux:text>
            </div>

            <flux:field>
                <flux:label>Nomor Seri Sertifikat</flux:label>
                <flux:input wire:model="certificate_number" placeholder="CIT-CERT/2026/WEB/0001" required />
                <flux:error name="certificate_number" />
            </flux:field>

            <flux:field>
                <flux:label>Judul Sertifikat</flux:label>
                <flux:input wire:model="title" placeholder="Sertifikat Kelulusan - Full-Stack Web Development" required />
                <flux:error name="title" />
            </flux:field>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Predikat / Nilai Kelulusan</flux:label>
                    <flux:input wire:model="grade" placeholder="Contoh: Lulus dengan Sangat Baik (A)" />
                    <flux:error name="grade" />
                </flux:field>

                <flux:field>
                    <flux:label>Tanggal Diterbitkan</flux:label>
                    <flux:input type="datetime-local" wire:model="issued_at" required />
                    <flux:error name="issued_at" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Catatan Apresiasi / Keterangan</flux:label>
                <flux:textarea wire:model="notes" rows="3" placeholder="Tuliskan catatan apresiasi kelulusan bagi peserta..." />
                <flux:error name="notes" />
            </flux:field>

            <flux:field>
                <flux:label>Tautan Berkas Unduhan PDF / Cloud Drive (Opsional)</flux:label>
                <flux:input wire:model="certificate_url" placeholder="https://drive.google.com/file/d/..." />
                <flux:error name="certificate_url" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button wire:click="$set('showIssueModal', false)" variant="subtle">
                    Batal
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ $editingCertificateId ? 'Simpan Perubahan' : 'Terbitkan Sertifikat' }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Preview Modal -->
    <flux:modal wire:model="showPreviewModal" class="md:w-[760px] p-0 overflow-hidden">
        @if ($this->activePreviewCertificate)
            @php
                $cert = $this->activePreviewCertificate;
            @endphp
            <div class="p-6 md:p-8 space-y-6">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-200 dark:border-zinc-800">
                    <span class="text-xs font-bold text-yellow-600 dark:text-yellow-400 flex items-center gap-1.5">
                        <flux:icon name="shield-check" class="size-4" />
                        Preview Sertifikat Digital Mahasiswa
                    </span>
                    <flux:button wire:click="$set('showPreviewModal', false)" variant="subtle" size="xs">
                        Tutup
                    </flux:button>
                </div>

                <div class="rounded-2xl border-4 border-yellow-400 bg-gradient-to-br from-amber-50 via-white to-amber-50 dark:from-zinc-900 dark:via-zinc-900 dark:to-zinc-800 p-8 text-center shadow-lg space-y-5">
                    <div class="size-12 rounded-full bg-yellow-400 text-zinc-950 flex items-center justify-center mx-auto">
                        <flux:icon name="academic-cap" class="size-7" />
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-[0.3em] font-extrabold text-yellow-600 dark:text-yellow-400">CIT LEARNING SYSTEM</p>
                        <h2 class="text-2xl font-black text-zinc-900 dark:text-zinc-100 mt-1">SERTIFIKAT KELULUSAN</h2>
                        <p class="text-xs font-mono text-zinc-500 mt-0.5">No. Seri: {{ $cert->certificate_number }}</p>
                    </div>

                    <div class="pt-2">
                        <p class="text-xs text-zinc-500 italic">Diberikan kepada:</p>
                        <h3 class="text-xl font-black text-zinc-900 dark:text-yellow-400 mt-1">{{ $cert->user->name }}</h3>
                        <p class="text-xs text-zinc-600 dark:text-zinc-300">{{ $cert->user->major ?? 'Mahasiswa' }}</p>
                    </div>

                    <div class="pt-2">
                        <p class="text-xs text-zinc-600 dark:text-zinc-400">Telah menyelesaikan program pembelajaran:</p>
                        <p class="text-base font-bold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $cert->learningPath->title }}</p>
                        @if ($cert->grade)
                            <span class="inline-block mt-2 px-2.5 py-0.5 rounded-full text-xs font-bold bg-yellow-400 text-zinc-950">
                                Predikat: {{ $cert->grade }}
                            </span>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 gap-4 pt-6 border-t border-zinc-200 dark:border-zinc-800 text-xs">
                        <div class="text-left">
                            <p class="text-[10px] text-zinc-400">Tanggal Terbit:</p>
                            <p class="font-bold text-zinc-800 dark:text-zinc-200">{{ $cert->issued_at->locale('id')->isoFormat('D MMMM Y') }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] text-zinc-400">Disahkan oleh:</p>
                            <p class="font-bold text-zinc-800 dark:text-zinc-200">{{ $cert->issuer ? $cert->issuer->name : 'CIT Administrator' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
