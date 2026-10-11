<?php

use App\Models\CalendarEvent;
use App\Models\LearningPath;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Agenda Kalender - Admin')] class extends Component {
    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public ?int $editingEventId = null;

    // Form fields
    public string $title = '';
    public string $description = '';
    public string $event_date = '';
    public string $event_time = '';
    public string $event_type = 'event'; // 'event', 'deadline', 'workshop', 'session'
    public ?int $learning_path_id = null;

    /**
     * Get all calendar events.
     */
    #[Computed]
    public function events()
    {
        return CalendarEvent::with('learningPath')->orderBy('event_date')->get();
    }

    /**
     * Get learning paths.
     */
    #[Computed]
    public function learningPaths()
    {
        return LearningPath::orderBy('title')->get();
    }

    /**
     * Open create modal.
     */
    public function openCreateModal(): void
    {
        $this->reset(['title', 'description', 'event_time', 'learning_path_id', 'editingEventId']);
        $this->event_date = now()->toDateString();
        $this->event_type = 'event';
        $this->showCreateModal = true;
    }

    /**
     * Save event.
     */
    public function createEvent(): void
    {
        $validated = $this->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'event_date' => 'required|date',
            'event_time' => 'nullable|string|max:50',
            'event_type' => 'required|in:event,deadline,workshop,session',
            'learning_path_id' => 'nullable|exists:learning_paths,id',
        ]);

        CalendarEvent::create($validated);

        $this->showCreateModal = false;
        Flux::toast(variant: 'success', text: __('Agenda kalender berhasil ditambahkan!'));
    }

    /**
     * Open edit modal.
     */
    public function editEvent(int $id): void
    {
        $event = CalendarEvent::findOrFail($id);
        $this->editingEventId = $event->id;
        $this->title = $event->title;
        $this->description = $event->description ?? '';
        $this->event_date = $event->event_date->toDateString();
        $this->event_time = $event->event_time ?? '';
        $this->event_type = $event->event_type;
        $this->learning_path_id = $event->learning_path_id;

        $this->showEditModal = true;
    }

    /**
     * Update event.
     */
    public function updateEvent(): void
    {
        $event = CalendarEvent::findOrFail($this->editingEventId);

        $validated = $this->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'event_date' => 'required|date',
            'event_time' => 'nullable|string|max:50',
            'event_type' => 'required|in:event,deadline,workshop,session',
            'learning_path_id' => 'nullable|exists:learning_paths,id',
        ]);

        $event->update($validated);

        $this->showEditModal = false;
        Flux::toast(variant: 'success', text: __('Agenda berhasil diperbarui!'));
    }

    /**
     * Delete event.
     */
    public function deleteEvent(int $id): void
    {
        $ev = CalendarEvent::findOrFail($id);
        $ev->delete();

        Flux::toast(variant: 'warning', text: __('Agenda telah dihapus.'));
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
                Kelola Agenda Kalender
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                Jadwalkan kegiatan, workshop, dan batas waktu yang otomatis tampil di kalender dashboard seluruh peserta.
            </p>
        </div>

        <flux:button wire:click="openCreateModal" variant="primary" icon="plus">
            Tambah Agenda Baru
        </flux:button>
    </div>

    <!-- Table of Events -->
    <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-zinc-50 dark:bg-zinc-800/60 text-zinc-600 dark:text-zinc-400 uppercase font-semibold border-b border-zinc-200 dark:border-zinc-800">
                    <tr>
                        <th class="px-5 py-3.5">Tanggal & Waktu</th>
                        <th class="px-5 py-3.5">Nama Kegiatan / Agenda</th>
                        <th class="px-5 py-3.5">Tipe Agenda</th>
                        <th class="px-5 py-3.5">Path Terkait</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->events as $event)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-colors">
                            <td class="px-5 py-4">
                                <p class="font-bold text-zinc-900 dark:text-zinc-100">{{ $event->event_date->translatedFormat('d F Y') }}</p>
                                <p class="text-zinc-400 text-[11px]">{{ $event->event_time ?? '-' }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <p class="font-bold text-zinc-900 dark:text-zinc-100 text-sm">{{ $event->title }}</p>
                                @if ($event->description)
                                    <p class="text-zinc-400 text-[11px] truncate max-w-sm mt-0.5">{{ $event->description }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $event->event_type === 'deadline' ? 'bg-red-500/20 text-red-600 dark:text-red-400' : ($event->event_type === 'workshop' ? 'bg-yellow-400/20 text-yellow-800 dark:text-yellow-300' : 'bg-sky-500/20 text-sky-600 dark:text-sky-400') }}">
                                    {{ $event->event_type }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                @if ($event->learningPath)
                                    <span class="inline-flex items-center gap-1 text-[11px] font-medium text-zinc-700 dark:text-zinc-300">
                                        {{ $event->learningPath->title }}
                                    </span>
                                @else
                                    <span class="text-zinc-400 text-[11px]">Semua Path</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <flux:button wire:click="editEvent({{ $event->id }})" variant="subtle" size="xs" icon="pencil-square">
                                        Edit
                                    </flux:button>
                                    <flux:button wire:click="deleteEvent({{ $event->id }})" wire:confirm="Hapus agenda ini?" variant="danger" size="xs" icon="trash">
                                        Hapus
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-zinc-400 italic">
                                Belum ada agenda kegiatan yang ditambahkan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Modal -->
    <flux:modal wire:model="showCreateModal" class="md:w-[600px]">
        <form wire:submit="createEvent" class="space-y-4">
            <flux:heading size="lg">Tambah Agenda Kegiatan</flux:heading>
            <flux:text class="text-xs">Agenda akan ditampilkan pada kalender dashboard seluruh peserta.</flux:text>

            <flux:field>
                <flux:label>Judul Kegiatan / Agenda</flux:label>
                <flux:input wire:model="title" placeholder="Contoh: Live Workshop: Front-End Architecture" required />
                <flux:error name="title" />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Tanggal Kegiatan</flux:label>
                    <flux:input type="date" wire:model="event_date" required />
                    <flux:error name="event_date" />
                </flux:field>

                <flux:field>
                    <flux:label>Waktu / Jam (Opsional)</flux:label>
                    <flux:input wire:model="event_time" placeholder="19:30 - 21:00 WIB" />
                    <flux:error name="event_time" />
                </flux:field>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Tipe Agenda</flux:label>
                    <flux:select wire:model="event_type" required>
                        <option value="event">Kegiatan / Event Umum</option>
                        <option value="workshop">Workshop / Live Session</option>
                        <option value="deadline">Batas Waktu (Deadline)</option>
                        <option value="session">Sesi Mentoring</option>
                    </flux:select>
                    <flux:error name="event_type" />
                </flux:field>

                <flux:field>
                    <flux:label>Path Terkait (Opsional)</flux:label>
                    <flux:select wire:model="learning_path_id">
                        <option value="">Semua Path (Umum)</option>
                        @foreach ($this->learningPaths as $lp)
                            <option value="{{ $lp->id }}">{{ $lp->title }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="learning_path_id" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Deskripsi Tambahan</flux:label>
                <flux:textarea wire:model="description" rows="3" placeholder="Informasi detail kegiatan atau tautan meet..." />
                <flux:error name="description" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button wire:click="$set('showCreateModal', false)" variant="subtle">
                    Batal
                </flux:button>
                <flux:button type="submit" variant="primary">
                    Simpan Agenda
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Edit Modal -->
    <flux:modal wire:model="showEditModal" class="md:w-[600px]">
        <form wire:submit="updateEvent" class="space-y-4">
            <flux:heading size="lg">Edit Agenda Kegiatan</flux:heading>

            <flux:field>
                <flux:label>Judul Kegiatan</flux:label>
                <flux:input wire:model="title" required />
                <flux:error name="title" />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Tanggal Kegiatan</flux:label>
                    <flux:input type="date" wire:model="event_date" required />
                    <flux:error name="event_date" />
                </flux:field>

                <flux:field>
                    <flux:label>Waktu / Jam</flux:label>
                    <flux:input wire:model="event_time" />
                    <flux:error name="event_time" />
                </flux:field>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Tipe Agenda</flux:label>
                    <flux:select wire:model="event_type" required>
                        <option value="event">Kegiatan / Event Umum</option>
                        <option value="workshop">Workshop / Live Session</option>
                        <option value="deadline">Batas Waktu (Deadline)</option>
                        <option value="session">Sesi Mentoring</option>
                    </flux:select>
                    <flux:error name="event_type" />
                </flux:field>

                <flux:field>
                    <flux:label>Path Terkait</flux:label>
                    <flux:select wire:model="learning_path_id">
                        <option value="">Semua Path (Umum)</option>
                        @foreach ($this->learningPaths as $lp)
                            <option value="{{ $lp->id }}">{{ $lp->title }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="learning_path_id" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Deskripsi Tambahan</flux:label>
                <flux:textarea wire:model="description" rows="3" />
                <flux:error name="description" />
            </flux:field>

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
</div>
