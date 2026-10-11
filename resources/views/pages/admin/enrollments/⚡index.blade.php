<?php

use App\Models\LearningPath;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Data Pendaftar - Admin')] class extends Component {
    #[Url]
    public string $search = '';

    #[Url]
    public ?int $selectedPathId = null;

    #[Url]
    public string $selectionStatus = 'all'; // 'all', 'enrolled', 'not_enrolled'

    /**
     * Get learning paths for filter.
     */
    #[Computed]
    public function learningPaths()
    {
        return LearningPath::orderBy('title')->get();
    }

    /**
     * Get students list.
     */
    #[Computed]
    public function students()
    {
        $query = User::where('role', 'user')
            ->with(['enrollments.learningPath', 'completedMaterials']);

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%')
                    ->orWhere('major', 'like', '%'.$this->search.'%')
                    ->orWhere('batch_year', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->selectedPathId) {
            $query->whereHas('enrollments', function ($q) {
                $q->where('learning_path_id', $this->selectedPathId);
            });
        }

        if ($this->selectionStatus === 'enrolled') {
            $query->has('enrollments');
        } elseif ($this->selectionStatus === 'not_enrolled') {
            $query->doesntHave('enrollments');
        }

        return $query->latest()->get();
    }

    /**
     * Summary stats.
     */
    #[Computed]
    public function stats(): array
    {
        $totalStudents = User::where('role', 'user')->count();
        $enrolledCount = User::where('role', 'user')->has('enrollments')->count();
        $notEnrolledCount = $totalStudents - $enrolledCount;

        return [
            'total' => $totalStudents,
            'enrolled' => $enrolledCount,
            'notEnrolled' => $notEnrolledCount,
        ];
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
                Data Mahasiswa & Pilihan Path
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                Pantau seluruh mahasiswa yang telah memilih path learning maupun yang belum memilih.
            </p>
        </div>
    </div>

    <!-- Stats Bar -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs">
            <p class="text-xs text-zinc-500 font-medium">Total Mahasiswa Terdaftar</p>
            <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 mt-1">{{ $this->stats['total'] }}</p>
        </div>

        <div class="p-4 rounded-xl border border-emerald-500/30 bg-emerald-500/5 dark:bg-emerald-500/10 shadow-xs">
            <p class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">Sudah Memilih Path</p>
            <p class="text-2xl font-bold text-emerald-700 dark:text-emerald-300 mt-1">{{ $this->stats['enrolled'] }}</p>
        </div>

        <div class="p-4 rounded-xl border border-amber-500/30 bg-amber-500/5 dark:bg-amber-500/10 shadow-xs">
            <p class="text-xs text-amber-600 dark:text-amber-400 font-medium">Belum Memilih Path</p>
            <p class="text-2xl font-bold text-amber-700 dark:text-amber-300 mt-1">{{ $this->stats['notEnrolled'] }}</p>
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="flex flex-col sm:flex-row gap-4 items-center justify-between">
        <div class="w-full sm:w-72">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Cari nama, email, jurusan..."
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
                <flux:select wire:model.live="selectionStatus" size="sm">
                    <option value="all">Semua Status</option>
                    <option value="enrolled">Sudah Memilih</option>
                    <option value="not_enrolled">Belum Memilih</option>
                </flux:select>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-zinc-50 dark:bg-zinc-800/60 text-zinc-600 dark:text-zinc-400 uppercase font-semibold border-b border-zinc-200 dark:border-zinc-800">
                    <tr>
                        <th class="px-5 py-3.5">Mahasiswa</th>
                        <th class="px-5 py-3.5">Jurusan & Angkatan</th>
                        <th class="px-5 py-3.5">Path Learning Terpilih</th>
                        <th class="px-5 py-3.5">Waktu Memilih</th>
                        <th class="px-5 py-3.5">Progress Baca</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->students as $student)
                        @php
                            $enrollments = $student->enrollments;
                            $latestEnrollment = $enrollments->sortByDesc('enrolled_at')->first();
                            $completedCount = $student->completedMaterials->count();
                        @endphp
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-colors">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="size-8 rounded-full bg-yellow-400/20 text-yellow-700 dark:text-yellow-300 font-bold flex items-center justify-center text-xs">
                                        {{ $student->initials() }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-zinc-900 dark:text-zinc-100 text-sm">{{ $student->name }}</p>
                                        <p class="text-zinc-400 text-[11px]">{{ $student->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <p class="text-zinc-800 dark:text-zinc-200 font-medium">{{ $student->major ?? '-' }}</p>
                                <p class="text-zinc-400 text-[11px]">Angkatan: {{ $student->batch_year ?? '-' }}</p>
                            </td>
                            <td class="px-5 py-4">
                                @if ($enrollments->isNotEmpty())
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($enrollments as $enr)
                                            @if ($enr->learningPath)
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-yellow-400/20 text-yellow-800 dark:text-yellow-300 border border-yellow-400/30">
                                                    <flux:icon name="{{ $enr->learningPath->icon ?: 'academic-cap' }}" class="size-3" />
                                                    {{ $enr->learningPath->title }}
                                                </span>
                                            @endif
                                        @endforeach
                                    </div>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                        Belum Memilih
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-zinc-500">
                                @if ($latestEnrollment)
                                    {{ $latestEnrollment->enrolled_at->translatedFormat('d M Y, H:i') }} WIB
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $completedCount }}</span>
                                <span class="text-zinc-400">Modul Selesai</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-zinc-400 italic">
                                Tidak ada data mahasiswa yang cocok dengan filter pencarian.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
