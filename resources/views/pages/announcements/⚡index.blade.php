<?php

use App\Models\Announcement;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pengumuman & Informasi')] class extends Component {
    public string $filter = 'all'; // 'all', 'mine'

    /**
     * Get announcements.
     */
    #[Computed]
    public function announcements()
    {
        $user = Auth::user();
        $userPathId = $user->enrollment?->learning_path_id;

        $query = Announcement::with('learningPath', 'creator')->latest();

        if ($this->filter === 'mine' && $userPathId) {
            $query->where(function ($q) use ($userPathId) {
                $q->whereNull('learning_path_id')
                    ->orWhere('learning_path_id', $userPathId);
            });
        }

        return $query->get();
    }
};
?>

<div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
        <div>
            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-400/10 text-yellow-600 dark:text-yellow-400 mb-2 border border-yellow-400/20">
                <flux:icon name="bell" class="size-3.5" />
                <span>Pusat Informasi CIT</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                Pengumuman & Informasi
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                Informasi penting mengenai jadwal, deadline pemilihan path, workshop, dan notifikasi resmi CIT.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <button
                type="button"
                wire:click="$set('filter', 'all')"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold cursor-pointer transition-all {{ $filter === 'all' ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400' }}"
            >
                Semua Pengumuman
            </button>
            <button
                type="button"
                wire:click="$set('filter', 'mine')"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold cursor-pointer transition-all {{ $filter === 'mine' ? 'bg-yellow-400 text-zinc-950 font-bold' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400' }}"
            >
                Khusus Path Saya
            </button>
        </div>
    </div>

    <!-- Announcements List -->
    <div class="space-y-4">
        @forelse ($this->announcements as $announcement)
            <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-6 shadow-xs space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase {{ $announcement->priority === 'urgent' ? 'bg-red-500/20 text-red-600 dark:text-red-400' : ($announcement->priority === 'important' ? 'bg-amber-500/20 text-amber-600 dark:text-amber-400' : 'bg-sky-500/20 text-sky-600 dark:text-sky-400') }}">
                            {{ $announcement->priority }}
                        </span>

                        @if ($announcement->learningPath)
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300">
                                Path: {{ $announcement->learningPath->title }}
                            </span>
                        @else
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300">
                                Semua Peserta
                            </span>
                        @endif

                        @if ($announcement->is_sent_to_email)
                            <span class="inline-flex items-center gap-1 text-[11px] text-zinc-400">
                                <flux:icon name="envelope" class="size-3" />
                                Terkirim ke Email
                            </span>
                        @endif
                    </div>

                    <span class="text-xs text-zinc-400">
                        {{ $announcement->created_at->translatedFormat('d F Y, H:i') }} WIB ({{ $announcement->created_at->diffForHumans() }})
                    </span>
                </div>

                <div>
                    <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">
                        {{ $announcement->title }}
                    </h2>
                    <div class="prose dark:prose-invert max-w-none text-sm text-zinc-700 dark:text-zinc-300 mt-2 leading-relaxed">
                        {!! nl2br(e($announcement->content)) !!}
                    </div>
                </div>

                <div class="pt-3 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-between text-xs text-zinc-400">
                    <span>Oleh: {{ $announcement->creator->name ?? 'Admin CIT' }}</span>
                </div>
            </div>
        @empty
            <div class="py-12 text-center rounded-2xl border border-dashed border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900/40">
                <flux:icon name="bell-slash" class="size-10 mx-auto text-zinc-400 mb-2" />
                <h3 class="text-sm font-bold text-zinc-800 dark:text-zinc-200">Tidak ada pengumuman</h3>
                <p class="text-xs text-zinc-500 mt-1">Saat ini belum ada pengumuman baru.</p>
            </div>
        @endforelse
    </div>
</div>
