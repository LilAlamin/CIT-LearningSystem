<?php

use App\Models\LearningPath;
use App\Models\Material;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Baca Materi')] class extends Component {
    public LearningPath $path;
    public Material $material;

    /**
     * Mount the component with route model binding.
     */
    public function mount(LearningPath $path, Material $material): void
    {
        $this->path = $path;
        $this->material = $material->loadMissing('pathMeeting');
    }

    /**
     * Get all published materials for this path to build chapter list.
     */
    #[Computed]
    public function pathMaterials()
    {
        return $this->path->materials()
            ->where('is_published', true)
            ->orderBy('order')
            ->get();
    }

    /**
     * Check if the current user completed this material.
     */
    #[Computed]
    public function isCompleted(): bool
    {
        return $this->material->isCompletedBy(Auth::user());
    }

    /**
     * Get IDs of completed materials for current user in this path.
     */
    #[Computed]
    public function completedIds(): array
    {
        return Auth::user()->completedMaterials()
            ->where('learning_path_id', $this->path->id)
            ->pluck('materials.id')
            ->toArray();
    }

    /**
     * Toggle completion status.
     */
    public function toggleComplete(): void
    {
        $user = Auth::user();

        if ($this->isCompleted) {
            $user->completedMaterials()->detach($this->material->id);
            Flux::toast(variant: 'info', text: __('Status materi diubah menjadi belum selesai.'));
        } else {
            $user->completedMaterials()->syncWithoutDetaching([
                $this->material->id => ['completed_at' => now()],
            ]);
            Flux::toast(variant: 'success', text: __('Bagus! Modul ini telah ditandai selesai.'));
        }

        unset($this->isCompleted, $this->completedIds);
    }

    /**
     * Get the previous material in this path.
     */
    #[Computed]
    public function previousMaterial(): ?Material
    {
        return Material::where('learning_path_id', $this->path->id)
            ->where('is_published', true)
            ->where('order', '<', $this->material->order)
            ->orderByDesc('order')
            ->first();
    }

    /**
     * Get the next material in this path.
     */
    #[Computed]
    public function nextMaterial(): ?Material
    {
        return Material::where('learning_path_id', $this->path->id)
            ->where('is_published', true)
            ->where('order', '>', $this->material->order)
            ->orderBy('order')
            ->first();
    }
};
?>

