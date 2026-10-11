<?php

use App\Models\Certificate;
use App\Models\LearningPath;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Capaian Saya & Kelulusan')] class extends Component {
    public ?int $selectedCertificateId = null;
    public bool $showCertificateModal = false;

    /**
     * Get user enrolled paths with related materials and certificate.
     */
    #[Computed]
    public function enrolledPaths()
    {
        $user = Auth::user();

        return $user->enrolledLearningPaths()
            ->with([
                'materials' => fn ($q) => $q->where('is_published', true),
                'meetings',
                'certificates' => fn ($q) => $q->where('user_id', $user->id)->where('status', 'issued'),
            ])
            ->get();
    }

    /**
     * Get overall metrics.
     */
    #[Computed]
    public function metrics(): array
    {
        $user = Auth::user();
        $enrolledCount = $this->enrolledPaths->count();
        $certificatesCount = Certificate::where('user_id', $user->id)->where('status', 'issued')->count();

        $totalMaterialsCount = 0;
        foreach ($this->enrolledPaths as $path) {
            $totalMaterialsCount += $path->materials->count();
        }

        $completedMaterialsCount = $user->completedMaterials()
            ->whereIn('learning_path_id', $this->enrolledPaths->pluck('id'))
            ->count();

        $overallProgress = $totalMaterialsCount > 0 ? (int) round(($completedMaterialsCount / $totalMaterialsCount) * 100) : 0;

        return [
            'enrolledCount' => $enrolledCount,
            'certificatesCount' => $certificatesCount,
            'completedMaterialsCount' => $completedMaterialsCount,
            'totalMaterialsCount' => $totalMaterialsCount,
            'overallProgress' => $overallProgress,
        ];
    }

    /**
     * Open digital certificate preview modal.
     */
    public function viewCertificate(int $certificateId): void
    {
        $this->selectedCertificateId = $certificateId;
        unset($this->activeCertificate);
        $this->showCertificateModal = true;
    }

    /**
     * Get active certificate for preview.
     */
    #[Computed]
    public function activeCertificate(): ?Certificate
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
                <flux:icon name="trophy" class="size-3.5" />
                <span>Milestone & Sertifikasi</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                Capaian Saya & Kelulusan
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                Pantau progres alur belajar, evaluasi kelulusan kelas, dan akses sertifikat resmi yang telah diterbitkan oleh Admin.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <flux:button href="{{ route('meetings.index') }}" variant="subtle" icon="calendar-days" wire:navigate>
                Alur & Pertemuan
            </flux:button>
            <flux:button href="{{ route('materials.index') }}" variant="subtle" icon="book-open" wire:navigate>
                Materi Belajar
            </flux:button>
        </div>
    </div>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="p-4 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Path Diambil</p>
                <p class="text-2xl font-black text-zinc-900 dark:text-zinc-100 mt-1">{{ $this->metrics['enrolledCount'] }} Path</p>
            </div>
            <span class="p-2.5 rounded-xl bg-yellow-400/10 text-yellow-600 dark:text-yellow-400">
                <flux:icon name="academic-cap" class="size-5" />
            </span>
        </div>

        <div class="p-4 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Sertifikat Kelulusan</p>
                <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ $this->metrics['certificatesCount'] }} Diterbitkan</p>
            </div>
            <span class="p-2.5 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                <flux:icon name="trophy" class="size-5" />
            </span>
        </div>

        <div class="p-4 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Progres Modul Terbaca</p>
                <p class="text-2xl font-black text-zinc-900 dark:text-zinc-100 mt-1">{{ $this->metrics['completedMaterialsCount'] }} / {{ $this->metrics['totalMaterialsCount'] }} ({{ $this->metrics['overallProgress'] }}%)</p>
            </div>
            <span class="p-2.5 rounded-xl bg-amber-500/10 text-amber-500">
                <flux:icon name="check-circle" class="size-5" />
            </span>
        </div>
    </div>

    <!-- Enrolled Paths Milestone Cards -->
    @if ($this->enrolledPaths->isNotEmpty())
        <div class="space-y-6">
            @foreach ($this->enrolledPaths as $path)
                @php
                    $certificate = $path->certificates->first();
                    $isGraduated = $certificate !== null;
                    $totalMats = $path->materials->count();
                    $completedMats = auth()->user()->completedMaterials()->where('learning_path_id', $path->id)->count();
                    $matPercent = $totalMats > 0 ? (int) round(($completedMats / $totalMats) * 100) : 0;
                    $totalMeetings = $path->meetings->count();
                    $pastMeetings = $path->meetings->filter->isPast()->count();
                @endphp

                <div class="rounded-2xl border {{ $isGraduated ? 'border-yellow-400/50 bg-gradient-to-br from-yellow-400/5 via-white to-white dark:from-yellow-400/10 dark:via-zinc-900 dark:to-zinc-900 shadow-sm' : 'border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs' }} p-6 space-y-6">
                    <!-- Path Header & Status -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                        <div class="flex items-center gap-3">
                            <span class="p-3 rounded-2xl {{ $isGraduated ? 'bg-yellow-400 text-zinc-950 font-black shadow-xs' : 'bg-yellow-400/15 text-yellow-600 dark:text-yellow-400' }}">
                                <flux:icon name="{{ $path->icon ?: 'academic-cap' }}" class="size-6" />
                            </span>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">
                                        {{ $path->title }}
                                    </h2>
                                    @if ($isGraduated)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-black bg-yellow-400 text-zinc-950 shadow-xs">
                                            <flux:icon name="trophy" class="size-3" />
                                            LULUS & BERSERTIFIKAT
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                            <flux:icon name="clock" class="size-3" />
                                            Sedang Belajar
                                        </span>
                                    @endif
                                </div>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                    Terdaftar sejak {{ $path->pivot->enrolled_at ? \Carbon\Carbon::parse($path->pivot->enrolled_at)->translatedFormat('d F Y') : '-' }}
                                </p>
                            </div>
                        </div>

                        <!-- Action button -->
                        <div>
                            @if ($isGraduated)
                                <flux:button wire:click="viewCertificate({{ $certificate->id }})" variant="primary" icon="trophy">
                                    Lihat Sertifikat Digital
                                </flux:button>
                            @else
                                <flux:button href="{{ route('meetings.index', ['selectedPathId' => $path->id]) }}" variant="subtle" size="sm" icon-trailing="arrow-right" wire:navigate>
                                    Ikuti Alur Pertemuan
                                </flux:button>
                            @endif
                        </div>
                    </div>

                    <!-- Milestone Progress Bars -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Modul Progress -->
                        <div class="p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-100 dark:border-zinc-800 space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-zinc-700 dark:text-zinc-300 flex items-center gap-1.5">
                                    <flux:icon name="book-open" class="size-3.5 text-yellow-500" />
                                    Modul Pembelajaran
                                </span>
                                <span class="font-extrabold text-zinc-900 dark:text-zinc-100">{{ $completedMats }} / {{ $totalMats }} Modul ({{ $matPercent }}%)</span>
                            </div>
                            <div class="w-full h-2 rounded-full bg-zinc-200 dark:bg-zinc-700 overflow-hidden">
                                <div class="h-full rounded-full bg-yellow-400 transition-all duration-500" style="width: {{ $matPercent }}%"></div>
                            </div>
                            <p class="text-[11px] text-zinc-400">
                                {{ $totalMats - $completedMats > 0 ? 'Tersisa '.($totalMats - $completedMats).' modul belum ditandai selesai.' : 'Semua modul di path ini telah selesai dibaca!' }}
                            </p>
                        </div>

                        <!-- Pertemuan Progress -->
                        <div class="p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-100 dark:border-zinc-800 space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-zinc-700 dark:text-zinc-300 flex items-center gap-1.5">
                                    <flux:icon name="calendar-days" class="size-3.5 text-yellow-500" />
                                    Rangkaian Pertemuan
                                </span>
                                <span class="font-extrabold text-zinc-900 dark:text-zinc-100">{{ $pastMeetings }} / {{ $totalMeetings }} Sesi Selesai</span>
                            </div>
                            <div class="w-full h-2 rounded-full bg-zinc-200 dark:bg-zinc-700 overflow-hidden">
                                @php
                                    $meetPercent = $totalMeetings > 0 ? (int) round(($pastMeetings / $totalMeetings) * 100) : 0;
                                @endphp
                                <div class="h-full rounded-full bg-emerald-500 transition-all duration-500" style="width: {{ $meetPercent }}%"></div>
                            </div>
                            <p class="text-[11px] text-zinc-400">
                                {{ $totalMeetings - $pastMeetings > 0 ? 'Tersisa '.($totalMeetings - $pastMeetings).' pertemuan yang akan datang.' : 'Seluruh rangkaian pertemuan telah terlaksana.' }}
                            </p>
                        </div>
                    </div>

                    <!-- Certificate Box or Graduation Criteria -->
                    @if ($isGraduated)
                        <div class="p-4 rounded-xl bg-yellow-400/10 border border-yellow-400/30 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-yellow-800 dark:text-yellow-300">
                                        Nomor Sertifikat: <span class="font-mono">{{ $certificate->certificate_number }}</span>
                                    </span>
                                    @if ($certificate->grade)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-yellow-400 text-zinc-950">
                                            {{ $certificate->grade }}
                                        </span>
                                    @endif
                                </div>
                                <p class="text-xs text-zinc-600 dark:text-zinc-400">
                                    Diterbitkan pada {{ $certificate->issued_at->locale('id')->isoFormat('D MMMM Y') }}
                                    @if ($certificate->issuer) oleh {{ $certificate->issuer->name }} (CIT Administrator) @endif
                                </p>
                                @if ($certificate->notes)
                                    <p class="text-xs text-zinc-700 dark:text-zinc-300 italic pt-1">
                                        "{{ $certificate->notes }}"
                                    </p>
                                @endif
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <flux:button wire:click="viewCertificate({{ $certificate->id }})" variant="primary" size="sm" icon="eye">
                                    Buka Sertifikat
                                </flux:button>
                                @if ($certificate->certificate_url)
                                    <flux:button href="{{ $certificate->certificate_url }}" target="_blank" variant="subtle" size="sm" icon="arrow-down-tray">
                                        Unduh File
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/40 border border-dashed border-zinc-200 dark:border-zinc-700 text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-start gap-2.5">
                                <flux:icon name="information-circle" class="size-4 text-yellow-500 shrink-0 mt-0.5" />
                                <div>
                                    <p class="font-bold text-zinc-800 dark:text-zinc-200">Kriteria Penerbitan Sertifikat Kelulusan:</p>
                                    <p class="text-zinc-500 dark:text-zinc-400 text-[11px] mt-0.5">
                                        Sertifikat resmi akan diberikan langsung oleh Admin setelah Anda menuntaskan rangkaian modul dan menghadiri pertemuan kelas ini.
                                    </p>
                                </div>
                            </div>
                            <span class="text-[11px] font-semibold text-amber-600 dark:text-amber-400 shrink-0">
                                Menunggu Evaluasi Akhir
                            </span>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <!-- Empty State jika belum ada path yang dipilih -->
        <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-8 sm:p-12 text-center shadow-xs">
            <div class="size-14 mx-auto rounded-2xl bg-yellow-400/10 text-yellow-600 dark:text-yellow-400 flex items-center justify-center mb-4">
                <flux:icon name="academic-cap" class="size-7" />
            </div>
            <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">
                Belum Ada Path Learning yang Diambil
            </h3>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 max-w-md mx-auto mt-2 leading-relaxed">
                Anda belum memilih learning path. Mulailah perjalanan belajar Anda dengan memilih kelas yang tersedia untuk meraih sertifikat kelulusan.
            </p>
            <div class="mt-6">
                <flux:button href="{{ route('learning-paths.index') }}" variant="primary" icon="plus" wire:navigate>
                    Pilih Path Learning Sekarang
                </flux:button>
            </div>
        </div>
    @endif

    <!-- Certificate Preview Modal -->
    <flux:modal wire:model="showCertificateModal" class="md:w-[800px] p-0 overflow-hidden">
        @if ($this->activeCertificate)
            @php
                $cert = $this->activeCertificate;
            @endphp
            <div class="p-6 md:p-8 space-y-6">
                <!-- Action bar on top of modal -->
                <div class="flex items-center justify-between pb-3 border-b border-zinc-200 dark:border-zinc-800 print:hidden">
                    <span class="text-xs font-bold text-yellow-600 dark:text-yellow-400 flex items-center gap-1.5">
                        <flux:icon name="shield-check" class="size-4" />
                        Sertifikat Digital Terverifikasi CIT Learning System
                    </span>

                    <div class="flex items-center gap-2">
                        <flux:button onclick="window.print()" variant="subtle" size="xs" icon="printer">
                            Cetak / Print
                        </flux:button>
                        <flux:button wire:click="$set('showCertificateModal', false)" variant="subtle" size="xs">
                            Tutup
                        </flux:button>
                    </div>
                </div>

                <!-- Printable Certificate Graphic Card -->
                <div class="relative rounded-2xl border-4 border-yellow-400 bg-gradient-to-br from-amber-50 via-white to-amber-50 dark:from-zinc-900 dark:via-zinc-900 dark:to-zinc-800 p-8 sm:p-12 text-center shadow-xl space-y-6">
                    <!-- Corner Ornaments -->
                    <div class="absolute top-3 left-3 size-6 border-t-2 border-l-2 border-yellow-500"></div>
                    <div class="absolute top-3 right-3 size-6 border-t-2 border-r-2 border-yellow-500"></div>
                    <div class="absolute bottom-3 left-3 size-6 border-b-2 border-l-2 border-yellow-500"></div>
                    <div class="absolute bottom-3 right-3 size-6 border-b-2 border-r-2 border-yellow-500"></div>

                    <!-- Certificate Title & Logo -->
                    <div class="space-y-2">
                        <div class="inline-flex items-center justify-center size-14 rounded-full bg-yellow-400 text-zinc-950 shadow-md mx-auto mb-2">
                            <flux:icon name="academic-cap" class="size-8" />
                        </div>
                        <p class="text-xs uppercase tracking-[0.3em] font-extrabold text-yellow-600 dark:text-yellow-400">
                            CIT LEARNING SYSTEM • CERTIFICATE OF COMPLETION
                        </p>
                        <h2 class="text-2xl sm:text-3xl font-black text-zinc-900 dark:text-zinc-100 tracking-tight">
                            SERTIFIKAT KELULUSAN
                        </h2>
                        <p class="text-xs font-mono text-zinc-500 dark:text-zinc-400">
                            No. Seri: {{ $cert->certificate_number }}
                        </p>
                    </div>

                    <!-- Given To -->
                    <div class="space-y-2 pt-2">
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 italic">
                            Diberikan kepada:
                        </p>
                        <h3 class="text-xl sm:text-2xl font-black text-zinc-900 dark:text-yellow-400 underline decoration-yellow-400 underline-offset-8">
                            {{ $cert->user->name }}
                        </h3>
                        <p class="text-xs text-zinc-600 dark:text-zinc-300">
                            {{ $cert->user->major ?? 'Mahasiswa' }} @if ($cert->user->batch_year) • Angkatan {{ $cert->user->batch_year }} @endif
                        </p>
                    </div>

                    <!-- Course Description -->
                    <div class="space-y-1 max-w-lg mx-auto pt-2">
                        <p class="text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">
                            Atas keberhasilan dan komitmennya dalam menyelesaikan seluruh rangkaian pembelajaran dan evaluasi pada program:
                        </p>
                        <p class="text-base sm:text-lg font-bold text-zinc-900 dark:text-zinc-100">
                            {{ $cert->learningPath->title }}
                        </p>
                        @if ($cert->grade)
                            <div class="pt-2">
                                <span class="px-3 py-1 rounded-full text-xs font-black bg-yellow-400 text-zinc-950 shadow-xs inline-block">
                                    Predikat: {{ $cert->grade }}
                                </span>
                            </div>
                        @endif
                    </div>

                    <!-- Signature & Date Section -->
                    <div class="grid grid-cols-2 gap-6 pt-8 border-t border-zinc-200 dark:border-zinc-800 text-xs">
                        <div class="space-y-1 text-left">
                            <p class="text-zinc-400 text-[10px]">Tanggal Terbit:</p>
                            <p class="font-bold text-zinc-800 dark:text-zinc-200">
                                {{ $cert->issued_at->locale('id')->isoFormat('D MMMM Y') }}
                            </p>
                            <p class="text-[10px] text-emerald-600 font-semibold">Status: Resmi & Terverifikasi ✓</p>
                        </div>

                        <div class="space-y-1 text-right">
                            <p class="text-zinc-400 text-[10px]">Disahkan oleh:</p>
                            <p class="font-bold text-zinc-800 dark:text-zinc-200">
                                {{ $cert->issuer ? $cert->issuer->name : 'CIT Academic Board' }}
                            </p>
                            <p class="text-[10px] text-zinc-500">Administrator Program CIT</p>
                        </div>
                    </div>
                </div>

                @if ($cert->certificate_url)
                    <div class="flex justify-center pt-2">
                        <flux:button href="{{ $cert->certificate_url }}" target="_blank" variant="primary" icon="arrow-down-tray">
                            Unduh Berkas Resmi (PDF / Cloud Link)
                        </flux:button>
                    </div>
                @endif
            </div>
        @endif
    </flux:modal>
</div>
