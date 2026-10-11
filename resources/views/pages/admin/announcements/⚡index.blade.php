<?php

use App\Mail\AnnouncementNotificationMail;
use App\Models\Announcement;
use App\Models\LearningPath;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pengumuman & Push Email - Admin')] class extends Component {
    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public ?int $editingAnnouncementId = null;

    // Form fields
    public string $title = '';
    public string $content = '';
    public ?int $learning_path_id = null;
    public string $priority = 'normal'; // 'normal', 'important', 'urgent'
    public bool $sendEmailImmediately = true;

    /**
     * Get learning paths for target dropdown.
     */
    #[Computed]
    public function learningPaths()
    {
        return LearningPath::orderBy('title')->get();
    }

    /**
     * Get all announcements.
     */
    #[Computed]
    public function announcements()
    {
        return Announcement::with('learningPath', 'creator')->latest()->get();
    }

    /**
     * Open create modal.
     */
    public function openCreateModal(): void
    {
        $this->reset(['title', 'content', 'learning_path_id', 'editingAnnouncementId']);
        $this->priority = 'normal';
        $this->sendEmailImmediately = true;
        $this->showCreateModal = true;
    }

    /**
     * Create announcement and optionally send push email notification.
     */
    public function createAnnouncement(): void
    {
        $validated = $this->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'learning_path_id' => 'nullable|exists:learning_paths,id',
            'priority' => 'required|in:normal,important,urgent',
        ]);

        $announcement = Announcement::create([
            ...$validated,
            'created_by' => Auth::id(),
            'is_sent_to_email' => false,
            'sent_at' => null,
        ]);

        $recipientCount = 0;

        if ($this->sendEmailImmediately) {
            $recipientCount = $this->dispatchEmailNotification($announcement);
        }

        $this->showCreateModal = false;

        if ($recipientCount > 0) {
            Flux::toast(
                variant: 'success',
                text: __('Pengumuman dibuat & push email berhasil dikirim ke :count pendaftar!', ['count' => $recipientCount])
            );
        } else {
            Flux::toast(variant: 'success', text: __('Pengumuman berhasil dipublikasikan!'));
        }
    }

    /**
     * Send or resend push email for an announcement.
     */
    public function sendEmail(int $id): void
    {
        $announcement = Announcement::findOrFail($id);
        $count = $this->dispatchEmailNotification($announcement);

        Flux::toast(
            variant: 'success',
            text: __('Push email notifikasi berhasil dikirim ke :count pendaftar!', ['count' => $count])
        );
    }

    /**
     * Helper to dispatch emails to targeted students.
     */
    protected function dispatchEmailNotification(Announcement $announcement): int
    {
        // Find targeted users
        if ($announcement->learning_path_id) {
            $recipients = User::whereHas('enrollment', function ($q) use ($announcement) {
                $q->where('learning_path_id', $announcement->learning_path_id);
            })->get();
        } else {
            $recipients = User::where('role', 'user')->get();
        }

        $count = 0;
        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient->email)->send(
                    new AnnouncementNotificationMail($announcement, $recipient)
                );
                $count++;
            } catch (\Throwable $e) {
                Log::error('Failed sending announcement email to '.$recipient->email.': '.$e->getMessage());
            }
        }

        $announcement->update([
            'is_sent_to_email' => true,
            'sent_at' => now(),
        ]);

        return $count;
    }

    /**
     * Delete announcement.
     */
    public function deleteAnnouncement(int $id): void
    {
        $ann = Announcement::findOrFail($id);
        $ann->delete();

        Flux::toast(variant: 'warning', text: __('Pengumuman telah dihapus.'));
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
                Pengumuman & Push Notifikasi Email
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                Kirim informasi dan broadcast push notifikasi via email ke seluruh pendaftar atau peserta path tertentu.
            </p>
        </div>

        <flux:button wire:click="openCreateModal" variant="primary" icon="plus">
            Buat Pengumuman & Broadcast Email
        </flux:button>
    </div>

    <!-- Table of Announcements -->
    <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-zinc-50 dark:bg-zinc-800/60 text-zinc-600 dark:text-zinc-400 uppercase font-semibold border-b border-zinc-200 dark:border-zinc-800">
                    <tr>
                        <th class="px-5 py-3.5">Judul Pengumuman</th>
                        <th class="px-5 py-3.5">Target Penerima</th>
                        <th class="px-5 py-3.5">Prioritas</th>
                        <th class="px-5 py-3.5">Status Push Email</th>
                        <th class="px-5 py-3.5">Tanggal</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->announcements as $ann)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-colors">
                            <td class="px-5 py-4">
                                <p class="font-bold text-zinc-900 dark:text-zinc-100 text-sm">{{ $ann->title }}</p>
                                <p class="text-zinc-400 text-[11px] truncate max-w-sm mt-0.5">{{ Str::limit($ann->content, 90) }}</p>
                            </td>
                            <td class="px-5 py-4 font-medium text-zinc-700 dark:text-zinc-300">
                                @if ($ann->learningPath)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold bg-yellow-400/10 text-yellow-700 dark:text-yellow-300">
                                        {{ $ann->learningPath->title }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400">
                                        Semua Mahasiswa
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $ann->priority === 'urgent' ? 'bg-red-500/20 text-red-600 dark:text-red-400' : ($ann->priority === 'important' ? 'bg-amber-500/20 text-amber-600 dark:text-amber-400' : 'bg-sky-500/20 text-sky-600 dark:text-sky-400') }}">
                                    {{ $ann->priority }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                @if ($ann->is_sent_to_email)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                        <flux:icon name="check-circle" class="size-3" />
                                        Terkirim ({{ $ann->sent_at?->translatedFormat('d M, H:i') }})
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-500">
                                        Belum Dikirim Email
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-zinc-400">
                                {{ $ann->created_at->translatedFormat('d M Y') }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <flux:button
                                        wire:click="sendEmail({{ $ann->id }})"
                                        wire:confirm="Kirim push notifikasi email sekarang ke pendaftar target?"
                                        variant="subtle"
                                        size="xs"
                                        icon="envelope"
                                        title="Kirim / Resend Email"
                                    >
                                        {{ $ann->is_sent_to_email ? 'Kirim Ulang' : 'Kirim Email' }}
                                    </flux:button>

                                    <flux:button wire:click="deleteAnnouncement({{ $ann->id }})" wire:confirm="Hapus pengumuman ini?" variant="danger" size="xs" icon="trash">
                                        Hapus
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-zinc-400 italic">
                                Belum ada pengumuman yang dibuat.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Modal -->
    <flux:modal wire:model="showCreateModal" class="md:w-[650px]">
        <form wire:submit="createAnnouncement" class="space-y-4">
            <flux:heading size="lg">Buat Pengumuman & Broadcast Email</flux:heading>
            <flux:text class="text-xs">Pengumuman akan tampil di portal peserta dan dapat dikirim otomatis via email.</flux:text>

            <flux:field>
                <flux:label>Judul Pengumuman</flux:label>
                <flux:input wire:model="title" placeholder="Contoh: Pengingat Batas Akhir Pemilihan Path Learning" required />
                <flux:error name="title" />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Target Penerima</flux:label>
                    <flux:select wire:model="learning_path_id">
                        <option value="">Semua Pendaftar (Seluruh Mahasiswa)</option>
                        @foreach ($this->learningPaths as $lp)
                            <option value="{{ $lp->id }}">Khusus Path: {{ $lp->title }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="learning_path_id" />
                </flux:field>

                <flux:field>
                    <flux:label>Tingkat Prioritas</flux:label>
                    <flux:select wire:model="priority" required>
                        <option value="normal">Normal</option>
                        <option value="important">Penting (Important)</option>
                        <option value="urgent">Mendesak (Urgent)</option>
                    </flux:select>
                    <flux:error name="priority" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Isi Pesan Pengumuman</flux:label>
                <flux:textarea wire:model="content" rows="6" placeholder="Tuliskan informasi detail pengumuman di sini..." required />
                <flux:error name="content" />
            </flux:field>

            <div class="p-3.5 rounded-xl bg-yellow-400/10 border border-yellow-400/30">
                <flux:field>
                    <flux:checkbox
                        wire:model="sendEmailImmediately"
                        label="Kirim Push Notifikasi Email ke Pendaftar Sekarang"
                    />
                </flux:field>
                <p class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-1 pl-6">
                    Sistem akan mengirimkan email berdesain CIT Learning System langsung ke alamat email seluruh pendaftar yang ditargetkan.
                </p>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button wire:click="$set('showCreateModal', false)" variant="subtle">
                    Batal
                </flux:button>
                <flux:button type="submit" variant="primary" icon="paper-airplane">
                    Publikasikan & Kirim
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
