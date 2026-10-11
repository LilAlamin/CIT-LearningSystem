<?php

use App\Models\LearningPath;
use App\Models\LearningPathEnrollment;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pemilihan Path Learning')] class extends Component {
    public ?int $selectedPathForModal = null;
    public bool $showSyllabusModal = false;

    /**
     * Get all active learning paths with relationships.
     */
    #[Computed]
    public function learningPaths()
    {
        return LearningPath::with(['materials' => function ($query) {
            $query->where('is_published', true)->orderBy('order');
        }, 'meetings' => function ($query) {
            $query->orderBy('meeting_number');
        }])
            ->withCount('enrollments')
            ->where('is_active', true)
            ->get();
    }

    /**
     * Get IDs of paths currently chosen by the user.
     */
    #[Computed]
    public function enrolledPathIds(): array
    {
        return Auth::user()->enrollments()->pluck('learning_path_id')->toArray();
    }

    /**
     * Select a learning path.
     */
    public function selectPath(int $pathId): void
    {
        if (! in_array($pathId, $this->enrolledPathIds, true)) {
            $this->toggleEnrollment($pathId);
        }
    }

    /**
     * Unselect a learning path.
     */
    public function unselectPath(int $pathId): void
    {
        if (in_array($pathId, $this->enrolledPathIds, true)) {
            $this->toggleEnrollment($pathId);
        }
    }

    /**
     * Toggle enrollment (select or cancel selection) for a learning path.
     */
    public function toggleEnrollment(int $pathId): void
    {
        $user = Auth::user();
        $path = LearningPath::findOrFail($pathId);
        $isEnrolled = in_array($path->id, $this->enrolledPathIds, true);

        // 1. Strict Deadline Check: if expired, cannot enroll or unenroll
        if ($path->isRegistrationExpired()) {
            Flux::toast(
                variant: 'danger',
                text: __('Batas waktu pemilihan telah berakhir untuk path ini. Anda tidak dapat mengubah pilihan.')
            );
            return;
        }

        // 2. Upcoming Check
        if ($path->isRegistrationUpcoming()) {
            Flux::toast(
                variant: 'warning',
                text: __('Periode pemilihan untuk path ini belum dibuka.')
            );
            return;
        }

        if ($isEnrolled) {
            // Unenroll from this path
            LearningPathEnrollment::where('user_id', $user->id)
                ->where('learning_path_id', $path->id)
                ->delete();

            unset($this->enrolledPathIds);

            Flux::toast(
                variant: 'info',
                text: __('Pendaftaran pada path :title berhasil dibatalkan.', ['title' => $path->title])
            );
        } else {
            // 3. Quota Check
            if ($path->isFull()) {
                Flux::toast(
                    variant: 'danger',
                    text: __('Kuota untuk path ini sudah penuh. Silakan pilih path lain.')
                );
                return;
            }

            // Enroll in this path
            LearningPathEnrollment::create([
                'user_id' => $user->id,
                'learning_path_id' => $path->id,
                'enrolled_at' => now(),
            ]);

            unset($this->enrolledPathIds);

            Flux::toast(
                variant: 'success',
                text: __('Berhasil memilih path: :title!', ['title' => $path->title])
            );
        }
    }

    /**
     * View syllabus modal for a learning path.
     */
    public function viewSyllabus(int $pathId): void
    {
        $this->selectedPathForModal = $pathId;
        $this->showSyllabusModal = true;
    }
};
?>