<div class="flex flex-col gap-6">
    <!-- Breadcrumbs Navigation -->
    <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
        <a href="{{ route('materials.index') }}" class="hover:text-zinc-800 dark:hover:text-zinc-200 transition-colors" wire:navigate>
            Materi
        </a>
        <span>/</span>
        <a href="{{ route('materials.index', ['selectedPathId' => $path->id]) }}" class="hover:text-zinc-800 dark:hover:text-zinc-200 font-medium transition-colors" wire:navigate>
            {{ $path->title }}
        </a>
        <span>/</span>
        <span class="text-zinc-800 dark:text-zinc-200 font-bold truncate max-w-xs">
            Modul {{ $material->order }}: {{ $material->title }}
        </span>
    </div>

    <!-- Layout: Chapter Sidebar + Article Reader -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <!-- Sidebar Navigation (4 cols) -->
        <div class="lg:col-span-4 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 shadow-xs sticky top-4">
            <div class="pb-3 border-b border-zinc-100 dark:border-zinc-800">
                <span class="text-[11px] font-semibold text-yellow-600 dark:text-yellow-400 uppercase tracking-wider">
                    Daftar Modul Belajar
                </span>
                <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100 mt-0.5">
                    {{ $path->title }}
                </h3>
            </div>

            <!-- Chapters list -->
            <div class="space-y-1.5 mt-3 max-h-[70vh] overflow-y-auto pr-1">
                @foreach ($this->pathMaterials as $mat)
                    @php
                        $isActive = $mat->id === $material->id;
                        $isMatDone = in_array($mat->id, $this->completedIds, true);
                    @endphp

                    <a
                        href="{{ route('materials.show', [$path->slug, $mat->slug]) }}"
                        class="flex items-center gap-3 p-2.5 rounded-xl text-xs transition-all {{ $isActive ? 'bg-yellow-400/10 text-yellow-700 dark:text-yellow-300 font-bold border border-yellow-400/30' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-800/60' }}"
                        wire:navigate
                    >
                        <span class="size-5 rounded-full {{ $isMatDone ? 'bg-emerald-500 text-white' : ($isActive ? 'bg-yellow-400 text-zinc-950' : 'bg-zinc-200 dark:bg-zinc-800 text-zinc-500') }} text-[10px] font-bold flex items-center justify-center shrink-0">
                            @if ($isMatDone)
                                <flux:icon name="check" class="size-3 stroke-[3]" />
                            @else
                                {{ $mat->order }}
                            @endif
                        </span>
                        <div class="flex-1 truncate">
                            <p class="truncate">{{ $mat->title }}</p>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-4 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button href="{{ route('materials.index') }}" variant="subtle" size="xs" icon="arrow-left" class="w-full" wire:navigate>
                    Kembali ke Semua Materi
                </flux:button>
            </div>
        </div>

        <!-- Reader Main Area (8 cols) -->
        <div class="lg:col-span-8 flex flex-col gap-6 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-6 md:p-8 shadow-xs">
            <!-- Header of Chapter -->
            <div class="space-y-3 pb-6 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-yellow-400/20 text-yellow-700 dark:text-yellow-300">
                            Modul {{ $material->order }}
                        </span>
                        <span class="text-xs text-zinc-400 flex items-center gap-1">
                            <flux:icon name="clock" class="size-3.5" />
                            {{ $material->read_time_minutes }} Menit Baca
                        </span>
                    </div>

                    <!-- Toggle Complete Button -->
                    <flux:button
                        wire:click="toggleComplete"
                        variant="{{ $this->isCompleted ? 'subtle' : 'primary' }}"
                        size="sm"
                        icon="{{ $this->isCompleted ? 'check-circle' : 'check' }}"
                    >
                        {{ $this->isCompleted ? 'Selesai Dibaca ✓' : 'Tandai Selesai' }}
                    </flux:button>
                </div>

                <h1 class="text-2xl md:text-3xl font-extrabold text-zinc-900 dark:text-zinc-100 tracking-tight leading-tight">
                    {{ $material->title }}
                </h1>

                @if ($material->summary)
                    <p class="text-sm text-zinc-500 dark:text-zinc-400 italic">
                        {{ $material->summary }}
                    </p>
                @endif
            </div>

            <!-- Pertemuan Source Callout -->
            @if ($material->pathMeeting)
                <div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-start sm:items-center gap-3 text-xs">
                        <span class="p-2 rounded-lg bg-yellow-400 text-zinc-950 font-bold shrink-0 text-xs">
                            P-{{ $material->pathMeeting->meeting_number }}
                        </span>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-bold text-zinc-900 dark:text-zinc-100">
                                    Disampaikan pada Pertemuan {{ $material->pathMeeting->meeting_number }}: {{ $material->pathMeeting->title }}
                                </span>
                                @if ($material->pathMeeting->isPast())
                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">
                                        Sudah Terlaksana
                                    </span>
                                @else
                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-600 dark:text-amber-400">
                                        Pertemuan Mendatang
                                    </span>
                                @endif
                            </div>
                            <p class="text-zinc-500 dark:text-zinc-400 text-[11px] mt-0.5">
                                Jadwal: {{ $material->pathMeeting->meeting_date->locale('id')->isoFormat('dddd, D MMMM Y • HH:mm') }} WIB
                                @if ($material->pathMeeting->location)
                                    • Lokasi: {{ $material->pathMeeting->location }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <flux:button href="{{ route('meetings.index', ['selectedPathId' => $path->id]) }}" variant="subtle" size="xs" icon-trailing="arrow-right" wire:navigate>
                        Lihat Alur Pertemuan
                    </flux:button>
                </div>
            @endif

            <!-- External Resource Link if available -->
            @if ($material->resource_url)
                <div class="p-4 rounded-xl bg-yellow-400/5 border border-yellow-400/20 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 text-xs">
                        <flux:icon name="link" class="size-4 text-yellow-500 shrink-0" />
                        <div>
                            <p class="font-bold text-zinc-900 dark:text-zinc-100">Referensi / Sumber Tambahan</p>
                            <p class="text-zinc-500 dark:text-zinc-400 text-[11px] truncate max-w-sm">{{ $material->resource_url }}</p>
                        </div>
                    </div>
                    <flux:button href="{{ $material->resource_url }}" target="_blank" variant="subtle" size="xs" icon="arrow-top-right-on-square">
                        Buka Tautan
                    </flux:button>
                </div>
            @endif

            <!-- Content Area (Markdown styled article) -->
            <article class="prose dark:prose-invert max-w-none text-zinc-800 dark:text-zinc-200 text-sm leading-relaxed space-y-4">
                {!! nl2br(e($material->content)) !!}
            </article>

            <!-- Bottom Chapter Navigation -->
            <div class="mt-8 pt-6 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-between gap-4">
                @if ($this->previousMaterial)
                    <flux:button
                        href="{{ route('materials.show', [$path->slug, $this->previousMaterial->slug]) }}"
                        variant="subtle"
                        size="sm"
                        icon="arrow-left"
                        wire:navigate
                    >
                        <span class="truncate max-w-[120px] sm:max-w-xs">{{ $this->previousMaterial->title }}</span>
                    </flux:button>
                @else
                    <div></div>
                @endif

                @if ($this->nextMaterial)
                    <flux:button
                        href="{{ route('materials.show', [$path->slug, $this->nextMaterial->slug]) }}"
                        variant="primary"
                        size="sm"
                        icon-trailing="arrow-right"
                        wire:navigate
                    >
                        <span class="truncate max-w-[120px] sm:max-w-xs">{{ $this->nextMaterial->title }}</span>
                    </flux:button>
                @else
                    <flux:button href="{{ route('materials.index') }}" variant="subtle" size="sm" wire:navigate>
                        Selesai Semua Modul
                    </flux:button>
                @endif
            </div>
        </div>
    </div>
</div>
