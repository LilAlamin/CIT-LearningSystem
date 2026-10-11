<?php

use App\Models\LearningPath;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Kelola Path Learning - Admin')] class extends Component {
    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public ?int $editingPathId = null;

    // Form fields
    public string $title = '';
    public string $slug = '';
    public string $description = '';
    public string $icon = 'academic-cap';
    public ?int $quota = null;
    public ?string $registration_start_at = null;
    public ?string $registration_end_at = null;
    public bool $is_active = true;

    /**
     * Get learning paths with enrollment count.
     */
    #[Computed]
    public function learningPaths()
    {
        return LearningPath::withCount('enrollments', 'materials')->latest()->get();
    }

    /**
     * Open create modal.
     */
    public function openCreateModal(): void
    {
        $this->reset(['title', 'slug', 'description', 'icon', 'quota', 'registration_start_at', 'registration_end_at', 'is_active', 'editingPathId']);
        $this->is_active = true;
        $this->icon = 'academic-cap';
        $this->registration_start_at = now()->format('Y-m-d\TH:i');
        $this->registration_end_at = now()->addDays(7)->format('Y-m-d\TH:i');
        $this->showCreateModal = true;
    }

    /**
     * Auto-generate slug when title changes.
     */
    public function updatedTitle(): void
    {
        if (! $this->editingPathId) {
            $this->slug = Str::slug($this->title);
        }
    }

    /**
     * Save newly created learning path.
     */
    public function createPath(): void
    {
        $validated = $this->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:learning_paths,slug',
            'description' => 'required|string',
            'icon' => 'required|string',
            'quota' => 'nullable|integer|min:1',
            'registration_start_at' => 'nullable|date',
            'registration_end_at' => 'nullable|date|after_or_equal:registration_start_at',
            'is_active' => 'boolean',
        ]);

        LearningPath::create($validated);

        $this->showCreateModal = false;
        Flux::toast(variant: 'success', text: __('Path learning baru berhasil ditambahkan!'));
    }

    /**
     * Open edit modal.
     */
    public function editPath(int $id): void
    {
        $path = LearningPath::findOrFail($id);
        $this->editingPathId = $path->id;
        $this->title = $path->title;
        $this->slug = $path->slug;
        $this->description = $path->description;
        $this->icon = $path->icon;
        $this->quota = $path->quota;
        $this->registration_start_at = $path->registration_start_at ? $path->registration_start_at->format('Y-m-d\TH:i') : null;
        $this->registration_end_at = $path->registration_end_at ? $path->registration_end_at->format('Y-m-d\TH:i') : null;
        $this->is_active = $path->is_active;

        $this->showEditModal = true;
    }

    /**
     * Update existing path.
     */
    public function updatePath(): void
    {
        $path = LearningPath::findOrFail($this->editingPathId);

        $validated = $this->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:learning_paths,slug,'.$path->id,
            'description' => 'required|string',
            'icon' => 'required|string',
            'quota' => 'nullable|integer|min:1',
            'registration_start_at' => 'nullable|date',
            'registration_end_at' => 'nullable|date|after_or_equal:registration_start_at',
            'is_active' => 'boolean',
        ]);

        $path->update($validated);

        $this->showEditModal = false;
        Flux::toast(variant: 'success', text: __('Path learning berhasil diperbarui!'));
    }

    /**
     * Delete a learning path.
     */
    public function deletePath(int $id): void
    {
        $path = LearningPath::findOrFail($id);
        $path->delete();

        Flux::toast(variant: 'warning', text: __('Path learning telah dihapus.'));
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
                Kelola Path Learning
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                Atur judul, kuota, serta periode batas waktu (deadline) pemilihan path untuk mahasiswa.
            </p>
        </div>

        <flux:button wire:click="openCreateModal" variant="primary" icon="plus">
            Tambah Path Baru
        </flux:button>
    </div>

    <!-- Table of Learning Paths -->
    <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-zinc-50 dark:bg-zinc-800/60 text-zinc-600 dark:text-zinc-400 uppercase font-semibold border-b border-zinc-200 dark:border-zinc-800">
                    <tr>
                        <th class="px-5 py-3.5">Path Learning</th>
                        <th class="px-5 py-3.5">Kuota & Pendaftar</th>
                        <th class="px-5 py-3.5">Periode Pemilihan</th>
                        <th class="px-5 py-3.5">Status Pendaftaran</th>
                        <th class="px-5 py-3.5">Modul</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->learningPaths as $path)
                        @php
                            $isExpired = $path->isRegistrationExpired();
                            $isOpen = $path->isOpenForRegistration();
                        @endphp
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-colors">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="p-2 rounded-xl bg-yellow-400/10 text-yellow-600 dark:text-yellow-400">
                                        <flux:icon name="{{ $path->icon ?: 'academic-cap' }}" class="size-5" />
                                    </span>
                                    <div>
                                        <p class="font-bold text-zinc-900 dark:text-zinc-100 text-sm">{{ $path->title }}</p>
                                        <p class="text-zinc-400 text-[11px]">{{ $path->slug }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <span class="font-bold text-zinc-800 dark:text-zinc-200">{{ $path->enrollments_count }}</span>
                                <span class="text-zinc-400">/ {{ $path->quota ?? '∞' }} Kuota</span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="space-y-0.5">
                                    <p class="text-zinc-700 dark:text-zinc-300 font-medium">
                                        @if ($path->registration_start_at && $path->registration_end_at)
                                            {{ $path->registration_start_at->translatedFormat('d M') }} - {{ $path->registration_end_at->translatedFormat('d M Y, H:i') }}
                                        @elseif ($path->registration_end_at)
                                            s/d {{ $path->registration_end_at->translatedFormat('d M Y, H:i') }}
                                        @else
                                            Tidak Dibatasi
                                        @endif
                                    </p>
                                    @if ($path->registration_end_at)
                                        <p class="text-[11px] {{ $isExpired ? 'text-red-500 font-semibold' : 'text-emerald-500' }}">
                                            {{ $isExpired ? 'Lewat Batas Waktu' : 'Tersisa '.$path->registration_end_at->diffForHumans() }}
                                        </p>
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                @if (! $path->is_active)
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-zinc-200 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400">
                                        Nonaktif
                                    </span>
                                @elseif ($isExpired)
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20">
                                        Ditutup (Lewat)
                                    </span>
                                @elseif ($isOpen)
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                        Dibuka
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                        Belum Dibuka
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-zinc-600 dark:text-zinc-400">
                                {{ $path->materials_count }} Modul
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <flux:button wire:click="editPath({{ $path->id }})" variant="subtle" size="xs" icon="pencil-square">
                                        Edit
                                    </flux:button>
                                    <flux:button wire:click="deletePath({{ $path->id }})" wire:confirm="Yakin ingin menghapus path learning ini?" variant="danger" size="xs" icon="trash">
                                        Hapus
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-zinc-400 italic">
                                Belum ada path learning. Klik tombol "Tambah Path Baru" di atas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Modal -->
    <flux:modal wire:model="showCreateModal" class="md:w-[600px]">
        <form wire:submit="createPath" class="space-y-4">
            <flux:heading size="lg">Tambah Path Learning Baru</flux:heading>
            <flux:text class="text-xs">Tentukan judul, deskripsi, kuota, serta batas waktu pemilihan oleh peserta.</flux:text>

            <flux:field>
                <flux:label>Judul Path Learning</flux:label>
                <flux:input wire:model.live.debounce.300ms="title" placeholder="Contoh: Cyber Security & Ethical Hacking" required />
                <flux:error name="title" />
            </flux:field>

            <flux:field>
                <flux:label>Slug URL</flux:label>
                <flux:input wire:model="slug" placeholder="cyber-security-ethical-hacking" required />
                <flux:error name="slug" />
            </flux:field>

            <flux:field>
                <flux:label>Deskripsi Singkat</flux:label>
                <flux:textarea wire:model="description" rows="3" placeholder="Jelaskan fokus materi dan target capaian dari path ini..." required />
                <flux:error name="description" />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Icon Heroicon</flux:label>
                    <flux:input wire:model="icon" placeholder="academic-cap, code-bracket, shield-check" required />
                    <flux:error name="icon" />
                </flux:field>

                <flux:field>
                    <flux:label>Batas Kuota Peserta (Opsional)</flux:label>
                    <flux:input type="number" wire:model="quota" placeholder="Kosongkan jika tak terbatas" />
                    <flux:error name="quota" />
                </flux:field>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Waktu Buka Pemilihan</flux:label>
                    <flux:input type="datetime-local" wire:model="registration_start_at" />
                    <flux:error name="registration_start_at" />
                </flux:field>

                <flux:field>
                    <flux:label>Batas Waktu (Deadline) Pemilihan</flux:label>
                    <flux:input type="datetime-local" wire:model="registration_end_at" />
                    <flux:error name="registration_end_at" />
                </flux:field>
            </div>

            <flux:field>
                <flux:checkbox wire:model="is_active" label="Aktifkan Path (Dapat dilihat peserta)" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button wire:click="$set('showCreateModal', false)" variant="subtle">
                    Batal
                </flux:button>
                <flux:button type="submit" variant="primary">
                    Simpan Path
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Edit Modal -->
    <flux:modal wire:model="showEditModal" class="md:w-[600px]">
        <form wire:submit="updatePath" class="space-y-4">
            <flux:heading size="lg">Edit Path Learning</flux:heading>
            <flux:text class="text-xs">Perbarui pengaturan jadwal deadline, kuota, atau deskripsi path.</flux:text>

            <flux:field>
                <flux:label>Judul Path Learning</flux:label>
                <flux:input wire:model="title" required />
                <flux:error name="title" />
            </flux:field>

            <flux:field>
                <flux:label>Slug URL</flux:label>
                <flux:input wire:model="slug" required />
                <flux:error name="slug" />
            </flux:field>

            <flux:field>
                <flux:label>Deskripsi</flux:label>
                <flux:textarea wire:model="description" rows="3" required />
                <flux:error name="description" />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Icon</flux:label>
                    <flux:input wire:model="icon" required />
                    <flux:error name="icon" />
                </flux:field>

                <flux:field>
                    <flux:label>Kuota Peserta</flux:label>
                    <flux:input type="number" wire:model="quota" />
                    <flux:error name="quota" />
                </flux:field>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Waktu Buka</flux:label>
                    <flux:input type="datetime-local" wire:model="registration_start_at" />
                    <flux:error name="registration_start_at" />
                </flux:field>

                <flux:field>
                    <flux:label>Batas Waktu (Deadline)</flux:label>
                    <flux:input type="datetime-local" wire:model="registration_end_at" />
                    <flux:error name="registration_end_at" />
                </flux:field>
            </div>

            <flux:field>
                <flux:checkbox wire:model="is_active" label="Aktifkan Path" />
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
