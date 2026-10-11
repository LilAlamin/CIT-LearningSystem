<?php

use App\Models\LearningPath;
use App\Models\PathMeeting;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Alur & Rangkaian Pertemuan')] class extends Component {
    #[Url]
    public ?int $selectedPathId = null;

    public ?int $activeMeetingModalId = null;
    public bool $showMeetingModal = false;

    /**
     * Mount component.
     */
    public function mount(): void
    {
        $user = Auth::user();
        $enrolledIds = $user ? $user->enrollments()->pluck('learning_path_id')->toArray() : [];

        if (! $this->selectedPathId || (! in_array($this->selectedPathId, $enrolledIds) && ! $user?->isAdmin())) {
            $this->selectedPathId = ! empty($enrolledIds) ? $enrolledIds[0] : null;
        }
    }

    /**
     * Get learning paths list.
     * Shows ONLY the paths chosen/enrolled by the user.
     * (If admin has not enrolled, shows all active paths for admin preview).
     */
    #[Computed]
    public function learningPaths()
    {
        $user = Auth::user();
        $enrolledIds = $user ? $user->enrollments()->pluck('learning_path_id')->toArray() : [];

        if (empty($enrolledIds) && $user?->isAdmin()) {
            return LearningPath::withCount('meetings')
                ->where('is_active', true)
                ->get();
        }

        return LearningPath::withCount('meetings')
            ->whereIn('id', $enrolledIds)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Get the active learning path.
     */
    #[Computed]
    public function activePath(): ?LearningPath
    {
        if ($this->selectedPathId && $this->learningPaths->contains('id', $this->selectedPathId)) {
            return LearningPath::with(['meetings.materials'])->find($this->selectedPathId);
        }

        $first = $this->learningPaths->first();

        return $first ? LearningPath::with(['meetings.materials'])->find($first->id) : null;
    }

    /**
     * Get user enrolled path IDs.
     */
    #[Computed]
    public function enrolledPathIds(): array
    {
        return Auth::user()->enrollments()->pluck('learning_path_id')->toArray();
    }

    /**
     * Open meeting details modal.
     */
    public function openMeetingModal(int $meetingId): void
    {
        $this->activeMeetingModalId = $meetingId;
        $this->showMeetingModal = true;
    }
};
?>