<div class="flex flex-col gap-6">
    <!-- Header Page -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                Pemilihan Path Learning
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                Anda bebas memilih lebih dari 1 path learning yang diminati. Perhatikan batas waktu pendaftaran pada masing-masing path!
            </p>
        </div>

        <div class="flex items-center gap-2">
            @if (count($this->enrolledPathIds) > 0)
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-semibold">
                    <flux:icon name="check-circle" class="size-4" />
                    <span>Terdaftar di {{ count($this->enrolledPathIds) }} Path Learning</span>
                </div>
            @else
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400 text-xs font-semibold">
                    <flux:icon name="exclamation-circle" class="size-4" />
                    <span>Anda belum memilih path</span>
                </div>
            @endif
        </div>
    </div>

    <!-- Alert Information -->
    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900/60 text-xs text-zinc-600 dark:text-zinc-400 flex items-start gap-3">
        <flux:icon name="information-circle" class="size-5 text-yellow-500 shrink-0 mt-0.5" />
        <div class="space-y-1">
            <p class="font-bold text-zinc-800 dark:text-zinc-200">
                Aturan & Ketentuan Pemilihan Path:
            </p>
            <ul class="list-disc list-inside space-y-0.5 text-zinc-500 dark:text-zinc-400">
                <li>Mahasiswa <strong>bebas memilih lebih dari satu</strong> path learning sesuai minat dan kapasitas belajar.</li>
                <li>Pemilihan dan pembatalan dapat dilakukan secara fleksibel selama jangka waktu pemilihan masih aktif.</li>
                <li><strong>Jika batas waktu telah lewat, sistem otomatis mengunci pilihan</strong> dan Anda tidak dapat memilih atau mengubah path lagi.</li>
                <li>Setiap path memiliki alur pertemuan terstruktur yang dapat dipantau pada menu <strong>Alur & Pertemuan</strong>.</li>
            </ul>
        </div>
    </div>

    <!-- Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @foreach ($this->learningPaths as $path)
            @php
                $isEnrolled = in_array($path->id, $this->enrolledPathIds, true);
                $isExpired = $path->isRegistrationExpired();
                $isUpcoming = $path->isRegistrationUpcoming();
                $isFull = $path->isFull();
                $isOpen = $path->isOpenForRegistration();
                $quotaFilled = $path->enrollments_count;
                $quotaMax = $path->quota;
                $quotaPercent = $quotaMax ? min(100, (int) round(($quotaFilled / $quotaMax) * 100)) : 0;
            @endphp

            <div class="flex flex-col justify-between rounded-2xl border transition-all duration-200 p-6 {{ $isEnrolled ? 'border-yellow-400 ring-2 ring-yellow-400/30 bg-yellow-500/[0.03] dark:bg-yellow-400/[0.04]' : 'border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 hover:border-zinc-300 dark:hover:border-zinc-700' }} shadow-xs">
                <div class="space-y-4">
                    <!-- Top Badge & Status -->
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="p-2 rounded-xl bg-yellow-400/10 text-yellow-600 dark:text-yellow-400 font-bold">
                                <flux:icon name="{{ $path->icon ?: 'academic-cap' }}" class="size-6" />
                            </span>
                            @if ($isEnrolled)
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-yellow-400 text-zinc-950 flex items-center gap-1 shadow-xs">
                                    <flux:icon name="check" class="size-3" />
                                    Terdaftar di Path Ini
                                </span>
                            @endif
                        </div>

                        <!-- Status Badge -->
                        <div>
                            @if ($isExpired)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20">
                                    <flux:icon name="lock-closed" class="size-3" />
                                    Pendaftaran Ditutup
                                </span>
                            @elseif ($isUpcoming)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                    <flux:icon name="clock" class="size-3" />
                                    Segera Dibuka
                                </span>
                            @elseif ($isFull)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-zinc-500/10 text-zinc-600 dark:text-zinc-400 border border-zinc-500/20">
                                    <flux:icon name="user-minus" class="size-3" />
                                    Kuota Penuh
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                    <flux:icon name="check-circle" class="size-3" />
                                    Pendaftaran Dibuka
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Title & Description -->
                    <div>
                        <h2 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">
                            {{ $path->title }}
                        </h2>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2 leading-relaxed">
                            {{ $path->description }}
                        </p>
                    </div>

                    <!-- Registration Deadline Window Info -->
                    <div class="p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200/60 dark:border-zinc-700/60 text-xs space-y-1.5">
                        <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                            <span class="flex items-center gap-1.5">
                                <flux:icon name="calendar" class="size-3.5" />
                                Periode Pemilihan:
                            </span>
                            <span class="font-medium text-zinc-800 dark:text-zinc-200">
                                @if ($path->registration_start_at && $path->registration_end_at)
                                    {{ $path->registration_start_at->translatedFormat('d M') }} - {{ $path->registration_end_at->translatedFormat('d M Y, H:i') }} WIB
                                @elseif ($path->registration_end_at)
                                    s/d {{ $path->registration_end_at->translatedFormat('d M Y, H:i') }} WIB
                                @else
                                    Tidak dibatasi
                                @endif
                            </span>
                        </div>

                        <!-- Relative Deadline Countdown -->
                        @if ($path->registration_end_at)
                            <div class="flex items-center justify-between font-semibold">
                                <span class="text-zinc-500 dark:text-zinc-400">Batas Waktu:</span>
                                @if ($isExpired)
                                    <span class="text-red-600 dark:text-red-400">Telah Berakhir ({{ $path->registration_end_at->diffForHumans() }})</span>
                                @else
                                    <span class="text-emerald-600 dark:text-emerald-400">{{ $path->registration_end_at->diffForHumans() }}</span>
                                @endif
                            </div>
                        @endif
                    </div>

                    <!-- Info Pertemuan & Quota -->
                    <div class="grid grid-cols-2 gap-3 pt-1 text-xs">
                        <div class="p-2.5 rounded-xl bg-zinc-100 dark:bg-zinc-800/80">
                            <p class="text-zinc-500 dark:text-zinc-400 text-[11px]">Agenda Belajar</p>
                            <p class="font-bold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $path->meetings->count() }} Sesi Pertemuan</p>
                        </div>
                        <div class="p-2.5 rounded-xl bg-zinc-100 dark:bg-zinc-800/80">
                            <p class="text-zinc-500 dark:text-zinc-400 text-[11px]">Modul Materi</p>
                            <p class="font-bold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $path->materials->count() }} Modul Teks</p>
                        </div>
                    </div>

                    <!-- Quota Progress Bar -->
                    @if ($quotaMax)
                        <div class="space-y-1.5">
                            <div class="flex justify-between text-xs text-zinc-600 dark:text-zinc-400 font-medium">
                                <span>Kapasitas Peserta</span>
                                <span>{{ $quotaFilled }}/{{ $quotaMax }} Terisi ({{ $quotaPercent }}%)</span>
                            </div>
                            <div class="w-full bg-zinc-100 dark:bg-zinc-800 h-2 rounded-full overflow-hidden">
                                <div class="bg-yellow-400 h-full rounded-full transition-all duration-300" style="width: {{ $quotaPercent }}%"></div>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Footer Actions -->
                <div class="mt-6 pt-4 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <flux:button wire:click="viewSyllabus({{ $path->id }})" variant="subtle" size="sm" icon="book-open">
                            Silabus
                        </flux:button>
                        <flux:button href="{{ route('meetings.index', ['selectedPathId' => $path->id]) }}" variant="subtle" size="sm" icon="calendar-days" wire:navigate>
                            Alur Sesi
                        </flux:button>
                    </div>

                    <div>
                        @if ($isExpired)
                            @if ($isEnrolled)
                                <flux:badge variant="solid" color="zinc" icon="lock-closed">
                                    Terkunci (Terdaftar)
                                </flux:badge>
                            @else
                                <flux:tooltip content="Batas waktu pemilihan telah terlewat. Anda tidak dapat memilih path ini.">
                                    <flux:button variant="danger" size="sm" disabled icon="lock-closed" class="opacity-75 cursor-not-allowed">
                                        Waktu Habis
                                    </flux:button>
                                </flux:tooltip>
                            @endif
                        @elseif ($isUpcoming)
                            <flux:button variant="subtle" size="sm" disabled class="opacity-75 cursor-not-allowed">
                                Belum Dibuka
                            </flux:button>
                        @elseif ($isEnrolled)
                            <flux:button
                                wire:click="toggleEnrollment({{ $path->id }})"
                                wire:confirm="Apakah Anda yakin ingin membatalkan pendaftaran pada path {{ $path->title }}?"
                                variant="subtle"
                                size="sm"
                                class="text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/20"
                            >
                                Batalkan Pilihan
                            </flux:button>
                        @elseif ($isFull)
                            <flux:button variant="subtle" size="sm" disabled class="opacity-75 cursor-not-allowed">
                                Penuh
                            </flux:button>
                        @else
                            <flux:button
                                wire:click="toggleEnrollment({{ $path->id }})"
                                wire:loading.attr="disabled"
                                variant="primary"
                                size="sm"
                                icon="check"
                            >
                                Pilih Path Ini
                            </flux:button>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Syllabus Modal -->
    <flux:modal wire:model="showSyllabusModal" class="md:w-[650px]">
        @php
            $modalPath = $this->selectedPathForModal ? LearningPath::with(['materials', 'meetings'])->find($this->selectedPathForModal) : null;
        @endphp

        @if ($modalPath)
            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <span class="p-2 rounded-xl bg-yellow-400/10 text-yellow-600 dark:text-yellow-400 font-bold">
                        <flux:icon name="{{ $modalPath->icon ?: 'academic-cap' }}" class="size-6" />
                    </span>
                    <div>
                        <flux:heading size="lg">{{ $modalPath->title }}</flux:heading>
                        <flux:text class="text-xs">Silabus Modul Materi & Rangkaian Pertemuan</flux:text>
                    </div>
                </div>

                <div class="space-y-2 mt-4 max-h-[60vh] overflow-y-auto pr-1">
                    @forelse ($modalPath->materials as $mat)
                        <div class="p-3 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-800/50 flex items-start gap-3">
                            <span class="size-6 rounded-full bg-yellow-400/20 text-yellow-600 dark:text-yellow-400 text-xs font-bold flex items-center justify-center shrink-0 mt-0.5">
                                {{ $mat->order }}
                            </span>
                            <div class="flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <h4 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">{{ $mat->title }}</h4>
                                    @if ($mat->pathMeeting)
                                        <span class="px-2 py-0.2 rounded text-[10px] font-semibold bg-yellow-400/15 text-yellow-700 dark:text-yellow-300">
                                            Pertemuan {{ $mat->pathMeeting->meeting_number }}
                                        </span>
                                    @endif
                                </div>
                                @if ($mat->summary)
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">{{ $mat->summary }}</p>
                                @endif
                                <div class="flex items-center gap-3 mt-2 text-[11px] text-zinc-400">
                                    <span>Estimasi: {{ $mat->read_time_minutes }} menit baca</span>
                                    <a href="{{ route('materials.show', [$modalPath->slug, $mat->slug]) }}" class="text-yellow-600 dark:text-yellow-400 font-medium hover:underline" wire:navigate>
                                        Baca Sekarang &rarr;
                                    </a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-zinc-400 italic py-4 text-center">Belum ada modul materi pada path ini.</p>
                    @endforelse
                </div>

                <div class="flex justify-end pt-3 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:button wire:click="$set('showSyllabusModal', false)" variant="subtle">
                        Tutup
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
