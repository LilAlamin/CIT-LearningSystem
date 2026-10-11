<?php

use App\Models\LearningPath;
use App\Models\Material;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Materi Pembelajaran')] class extends Component {
    #[Url]
    public string $search = '';

    #[Url]
    public ?int $selectedPathId = null;

    #[Url]
    public string $statusFilter = 'all'; // 'all', 'completed', 'uncompleted'

    /**
     * Get list of all active paths for filter tabs.
     */
    #[Computed]
    public function learningPaths()
    {
        return LearningPath::where('is_active', true)->withCount('materials')->get();
    }

    /**
     * Get filtered materials.
     */
    #[Computed]
    public function materials()
    {
        $user = Auth::user();
        $completedIds = $user->completedMaterials()->pluck('materials.id')->toArray();

        $query = Material::with(['learningPath', 'pathMeeting'])
            ->where('is_published', true);

        if ($this->selectedPathId) {
            $query->where('learning_path_id', $this->selectedPathId);
        }

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%'.$this->search.'%')
                    ->orWhere('summary', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->statusFilter === 'completed') {
            $query->whereIn('id', $completedIds);
        } elseif ($this->statusFilter === 'uncompleted') {
            $query->whereNotIn('id', $completedIds);
        }

        return $query->orderBy('learning_path_id')->orderBy('order')->get();
    }

    /**
     * Set active path filter.
     */
    public function setPathFilter(?int $pathId): void
    {
        $this->selectedPathId = $pathId;
    }
};
?>

<div class="flex flex-col gap-6">
    <!-- Header Page -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
        <div>
            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-400/10 text-yellow-600 dark:text-yellow-400 mb-2 border border-yellow-400/20">
                <flux:icon name="book-open" class="size-3.5" />
                <span>Akses Belajar Terbuka Kapan Saja</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                Materi Pembelajaran
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                Eksplorasi seluruh silabus dan modul materi per learning path yang dapat Anda baca dan pelajari kapan saja.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <flux:button href="{{ route('learning-paths.index') }}" variant="subtle" icon="academic-cap" wire:navigate>
                Pilih / Cek Path Anda
            </flux:button>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="flex flex-col md:flex-row gap-4 justify-between items-start md:items-center">
        <!-- Path Tabs -->
        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                wire:click="setPathFilter(null)"
                class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all cursor-pointer {{ $selectedPathId === null ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900 shadow-xs' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700' }}"
            >
                Semua Path
            </button>

            @foreach ($this->learningPaths as $path)
                <button
                    type="button"
                    wire:click="setPathFilter({{ $path->id }})"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all cursor-pointer {{ $selectedPathId === $path->id ? 'bg-yellow-400 text-zinc-950 font-bold shadow-xs' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700' }}"
                >
                    {{ $path->title }} ({{ $path->materials_count }})
                </button>
            @endforeach
        </div>

        <!-- Search & Status Filter -->
        <div class="flex items-center gap-3 w-full md:w-auto">
            <div class="w-full md:w-64">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari materi pembelajaran..."
                    icon="magnifying-glass"
                    size="sm"
                    clearable
                />
            </div>
            <div class="w-40 shrink-0">
                <flux:select wire:model.live="statusFilter" size="sm">
                    <option value="all">Semua Status</option>
                    <option value="completed">Selesai Dibaca</option>
                    <option value="uncompleted">Belum Selesai</option>
                </flux:select>
            </div>
        </div>
    </div>

    <!-- Materials Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @php
            $userCompletedIds = auth()->user()->completedMaterials()->pluck('materials.id')->toArray();
        @endphp

        @forelse ($this->materials as $material)
            @php
                $isCompleted = in_array($material->id, $userCompletedIds, true);
            @endphp

            <div class="flex flex-col justify-between rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 hover:border-zinc-300 dark:hover:border-zinc-700 transition-all shadow-xs">
                <div class="space-y-3">
                    <!-- Top meta -->
                    <div class="flex items-center justify-between gap-2">
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400">
                            {{ $material->learningPath->title }}
                        </span>

                        @if ($isCompleted)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                <flux:icon name="check-circle" class="size-3" />
                                Selesai
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-zinc-100 dark:bg-zinc-800/80 text-zinc-400">
                                Belum Selesai
                            </span>
                        @endif
                    </div>

                    <!-- Title & Summary -->
                    <div>
                        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-yellow-600 dark:text-yellow-400 mb-1">
                            <span>Modul {{ $material->order }}</span>
                            <span>&bull;</span>
                            <span>{{ $material->read_time_minutes }} Menit Baca</span>
                            @if ($material->pathMeeting)
                                <span>&bull;</span>
                                <span class="text-zinc-600 dark:text-zinc-300 font-medium">Pertemuan {{ $material->pathMeeting->meeting_number }}</span>
                            @endif
                        </div>
                        <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100 line-clamp-2">
                            {{ $material->title }}
                        </h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-2 line-clamp-3 leading-relaxed">
                            {{ $material->summary ?? Str::limit(strip_tags($material->content), 120) }}
                        </p>
                    </div>
                </div>

                <!-- Footer Action -->
                <div class="mt-5 pt-4 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-between">
                    <span class="text-[11px] text-zinc-400">
                        {{ $material->resource_url ? 'Ada Lampiran Eksternal' : 'Modul Teks' }}
                    </span>

                    <flux:button
                        href="{{ route('materials.show', [$material->learningPath->slug, $material->slug]) }}"
                        variant="{{ $isCompleted ? 'subtle' : 'primary' }}"
                        size="sm"
                        icon-trailing="arrow-right"
                        wire:navigate
                    >
                        {{ $isCompleted ? 'Baca Ulang' : 'Baca Materi' }}
                    </flux:button>
                </div>
            </div>
        @empty
            <div class="col-span-full py-12 text-center rounded-2xl border border-dashed border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/30">
                <flux:icon name="document-text" class="size-10 mx-auto text-zinc-400 mb-2" />
                <h3 class="text-sm font-bold text-zinc-800 dark:text-zinc-200">Tidak ada materi yang ditemukan</h3>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                    Coba sesuaikan kata kunci pencarian atau filter path di atas.
                </p>
            </div>
        @endforelse
    </div>
</div>