<div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
        <div>
            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-400/10 text-yellow-600 dark:text-yellow-400 mb-2 border border-yellow-400/20">
                <flux:icon name="presentation-chart-line" class="size-3.5" />
                <span>Alur Belajar & Sesi Pertemuan</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                Alur & Rangkaian Pertemuan
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                Ikuti alur sesi pertemuan per learning path lengkap dengan detail jadwal, notes persiapan, dan rangkuman materi pas acara.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <flux:button href="{{ route('materials.index', ['selectedPathId' => $selectedPathId]) }}" variant="subtle" icon="book-open" wire:navigate>
                Lihat Modul Teks
            </flux:button>
        </div>
    </div>
    @if ($this->learningPaths->isNotEmpty())
    <!-- Path Selector Tabs -->
    <div class="flex flex-wrap items-center gap-2">
        @foreach ($this->learningPaths as $path)
            @php
                $isSelected = $this->selectedPathId === $path->id;
                $isEnrolled = in_array($path->id, $this->enrolledPathIds, true);
            @endphp
            <button
                type="button"
                wire:click="$set('selectedPathId', {{ $path->id }})"
                class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold transition-all cursor-pointer {{ $isSelected ? 'bg-yellow-400 text-zinc-950 font-bold shadow-xs' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700' }}"
            >
                <flux:icon name="{{ $path->icon ?: 'academic-cap' }}" class="size-4" />
                <span>{{ $path->title }}</span>
                @if ($isEnrolled)
                    <span class="size-1.5 rounded-full bg-emerald-500" title="Anda terdaftar di path ini"></span>
                @endif
            </button>
        @endforeach
    </div>

    @if ($this->activePath)
        @php
            $meetings = $this->activePath->meetings;
            $totalMeetings = $meetings->count();
            $completedMeetings = $meetings->filter(fn($m) => $m->isPast())->count();
            $upcomingMeetings = $totalMeetings - $completedMeetings;
            $isEnrolledInActive = in_array($this->activePath->id, $this->enrolledPathIds, true);
        @endphp

        <!-- Path Header Card -->
        <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-6 shadow-xs">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-yellow-600 dark:text-yellow-400 uppercase tracking-wider">
                            Alur Pembelajaran
                        </span>
                        @if ($isEnrolledInActive)
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                ✓ Path Pilihan Anda
                            </span>
                        @endif
                    </div>
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">
                        {{ $this->activePath->title }}
                    </h2>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 max-w-2xl leading-relaxed">
                        {{ $this->activePath->description }}
                    </p>
                </div>

                <div class="flex items-center gap-4 bg-zinc-50 dark:bg-zinc-800/60 p-4 rounded-xl border border-zinc-200/60 dark:border-zinc-700/60 text-xs">
                    <div>
                        <p class="text-zinc-500 dark:text-zinc-400">Total Sesi</p>
                        <p class="text-base font-bold text-zinc-900 dark:text-zinc-100">{{ $totalMeetings }} Pertemuan</p>
                    </div>
                    <div class="w-px h-8 bg-zinc-200 dark:bg-zinc-700"></div>
                    <div>
                        <p class="text-zinc-500 dark:text-zinc-400">Telah Selesai</p>
                        <p class="text-base font-bold text-emerald-600 dark:text-emerald-400">{{ $completedMeetings }} Sesi</p>
                    </div>
                    <div class="w-px h-8 bg-zinc-200 dark:bg-zinc-700"></div>
                    <div>
                        <p class="text-zinc-500 dark:text-zinc-400">Mendatang</p>
                        <p class="text-base font-bold text-yellow-600 dark:text-yellow-400">{{ $upcomingMeetings }} Sesi</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Meeting Timeline -->
        <div class="relative space-y-6 before:absolute before:inset-0 before:left-5 sm:before:left-7 before:w-0.5 before:bg-zinc-200 dark:before:bg-zinc-800">
            @forelse ($meetings as $meeting)
                @php
                    $isPast = $meeting->isPast();
                    $isNext = ! $isPast && ($meetings->where('is_completed', false)->sortBy('meeting_date')->first()?->id === $meeting->id);
                @endphp

                <div class="relative flex items-start gap-4 sm:gap-6">
                    <!-- Timeline Node Icon -->
                    <div class="relative z-10 flex size-10 sm:size-14 items-center justify-center rounded-2xl shrink-0 font-bold text-sm shadow-xs transition-all {{ $isPast ? 'bg-emerald-500 text-white' : ($isNext ? 'bg-yellow-400 text-zinc-950 ring-4 ring-yellow-400/20' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-500 border border-zinc-200 dark:border-zinc-700') }}">
                        @if ($isPast)
                            <flux:icon name="check" class="size-5 stroke-[3]" />
                        @else
                            {{ $meeting->meeting_number }}
                        @endif
                    </div>

                    <!-- Meeting Card -->
                    <div class="flex-1 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 sm:p-6 shadow-xs space-y-4 hover:border-zinc-300 dark:hover:border-zinc-700 transition-all">
                        <!-- Top Meta Header -->
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-yellow-400/20 text-yellow-700 dark:text-yellow-300">
                                    Pertemuan {{ $meeting->meeting_number }}
                                </span>

                                @if ($isPast)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                        <flux:icon name="check-circle" class="size-3.5" />
                                        Event Selesai
                                    </span>
                                @elseif ($isNext)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-yellow-400 text-zinc-950 animate-pulse">
                                        <flux:icon name="clock" class="size-3.5" />
                                        Pertemuan Selanjutnya!
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-500">
                                        Sesi Mendatang
                                    </span>
                                @endif
                            </div>

                            <div class="text-xs text-zinc-500 dark:text-zinc-400 font-medium flex items-center gap-2">
                                <span class="flex items-center gap-1">
                                    <flux:icon name="calendar" class="size-3.5 text-yellow-500" />
                                    {{ $meeting->meeting_date->translatedFormat('l, d F Y, H:i') }} WIB
                                </span>
                                @if ($meeting->end_time)
                                    <span>- {{ $meeting->end_time }}</span>
                                @endif
                            </div>
                        </div>

                        <!-- Title & Detail Acara -->
                        <div>
                            <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">
                                {{ $meeting->title }}
                            </h3>
                            @if ($meeting->description)
                                <p class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 mt-2 leading-relaxed">
                                    {{ $meeting->description }}
                                </p>
                            @endif
                        </div>

                        <!-- Location & Meeting Link -->
                        <div class="flex flex-wrap items-center justify-between gap-3 p-3 rounded-xl bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200/60 dark:border-zinc-700/60 text-xs">
                            <div class="flex items-center gap-2 text-zinc-600 dark:text-zinc-300">
                                <flux:icon name="map-pin" class="size-4 text-yellow-500 shrink-0" />
                                <span>Lokasi: <strong class="text-zinc-900 dark:text-zinc-100">{{ $meeting->location ?? 'Online Meeting' }}</strong></span>
                            </div>

                            @if ($meeting->meeting_url && ! $isPast)
                                <flux:button href="{{ $meeting->meeting_url }}" target="_blank" variant="primary" size="xs" icon="video-camera">
                                    Masuk Link Pertemuan
                                </flux:button>
                            @endif
                        </div>

                        <!-- Notes / Catatan Persiapan -->
                        @if ($meeting->notes)
                            <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-xs">
                                <div class="flex items-center gap-1.5 font-bold text-amber-700 dark:text-amber-300 mb-1">
                                    <flux:icon name="clipboard-document-list" class="size-4" />
                                    <span>Notes & Persiapan Peserta:</span>
                                </div>
                                <p class="text-zinc-700 dark:text-zinc-300 leading-relaxed pl-5">
                                    {{ $meeting->notes }}
                                </p>
                            </div>
                        @endif

                        <!-- Rangkuman Materi Pas Acara (Event Recap - Shown if event is past) -->
                        @if ($isPast)
                            <div class="p-4 rounded-xl bg-yellow-400/5 dark:bg-yellow-400/10 border border-yellow-400/30 text-xs space-y-3">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-1.5 font-bold text-yellow-700 dark:text-yellow-300">
                                        <flux:icon name="sparkles" class="size-4" />
                                        <span>Rangkuman Materi Pas Acara (Review Materi)</span>
                                    </div>
                                    <span class="text-[10px] text-zinc-400 italic">Dapat diakses kembali kapan saja</span>
                                </div>

                                @if ($meeting->recap_summary)
                                    <div class="prose dark:prose-invert max-w-none text-zinc-700 dark:text-zinc-300 text-xs leading-relaxed">
                                        {!! nl2br(e($meeting->recap_summary)) !!}
                                    </div>
                                @else
                                    <p class="text-zinc-400 italic">Rangkuman materi pas acara sedang diproses oleh instruktur.</p>
                                @endif

                                <!-- Slide & Recording buttons -->
                                <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-yellow-400/20">
                                    @if ($meeting->recap_slides_url)
                                        <flux:button href="{{ $meeting->recap_slides_url }}" target="_blank" variant="subtle" size="xs" icon="document-text">
                                            Buka Slide Materi Tayang
                                        </flux:button>
                                    @endif

                                    @if ($meeting->recap_recording_url)
                                        <flux:button href="{{ $meeting->recap_recording_url }}" target="_blank" variant="subtle" size="xs" icon="play-circle">
                                            Tonton Rekaman Acara
                                        </flux:button>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="p-3 rounded-xl bg-zinc-50 dark:bg-zinc-800/30 border border-dashed border-zinc-200 dark:border-zinc-800 text-xs text-zinc-400 flex items-center gap-2">
                                <flux:icon name="information-circle" class="size-4 text-zinc-400" />
                                <span>Rangkuman materi dan slide presentasi akan otomatis ditampilkan di sini setelah pertemuan selesai.</span>
                            </div>
                        @endif

                        <!-- Modul Materi Terkait Pertemuan Ini -->
                        @if ($meeting->materials->count() > 0)
                            <div class="pt-2 border-t border-zinc-100 dark:border-zinc-800 space-y-2">
                                <p class="text-xs font-bold text-zinc-800 dark:text-zinc-200 flex items-center gap-1.5">
                                    <flux:icon name="book-open" class="size-3.5 text-yellow-500" />
                                    <span>Modul Materi Terkait Pertemuan Ini:</span>
                                </p>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    @foreach ($meeting->materials as $mat)
                                        @php
                                            $isCompleted = auth()->user()->completedMaterials()->where('materials.id', $mat->id)->exists();
                                        @endphp
                                        <a
                                            href="{{ route('materials.show', [$this->activePath->slug, $mat->slug]) }}"
                                            class="flex items-center justify-between p-2.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/60 dark:bg-zinc-800/50 hover:border-yellow-400/50 transition-all text-xs"
                                            wire:navigate
                                        >
                                            <div class="truncate mr-2">
                                                <p class="font-bold text-zinc-900 dark:text-zinc-100 truncate">Modul {{ $mat->order }}: {{ $mat->title }}</p>
                                                <p class="text-[10px] text-zinc-400">{{ $mat->read_time_minutes }} Menit Baca</p>
                                            </div>
                                            <span class="shrink-0 text-[10px] font-semibold px-2 py-0.5 rounded-full {{ $isCompleted ? 'bg-emerald-500/10 text-emerald-600' : 'bg-yellow-400/10 text-yellow-700 dark:text-yellow-300' }}">
                                                {{ $isCompleted ? 'Selesai ✓' : 'Baca &rarr;' }}
                                            </span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="py-12 text-center rounded-2xl border border-dashed border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900/30">
                    <flux:icon name="calendar" class="size-10 mx-auto text-zinc-400 mb-2" />
                    <h3 class="text-sm font-bold text-zinc-800 dark:text-zinc-200">Belum ada alur pertemuan</h3>
                    <p class="text-xs text-zinc-500 mt-1">Jadwal pertemuan untuk path ini akan segera diperbarui oleh admin.</p>
                </div>
            @endforelse
        </div>
    @endif
    @else
        <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-8 sm:p-12 text-center shadow-xs">
            <div class="size-14 mx-auto rounded-2xl bg-yellow-400/10 text-yellow-600 dark:text-yellow-400 flex items-center justify-center mb-4">
                <flux:icon name="academic-cap" class="size-7" />
            </div>
            <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">
                Belum Ada Path Learning yang Dipilih
            </h3>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 max-w-md mx-auto mt-2 leading-relaxed">
                Halaman ini hanya menampilkan alur dan rangkaian pertemuan untuk learning path yang Anda ikuti. Silakan pilih path pembelajaran terlebih dahulu.
            </p>
            <div class="mt-6">
                <flux:button href="{{ route('learning-paths.index') }}" variant="primary" icon="plus" wire:navigate>
                    Pilih Path Learning Sekarang
                </flux:button>
            </div>
        </div>
    @endif
</div>
