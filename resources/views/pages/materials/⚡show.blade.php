<?php

use App\Models\LearningPath;
use App\Models\Material;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
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
     * Get rendered HTML from markdown content.
     */
    #[Computed]
    public function renderedContent(): string
    {
        return Str::markdown($this->material->content ?? '', [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
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
            <article class="prose-material max-w-none">
                {!! $this->renderedContent !!}
            </article>

            <style>
                .prose-material {
                    color: #27272a;
                    line-height: 1.85;
                    font-size: 0.95rem;
                }
                .dark .prose-material {
                    color: #e4e4e7;
                }
                .prose-material h1 {
                    font-size: 1.75rem;
                    font-weight: 800;
                    letter-spacing: -0.025em;
                    margin-top: 2rem;
                    margin-bottom: 1rem;
                    padding-bottom: 0.5rem;
                    border-bottom: 2px solid rgba(250, 204, 21, 0.3);
                    color: #09090b;
                }
                .dark .prose-material h1 {
                    color: #fafafa;
                    border-bottom-color: rgba(250, 204, 21, 0.2);
                }
                .prose-material h2 {
                    font-size: 1.35rem;
                    font-weight: 700;
                    letter-spacing: -0.02em;
                    margin-top: 1.85rem;
                    margin-bottom: 0.85rem;
                    padding-bottom: 0.35rem;
                    border-bottom: 1px solid #e4e4e7;
                    color: #09090b;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                .dark .prose-material h2 {
                    color: #fafafa;
                    border-bottom-color: #27272a;
                }
                .prose-material h2::before {
                    content: "";
                    display: inline-block;
                    width: 4px;
                    height: 1.1em;
                    background-color: #facc15;
                    border-radius: 2px;
                }
                .prose-material h3 {
                    font-size: 1.15rem;
                    font-weight: 700;
                    margin-top: 1.5rem;
                    margin-bottom: 0.5rem;
                    color: #18181b;
                }
                .dark .prose-material h3 {
                    color: #f4f4f5;
                }
                .prose-material h4 {
                    font-size: 1rem;
                    font-weight: 600;
                    margin-top: 1.25rem;
                    margin-bottom: 0.35rem;
                    color: #27272a;
                }
                .dark .prose-material h4 {
                    color: #d4d4d8;
                }
                .prose-material p {
                    margin-top: 1rem;
                    margin-bottom: 1rem;
                    line-height: 1.85;
                    color: #3f3f46;
                }
                .dark .prose-material p {
                    color: #d4d4d8;
                }
                .prose-material strong,
                .prose-material b {
                    font-weight: 700;
                    color: #09090b;
                }
                .dark .prose-material strong,
                .dark .prose-material b {
                    color: #ffffff;
                }
                .prose-material em,
                .prose-material i {
                    font-style: italic;
                }
                .prose-material ul {
                    list-style-type: disc;
                    padding-left: 1.6rem;
                    margin-top: 0.85rem;
                    margin-bottom: 0.85rem;
                }
                .prose-material ol {
                    list-style-type: decimal;
                    padding-left: 1.6rem;
                    margin-top: 0.85rem;
                    margin-bottom: 0.85rem;
                }
                .prose-material li {
                    margin-top: 0.45rem;
                    margin-bottom: 0.45rem;
                    line-height: 1.75;
                    color: #3f3f46;
                }
                .dark .prose-material li {
                    color: #d4d4d8;
                }
                .prose-material ul > li::marker {
                    color: #eab308;
                }
                .prose-material ol > li::marker {
                    color: #eab308;
                    font-weight: 700;
                }
                .prose-material blockquote {
                    border-left: 4px solid #facc15;
                    background: linear-gradient(to right, rgba(250, 204, 21, 0.08), transparent);
                    padding: 1rem 1.25rem;
                    border-radius: 0 0.75rem 0.75rem 0;
                    margin: 1.5rem 0;
                    font-style: italic;
                    color: #3f3f46;
                }
                .dark .prose-material blockquote {
                    background: linear-gradient(to right, rgba(250, 204, 21, 0.12), transparent);
                    color: #d4d4d8;
                }
                .prose-material hr {
                    border: 0;
                    height: 1px;
                    background: linear-gradient(to right, transparent, #e4e4e7, transparent);
                    margin: 2.25rem 0;
                }
                .dark .prose-material hr {
                    background: linear-gradient(to right, transparent, #27272a, transparent);
                }
                .prose-material :not(pre) > code {
                    background-color: rgba(250, 204, 21, 0.12);
                    color: #ca8a04;
                    padding: 0.2rem 0.45rem;
                    border-radius: 0.375rem;
                    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                    font-size: 0.85em;
                    font-weight: 600;
                    border: 1px solid rgba(250, 204, 21, 0.25);
                }
                .dark .prose-material :not(pre) > code {
                    background-color: rgba(250, 204, 21, 0.15);
                    color: #facc15;
                    border-color: rgba(250, 204, 21, 0.3);
                }
                .prose-material pre {
                    background-color: #18181b;
                    color: #f4f4f5;
                    padding: 1.25rem 1.5rem;
                    border-radius: 0.875rem;
                    overflow-x: auto;
                    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                    font-size: 0.875rem;
                    line-height: 1.7;
                    margin: 1.5rem 0;
                    border: 1px solid #27272a;
                    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
                }
                .prose-material pre code {
                    background: transparent;
                    padding: 0;
                    border: 0;
                    color: inherit;
                    font-size: inherit;
                    font-weight: normal;
                }
                .prose-material a {
                    color: #ca8a04;
                    text-decoration: underline;
                    text-underline-offset: 3px;
                    font-weight: 600;
                    transition: color 0.15s;
                }
                .dark .prose-material a {
                    color: #facc15;
                }
                .prose-material a:hover {
                    color: #a16207;
                }
                .dark .prose-material a:hover {
                    color: #fde047;
                }
                .prose-material table {
                    width: 100%;
                    border-collapse: separate;
                    border-spacing: 0;
                    margin: 1.5rem 0;
                    border-radius: 0.75rem;
                    overflow: hidden;
                    border: 1px solid #e4e4e7;
                    font-size: 0.875rem;
                }
                .dark .prose-material table {
                    border-color: #27272a;
                }
                .prose-material th {
                    background-color: #f4f4f5;
                    color: #09090b;
                    font-weight: 700;
                    padding: 0.75rem 1rem;
                    text-align: left;
                    border-bottom: 1px solid #e4e4e7;
                }
                .dark .prose-material th {
                    background-color: #18181b;
                    color: #ffffff;
                    border-bottom-color: #27272a;
                }
                .prose-material td {
                    padding: 0.75rem 1rem;
                    border-bottom: 1px solid #f4f4f5;
                    color: #3f3f46;
                }
                .dark .prose-material td {
                    border-bottom-color: #18181b;
                    color: #d4d4d8;
                }
                .prose-material tr:last-child td {
                    border-bottom: 0;
                }
                .prose-material img {
                    border-radius: 0.75rem;
                    max-width: 100%;
                    height: auto;
                    margin-top: 1.5rem;
                    margin-bottom: 1.5rem;
                    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                }
            </style>

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
