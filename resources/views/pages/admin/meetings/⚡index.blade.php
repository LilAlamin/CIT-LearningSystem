<?php

use App\Models\LearningPath;
use App\Models\MeetingAttendance;
use App\Models\PathMeeting;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Kelola Pertemuan & Alur - Admin')] class extends Component {
    #[Url]
    public ?int $selectedPathId = null;

    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public ?int $editingMeetingId = null;

    public bool $showAttendanceModal = false;
    public ?int $attendanceMeetingId = null;

    /** @var array<int, string> */
    public array $attendanceStatuses = [];

    /** @var array<int, string> */
    public array $attendanceNotes = [];

    // Form fields
    public ?int $learning_path_id = null;
    public ?int $meeting_number = 1;
    public string $title = '';
    public string $description = '';
    public ?string $meeting_date = null;
    public string $end_time = '';
    public string $location = '';
    public string $meeting_url = '';
    public string $notes = '';
    public string $recap_summary = '';
    public string $recap_slides_url = '';
    public string $recap_recording_url = '';
    public bool $is_completed = false;

    /**
     * Initialize component and enforce PJ path scoping.
     */
    public function mount(): void
    {
        $user = Auth::user();
        if ($user && $user->isPj()) {
            $managedIds = $user->managedLearningPathIds();
            if (! $this->selectedPathId || ! in_array($this->selectedPathId, $managedIds, true)) {
                $this->selectedPathId = $managedIds[0] ?? null;
            }
        }
    }

    /**
     * All available learning paths (scoped for PJ).
     */
    #[Computed]
    public function learningPaths()
    {
        $user = Auth::user();
        if ($user && $user->isPj()) {
            return $user->managedLearningPaths()->orderBy('title')->get();
        }

        return LearningPath::orderBy('title')->get();
    }

    /**
     * Get meetings query filtered by selected path (scoped for PJ).
     */
    #[Computed]
    public function meetings()
    {
        $user = Auth::user();
        $query = PathMeeting::with(['learningPath'])->withCount([
            'materials',
            'attendances as hadir_count' => fn ($q) => $q->where('status', MeetingAttendance::STATUS_HADIR),
            'attendances as izin_count' => fn ($q) => $q->where('status', MeetingAttendance::STATUS_IZIN),
            'attendances as alpha_count' => fn ($q) => $q->where('status', MeetingAttendance::STATUS_ALPHA),
        ]);

        if ($user && $user->isPj()) {
            $query->whereIn('learning_path_id', $user->managedLearningPathIds());
        }

        return $query
            ->when($this->selectedPathId, fn ($q) => $q->where('learning_path_id', $this->selectedPathId))
            ->orderBy('learning_path_id')
            ->orderBy('meeting_number')
            ->get();
    }

    /**
     * Get the meeting currently selected for attendance recording.
     */
    #[Computed]
    public function attendanceMeeting(): ?PathMeeting
    {
        if (! $this->attendanceMeetingId) {
            return null;
        }

        return PathMeeting::with(['learningPath', 'attendances'])->find($this->attendanceMeetingId);
    }

    /**
     * Get enrolled students for the currently selected attendance meeting's path.
     */
    #[Computed]
    public function attendanceStudents()
    {
        $meeting = $this->attendanceMeeting;
        if (! $meeting) {
            return collect();
        }

        return User::where('role', 'user')
            ->whereHas('enrollments', fn ($q) => $q->where('learning_path_id', $meeting->learning_path_id))
            ->with(['meetingAttendances.meeting'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Open attendance modal for a specific meeting.
     */
    public function openAttendanceModal(int $meetingId): void
    {
        $meeting = PathMeeting::with('attendances')->findOrFail($meetingId);
        abort_unless(Auth::user()->canManagePath((int) $meeting->learning_path_id), 403, 'Anda tidak memiliki hak akses untuk mengisi absensi pertemuan ini.');

        $this->attendanceMeetingId = $meeting->id;
        $this->attendanceStatuses = [];
        $this->attendanceNotes = [];

        $existingByUser = $meeting->attendances->keyBy('user_id');

        $students = User::where('role', 'user')
            ->whereHas('enrollments', fn ($q) => $q->where('learning_path_id', $meeting->learning_path_id))
            ->orderBy('name')
            ->get();

        foreach ($students as $student) {
            $existing = $existingByUser->get($student->id);
            $this->attendanceStatuses[$student->id] = $existing?->status ?? '';
            $this->attendanceNotes[$student->id] = $existing?->notes ?? '';
        }

        unset($this->attendanceMeeting, $this->attendanceStudents);
        $this->showAttendanceModal = true;
    }

    /**
     * Quick helper to mark all enrolled students as present (Hadir = +100 Pt).
     */
    public function markAllPresent(): void
    {
        foreach ($this->attendanceStudents as $student) {
            $this->attendanceStatuses[$student->id] = MeetingAttendance::STATUS_HADIR;
        }
    }

    /**
     * Save student attendance and points for the active meeting.
     */
    public function saveAttendances(): void
    {
        $meeting = PathMeeting::findOrFail($this->attendanceMeetingId);
        abort_unless(Auth::user()->canManagePath((int) $meeting->learning_path_id), 403, 'Anda tidak memiliki hak akses untuk menyimpan absensi pertemuan ini.');

        $allowedStatuses = [
            MeetingAttendance::STATUS_HADIR,
            MeetingAttendance::STATUS_IZIN,
            MeetingAttendance::STATUS_ALPHA,
        ];

        $savedCount = 0;

        foreach ($this->attendanceStudents as $student) {
            $status = $this->attendanceStatuses[$student->id] ?? '';
            if (! in_array($status, $allowedStatuses, true)) {
                continue;
            }

            $note = trim((string) ($this->attendanceNotes[$student->id] ?? ''));

            MeetingAttendance::updateOrCreate(
                [
                    'path_meeting_id' => $meeting->id,
                    'user_id' => $student->id,
                ],
                [
                    'status' => $status,
                    'points' => MeetingAttendance::pointsForStatus($status),
                    'notes' => $note !== '' ? $note : null,
                    'recorded_by' => Auth::id(),
                ],
            );

            $savedCount++;
        }

        unset($this->meetings, $this->attendanceMeeting, $this->attendanceStudents);
        $this->showAttendanceModal = false;

        Flux::toast(
            variant: 'success',
            text: "Absensi & poin keaktifan untuk {$savedCount} peserta pada P-{$meeting->meeting_number} berhasil disimpan!",
        );
    }

    /**
     * Summary metrics (scoped for PJ).
     */
    #[Computed]
    public function metrics(): array
    {
        $user = Auth::user();
        $query = PathMeeting::query();

        if ($user && $user->isPj()) {
            $query->whereIn('learning_path_id', $user->managedLearningPathIds());
        }

        $all = $query->get();

        return [
            'total' => $all->count(),
            'completed' => $all->filter->isPast()->count(),
            'upcoming' => $all->reject->isPast()->count(),
        ];
    }

    /**
     * Open create modal.
     */
    public function openCreateModal(): void
    {
        $this->reset([
            'editingMeetingId',
            'title',
            'description',
            'meeting_date',
            'end_time',
            'location',
            'meeting_url',
            'notes',
            'recap_summary',
            'recap_slides_url',
            'recap_recording_url',
            'is_completed',
        ]);

        $this->learning_path_id = $this->selectedPathId ?? $this->learningPaths->first()?->id;

        // Auto compute next meeting number
        if ($this->learning_path_id) {
            $maxNum = PathMeeting::where('learning_path_id', $this->learning_path_id)->max('meeting_number') ?? 0;
            $this->meeting_number = $maxNum + 1;
        } else {
            $this->meeting_number = 1;
        }

        $this->meeting_date = now()->addDays(3)->setTime(19, 0)->format('Y-m-d\TH:i');
        $this->end_time = '21:00 WIB';
        $this->location = 'Online Zoom / Lab Komputer';

        $this->showCreateModal = true;
    }

    /**
     * Recalculate next meeting number when path changes in form.
     */
    public function updatedLearningPathId(): void
    {
        if (! $this->editingMeetingId && $this->learning_path_id) {
            $maxNum = PathMeeting::where('learning_path_id', $this->learning_path_id)->max('meeting_number') ?? 0;
            $this->meeting_number = $maxNum + 1;
        }
    }

    /**
     * Save new meeting.
     */
    public function createMeeting(): void
    {
        abort_unless(Auth::user()->canManagePath((int) $this->learning_path_id), 403, 'Anda tidak memiliki hak akses untuk mengelola path ini.');

        $validated = $this->validate([
            'learning_path_id' => 'required|exists:learning_paths,id',
            'meeting_number' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('path_meetings')->where('learning_path_id', $this->learning_path_id),
            ],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'meeting_date' => 'required|date',
            'end_time' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'meeting_url' => 'nullable|url|max:255',
            'notes' => 'nullable|string',
            'recap_summary' => 'nullable|string',
            'recap_slides_url' => 'nullable|url|max:255',
            'recap_recording_url' => 'nullable|url|max:255',
            'is_completed' => 'boolean',
        ]);

        PathMeeting::create($validated);

        $this->showCreateModal = false;
        Flux::toast(variant: 'success', text: __('Pertemuan berhasil dijadwalkan!'));
    }

    /**
     * Open edit modal.
     */
    public function editMeeting(int $id): void
    {
        $meeting = PathMeeting::findOrFail($id);
        abort_unless(Auth::user()->canManagePath((int) $meeting->learning_path_id), 403, 'Anda tidak memiliki hak akses mengedit pertemuan path ini.');

        $this->editingMeetingId = $meeting->id;
        $this->learning_path_id = $meeting->learning_path_id;
        $this->meeting_number = $meeting->meeting_number;
        $this->title = $meeting->title;
        $this->description = $meeting->description ?? '';
        $this->meeting_date = $meeting->meeting_date?->format('Y-m-d\TH:i');
        $this->end_time = $meeting->end_time ?? '';
        $this->location = $meeting->location ?? '';
        $this->meeting_url = $meeting->meeting_url ?? '';
        $this->notes = $meeting->notes ?? '';
        $this->recap_summary = $meeting->recap_summary ?? '';
        $this->recap_slides_url = $meeting->recap_slides_url ?? '';
        $this->recap_recording_url = $meeting->recap_recording_url ?? '';
        $this->is_completed = (bool) $meeting->is_completed;

        $this->showEditModal = true;
    }

    /**
     * Update existing meeting.
     */
    public function updateMeeting(): void
    {
        $meeting = PathMeeting::findOrFail($this->editingMeetingId);
        abort_unless(Auth::user()->canManagePath((int) $meeting->learning_path_id), 403, 'Anda tidak memiliki hak akses mengedit pertemuan path ini.');
        abort_unless(Auth::user()->canManagePath((int) $this->learning_path_id), 403, 'Anda tidak memiliki hak akses memindahkan ke path ini.');

        $validated = $this->validate([
            'learning_path_id' => 'required|exists:learning_paths,id',
            'meeting_number' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('path_meetings')->where('learning_path_id', $this->learning_path_id)->ignore($meeting->id),
            ],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'meeting_date' => 'required|date',
            'end_time' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'meeting_url' => 'nullable|url|max:255',
            'notes' => 'nullable|string',
            'recap_summary' => 'nullable|string',
            'recap_slides_url' => 'nullable|url|max:255',
            'recap_recording_url' => 'nullable|url|max:255',
            'is_completed' => 'boolean',
        ]);

        $meeting->update($validated);

        $this->showEditModal = false;
        Flux::toast(variant: 'success', text: __('Data pertemuan & rangkuman materi berhasil disimpan!'));
    }

    /**
     * Quick toggle completion.
     */
    public function toggleComplete(int $id): void
    {
        $meeting = PathMeeting::findOrFail($id);
        abort_unless(Auth::user()->canManagePath((int) $meeting->learning_path_id), 403);

        $meeting->update([
            'is_completed' => ! $meeting->is_completed,
        ]);

        $status = $meeting->is_completed ? 'selesai' : 'aktif/mendatang';
        Flux::toast(variant: 'info', text: "Pertemuan diubah menjadi {$status}.");
    }

    /**
     * Delete a meeting.
     */
    public function deleteMeeting(int $id): void
    {
        $meeting = PathMeeting::findOrFail($id);
        abort_unless(Auth::user()->canManagePath((int) $meeting->learning_path_id), 403);

        $meeting->delete();

        Flux::toast(variant: 'warning', text: __('Pertemuan berhasil dihapus.'));
    }
};
?>

