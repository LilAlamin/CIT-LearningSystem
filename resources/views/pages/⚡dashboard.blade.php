<?php

use App\Models\Announcement;
use App\Models\CalendarEvent;
use App\Models\Certificate;
use App\Models\LearningPath;
use App\Models\LearningPathEnrollment;
use App\Models\Material;
use App\Models\PathMeeting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    public int $currentYear;
    public int $currentMonth;
    public ?string $selectedDate = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $today = Carbon::today();
        $this->currentYear = $today->year;
        $this->currentMonth = $today->month;
        $this->selectedDate = $today->toDateString();
    }

    /**
     * Navigate to previous month.
     */
    public function previousMonth(): void
    {
        $date = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->subMonth();
        $this->currentYear = $date->year;
        $this->currentMonth = $date->month;
    }

    /**
     * Navigate to next month.
     */
    public function nextMonth(): void
    {
        $date = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->addMonth();
        $this->currentYear = $date->year;
        $this->currentMonth = $date->month;
    }

    /**
     * Reset to current month & today.
     */
    public function goToToday(): void
    {
        $today = Carbon::today();
        $this->currentYear = $today->year;
        $this->currentMonth = $today->month;
        $this->selectedDate = $today->toDateString();
    }

    /**
     * Select a specific date on the calendar.
     */
    public function selectDate(string $date): void
    {
        $this->selectedDate = $date;
    }

    /**
     * Get the calendar matrix days.
     */
    #[Computed]
    public function calendarDays(): array
    {
        $startOfMonth = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        // Start from Monday (ISO-8601)
        $startDay = $startOfMonth->copy()->startOfWeek(Carbon::MONDAY);
        $endDay = $endOfMonth->copy()->endOfWeek(Carbon::SUNDAY);

        // Fetch calendar events
        $events = CalendarEvent::with('learningPath')
            ->whereBetween('event_date', [$startDay->toDateString(), $endDay->toDateString()])
            ->get()
            ->groupBy(fn ($event) => $event->event_date->toDateString());

        // Fetch meetings in this window
        $meetings = PathMeeting::with('learningPath')
            ->whereBetween('meeting_date', [$startDay->startOfDay(), $endDay->endOfDay()])
            ->get()
            ->groupBy(fn ($m) => $m->meeting_date->toDateString());

        // Fetch deadlines
        $deadlines = LearningPath::whereNotNull('registration_end_at')
            ->whereBetween('registration_end_at', [$startDay->startOfDay(), $endDay->endOfDay()])
            ->get();

        $days = [];
        $cursor = $startDay->copy();

        while ($cursor->lte($endDay)) {
            $dateString = $cursor->toDateString();
            $dayEvents = $events->get($dateString, collect())->all();
            $dayMeetings = $meetings->get($dateString, collect())->all();

            $pathDeadlines = $deadlines->filter(function ($lp) use ($cursor) {
                return $lp->registration_end_at && $lp->registration_end_at->toDateString() === $cursor->toDateString();
            });

            $days[] = [
                'date' => $dateString,
                'dayNumber' => $cursor->day,
                'isCurrentMonth' => $cursor->month === $this->currentMonth,
                'isToday' => $cursor->isToday(),
                'isSelected' => $this->selectedDate === $dateString,
                'events' => $dayEvents,
                'meetings' => $dayMeetings,
                'pathDeadlines' => $pathDeadlines,
                'hasEvents' => count($dayEvents) > 0 || count($dayMeetings) > 0 || $pathDeadlines->count() > 0,
            ];

            $cursor->addDay();
        }

        return $days;
    }

    /**
     * Get events and meetings for the selected date.
     */
    #[Computed]
    public function selectedDayEvents(): array
    {
        if (! $this->selectedDate) {
            return [];
        }

        $events = CalendarEvent::with('learningPath')
            ->whereDate('event_date', $this->selectedDate)
            ->get();

        $meetings = PathMeeting::with('learningPath')
            ->whereDate('meeting_date', $this->selectedDate)
            ->get();

        $pathDeadlines = LearningPath::whereNotNull('registration_end_at')
            ->whereDate('registration_end_at', $this->selectedDate)
            ->get();

        return [
            'events' => $events,
            'meetings' => $meetings,
            'deadlines' => $pathDeadlines,
        ];
    }

    /**
     * Get next upcoming meeting for the user (tanggal berapa, path apa).
     */
    #[Computed]
    public function nextUpcomingMeeting(): ?PathMeeting
    {
        $user = Auth::user();
        $enrolledIds = $user->enrollments()->pluck('learning_path_id')->toArray();

        $query = PathMeeting::with('learningPath')
            ->where('meeting_date', '>=', now())
            ->where('is_completed', false);

        if (count($enrolledIds) > 0) {
            $enrolledMeeting = (clone $query)->whereIn('learning_path_id', $enrolledIds)->orderBy('meeting_date')->first();
            if ($enrolledMeeting) {
                return $enrolledMeeting;
            }
        }

        return $query->orderBy('meeting_date')->first();
    }

    /**
     * Get upcoming events list.
     */
    #[Computed]
    public function upcomingEvents()
    {
        return CalendarEvent::with('learningPath')
            ->where('event_date', '>=', Carbon::today())
            ->orderBy('event_date')
            ->take(5)
            ->get();
    }

    /**
     * Get latest announcements.
     */
    #[Computed]
    public function latestAnnouncements()
    {
        return Announcement::with('learningPath', 'creator')
            ->latest()
            ->take(3)
            ->get();
    }

    /**
     * Get all enrolled learning paths with progress.
     */
    #[Computed]
    public function enrolledPathsData(): array
    {
        $user = Auth::user();
        $enrollments = $user->enrollments()->with('learningPath.materials')->get();

        $results = [];
        foreach ($enrollments as $enrollment) {
            $path = $enrollment->learningPath;
            if (! $path) {
                continue;
            }

            $totalMaterials = $path->materials()->where('is_published', true)->count();
            $completedMaterials = $user->completedMaterials()->where('learning_path_id', $path->id)->count();
            $percentage = $totalMaterials > 0 ? (int) round(($completedMaterials / $totalMaterials) * 100) : 0;
            $nextMeeting = $path->nextMeeting();

            $results[] = [
                'enrollment' => $enrollment,
                'path' => $path,
                'totalMaterials' => $totalMaterials,
                'completedMaterials' => $completedMaterials,
                'percentage' => $percentage,
                'isExpired' => $path->isRegistrationExpired(),
                'nextMeeting' => $nextMeeting,
            ];
        }

        return $results;
    }

    /**
     * General system stats.
     */
    #[Computed]
    public function stats(): array
    {
        return [
            'totalPaths' => LearningPath::where('is_active', true)->count(),
            'totalMaterials' => Material::where('is_published', true)->count(),
            'totalAnnouncements' => Announcement::count(),
            'enrolledCount' => Auth::user()->enrollments()->count(),
        ];
    }

    /**
     * Comprehensive admin metrics.
     */
    #[Computed]
    public function adminStats(): array
    {
        return [
            'totalPaths' => LearningPath::count(),
            'activePaths' => LearningPath::where('is_active', true)->count(),
            'totalMaterials' => Material::count(),
            'publishedMaterials' => Material::where('is_published', true)->count(),
            'totalStudents' => User::where('role', 'user')->count(),
            'totalEnrollments' => LearningPathEnrollment::count(),
            'totalCertificates' => Certificate::where('status', 'issued')->count(),
            'totalMeetings' => PathMeeting::count(),
            'upcomingMeetings' => PathMeeting::where('meeting_date', '>=', now())->count(),
            'totalAnnouncements' => Announcement::count(),
        ];
    }

    /**
     * Learning paths overview for admin with counts.
     */
    #[Computed]
    public function adminPathsOverview()
    {
        return LearningPath::withCount(['enrollments', 'materials', 'meetings'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Latest 5 student enrollments for admin.
     */
    #[Computed]
    public function recentEnrollments()
    {
        return LearningPathEnrollment::with(['user', 'learningPath'])
            ->latest('enrolled_at')
            ->take(5)
            ->get();
    }
};
?>

<div class="flex flex-col gap-6">
    <!-- Top Welcome Banner -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-zinc-900 via-zinc-800 to-zinc-900 p-6 md:p-8 text-white border border-zinc-700/80 shadow-xl">
        <div class="absolute -right-12 -top-12 size-56 rounded-full bg-yellow-400/10 blur-3xl pointer-events-none"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-2">
                @if (auth()->user()->isAdmin())
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-yellow-400/20 text-yellow-300 border border-yellow-400/30">
                        <flux:icon name="shield-check" class="size-3.5" />
                        <span>Administrator Panel Aktif</span>
                    </div>
                    <h1 class="text-2xl md:text-3xl font-bold tracking-tight">
                        Selamat Datang, {{ auth()->user()->name }}! 👋
                    </h1>
                    <p class="text-zinc-400 text-sm max-w-2xl leading-relaxed">
                        Pusat kendali CIT Learning System — Kelola seluruh alur path, kuota pendaftar, modul materi, sesi pertemuan, dan penerbitan sertifikat kelulusan.
                    </p>
                @else
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-yellow-400/20 text-yellow-300 border border-yellow-400/30">
                        <flux:icon name="academic-cap" class="size-3.5" />
                        <span>Peserta CIT Learning System ({{ $this->stats['enrolledCount'] }} Path Terpilih)</span>
                    </div>
                    <h1 class="text-2xl md:text-3xl font-bold tracking-tight">
                        Selamat Datang, {{ auth()->user()->name }}! 👋
                    </h1>
                    <p class="text-zinc-400 text-sm max-w-2xl">
                        Jurusan: <span class="text-zinc-200 font-medium">{{ auth()->user()->major ?? '-' }}</span> |
                        Angkatan: <span class="text-zinc-200 font-medium">{{ auth()->user()->batch_year ?? '-' }}</span>
                    </p>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-3">
                @if (auth()->user()->isAdmin())
                    <flux:button href="{{ route('admin.learning-paths.index') }}" variant="primary" icon="rectangle-stack" wire:navigate>
                        Kelola Path
                    </flux:button>
                    <flux:button href="{{ route('admin.enrollments.index') }}" variant="subtle" icon="users" wire:navigate>
                        Data Pendaftar
                    </flux:button>
                    <flux:button href="{{ route('admin.announcements.index') }}" variant="subtle" icon="envelope" wire:navigate>
                        Pengumuman
                    </flux:button>
                @else
                    <flux:button href="{{ route('learning-paths.index') }}" variant="primary" icon="academic-cap" wire:navigate>
                        Pilih / Kelola Path
                    </flux:button>
                    <flux:button href="{{ route('meetings.index') }}" variant="subtle" icon="calendar-days" wire:navigate>
                        Alur & Pertemuan
                    </flux:button>
                @endif
            </div>
        </div>
    </div>

    <!-- PROMINENT WIDGET: PERTEMUAN SELANJUTNYA (Tanggal berapa & Path apa) -->
    @if ($this->nextUpcomingMeeting)
        @php
            $nextMeet = $this->nextUpcomingMeeting;
            $meetPath = $nextMeet->learningPath;
            $isUserEnrolledInMeetPath = auth()->user()->isEnrolledIn($meetPath->id);
        @endphp
        <div class="relative overflow-hidden rounded-2xl border-2 border-yellow-400/80 bg-gradient-to-br from-yellow-400/15 via-yellow-400/5 to-zinc-900/10 dark:from-yellow-400/20 dark:via-zinc-900 dark:to-zinc-900 p-6 shadow-md">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold bg-yellow-400 text-zinc-950 shadow-xs">
                            <flux:icon name="clock" class="size-3.5" />
                            Pertemuan Selanjutnya
                        </span>

                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-zinc-900 text-white dark:bg-zinc-800 dark:text-yellow-400 border border-zinc-700">
                            <flux:icon name="{{ $meetPath->icon ?: 'academic-cap' }}" class="size-3.5" />
                            Path: {{ $meetPath->title }}
                        </span>

                        @if (auth()->user()->isAdmin())
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-purple-500/20 text-purple-700 dark:text-purple-300">
                                Sesi Terjadwal Sistem
                            </span>
                        @elseif ($isUserEnrolledInMeetPath)
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-700 dark:text-emerald-300">
                                ✓ Path Pilihan Anda
                            </span>
                        @endif
                    </div>

                    <div>
                        <h2 class="text-xl sm:text-2xl font-black text-zinc-900 dark:text-zinc-100 tracking-tight">
                            {{ $nextMeet->title }}
                        </h2>
                        <p class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 mt-1 max-w-3xl leading-relaxed">
                            {{ $nextMeet->description }}
                        </p>
                    </div>

                    <!-- Date, Time, Location -->
                    <div class="flex flex-wrap items-center gap-4 text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                        <div class="flex items-center gap-1.5 bg-white/70 dark:bg-zinc-800/80 px-3 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700">
                            <flux:icon name="calendar" class="size-4 text-yellow-600 dark:text-yellow-400" />
                            <span>{{ $nextMeet->meeting_date->translatedFormat('l, d F Y') }}</span>
                        </div>

                        <div class="flex items-center gap-1.5 bg-white/70 dark:bg-zinc-800/80 px-3 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700">
                            <flux:icon name="clock" class="size-4 text-yellow-600 dark:text-yellow-400" />
                            <span>{{ $nextMeet->meeting_date->format('H:i') }} WIB @if($nextMeet->end_time) - {{ $nextMeet->end_time }} @endif</span>
                        </div>

                        <div class="flex items-center gap-1.5 bg-white/70 dark:bg-zinc-800/80 px-3 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700">
                            <flux:icon name="map-pin" class="size-4 text-yellow-600 dark:text-yellow-400" />
                            <span>{{ $nextMeet->location ?? 'Online Meeting' }}</span>
                        </div>

                        <span class="text-yellow-600 dark:text-yellow-400 font-bold bg-yellow-400/20 px-2.5 py-1 rounded-lg">
                            {{ $nextMeet->meeting_date->diffForHumans() }}
                        </span>
                    </div>

                    @if ($nextMeet->notes)
                        <div class="p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-xs text-zinc-700 dark:text-zinc-300">
                            <strong>Notes Persiapan:</strong> {{ $nextMeet->notes }}
                        </div>
                    @endif
                </div>

                <div class="flex flex-col sm:flex-row lg:flex-col gap-3 shrink-0">
                    @if (auth()->user()->isAdmin())
                        <flux:button href="{{ route('admin.meetings.index', ['selectedPathId' => $meetPath->id]) }}" variant="primary" icon="pencil-square" wire:navigate>
                            Kelola Sesi Ini
                        </flux:button>
                    @endif
                    @if ($nextMeet->meeting_url)
                        <flux:button href="{{ $nextMeet->meeting_url }}" target="_blank" variant="{{ auth()->user()->isAdmin() ? 'subtle' : 'primary' }}" icon="video-camera">
                            Gabung Sesi Live
                        </flux:button>
                    @endif
                    <flux:button href="{{ route(auth()->user()->isAdmin() ? 'admin.meetings.index' : 'meetings.index', ['selectedPathId' => $meetPath->id]) }}" variant="subtle" icon="presentation-chart-line" wire:navigate>
                        Lihat Alur Sesi
                    </flux:button>
                </div>
            </div>
        </div>
    @endif

    @if (auth()->user()->isAdmin())
        <!-- ============================================== -->
        <!-- ADMIN DASHBOARD SECTION -->
        <!-- ============================================== -->

        <!-- 1. Admin KPI Stats (5 cards) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <a href="{{ route('admin.learning-paths.index') }}" class="flex items-center gap-4 p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs hover:border-yellow-400/60 transition-all group" wire:navigate>
                <div class="size-12 rounded-xl bg-yellow-400/10 text-yellow-600 dark:text-yellow-400 flex items-center justify-center font-bold shrink-0 group-hover:scale-105 transition-transform">
                    <flux:icon name="rectangle-stack" class="size-6" />
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 font-medium truncate">Path Learning</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $this->adminStats['activePaths'] }} <span class="text-xs font-normal text-zinc-400">/ {{ $this->adminStats['totalPaths'] }} Aktif</span></p>
                </div>
            </a>

            <a href="{{ route('admin.enrollments.index') }}" class="flex items-center gap-4 p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs hover:border-sky-400/60 transition-all group" wire:navigate>
                <div class="size-12 rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center font-bold shrink-0 group-hover:scale-105 transition-transform">
                    <flux:icon name="users" class="size-6" />
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 font-medium truncate">Peserta Terdaftar</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $this->adminStats['totalStudents'] }} <span class="text-xs font-normal text-zinc-400">({{ $this->adminStats['totalEnrollments'] }} Enrolled)</span></p>
                </div>
            </a>

            <a href="{{ route('admin.materials.index') }}" class="flex items-center gap-4 p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs hover:border-emerald-400/60 transition-all group" wire:navigate>
                <div class="size-12 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold shrink-0 group-hover:scale-105 transition-transform">
                    <flux:icon name="document-text" class="size-6" />
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 font-medium truncate">Modul Materi</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $this->adminStats['publishedMaterials'] }} <span class="text-xs font-normal text-zinc-400">Modul Siap</span></p>
                </div>
            </a>

            <a href="{{ route('admin.meetings.index') }}" class="flex items-center gap-4 p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs hover:border-amber-400/60 transition-all group" wire:navigate>
                <div class="size-12 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold shrink-0 group-hover:scale-105 transition-transform">
                    <flux:icon name="video-camera" class="size-6" />
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 font-medium truncate">Sesi Pertemuan</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $this->adminStats['totalMeetings'] }} <span class="text-xs font-normal text-zinc-400">Total Sesi</span></p>
                </div>
            </a>

            <a href="{{ route('admin.certificates.index') }}" class="flex items-center gap-4 p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs hover:border-purple-400/60 transition-all group" wire:navigate>
                <div class="size-12 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold shrink-0 group-hover:scale-105 transition-transform">
                    <flux:icon name="trophy" class="size-6" />
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 font-medium truncate">Sertifikat Kelulusan</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $this->adminStats['totalCertificates'] }} <span class="text-xs font-normal text-zinc-400">Diterbitkan</span></p>
                </div>
            </a>
        </div>

        <!-- 2. Admin Quick Actions Grid -->
        <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 shadow-xs">
            <div class="flex items-center justify-between pb-3 border-b border-zinc-100 dark:border-zinc-800 mb-4">
                <div class="flex items-center gap-2">
                    <span class="p-1.5 rounded-lg bg-yellow-400/10 text-yellow-600 dark:text-yellow-400">
                        <flux:icon name="command-line" class="size-4" />
                    </span>
                    <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">
                        Pusat Pintasan Navigasi Admin
                    </h3>
                </div>
                <span class="text-xs text-zinc-400">Akses Cepat Pengelolaan</span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-3">
                <a href="{{ route('admin.learning-paths.index') }}" class="flex flex-col items-center text-center p-3 rounded-xl border border-zinc-100 dark:border-zinc-800 hover:border-yellow-400/60 hover:bg-yellow-400/5 transition-all group" wire:navigate>
                    <div class="size-10 rounded-lg bg-yellow-400/10 text-yellow-600 dark:text-yellow-400 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                        <flux:icon name="rectangle-stack" class="size-5" />
                    </div>
                    <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100">Kelola Path</span>
                    <span class="text-[10px] text-zinc-400 mt-0.5">Atur silabus & kuota</span>
                </a>

                <a href="{{ route('admin.meetings.index') }}" class="flex flex-col items-center text-center p-3 rounded-xl border border-zinc-100 dark:border-zinc-800 hover:border-amber-400/60 hover:bg-amber-400/5 transition-all group" wire:navigate>
                    <div class="size-10 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                        <flux:icon name="video-camera" class="size-5" />
                    </div>
                    <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100">Jadwal Sesi</span>
                    <span class="text-[10px] text-zinc-400 mt-0.5">Pertemuan & Zoom</span>
                </a>

                <a href="{{ route('admin.materials.index') }}" class="flex flex-col items-center text-center p-3 rounded-xl border border-zinc-100 dark:border-zinc-800 hover:border-emerald-400/60 hover:bg-emerald-400/5 transition-all group" wire:navigate>
                    <div class="size-10 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                        <flux:icon name="document-text" class="size-5" />
                    </div>
                    <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100">Kelola Materi</span>
                    <span class="text-[10px] text-zinc-400 mt-0.5">Modul & bacaan</span>
                </a>

                <a href="{{ route('admin.enrollments.index') }}" class="flex flex-col items-center text-center p-3 rounded-xl border border-zinc-100 dark:border-zinc-800 hover:border-sky-400/60 hover:bg-sky-400/5 transition-all group" wire:navigate>
                    <div class="size-10 rounded-lg bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                        <flux:icon name="users" class="size-5" />
                    </div>
                    <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100">Data Pendaftar</span>
                    <span class="text-[10px] text-zinc-400 mt-0.5">Daftar siswa & filter</span>
                </a>

                <a href="{{ route('admin.certificates.index') }}" class="flex flex-col items-center text-center p-3 rounded-xl border border-zinc-100 dark:border-zinc-800 hover:border-purple-400/60 hover:bg-purple-400/5 transition-all group" wire:navigate>
                    <div class="size-10 rounded-lg bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                        <flux:icon name="trophy" class="size-5" />
                    </div>
                    <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100">Sertifikat</span>
                    <span class="text-[10px] text-zinc-400 mt-0.5">Terbitkan kelulusan</span>
                </a>

                <a href="{{ route('admin.announcements.index') }}" class="flex flex-col items-center text-center p-3 rounded-xl border border-zinc-100 dark:border-zinc-800 hover:border-indigo-400/60 hover:bg-indigo-400/5 transition-all group" wire:navigate>
                    <div class="size-10 rounded-lg bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                        <flux:icon name="envelope" class="size-5" />
                    </div>
                    <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100">Pengumuman</span>
                    <span class="text-[10px] text-zinc-400 mt-0.5">Kirim info & blast</span>
                </a>

                <a href="{{ route('admin.calendar-events.index') }}" class="flex flex-col items-center text-center p-3 rounded-xl border border-zinc-100 dark:border-zinc-800 hover:border-rose-400/60 hover:bg-rose-400/5 transition-all group" wire:navigate>
                    <div class="size-10 rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                        <flux:icon name="calendar-days" class="size-5" />
                    </div>
                    <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100">Kalender Agenda</span>
                    <span class="text-[10px] text-zinc-400 mt-0.5">Batas waktu kegiatan</span>
                </a>
            </div>
        </div>

        <!-- 3. Status Jalur Pembelajaran & Pendaftar Terbaru -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Learning Paths Status & Quota Capacity (7 cols) -->
            <div class="lg:col-span-7 flex flex-col rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 sm:p-6 shadow-xs">
                <div class="flex items-center justify-between pb-4 border-b border-zinc-100 dark:border-zinc-800 mb-4">
                    <div class="flex items-center gap-2">
                        <span class="p-1.5 rounded-lg bg-yellow-400/10 text-yellow-600 dark:text-yellow-400">
                            <flux:icon name="academic-cap" class="size-4" />
                        </span>
                        <div>
                            <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">
                                Kapasitas & Status Jalur Pembelajaran
                            </h3>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">Pantau kuota terisi, modul, dan pertemuan per path</p>
                        </div>
                    </div>
                    <flux:button href="{{ route('admin.learning-paths.index') }}" variant="subtle" size="xs" wire:navigate>
                        Kelola Path &rarr;
                    </flux:button>
                </div>

                <div class="space-y-4">
                    @foreach ($this->adminPathsOverview as $path)
                        @php
                            $quota = $path->quota;
                            $enrolled = $path->enrollments_count;
                            $percentage = ($quota && $quota > 0) ? min(100, (int) round(($enrolled / $quota) * 100)) : null;
                        @endphp
                        <div class="rounded-xl border border-zinc-200/80 dark:border-zinc-800 p-4 bg-zinc-50/50 dark:bg-zinc-800/30 space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <div class="flex items-center gap-2.5">
                                    <span class="p-2 rounded-lg bg-yellow-400/10 text-yellow-600 dark:text-yellow-400">
                                        <flux:icon name="{{ $path->icon ?: 'academic-cap' }}" class="size-5" />
                                    </span>
                                    <div>
                                        <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">{{ $path->title }}</h4>
                                        <div class="flex flex-wrap items-center gap-3 text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                            <span>{{ $path->materials_count }} Modul</span>
                                            <span>•</span>
                                            <span>{{ $path->meetings_count }} Sesi Pertemuan</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 self-start sm:self-auto">
                                    @if (! $path->is_active)
                                        <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-zinc-200 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400">
                                            Non-Aktif
                                        </span>
                                    @elseif ($path->isRegistrationExpired())
                                        <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20">
                                            Pendaftaran Ditutup
                                        </span>
                                    @elseif ($path->isFull())
                                        <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                            Kuota Penuh
                                        </span>
                                    @else
                                        <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                            Pendaftaran Buka
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <!-- Quota Progress Bar -->
                            <div class="space-y-1.5 pt-1">
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-zinc-600 dark:text-zinc-400 font-medium">
                                        Pendaftar: <strong class="text-zinc-900 dark:text-zinc-100">{{ $enrolled }}</strong> @if($quota) / {{ $quota }} Kursi @else (Tanpa Batas Kuota) @endif
                                    </span>
                                    @if ($percentage !== null)
                                        <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ $percentage }}% Terisi</span>
                                    @endif
                                </div>
                                @if ($percentage !== null)
                                    <div class="w-full bg-zinc-200 dark:bg-zinc-700 h-2 rounded-full overflow-hidden">
                                        <div class="bg-yellow-400 h-full rounded-full transition-all duration-300" style="width: {{ $percentage }}%"></div>
                                    </div>
                                @endif
                            </div>

                            <div class="flex items-center justify-between pt-2 border-t border-zinc-200/60 dark:border-zinc-700/60 text-xs">
                                <a href="{{ route('admin.enrollments.index', ['selectedPathId' => $path->id]) }}" class="text-yellow-600 dark:text-yellow-400 hover:underline flex items-center gap-1 font-medium" wire:navigate>
                                    <flux:icon name="users" class="size-3.5" />
                                    <span>Lihat {{ $enrolled }} Peserta</span>
                                </a>
                                <a href="{{ route('admin.meetings.index', ['selectedPathId' => $path->id]) }}" class="text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-100 flex items-center gap-1" wire:navigate>
                                    <flux:icon name="calendar-days" class="size-3.5" />
                                    <span>Kelola Sesi</span>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Recent Enrollments (5 cols) -->
            <div class="lg:col-span-5 flex flex-col rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 sm:p-6 shadow-xs">
                <div class="flex items-center justify-between pb-4 border-b border-zinc-100 dark:border-zinc-800 mb-4">
                    <div class="flex items-center gap-2">
                        <span class="p-1.5 rounded-lg bg-sky-500/10 text-sky-600 dark:text-sky-400">
                            <flux:icon name="user-plus" class="size-4" />
                        </span>
                        <div>
                            <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">
                                Pendaftar Terbaru
                            </h3>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">5 pendaftaran siswa terakhir</p>
                        </div>
                    </div>
                    <flux:button href="{{ route('admin.enrollments.index') }}" variant="subtle" size="xs" wire:navigate>
                        Lihat Semua &rarr;
                    </flux:button>
                </div>

                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->recentEnrollments as $item)
                        <div class="py-3 first:pt-0 last:pb-0 flex items-start gap-3">
                            <div class="size-9 rounded-full bg-yellow-400/20 text-yellow-600 dark:text-yellow-400 font-bold flex items-center justify-center text-xs shrink-0 mt-0.5">
                                {{ $item->user->initials() }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-1">
                                    <p class="text-xs font-bold text-zinc-900 dark:text-zinc-100 truncate">
                                        {{ $item->user->name }}
                                    </p>
                                    <span class="text-[10px] text-zinc-400 shrink-0">
                                        {{ $item->enrolled_at ? $item->enrolled_at->diffForHumans() : '-' }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-zinc-500 truncate">
                                    {{ $item->user->major ?? 'Umum' }} • Angkatan {{ $item->user->batch_year ?? '-' }}
                                </p>
                                <span class="inline-block mt-1 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 truncate max-w-full">
                                    {{ $item->learningPath->title }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-zinc-400 py-6 text-center italic">Belum ada data pendaftar.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @else
        <!-- ============================================== -->
        <!-- STUDENT DASHBOARD SECTION -->
        <!-- ============================================== -->

        <!-- Enrolled Learning Paths Cards (Support Multiple Paths!) -->
        @if (count($this->enrolledPathsData) > 0)
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                        <flux:icon name="academic-cap" class="size-4 text-yellow-500" />
                        Path Learning Yang Anda Ikuti ({{ count($this->enrolledPathsData) }})
                    </h3>
                    <flux:button href="{{ route('learning-paths.index') }}" variant="subtle" size="xs" wire:navigate>
                        Kelola Pilihan Path
                    </flux:button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach ($this->enrolledPathsData as $data)
                        <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4 shadow-xs space-y-3">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="p-1.5 rounded-lg bg-yellow-400/10 text-yellow-600 dark:text-yellow-400">
                                        <flux:icon name="{{ $data['path']->icon ?: 'academic-cap' }}" class="size-4" />
                                    </span>
                                    <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">{{ $data['path']->title }}</h4>
                                </div>
                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full {{ $data['isExpired'] ? 'bg-zinc-200 dark:bg-zinc-800 text-zinc-500' : 'bg-emerald-500/10 text-emerald-600' }}">
                                    {{ $data['isExpired'] ? 'Terkunci' : 'Aktif' }}
                                </span>
                            </div>

                            <!-- Progress Bar -->
                            <div class="space-y-1">
                                <div class="flex justify-between text-xs text-zinc-500 dark:text-zinc-400">
                                    <span>Progress Modul</span>
                                    <span>{{ $data['completedMaterials'] }}/{{ $data['totalMaterials'] }} Modul ({{ $data['percentage'] }}%)</span>
                                </div>
                                <div class="w-full bg-zinc-100 dark:bg-zinc-800 h-2 rounded-full overflow-hidden">
                                    <div class="bg-yellow-400 h-full rounded-full transition-all duration-300" style="width: {{ $data['percentage'] }}%"></div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between pt-2 border-t border-zinc-100 dark:border-zinc-800 text-xs">
                                <a href="{{ route('meetings.index', ['selectedPathId' => $data['path']->id]) }}" class="text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-100 flex items-center gap-1" wire:navigate>
                                    <flux:icon name="calendar-days" class="size-3.5" />
                                    <span>Alur Pertemuan</span>
                                </a>

                                <flux:button href="{{ route('materials.index', ['selectedPathId' => $data['path']->id]) }}" variant="subtle" size="xs" wire:navigate>
                                    Baca Modul &rarr;
                                </flux:button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <!-- No path enrolled yet -->
            <div class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div class="p-2 rounded-lg bg-amber-500/20 text-amber-600 dark:text-amber-400">
                            <flux:icon name="exclamation-triangle" class="size-6" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100">
                                Anda Belum Memilih Path Learning!
                            </h3>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-0.5">
                                Anda bebas memilih lebih dari satu path yang diminati. Tentukan pilihan sebelum masa pendaftaran ditutup!
                            </p>
                        </div>
                    </div>

                    <flux:button href="{{ route('learning-paths.index') }}" variant="primary" icon="arrow-right" wire:navigate>
                        Pilih Path Sekarang
                    </flux:button>
                </div>
            </div>
        @endif

        <!-- Quick Stats Cards (Student) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="flex items-center gap-4 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs">
                <div class="size-12 rounded-lg bg-yellow-500/10 text-yellow-600 dark:text-yellow-400 flex items-center justify-center font-bold">
                    <flux:icon name="academic-cap" class="size-6" />
                </div>
                <div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">Path Learning Tersedia</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $this->stats['totalPaths'] }}</p>
                </div>
            </div>

            <div class="flex items-center gap-4 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs">
                <div class="size-12 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
                    <flux:icon name="book-open" class="size-6" />
                </div>
                <div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">Modul Materi Siap Baca</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $this->stats['totalMaterials'] }}</p>
                </div>
            </div>

            <div class="flex items-center gap-4 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs">
                <div class="size-12 rounded-lg bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center font-bold">
                    <flux:icon name="megaphone" class="size-6" />
                </div>
                <div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">Pengumuman & Info</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $this->stats['totalAnnouncements'] }}</p>
                </div>
            </div>

            <div class="flex items-center gap-4 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xs">
                <div class="size-12 rounded-lg bg-indigo-500/10 text-indigo-500 flex items-center justify-center font-bold">
                    <flux:icon name="check-badge" class="size-6" />
                </div>
                <div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">Path Anda Ikuti</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $this->stats['enrolledCount'] }} Path</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Main Content: Interactive Calendar & Upcoming Agenda -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Calendar Section (8 cols) -->
        <div class="lg:col-span-8 flex flex-col rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 shadow-xs">
            <div class="flex items-center justify-between pb-5 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-lg bg-yellow-400/10 text-yellow-600 dark:text-yellow-400">
                        <flux:icon name="calendar-days" class="size-6" />
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">
                            {{ Carbon::createFromDate($currentYear, $currentMonth, 1)->translatedFormat('F Y') }}
                        </h2>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            Jadwal sesi pertemuan, batas waktu deadline, dan agenda kegiatan
                        </p>
                    </div>
                </div>

                <!-- Calendar Navigation Controls -->
                <div class="flex items-center gap-1.5">
                    @if (auth()->user()->isAdmin())
                        <flux:button href="{{ route('admin.calendar-events.index') }}" variant="subtle" size="sm" icon="plus" wire:navigate title="Tambah Agenda">
                            Tambah Agenda
                        </flux:button>
                    @endif
                    <flux:button wire:click="previousMonth" variant="subtle" size="sm" icon="chevron-left" title="Bulan Sebelumnya" />
                    <flux:button wire:click="goToToday" variant="subtle" size="sm">
                        Hari Ini
                    </flux:button>
                    <flux:button wire:click="nextMonth" variant="subtle" size="sm" icon="chevron-right" title="Bulan Berikutnya" />
                </div>
            </div>

            <!-- Calendar Days of Week Header -->
            <div class="grid grid-cols-7 gap-1 mt-4 text-center">
                @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $dayName)
                    <div class="py-2 text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                        {{ $dayName }}
                    </div>
                @endforeach
            </div>

            <!-- Calendar Grid -->
            <div class="grid grid-cols-7 gap-1.5 mt-2">
                @foreach ($this->calendarDays as $day)
                    @php
                        $dayClasses = 'min-h-[64px] sm:min-h-[76px] p-1.5 sm:p-2 rounded-xl flex flex-col justify-between transition-all cursor-pointer relative text-left ';

                        if ($day['isSelected']) {
                            $dayClasses .= 'ring-2 ring-yellow-400 bg-yellow-400/10 dark:bg-yellow-400/15 ';
                        } elseif ($day['isToday']) {
                            $dayClasses .= 'border border-yellow-400/70 bg-yellow-50 dark:bg-yellow-950/20 ';
                        } elseif ($day['isCurrentMonth']) {
                            $dayClasses .= 'bg-zinc-50 dark:bg-zinc-800/60 hover:bg-zinc-100 dark:hover:bg-zinc-800 ';
                        } else {
                            $dayClasses .= 'bg-transparent text-zinc-300 dark:text-zinc-600 hover:bg-zinc-50 dark:hover:bg-zinc-800/30 ';
                        }
                    @endphp

                    <div wire:click="selectDate('{{ $day['date'] }}')" class="{{ $dayClasses }}">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold {{ $day['isToday'] ? 'text-yellow-600 dark:text-yellow-400' : ($day['isCurrentMonth'] ? 'text-zinc-800 dark:text-zinc-200' : 'text-zinc-400 dark:text-zinc-600') }}">
                                {{ $day['dayNumber'] }}
                            </span>

                            @if ($day['isToday'])
                                <span class="size-1.5 rounded-full bg-yellow-400"></span>
                            @endif
                        </div>

                        <!-- Dots / Indicators -->
                        <div class="flex flex-wrap gap-1 mt-1">
                            @foreach ($day['pathDeadlines'] as $deadline)
                                <span class="inline-block size-2 rounded-full bg-red-500" title="Deadline: {{ $deadline->title }}"></span>
                            @endforeach

                            @foreach ($day['meetings'] as $meet)
                                <span class="inline-block size-2 rounded-full bg-yellow-400" title="Pertemuan: {{ $meet->title }}"></span>
                            @endforeach

                            @foreach ($day['events'] as $event)
                                @php
                                    $dotColor = match ($event->event_type) {
                                        'deadline' => 'bg-red-500',
                                        'workshop' => 'bg-yellow-400',
                                        default => 'bg-sky-500',
                                    };
                                @endphp
                                <span class="inline-block size-2 rounded-full {{ $dotColor }}" title="{{ $event->title }}"></span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Calendar Legend -->
            <div class="flex flex-wrap items-center gap-4 mt-5 pt-4 border-t border-zinc-100 dark:border-zinc-800 text-xs text-zinc-500 dark:text-zinc-400">
                <div class="flex items-center gap-1.5">
                    <span class="size-2 rounded-full bg-yellow-400"></span>
                    <span>Sesi Pertemuan Path</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="size-2 rounded-full bg-red-500"></span>
                    <span>Batas Akhir / Deadline</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="size-2 rounded-full bg-sky-500"></span>
                    <span>Kegiatan & Agenda Umum</span>
                </div>
                <div class="flex items-center gap-1.5 ml-auto">
                    <span class="size-2 rounded-full border border-yellow-400 bg-yellow-50"></span>
                    <span>Hari Ini</span>
                </div>
            </div>

            <!-- Selected Date Events Drawer -->
            <div class="mt-5 p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200/80 dark:border-zinc-700/80">
                <h4 class="text-sm font-bold text-zinc-900 dark:text-zinc-100 mb-2">
                    Agenda pada {{ Carbon::parse($selectedDate)->translatedFormat('l, d F Y') }}:
                </h4>

                @php
                    $selEvents = $this->selectedDayEvents['events'];
                    $selMeetings = $this->selectedDayEvents['meetings'];
                    $selDeadlines = $this->selectedDayEvents['deadlines'];
                    $hasAny = count($selEvents) > 0 || count($selMeetings) > 0 || count($selDeadlines) > 0;
                @endphp

                @if (! $hasAny)
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 italic">
                        Tidak ada agenda kegiatan, sesi pertemuan, atau batas waktu pada tanggal ini.
                    </p>
                @else
                    <div class="space-y-2 mt-2">
                        @foreach ($selMeetings as $m)
                            <div class="flex items-start gap-2.5 p-2.5 rounded-lg bg-yellow-400/10 border border-yellow-400/30 text-xs">
                                <flux:icon name="video-camera" class="size-4 text-yellow-600 dark:text-yellow-400 shrink-0 mt-0.5" />
                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <p class="font-bold text-zinc-900 dark:text-zinc-100">
                                            {{ $m->title }} ({{ $m->learningPath->title }})
                                        </p>
                                        <span class="text-[10px] text-zinc-500">{{ $m->meeting_date->format('H:i') }} WIB</span>
                                    </div>
                                    <p class="text-zinc-600 dark:text-zinc-400 mt-0.5">{{ $m->description }}</p>
                                    @if ($m->location)
                                        <p class="text-[11px] text-yellow-600 dark:text-yellow-400 mt-1 font-medium">Lokasi: {{ $m->location }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach

                        @foreach ($selDeadlines as $deadline)
                            <div class="flex items-start gap-2.5 p-2.5 rounded-lg bg-red-500/10 border border-red-500/20 text-xs">
                                <flux:icon name="exclamation-circle" class="size-4 text-red-500 shrink-0 mt-0.5" />
                                <div>
                                    <p class="font-bold text-red-600 dark:text-red-400">
                                        Batas Akhir Pemilihan: {{ $deadline->title }}
                                    </p>
                                    <p class="text-zinc-600 dark:text-zinc-400 mt-0.5">
                                        Pukul {{ $deadline->registration_end_at->format('H:i') }} WIB. Setelah waktu ini, pemilihan ditutup otomatis.
                                    </p>
                                </div>
                            </div>
                        @endforeach

                        @foreach ($selEvents as $event)
                            <div class="flex items-start gap-2.5 p-2.5 rounded-lg bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 text-xs">
                                <flux:icon name="calendar" class="size-4 text-sky-500 shrink-0 mt-0.5" />
                                <div>
                                    <div class="flex items-center gap-2">
                                        <p class="font-bold text-zinc-900 dark:text-zinc-100">{{ $event->title }}</p>
                                        @if ($event->event_time)
                                            <span class="text-[10px] text-zinc-500 bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded">{{ $event->event_time }}</span>
                                        @endif
                                    </div>
                                    @if ($event->description)
                                        <p class="text-zinc-600 dark:text-zinc-400 mt-0.5">{{ $event->description }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- Right Side: Upcoming Agenda & Latest Announcements (4 cols) -->
        <div class="lg:col-span-4 flex flex-col gap-6">
            <!-- Upcoming Events Card -->
            <div class="rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5 shadow-xs">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-100 dark:border-zinc-800">
                    <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                        <flux:icon name="clock" class="size-4 text-yellow-500" />
                        Agenda Mendatang
                    </h3>
                    @if (auth()->user()->isAdmin())
                        <flux:button href="{{ route('admin.calendar-events.index') }}" variant="subtle" size="xs" wire:navigate>
                            Kelola Agenda
                        </flux:button>
                    @endif
                </div>

                <div class="divide-y divide-zinc-100 dark:divide-zinc-800 mt-2">
                    @forelse ($this->upcomingEvents as $upEvent)
                        <div class="py-3 first:pt-1 last:pb-0">
                            <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400 mb-1">
                                <span>{{ $upEvent->event_date->translatedFormat('d M Y') }}</span>
                                <span class="px-1.5 py-0.2 rounded text-[10px] font-semibold uppercase {{ $upEvent->event_type === 'deadline' ? 'bg-red-500/10 text-red-500' : 'bg-yellow-400/10 text-yellow-600 dark:text-yellow-400' }}">
                                    {{ $upEvent->event_type }}
                                </span>
                            </div>
                            <p class="text-xs font-bold text-zinc-900 dark:text-zinc-100">{{ $upEvent->title }}</p>
                            @if ($upEvent->event_time)
                                <p class="text-[11px] text-zinc-500 mt-0.5">{{ $upEvent->event_time }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-zinc-400 py-3 italic">Tidak ada agenda mendatang terdekat.</p>
                    @endforelse
                </div>
            </div>

            <!-- Latest Announcements Card -->
            <div class="rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5 shadow-xs">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-100 dark:border-zinc-800">
                    <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                        <flux:icon name="bell" class="size-4 text-yellow-500" />
                        Pengumuman Terbaru
                    </h3>
                    @if (auth()->user()->isAdmin())
                        <flux:button href="{{ route('admin.announcements.index') }}" variant="subtle" size="xs" wire:navigate>
                            Kelola Pengumuman
                        </flux:button>
                    @else
                        <flux:button href="{{ route('announcements.index') }}" variant="subtle" size="xs" wire:navigate>
                            Lihat Semua
                        </flux:button>
                    @endif
                </div>

                <div class="space-y-3 mt-3">
                    @forelse ($this->latestAnnouncements as $announcement)
                        <div class="p-3 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200/60 dark:border-zinc-700/60">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full {{ $announcement->priority === 'urgent' ? 'bg-red-500/20 text-red-600 dark:text-red-400' : ($announcement->priority === 'important' ? 'bg-amber-500/20 text-amber-600 dark:text-amber-400' : 'bg-sky-500/20 text-sky-600 dark:text-sky-400') }}">
                                    {{ strtoupper($announcement->priority) }}
                                </span>
                                <span class="text-[10px] text-zinc-400">
                                    {{ $announcement->created_at->diffForHumans() }}
                                </span>
                            </div>
                            <h4 class="text-xs font-bold text-zinc-900 dark:text-zinc-100 leading-snug">
                                {{ $announcement->title }}
                            </h4>
                            <p class="text-[11px] text-zinc-600 dark:text-zinc-400 line-clamp-2 mt-1">
                                {{ Str::limit($announcement->content, 90) }}
                            </p>
                        </div>
                    @empty
                        <p class="text-xs text-zinc-400 py-2 italic">Belum ada pengumuman.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
