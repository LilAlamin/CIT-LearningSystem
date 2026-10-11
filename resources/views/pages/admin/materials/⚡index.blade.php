<?php

use App\Models\LearningPath;
use App\Models\Material;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Kelola Materi - Admin')] class extends Component {
    #[Url]
    public ?int $selectedPathId = null;

    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public ?int $editingMaterialId = null;

    // Form fields
    public ?int $learning_path_id = null;
    public string $title = '';
    public string $slug = '';
    public string $summary = '';
    public string $content = '';
    public int $order = 1;
    public int $read_time_minutes = 15;
    public string $resource_url = '';
    public bool $is_published = true;

    /**
     * Get learning paths for selector.
     */
    #[Computed]
    public function learningPaths()
    {
        return LearningPath::orderBy('title')->get();
    }

    /**
     * Get materials list.
     */
    #[Computed]
    public function materials()
    {
        $query = Material::with('learningPath');

        if ($this->selectedPathId) {
            $query->where('learning_path_id', $this->selectedPathId);
        }

        return $query->orderBy('learning_path_id')->orderBy('order')->get();
    }

    /**
     * Open create modal.
     */
    public function openCreateModal(): void
    {
        $this->reset(['title', 'slug', 'summary', 'content', 'resource_url', 'editingMaterialId']);
        $this->learning_path_id = $this->selectedPathId ?? $this->learningPaths->first()?->id;
        $this->order = (Material::where('learning_path_id', $this->learning_path_id)->max('order') ?? 0) + 1;
        $this->read_time_minutes = 15;
        $this->is_published = true;
        $this->showCreateModal = true;
    }

    /**
     * Auto generate slug.
     */
    public function updatedTitle(): void
    {
        if (! $this->editingMaterialId) {
            $this->slug = Str::slug($this->title);
        }
    }

    /**
     * Save new material.
     */
    public function createMaterial(): void
    {
        $validated = $this->validate([
            'learning_path_id' => 'required|exists:learning_paths,id',
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'summary' => 'nullable|string',
            'content' => 'required|string',
            'order' => 'required|integer|min:1',
            'read_time_minutes' => 'required|integer|min:1',
            'resource_url' => 'nullable|url',
            'is_published' => 'boolean',
        ]);

        Material::create($validated);

        $this->showCreateModal = false;
        Flux::toast(variant: 'success', text: __('Materi pembelajaran berhasil ditambahkan!'));
    }

    /**
     * Open edit modal.
     */
    public function editMaterial(int $id): void
    {
        $material = Material::findOrFail($id);
        $this->editingMaterialId = $material->id;
        $this->learning_path_id = $material->learning_path_id;
        $this->title = $material->title;
        $this->slug = $material->slug;
        $this->summary = $material->summary ?? '';
        $this->content = $material->content;
        $this->order = $material->order;
        $this->read_time_minutes = $material->read_time_minutes;
        $this->resource_url = $material->resource_url ?? '';
        $this->is_published = $material->is_published;

        $this->showEditModal = true;
    }

    /**
     * Update material.
     */
    public function updateMaterial(): void
    {
        $material = Material::findOrFail($this->editingMaterialId);

        $validated = $this->validate([
            'learning_path_id' => 'required|exists:learning_paths,id',
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'summary' => 'nullable|string',
            'content' => 'required|string',
            'order' => 'required|integer|min:1',
            'read_time_minutes' => 'required|integer|min:1',
            'resource_url' => 'nullable|url',
            'is_published' => 'boolean',
        ]);

        $material->update($validated);

        $this->showEditModal = false;
        Flux::toast(variant: 'success', text: __('Materi berhasil diperbarui!'));
    }

    /**
     * Delete material.
     */
    public function deleteMaterial(int $id): void
    {
        $mat = Material::findOrFail($id);
        $mat->delete();

        Flux::toast(variant: 'warning', text: __('Materi telah dihapus.'));
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
                Kelola Materi Pembelajaran
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                Kelola isi materi, urutan modul, estimasi waktu baca, dan bahan ajar per path learning.
            </p>
        </div>

        <flux:button wire:click="openCreateModal" variant="primary" icon="plus">
            Tambah Materi Baru
        </flux:button>
    </div>

    <!-- Filter by Path -->
    <div class="flex items-center gap-3">
        <span class="text-xs font-semibold text-zinc-500">Filter Path:</span>
        <div class="w-64">
            <flux:select wire:model.live="selectedPathId" size="sm">
                <option value="">Semua Path Learning</option>
                @foreach ($this->learningPaths as $lp)
                    <option value="{{ $lp->id }}">{{ $lp->title }}</option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <!-- Table of Materials -->
    <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-zinc-50 dark:bg-zinc-800/60 text-zinc-600 dark:text-zinc-400 uppercase font-semibold border-b border-zinc-200 dark:border-zinc-800">
                    <tr>
                        <th class="px-5 py-3.5">Urutan</th>
                        <th class="px-5 py-3.5">Judul Materi</th>
                        <th class="px-5 py-3.5">Path Learning</th>
                        <th class="px-5 py-3.5">Waktu Baca</th>
                        <th class="px-5 py-3.5">Status</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->materials as $mat)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-colors">
                            <td class="px-5 py-4 font-bold text-yellow-600 dark:text-yellow-400">
                                Modul {{ $mat->order }}
                            </td>
                            <td class="px-5 py-4">
                                <p class="font-bold text-zinc-900 dark:text-zinc-100 text-sm">{{ $mat->title }}</p>
                                <p class="text-zinc-400 text-[11px] truncate max-w-md">{{ $mat->summary ?? Str::limit(strip_tags($mat->content), 80) }}</p>
                            </td>
                            <td class="px-5 py-4 font-medium text-zinc-700 dark:text-zinc-300">
                                {{ $mat->learningPath->title }}
                            </td>
                            <td class="px-5 py-4 text-zinc-500">
                                {{ $mat->read_time_minutes }} Menit
                            </td>
                            <td class="px-5 py-4">
                                @if ($mat->is_published)
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                        Published
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-zinc-200 dark:bg-zinc-800 text-zinc-500">
                                        Draft
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <flux:button href="{{ route('materials.show', [$mat->learningPath->slug, $mat->slug]) }}" target="_blank" variant="subtle" size="xs" icon="eye">
                                        Lihat
                                    </flux:button>
                                    <flux:button wire:click="editMaterial({{ $mat->id }})" variant="subtle" size="xs" icon="pencil-square">
                                        Edit
                                    </flux:button>
                                    <flux:button wire:click="deleteMaterial({{ $mat->id }})" wire:confirm="Hapus materi ini?" variant="danger" size="xs" icon="trash">
                                        Hapus
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-zinc-400 italic">
                                Belum ada materi pembelajaran untuk filter ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Modal -->
    <flux:modal wire:model="showCreateModal" class="md:w-[700px]">
        <form wire:submit="createMaterial" class="space-y-4">
            <flux:heading size="lg">Tambah Materi Baru</flux:heading>
            <flux:text class="text-xs">Tuliskan konten materi, pilih path learning, dan atur estimasi waktu baca.</flux:text>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Path Learning</flux:label>
                    <flux:select wire:model="learning_path_id" required>
                        @foreach ($this->learningPaths as $lp)
                            <option value="{{ $lp->id }}">{{ $lp->title }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="learning_path_id" />
                </flux:field>

                <flux:field>
                    <flux:label>Urutan Modul</flux:label>
                    <flux:input type="number" wire:model="order" required min="1" />
                    <flux:error name="order" />
                </flux:field>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Judul Materi</flux:label>
                    <flux:input wire:model.live.debounce.300ms="title" placeholder="Contoh: Arsitektur RESTful API" required />
                    <flux:error name="title" />
                </flux:field>

                <flux:field>
                    <flux:label>Slug URL</flux:label>
                    <flux:input wire:model="slug" placeholder="arsitektur-restful-api" required />
                    <flux:error name="slug" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Ringkasan Singkat (Summary)</flux:label>
                <flux:textarea wire:model="summary" rows="2" placeholder="Penjelasan singkat modul untuk tampilan card..." />
                <flux:error name="summary" />
            </flux:field>

            <flux:field>
                <flux:label>Isi Konten Materi (Teks / Markdown)</flux:label>
                <flux:textarea wire:model="content" rows="8" placeholder="Tulis materi lengkap di sini..." required />
                <flux:error name="content" />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Estimasi Waktu Baca (Menit)</flux:label>
                    <flux:input type="number" wire:model="read_time_minutes" required min="1" />
                    <flux:error name="read_time_minutes" />
                </flux:field>

                <flux:field>
                    <flux:label>Tautan Referensi Eksternal (Opsional)</flux:label>
                    <flux:input wire:model="resource_url" placeholder="https://..." />
                    <flux:error name="resource_url" />
                </flux:field>
            </div>

            <flux:field>
                <flux:checkbox wire:model="is_published" label="Publikasikan Materi (Dapat dibaca oleh peserta)" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button wire:click="$set('showCreateModal', false)" variant="subtle">
                    Batal
                </flux:button>
                <flux:button type="submit" variant="primary">
                    Simpan Materi
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Edit Modal -->
    <flux:modal wire:model="showEditModal" class="md:w-[700px]">
        <form wire:submit="updateMaterial" class="space-y-4">
            <flux:heading size="lg">Edit Materi</flux:heading>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Path Learning</flux:label>
                    <flux:select wire:model="learning_path_id" required>
                        @foreach ($this->learningPaths as $lp)
                            <option value="{{ $lp->id }}">{{ $lp->title }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="learning_path_id" />
                </flux:field>

                <flux:field>
                    <flux:label>Urutan Modul</flux:label>
                    <flux:input type="number" wire:model="order" required min="1" />
                    <flux:error name="order" />
                </flux:field>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Judul Materi</flux:label>
                    <flux:input wire:model="title" required />
                    <flux:error name="title" />
                </flux:field>

                <flux:field>
                    <flux:label>Slug URL</flux:label>
                    <flux:input wire:model="slug" required />
                    <flux:error name="slug" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Ringkasan</flux:label>
                <flux:textarea wire:model="summary" rows="2" />
                <flux:error name="summary" />
            </flux:field>

            <flux:field>
                <flux:label>Isi Konten Materi</flux:label>
                <flux:textarea wire:model="content" rows="8" required />
                <flux:error name="content" />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Estimasi Waktu Baca (Menit)</flux:label>
                    <flux:input type="number" wire:model="read_time_minutes" required min="1" />
                    <flux:error name="read_time_minutes" />
                </flux:field>

                <flux:field>
                    <flux:label>Tautan Referensi</flux:label>
                    <flux:input wire:model="resource_url" />
                    <flux:error name="resource_url" />
                </flux:field>
            </div>

            <flux:field>
                <flux:checkbox wire:model="is_published" label="Publikasikan Materi" />
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
