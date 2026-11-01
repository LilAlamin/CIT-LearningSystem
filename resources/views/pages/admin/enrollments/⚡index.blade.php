<?php

use App\Models\LearningPath;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Data Pendaftar & Pengguna - Admin')] class extends Component {
    #[Url]
    public string $search = '';

    #[Url]
    public ?int $selectedPathId = null;

    #[Url]
    public string $selectionStatus = 'all'; // 'all', 'enrolled', 'not_enrolled'

    #[Url]
    public string $roleFilter = 'all'; // 'all', 'user', 'admin'

    // Modal state
    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public ?int $editingUserId = null;

    // Create Form fields
    public string $name = '';
    public string $email = '';
    public string $role = 'user';
    public string $major = '';
    public string $batch_year = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $is_verified = true;

    // Edit Form fields
    public string $edit_name = '';
    public string $edit_email = '';
    public string $edit_role = 'user';
    public string $edit_major = '';
    public string $edit_batch_year = '';
    public string $edit_password = '';
    public string $edit_password_confirmation = '';
    public bool $edit_is_verified = true;

    /**
     * Get learning paths for filter.
     */
    #[Computed]
    public function learningPaths()
    {
        return LearningPath::orderBy('title')->get();
    }

    /**
     * Get students / users list.
     */
    #[Computed]
    public function students()
    {
        $query = User::with(['enrollments.learningPath', 'completedMaterials']);

        if ($this->roleFilter === 'user') {
            $query->where('role', 'user');
        } elseif ($this->roleFilter === 'admin') {
            $query->where('role', 'admin');
        }

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%')
                    ->orWhere('major', 'like', '%'.$this->search.'%')
                    ->orWhere('batch_year', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->selectedPathId) {
            $query->whereHas('enrollments', function ($q) {
                $q->where('learning_path_id', $this->selectedPathId);
            });
        }

        if ($this->selectionStatus === 'enrolled') {
            $query->has('enrollments');
        } elseif ($this->selectionStatus === 'not_enrolled') {
            $query->doesntHave('enrollments');
        }

        return $query->latest('id')->get();
    }

    /**
     * Summary stats.
     */
    #[Computed]
    public function stats(): array
    {
        $totalUsers = User::count();
        $totalStudents = User::where('role', 'user')->count();
        $enrolledCount = User::where('role', 'user')->has('enrollments')->count();
        $notEnrolledCount = $totalStudents - $enrolledCount;
        $totalAdmins = User::where('role', 'admin')->count();

        return [
            'totalUsers' => $totalUsers,
            'total' => $totalStudents,
            'enrolled' => $enrolledCount,
            'notEnrolled' => $notEnrolledCount,
            'totalAdmins' => $totalAdmins,
        ];
    }

    /**
     * Open create modal directly.
     */
    public function openCreateModal(): void
    {
        $this->reset(['name', 'email', 'role', 'major', 'batch_year', 'password', 'password_confirmation', 'is_verified']);
        $this->role = 'user';
        $this->is_verified = true;
        $this->resetValidation();
        $this->showCreateModal = true;
    }

    /**
     * Create user directly from this page.
     */
    public function createUser(): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'role' => 'required|in:admin,user',
            'major' => 'nullable|string|max:255',
            'batch_year' => 'nullable|string|max:10',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = new User();
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];
        $user->major = $validated['major'] ?: null;
        $user->batch_year = $validated['batch_year'] ?: null;
        $user->password = $validated['password'];

        if ($this->is_verified) {
            $user->email_verified_at = now();
        }

        $user->save();

        $this->showCreateModal = false;
        Flux::toast(variant: 'success', text: __('Pengguna :name (:role) berhasil ditambahkan!', [
            'name' => $user->name,
            'role' => $user->isAdmin() ? 'Administrator' : 'Peserta',
        ]));
    }

    /**
     * Open edit modal directly for a user.
     */
    public function editUser(int $id): void
    {
        $user = User::findOrFail($id);
        $this->editingUserId = $user->id;
        $this->edit_name = $user->name;
        $this->edit_email = $user->email;
        $this->edit_role = $user->role;
        $this->edit_major = (string) ($user->major ?? '');
        $this->edit_batch_year = (string) ($user->batch_year ?? '');
        $this->edit_password = '';
        $this->edit_password_confirmation = '';
        $this->edit_is_verified = $user->hasVerifiedEmail();

        $this->resetValidation();
        $this->showEditModal = true;
    }

    /**
     * Update user directly from this page.
     */
    public function updateUser(): void
    {
        $user = User::findOrFail($this->editingUserId);

        $rules = [
            'edit_name' => 'required|string|max:255',
            'edit_email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'edit_role' => 'required|in:admin,user',
            'edit_major' => 'nullable|string|max:255',
            'edit_batch_year' => 'nullable|string|max:10',
        ];

        if (! empty($this->edit_password)) {
            $rules['edit_password'] = 'required|string|min:8|confirmed';
        }

        $this->validate($rules);

        $user->name = $this->edit_name;
        $user->email = $this->edit_email;
        $user->role = $this->edit_role;
        $user->major = $this->edit_major ?: null;
        $user->batch_year = $this->edit_batch_year ?: null;

        if (! empty($this->edit_password)) {
            $user->password = $this->edit_password;
        }

        if ($this->edit_is_verified && ! $user->hasVerifiedEmail()) {
            $user->email_verified_at = now();
        } elseif (! $this->edit_is_verified && $user->hasVerifiedEmail()) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->showEditModal = false;
        Flux::toast(variant: 'success', text: __('Data pengguna :name berhasil diperbarui!', ['name' => $user->name]));
    }

    /**
     * Delete user directly from this page.
     */
    public function deleteUser(int $id): void
    {
        if ($id === Auth::id()) {
            Flux::toast(variant: 'danger', text: __('Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif digunakan.'));
            return;
        }

        $user = User::findOrFail($id);
        $name = $user->name;
        $user->delete();

        Flux::toast(variant: 'warning', text: __("Pengguna ':name' berhasil dihapus dari sistem.", ['name' => $name]));
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
                Data Mahasiswa & Pilihan Path
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                Pantau seluruh mahasiswa dan kelola akun pengguna (tambah baru, edit profil, atur role, atau hapus).
            </p>
        </div>

        <div class="flex items-center gap-2">
            <flux:button wire:click="openCreateModal" variant="primary" icon="user-plus">
                Tambah Pengguna Baru
            </flux:button>
        </div>
    </div>

    <!-- Stats Bar -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs">
            <p class="text-xs text-zinc-500 font-medium">Total Mahasiswa</p>
            <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 mt-1">{{ $this->stats['total'] }}</p>
        </div>

        <div class="p-4 rounded-xl border border-emerald-500/30 bg-emerald-500/5 dark:bg-emerald-500/10 shadow-xs">
            <p class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">Sudah Memilih Path</p>
            <p class="text-2xl font-bold text-emerald-700 dark:text-emerald-300 mt-1">{{ $this->stats['enrolled'] }}</p>
        </div>

        <div class="p-4 rounded-xl border border-amber-500/30 bg-amber-500/5 dark:bg-amber-500/10 shadow-xs">
            <p class="text-xs text-amber-600 dark:text-amber-400 font-medium">Belum Memilih Path</p>
            <p class="text-2xl font-bold text-amber-700 dark:text-amber-300 mt-1">{{ $this->stats['notEnrolled'] }}</p>
        </div>

        <div class="p-4 rounded-xl border border-purple-500/30 bg-purple-500/5 dark:bg-purple-500/10 shadow-xs">
            <p class="text-xs text-purple-600 dark:text-purple-400 font-medium">Total Administrator</p>
            <p class="text-2xl font-bold text-purple-700 dark:text-purple-300 mt-1">{{ $this->stats['totalAdmins'] }}</p>
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="flex flex-col sm:flex-row gap-4 items-center justify-between">
        <div class="w-full sm:w-72">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Cari nama, email, jurusan..."
                icon="magnifying-glass"
                size="sm"
                clearable
            />
        </div>

        <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
            <div class="w-40">
                <flux:select wire:model.live="roleFilter" size="sm">
                    <option value="all">Semua Role</option>
                    <option value="user">Peserta</option>
                    <option value="admin">Administrator</option>
                </flux:select>
            </div>

            <div class="w-48">
                <flux:select wire:model.live="selectedPathId" size="sm">
                    <option value="">Semua Path Learning</option>
                    @foreach ($this->learningPaths as $lp)
                        <option value="{{ $lp->id }}">{{ $lp->title }}</option>
                    @endforeach
                </flux:select>
            </div>

            <div class="w-44">
                <flux:select wire:model.live="selectionStatus" size="sm">
                    <option value="all">Semua Status</option>
                    <option value="enrolled">Sudah Memilih</option>
                    <option value="not_enrolled">Belum Memilih</option>
                </flux:select>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-zinc-50 dark:bg-zinc-800/60 text-zinc-600 dark:text-zinc-400 uppercase font-semibold border-b border-zinc-200 dark:border-zinc-800">
                    <tr>
                        <th class="px-5 py-3.5">Pengguna</th>
                        <th class="px-5 py-3.5">Role</th>
                        <th class="px-5 py-3.5">Jurusan & Angkatan</th>
                        <th class="px-5 py-3.5">Path Learning Terpilih</th>
                        <th class="px-5 py-3.5">Waktu Memilih</th>
                        <th class="px-5 py-3.5">Progress Baca</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->students as $student)
                        @php
                            $isCurrentUser = $student->id === auth()->id();
                            $enrollments = $student->enrollments;
                            $latestEnrollment = $enrollments->sortByDesc('enrolled_at')->first();
                            $completedCount = $student->completedMaterials->count();
                        @endphp
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-colors {{ $isCurrentUser ? 'bg-yellow-400/[0.03] dark:bg-yellow-400/[0.04]' : '' }}">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="size-8 rounded-full {{ $student->isAdmin() ? 'bg-purple-500/20 text-purple-700 dark:text-purple-300' : 'bg-yellow-400/20 text-yellow-700 dark:text-yellow-300' }} font-bold flex items-center justify-center text-xs shrink-0">
                                        {{ $student->initials() }}
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <p class="font-bold text-zinc-900 dark:text-zinc-100 text-sm">{{ $student->name }}</p>
                                            @if ($isCurrentUser)
                                                <span class="px-1.5 py-0.2 rounded text-[10px] font-semibold bg-zinc-200 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300">
                                                    Anda
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-zinc-400 text-[11px]">{{ $student->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                @if ($student->isAdmin())
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20">
                                        <flux:icon name="shield-check" class="size-3" />
                                        <span>Admin</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20">
                                        <flux:icon name="academic-cap" class="size-3" />
                                        <span>Peserta</span>
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                @if ($student->isAdmin())
                                    <p class="text-zinc-500 dark:text-zinc-400 italic text-[11px]">Staf Admin</p>
                                @else
                                    <p class="text-zinc-800 dark:text-zinc-200 font-medium">{{ $student->major ?? '-' }}</p>
                                    <p class="text-zinc-400 text-[11px]">Angkatan: {{ $student->batch_year ?? '-' }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                @if ($student->isAdmin())
                                    <span class="text-zinc-400 text-[11px]">-</span>
                                @elseif ($enrollments->isNotEmpty())
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($enrollments as $enr)
                                            @if ($enr->learningPath)
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-yellow-400/20 text-yellow-800 dark:text-yellow-300 border border-yellow-400/30">
                                                    <flux:icon name="{{ $enr->learningPath->icon ?: 'academic-cap' }}" class="size-3" />
                                                    {{ $enr->learningPath->title }}
                                                </span>
                                            @endif
                                        @endforeach
                                    </div>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                        Belum Memilih
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-zinc-500">
                                @if ($student->isAdmin())
                                    <span class="text-zinc-400 text-[11px]">-</span>
                                @elseif ($latestEnrollment)
                                    {{ $latestEnrollment->enrolled_at->translatedFormat('d M Y, H:i') }} WIB
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                @if ($student->isAdmin())
                                    <span class="text-zinc-400 text-[11px]">-</span>
                                @else
                                    <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $completedCount }}</span>
                                    <span class="text-zinc-400">Modul Selesai</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <flux:button wire:click="editUser({{ $student->id }})" variant="subtle" size="xs" icon="pencil-square">
                                        Edit
                                    </flux:button>
                                    @if (! $isCurrentUser)
                                        <flux:button
                                            wire:click="deleteUser({{ $student->id }})"
                                            wire:confirm="Yakin ingin menghapus pengguna '{{ $student->name }}'? Seluruh data pendaftaran dan progres belajar akan terhapus."
                                            variant="danger"
                                            size="xs"
                                            icon="trash"
                                        >
                                            Hapus
                                        </flux:button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-8 text-center text-zinc-400 italic">
                                Tidak ada data yang cocok dengan filter pencarian.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create User Modal -->
    <flux:modal wire:model="showCreateModal" class="md:w-[600px]">
        <form wire:submit="createUser" class="space-y-4">
            <div class="flex items-center gap-3 pb-3 border-b border-zinc-200 dark:border-zinc-800">
                <div class="size-10 rounded-xl bg-yellow-400/10 text-yellow-600 dark:text-yellow-400 flex items-center justify-center font-bold">
                    <flux:icon name="user-plus" class="size-5" />
                </div>
                <div>
                    <flux:heading size="lg">Tambah Pengguna Baru</flux:heading>
                    <flux:text class="text-xs">Buat akun untuk Administrator atau Peserta Mahasiswa baru langsung dari sini.</flux:text>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Nama Lengkap</flux:label>
                    <flux:input wire:model="name" placeholder="Contoh: Muhammad Rizky" required />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>Alamat Email</flux:label>
                    <flux:input type="email" wire:model="email" placeholder="nama@cit.ac.id" required />
                    <flux:error name="email" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Hak Akses (Role)</flux:label>
                <flux:select wire:model.live="role" required>
                    <option value="user">Peserta / Mahasiswa (Akses Belajar & Pemilihan Path)</option>
                    <option value="admin">Administrator (Akses Penuh Kelola Sistem LMS)</option>
                </flux:select>
                <flux:error name="role" />
            </flux:field>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 {{ $role === 'admin' ? 'opacity-60' : '' }}">
                <flux:field>
                    <flux:label>Jurusan / Program Studi {{ $role === 'admin' ? '(Opsional)' : '' }}</flux:label>
                    <flux:input wire:model="major" placeholder="Contoh: Teknik Informatika" />
                    <flux:error name="major" />
                </flux:field>

                <flux:field>
                    <flux:label>Tahun Angkatan {{ $role === 'admin' ? '(Opsional)' : '' }}</flux:label>
                    <flux:input wire:model="batch_year" placeholder="Contoh: 2026" />
                    <flux:error name="batch_year" />
                </flux:field>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Kata Sandi (Password)</flux:label>
                    <flux:input type="password" wire:model="password" placeholder="Minimal 8 karakter" required />
                    <flux:error name="password" />
                </flux:field>

                <flux:field>
                    <flux:label>Konfirmasi Kata Sandi</flux:label>
                    <flux:input type="password" wire:model="password_confirmation" placeholder="Ulangi kata sandi" required />
                    <flux:error name="password_confirmation" />
                </flux:field>
            </div>

            <flux:field>
                <flux:checkbox wire:model="is_verified" label="Tandai Email Terverifikasi (Langsung bisa login tanpa verifikasi email)" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button wire:click="$set('showCreateModal', false)" variant="subtle">
                    Batal
                </flux:button>
                <flux:button type="submit" variant="primary" icon="check">
                    Simpan Pengguna
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Edit User Modal -->
    <flux:modal wire:model="showEditModal" class="md:w-[600px]">
        <form wire:submit="updateUser" class="space-y-4">
            <div class="flex items-center gap-3 pb-3 border-b border-zinc-200 dark:border-zinc-800">
                <div class="size-10 rounded-xl bg-yellow-400/10 text-yellow-600 dark:text-yellow-400 flex items-center justify-center font-bold">
                    <flux:icon name="pencil-square" class="size-5" />
                </div>
                <div>
                    <flux:heading size="lg">Edit Data Pengguna</flux:heading>
                    <flux:text class="text-xs">Perbarui data profil, hak akses role, atau ubah kata sandi.</flux:text>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Nama Lengkap</flux:label>
                    <flux:input wire:model="edit_name" required />
                    <flux:error name="edit_name" />
                </flux:field>

                <flux:field>
                    <flux:label>Alamat Email</flux:label>
                    <flux:input type="email" wire:model="edit_email" required />
                    <flux:error name="edit_email" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Hak Akses (Role)</flux:label>
                <flux:select wire:model.live="edit_role" required>
                    <option value="user">Peserta / Mahasiswa</option>
                    <option value="admin">Administrator</option>
                </flux:select>
                <flux:error name="edit_role" />
            </flux:field>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Jurusan / Program Studi</flux:label>
                    <flux:input wire:model="edit_major" placeholder="Contoh: Teknik Informatika" />
                    <flux:error name="edit_major" />
                </flux:field>

                <flux:field>
                    <flux:label>Tahun Angkatan</flux:label>
                    <flux:input wire:model="edit_batch_year" placeholder="Contoh: 2026" />
                    <flux:error name="edit_batch_year" />
                </flux:field>
            </div>

            <div class="p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200/60 dark:border-zinc-700/60 space-y-3">
                <div>
                    <p class="text-xs font-bold text-zinc-900 dark:text-zinc-100">Reset Kata Sandi (Opsional)</p>
                    <p class="text-[11px] text-zinc-500">Biarkan kosong jika tidak ingin mengubah kata sandi pengguna.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <flux:field>
                        <flux:label>Kata Sandi Baru</flux:label>
                        <flux:input type="password" wire:model="edit_password" placeholder="Kosongkan jika tetap" />
                        <flux:error name="edit_password" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Konfirmasi Kata Sandi</flux:label>
                        <flux:input type="password" wire:model="edit_password_confirmation" placeholder="Ulangi kata sandi" />
                        <flux:error name="edit_password_confirmation" />
                    </flux:field>
                </div>
            </div>

            <flux:field>
                <flux:checkbox wire:model="edit_is_verified" label="Email Terverifikasi" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button wire:click="$set('showEditModal', false)" variant="subtle">
                    Batal
                </flux:button>
                <flux:button type="submit" variant="primary" icon="check">
                    Simpan Perubahan
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