<div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
        <div>
            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-400/10 text-yellow-600 dark:text-yellow-400 mb-2 border border-yellow-400/20">
                <flux:icon name="shield-check" class="size-3.5" />
                <span>{{ auth()->user()?->isPj() ? 'Panel Penanggung Jawab (PJ)' : 'Admin Panel' }}</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                Kelola Pertemuan, Absensi & Alur Belajar
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                Atur jadwal pertemuan per path, absensi & poin keaktifan peserta (Hadir: 100 pt, Izin: 50 pt, Maks: 600 pt), serta rangkuman materi setelah sesi selesai.
            </p>
        </div>

        <flux:button wire:click="openCreateModal" variant="primary" icon="plus">
            Tambah Pertemuan
        </flux:button>
    </div>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="p-4 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Total Pertemuan</p>
                <p class="text-2xl font-black text-zinc-900 dark:text-zinc-100 mt-1">{{ $this->metrics['total'] }}</p>
            </div>
            <span class="p-2.5 rounded-xl bg-yellow-400/10 text-yellow-600 dark:text-yellow-400">
                <flux:icon name="video-camera" class="size-5" />
            </span>
        </div>

        <div class="p-4 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Terlaksana / Selesai</p>
                <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ $this->metrics['completed'] }}</p>
            </div>
            <span class="p-2.5 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                <flux:icon name="check-circle" class="size-5" />
            </span>
        </div>

        <div class="p-4 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Pertemuan Mendatang</p>
                <p class="text-2xl font-black text-amber-500 dark:text-amber-400 mt-1">{{ $this->metrics['upcoming'] }}</p>
            </div>
            <span class="p-2.5 rounded-xl bg-amber-500/10 text-amber-500">
                <flux:icon name="clock" class="size-5" />
            </span>
        </div>
    </div>

    <!-- Filter by Path -->
    <div class="flex flex-wrap items-center gap-2 p-1.5 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900/60">
        <button
            wire:click="$set('selectedPathId', null)"
            class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all {{ is_null($selectedPathId) ? 'bg-yellow-400 text-zinc-950 shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
        >
            Semua Path Learning ({{ $this->metrics['total'] }})
        </button>

        @foreach ($this->learningPaths as $lp)
            <button
                wire:click="$set('selectedPathId', {{ $lp->id }})"
                class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all {{ $selectedPathId === $lp->id ? 'bg-yellow-400 text-zinc-950 shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                {{ $lp->title }}
            </button>
        @endforeach
    </div>

    <!-- Table of Meetings -->
    <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-zinc-50 dark:bg-zinc-800/60 text-zinc-600 dark:text-zinc-400 uppercase font-semibold border-b border-zinc-200 dark:border-zinc-800">
                    <tr>
                        <th class="px-5 py-3.5">Pertemuan & Path</th>
                        <th class="px-5 py-3.5">Jadwal & Waktu</th>
                        <th class="px-5 py-3.5">Lokasi & Tautan</th>
                        <th class="px-5 py-3.5">Absensi Peserta</th>
                        <th class="px-5 py-3.5">Rangkuman Pas Acara</th>
                        <th class="px-5 py-3.5">Status</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->meetings as $meeting)
                        @php
                            $isPast = $meeting->isPast();
                            $hasRecap = filled($meeting->recap_summary) || filled($meeting->recap_recording_url) || filled($meeting->recap_slides_url);
                            $totalAttendanceRecorded = (int) $meeting->hadir_count + (int) $meeting->izin_count + (int) $meeting->alpha_count;
                        @endphp
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-colors">
                            <td class="px-5 py-4">
                                <div class="flex items-start gap-3">
                                    <span class="p-2 rounded-xl bg-yellow-400/20 text-yellow-700 dark:text-yellow-400 font-extrabold text-xs shrink-0">
                                        P-{{ $meeting->meeting_number }}
                                    </span>
                                    <div>
                                        <p class="font-bold text-zinc-900 dark:text-zinc-100 text-sm">{{ $meeting->title }}</p>
                                        <p class="text-zinc-400 text-[11px] font-medium mt-0.5">
                                            Path: <span class="text-yellow-600 dark:text-yellow-400 font-semibold">{{ $meeting->learningPath->title }}</span>
                                            • {{ $meeting->materials_count }} modul terhubung
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="space-y-0.5">
                                    <p class="font-bold text-zinc-800 dark:text-zinc-200">
                                        {{ $meeting->meeting_date->locale('id')->isoFormat('dddd, D MMMM Y') }}
                                    </p>
                                    <p class="text-[11px] text-zinc-400">
                                        {{ $meeting->meeting_date->format('H:i') }} @if ($meeting->end_time) - {{ $meeting->end_time }} @endif
                                    </p>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="space-y-1">
                                    <p class="text-zinc-700 dark:text-zinc-300 font-medium truncate max-w-xs">
                                        {{ $meeting->location ?: 'Online' }}
                                    </p>
                                    @if ($meeting->meeting_url)
                                        <a href="{{ $meeting->meeting_url }}" target="_blank" class="inline-flex items-center gap-1 text-[11px] text-yellow-600 dark:text-yellow-400 hover:underline">
                                            <flux:icon name="arrow-top-right-on-square" class="size-3" />
                                            <span>Link Acara</span>
                                        </a>
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="space-y-1.5">
                                    @if ($totalAttendanceRecorded > 0)
                                        <div class="flex flex-wrap items-center gap-1 text-[10px] font-bold">
                                            <span class="px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                                {{ $meeting->hadir_count }} Hadir
                                            </span>
                                            <span class="px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                                {{ $meeting->izin_count }} Izin
                                            </span>
                                            @if ($meeting->alpha_count > 0)
                                                <span class="px-2 py-0.5 rounded-full bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20">
                                                    {{ $meeting->alpha_count }} Alpha
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-500">
                                            Belum Diabsen
                                        </span>
                                    @endif
                                    <div>
                                        <flux:button wire:click="openAttendanceModal({{ $meeting->id }})" variant="primary" size="xs" icon="clipboard-document-check">
                                            Absensi Peserta
                                        </flux:button>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                @if ($hasRecap)
                                    <div class="space-y-1">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                            <flux:icon name="check" class="size-3" />
                                            Rangkuman Terisi
                                        </span>
                                        <div class="flex items-center gap-2 text-[10px] text-zinc-400">
                                            @if ($meeting->recap_slides_url)
                                                <span>Slide ✓</span>
                                            @endif
                                            @if ($meeting->recap_recording_url)
                                                <span>Rekaman ✓</span>
                                            @endif
                                        </div>
                                    </div>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-zinc-200 dark:bg-zinc-800 text-zinc-500">
                                        Belum Ada Rangkuman
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <button
                                    wire:click="toggleComplete({{ $meeting->id }})"
                                    title="Klik untuk ubah status"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold transition-all {{ $isPast ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/25' : 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/20 hover:bg-amber-500/25' }}"
                                >
                                    <span class="size-1.5 rounded-full {{ $isPast ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                                    {{ $meeting->is_completed ? 'Selesai (Manual)' : ($isPast ? 'Selesai (Waktu)' : 'Mendatang') }}
                                </button>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <flux:button wire:click="editMeeting({{ $meeting->id }})" variant="subtle" size="xs" icon="pencil-square">
                                        Edit & Rangkuman
                                    </flux:button>
                                    <flux:button wire:click="deleteMeeting({{ $meeting->id }})" wire:confirm="Yakin ingin menghapus pertemuan ini?" variant="danger" size="xs" icon="trash">
                                        Hapus
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-zinc-400 italic">
                                Belum ada jadwal pertemuan untuk path yang dipilih. Klik tombol "Tambah Pertemuan" di atas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Modal -->
    <flux:modal wire:model="showCreateModal" class="md:w-[680px]">
        <form wire:submit="createMeeting" class="space-y-4">
            <flux:heading size="lg">Jadwalkan Pertemuan Baru</flux:heading>
            <flux:text class="text-xs">Tambahkan agenda pertemuan alur belajar untuk membimbing peserta.</flux:text>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <flux:field class="sm:col-span-2">
                    <flux:label>Pilih Path Learning</flux:label>
                    <flux:select wire:model.live="learning_path_id" required>
                        @foreach ($this->learningPaths as $lp)
                            <flux:select.option value="{{ $lp->id }}">{{ $lp->title }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="learning_path_id" />
                </flux:field>

                <flux:field>
                    <flux:label>Pertemuan Ke-</flux:label>
                    <flux:input type="number" min="1" wire:model="meeting_number" required />
                    <flux:error name="meeting_number" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Judul Pertemuan</flux:label>
                <flux:input wire:model="title" placeholder="Contoh: Pertemuan 1 - Kickoff & HTML/CSS Fundamental" required />
                <flux:error name="title" />
            </flux:field>

            <flux:field>
                <flux:label>Detail / Rundown Acara</flux:label>
                <flux:textarea wire:model="description" rows="2" placeholder="Penjelasan singkat apa saja yang akan dipelajari dan dipraktikkan pada pertemuan ini..." />
                <flux:error name="description" />
            </flux:field>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Tanggal & Jam Mulai</flux:label>
                    <flux:input type="datetime-local" wire:model="meeting_date" required />
                    <flux:error name="meeting_date" />
                </flux:field>

                <flux:field>
                    <flux:label>Estimasi Jam Selesai</flux:label>
                    <flux:input wire:model="end_time" placeholder="21:00 WIB" />
                    <flux:error name="end_time" />
                </flux:field>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Lokasi / Ruangan</flux:label>
                    <flux:input wire:model="location" placeholder="Zoom Online / Lab Komputer 3" />
                    <flux:error name="location" />
                </flux:field>

                <flux:field>
                    <flux:label>Tautan Link Acara (Meet/Zoom)</flux:label>
                    <flux:input wire:model="meeting_url" placeholder="https://zoom.us/j/..." />
                    <flux:error name="meeting_url" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Notes Persiapan Peserta</flux:label>
                <flux:textarea wire:model="notes" rows="2" placeholder="Notes/checklist untuk peserta (contoh: install Git, VS Code, Node LTS sebelum sesi dimulai)..." />
                <flux:error name="notes" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button wire:click="$set('showCreateModal', false)" variant="subtle">
                    Batal
                </flux:button>
                <flux:button type="submit" variant="primary">
                    Jadwalkan Pertemuan
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Edit Modal (With Recap Section) -->
    <flux:modal wire:model="showEditModal" class="md:w-[720px]">
        <form wire:submit="updateMeeting" class="space-y-5">
            <div>
                <flux:heading size="lg">Edit Pertemuan & Rangkuman Acara</flux:heading>
                <flux:text class="text-xs">Perbarui rundown, notes, atau masukkan rangkuman materi & rekaman video setelah acara berlangsung.</flux:text>
            </div>

            <!-- Tab / Section 1: Informasi Dasar -->
            <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/30 space-y-3">
                <p class="text-xs font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-1.5">
                    <flux:icon name="calendar-days" class="size-4 text-yellow-500" />
                    Informasi Jadwal & Acara
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <flux:field class="sm:col-span-2">
                        <flux:label>Path Learning</flux:label>
                        <flux:select wire:model.live="learning_path_id" required>
                            @foreach ($this->learningPaths as $lp)
                                <flux:select.option value="{{ $lp->id }}">{{ $lp->title }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="learning_path_id" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Pertemuan Ke-</flux:label>
                        <flux:input type="number" min="1" wire:model="meeting_number" required />
                        <flux:error name="meeting_number" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>Judul Pertemuan</flux:label>
                    <flux:input wire:model="title" required />
                    <flux:error name="title" />
                </flux:field>

                <flux:field>
                    <flux:label>Detail / Rundown Acara</flux:label>
                    <flux:textarea wire:model="description" rows="2" />
                    <flux:error name="description" />
                </flux:field>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <flux:field>
                        <flux:label>Tanggal & Jam Mulai</flux:label>
                        <flux:input type="datetime-local" wire:model="meeting_date" required />
                        <flux:error name="meeting_date" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Estimasi Selesai</flux:label>
                        <flux:input wire:model="end_time" />
                        <flux:error name="end_time" />
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <flux:field>
                        <flux:label>Lokasi / Ruangan</flux:label>
                        <flux:input wire:model="location" />
                        <flux:error name="location" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Tautan Link Acara</flux:label>
                        <flux:input wire:model="meeting_url" />
                        <flux:error name="meeting_url" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>Notes Persiapan Peserta</flux:label>
                    <flux:textarea wire:model="notes" rows="2" />
                    <flux:error name="notes" />
                </flux:field>
            </div>

            <!-- Tab / Section 2: Rangkuman Materi Pas Acara Selesai -->
            <div class="p-4 rounded-xl border border-yellow-400/30 bg-yellow-400/5 dark:bg-yellow-400/5 space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-yellow-700 dark:text-yellow-400 flex items-center gap-1.5">
                            <flux:icon name="document-text" class="size-4" />
                            Rangkuman Materi & Hasil Pertemuan (Pas Acara Selesai)
                        </p>
                        <p class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-0.5">
                            Bagian ini akan otomatis ditampilkan kepada peserta jika status pertemuan sudah lewat / selesai.
                        </p>
                    </div>

                    <flux:checkbox wire:model="is_completed" label="Tandai Selesai" />
                </div>

                <flux:field>
                    <flux:label>Rangkuman Materi Pas Acara (Summary / Poin Penting)</flux:label>
                    <flux:textarea wire:model="recap_summary" rows="4" placeholder="Tuliskan rangkuman apa yang telah dibahas saat sesi berlangsung, highlight diskusi, tips penting, dll..." />
                    <flux:error name="recap_summary" />
                </flux:field>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <flux:field>
                        <flux:label>Tautan Slide Materi (Google Slides / PDF)</flux:label>
                        <flux:input wire:model="recap_slides_url" placeholder="https://docs.google.com/presentation/..." />
                        <flux:error name="recap_slides_url" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Tautan Rekaman Acara (YouTube / Drive)</flux:label>
                        <flux:input wire:model="recap_recording_url" placeholder="https://youtube.com/watch?v=..." />
                        <flux:error name="recap_recording_url" />
                    </flux:field>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button wire:click="$set('showEditModal', false)" variant="subtle">
                    Batal
                </flux:button>
                <flux:button type="submit" variant="primary">
                    Simpan Perubahan
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Attendance & Activity Points Modal -->
    <flux:modal wire:model="showAttendanceModal" class="!max-w-3xl w-full">
        @if ($this->attendanceMeeting)
            @php
                $selectedValues = array_values($attendanceStatuses);
                $countHadir = count(array_filter($selectedValues, fn ($v) => $v === 'hadir'));
                $countIzin = count(array_filter($selectedValues, fn ($v) => $v === 'izin'));
                $countAlpha = count(array_filter($selectedValues, fn ($v) => $v === 'alpha'));
                $totalStudents = $this->attendanceStudents->count();
                $countUnmarked = max(0, $totalStudents - ($countHadir + $countIzin + $countAlpha));
            @endphp

            <form wire:submit="saveAttendances" class="space-y-4">
                <!-- Modal Header (padded right to avoid close X button collision) -->
                <div class="pr-8 pb-3 border-b border-zinc-200 dark:border-zinc-800">
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-yellow-400/15 text-yellow-700 dark:text-yellow-400 mb-1.5">
                        <flux:icon name="academic-cap" class="size-3.5" />
                        <span>Pertemuan Ke-{{ $this->attendanceMeeting->meeting_number }}</span>
                        <span>•</span>
                        <span>{{ $this->attendanceMeeting->learningPath->title }}</span>
                    </div>
                    <flux:heading size="lg">Absensi & Poin Keaktifan Peserta</flux:heading>
                    <flux:text class="text-xs mt-0.5">
                        {{ $this->attendanceMeeting->title }} — {{ $this->attendanceMeeting->meeting_date->locale('id')->isoFormat('dddd, D MMMM Y (HH:mm)') }} WIB
                    </flux:text>
                </div>

                <!-- Compact Point Rules Info Strip -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 p-3 rounded-xl bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200/80 dark:border-zinc-800 text-xs">
                    <div class="flex items-center gap-2.5 px-1">
                        <span class="size-2.5 rounded-full bg-emerald-500 shrink-0"></span>
                        <div>
                            <p class="font-bold text-zinc-900 dark:text-zinc-100 leading-tight">Hadir: +100 Pt</p>
                            <p class="text-[10px] text-zinc-500">Per pertemuan</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5 px-1">
                        <span class="size-2.5 rounded-full bg-amber-500 shrink-0"></span>
                        <div>
                            <p class="font-bold text-zinc-900 dark:text-zinc-100 leading-tight">Izin: +50 Pt</p>
                            <p class="text-[10px] text-zinc-500">Dengan keterangan</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5 px-1">
                        <span class="size-2.5 rounded-full bg-red-500 shrink-0"></span>
                        <div>
                            <p class="font-bold text-zinc-900 dark:text-zinc-100 leading-tight">Alpha: 0 Pt</p>
                            <p class="text-[10px] text-zinc-500">Tanpa keterangan</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5 px-1">
                        <span class="size-2.5 rounded-full bg-yellow-400 shrink-0"></span>
                        <div>
                            <p class="font-bold text-zinc-900 dark:text-zinc-100 leading-tight">Maks: 600 Pt</p>
                            <p class="text-[10px] text-zinc-500">Lulus Sertifikat ≥ 500 Pt</p>
                        </div>
                    </div>
                </div>

                <!-- Live Summary & Quick Action Toolbar -->
                @if ($this->attendanceStudents->isNotEmpty())
                    <div class="flex flex-wrap items-center justify-between gap-2 px-3.5 py-2.5 rounded-xl bg-zinc-100/80 dark:bg-zinc-800/80 border border-zinc-200/70 dark:border-zinc-700/60">
                        <div class="flex flex-wrap items-center gap-2 text-[11px] font-semibold">
                            <span class="text-zinc-500 dark:text-zinc-400 mr-1">Rekap Sesi Ini:</span>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-500/15 text-emerald-700 dark:text-emerald-300">
                                <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                {{ $countHadir }} Hadir
                            </span>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-500/15 text-amber-700 dark:text-amber-300">
                                <span class="size-1.5 rounded-full bg-amber-500"></span>
                                {{ $countIzin }} Izin
                            </span>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-red-500/15 text-red-700 dark:text-red-300">
                                <span class="size-1.5 rounded-full bg-red-500"></span>
                                {{ $countAlpha }} Alpha
                            </span>
                            @if ($countUnmarked > 0)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-zinc-200 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-300">
                                    {{ $countUnmarked }} Belum Dipilih
                                </span>
                            @endif
                        </div>

                        <flux:button type="button" wire:click="markAllPresent" variant="subtle" size="xs" icon="check-badge">
                            Tandai Semua Hadir (+100 Pt)
                        </flux:button>
                    </div>
                @endif

                <!-- Student List -->
                <div class="max-h-[380px] overflow-y-auto rounded-xl border border-zinc-200 dark:border-zinc-800 divide-y divide-zinc-200/70 dark:divide-zinc-800">
                    @forelse ($this->attendanceStudents as $student)
                        @php
                            $currentPoints = $student->pointsForPath($this->attendanceMeeting->learning_path_id);
                            $selectedStatus = $attendanceStatuses[$student->id] ?? '';
                        @endphp
                        <div class="p-4 bg-white dark:bg-zinc-900 hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40 transition-colors space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <!-- Student Identity & Current Total Points -->
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="size-9 rounded-xl bg-yellow-400/20 text-yellow-700 dark:text-yellow-300 font-bold text-xs flex items-center justify-center shrink-0 border border-yellow-400/30">
                                        {{ $student->initials() }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-bold text-xs text-zinc-900 dark:text-zinc-100 truncate">{{ $student->name }}</p>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold {{ $currentPoints >= 500 ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20' : 'bg-yellow-400/15 text-yellow-700 dark:text-yellow-300 border border-yellow-400/30' }}">
                                                <flux:icon name="sparkles" class="size-2.5" />
                                                {{ $currentPoints }} / 600 Pt
                                            </span>
                                        </div>
                                        <p class="text-[11px] text-zinc-400 truncate mt-0.5">
                                            {{ $student->email }}
                                            @if ($student->major)
                                                <span>• {{ $student->major }}</span>
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                <!-- Segmented Attendance Status Selector (Single Row) -->
                                <div class="inline-flex items-center p-1 rounded-xl bg-zinc-100 dark:bg-zinc-800 border border-zinc-200/80 dark:border-zinc-700 shrink-0 self-start sm:self-center">
                                    <button
                                        type="button"
                                        wire:click="$set('attendanceStatuses.{{ $student->id }}', 'hadir')"
                                        class="px-3 py-1.5 rounded-lg text-[11px] font-bold whitespace-nowrap transition-all cursor-pointer {{ $selectedStatus === 'hadir' ? 'bg-emerald-500 text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                                    >
                                        ✓ Hadir (+100)
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="$set('attendanceStatuses.{{ $student->id }}', 'izin')"
                                        class="px-3 py-1.5 rounded-lg text-[11px] font-bold whitespace-nowrap transition-all cursor-pointer {{ $selectedStatus === 'izin' ? 'bg-yellow-400 text-zinc-950 shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                                    >
                                        ! Izin (+50)
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="$set('attendanceStatuses.{{ $student->id }}', 'alpha')"
                                        class="px-3 py-1.5 rounded-lg text-[11px] font-bold whitespace-nowrap transition-all cursor-pointer {{ $selectedStatus === 'alpha' ? 'bg-red-500 text-white shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                                    >
                                        ✕ Alpha (0)
                                    </button>
                                </div>
                            </div>

                            <!-- Optional Notes Input -->
                            <div class="pl-0 sm:pl-12">
                                <flux:input
                                    size="sm"
                                    icon="pencil-square"
                                    wire:model="attendanceNotes.{{ $student->id }}"
                                    placeholder="Keterangan izin / catatan kehadiran opsional (contoh: Sakit dengan surat dokter)..."
                                />
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-xs text-zinc-400 italic">
                            Belum ada mahasiswa yang terdaftar pada learning path <span class="font-semibold">{{ $this->attendanceMeeting->learningPath->title }}</span>.
                        </div>
                    @endforelse
                </div>

                <!-- Modal Footer -->
                <div class="flex items-center justify-between pt-3 border-t border-zinc-200 dark:border-zinc-800">
                    <p class="text-xs text-zinc-500">
                        Total Terdaftar: <span class="font-bold text-zinc-800 dark:text-zinc-200">{{ $totalStudents }} Peserta</span>
                    </p>
                    <div class="flex items-center gap-2">
                        <flux:button type="button" wire:click="$set('showAttendanceModal', false)" variant="subtle">
                            Batal
                        </flux:button>
                        <flux:button type="submit" variant="primary" icon="check">
                            Simpan Absensi & Poin
                        </flux:button>
                    </div>
                </div>
            </form>
        @endif
    </flux:modal>
</div>
