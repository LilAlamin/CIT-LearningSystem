<?php

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Kelola Pengguna - Admin')] class extends Component {
    #[Url]
    public string $search = '';

    #[Url]
    public string $roleFilter = 'all'; // all, admin, user

    #[Url]
    public string $statusFilter = 'all'; // all, verified, unverified

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
     * Get users list with optional filters.
     */
    #[Computed]
    public function users()
    {
        $query = User::withCount(['enrollments', 'completedMaterials']);

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%')
                    ->orWhere('major', 'like', '%'.$this->search.'%')
                    ->orWhere('batch_year', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->roleFilter === 'admin') {
            $query->where('role', 'admin');
        } elseif ($this->roleFilter === 'user') {
            $query->where('role', 'user');
        }

        if ($this->statusFilter === 'verified') {
            $query->whereNotNull('email_verified_at');
        } elseif ($this->statusFilter === 'unverified') {
            $query->whereNull('email_verified_at');
        }

        return $query->latest('id')->get();
    }

    /**
     * Summary metrics.
     */
    #[Computed]
    public function stats(): array
    {
        return [
            'total' => User::count(),
            'admins' => User::where('role', 'admin')->count(),
            'students' => User::where('role', 'user')->count(),
            'enrolledStudents' => User::where('role', 'user')->has('enrollments')->count(),
        ];
    }

    /**
     * Open create user modal.
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
     * Create a new user (admin or student).
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
        Flux::toast(variant: 'success', text: __('Pengguna baru :name (:role) berhasil ditambahkan!', [
            'name' => $user->name,
            'role' => $user->isAdmin() ? 'Admin' : 'Peserta',
        ]));
    }

    /**
     * Open edit user modal.
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
     * Update existing user.
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
     * Toggle email verification for user.
     */
    public function toggleVerification(int $id): void
    {
        $user = User::findOrFail($id);

        if ($user->hasVerifiedEmail()) {
            $user->email_verified_at = null;
            $user->save();
            Flux::toast(variant: 'warning', text: __('Status verifikasi :name dinonaktifkan.', ['name' => $user->name]));
        } else {
            $user->email_verified_at = now();
            $user->save();
            Flux::toast(variant: 'success', text: __('Email :name berhasil diverifikasi!', ['name' => $user->name]));
        }
    }

    /**
     * Delete user account.
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

        Flux::toast(variant: 'warning', text: __("Pengguna ':name' telah dihapus dari sistem.", ['name' => $name]));
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
                Kelola Pengguna Sistem
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                Tambah pengguna baru (Administrator atau Peserta Mahasiswa), perbarui data profil, atur hak akses role, serta kelola akun.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <flux:button wire:click="openCreateModal" variant="primary" icon="user-plus">
                Tambah Pengguna Baru
            </flux:button>
        </div>
    </div>

    <!-- Stats Bar (4 KPI cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">Total Seluruh Pengguna</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 mt-1">{{ $this->stats['total'] }}</p>
                </div>
                <div class="size-10 rounded-xl bg-yellow-400/10 text-yellow-600 dark:text-yellow-400 flex items-center justify-center font-bold">
                    <flux:icon name="user-group" class="size-5" />
                </div>
            </div>
            <p class="text-[11px] text-zinc-400 mt-2">Akun terdaftar dalam database</p>
        </div>

        <div class="p-4 rounded-xl border border-purple-500/30 bg-purple-500/5 dark:bg-purple-500/10 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-purple-600 dark:text-purple-400 font-medium">Administrator</p>
                    <p class="text-2xl font-bold text-purple-700 dark:text-purple-300 mt-1">{{ $this->stats['admins'] }}</p>
                </div>
                <div class="size-10 rounded-xl bg-purple-500/20 text-purple-600 dark:text-purple-300 flex items-center justify-center font-bold">
                    <flux:icon name="shield-check" class="size-5" />
                </div>
            </div>
            <p class="text-[11px] text-purple-600/70 dark:text-purple-400/70 mt-2">Akses penuh panel admin</p>
        </div>

        <div class="p-4 rounded-xl border border-sky-500/30 bg-sky-500/5 dark:bg-sky-500/10 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-sky-600 dark:text-sky-400 font-medium">Peserta / Mahasiswa</p>
                    <p class="text-2xl font-bold text-sky-700 dark:text-sky-300 mt-1">{{ $this->stats['students'] }}</p>
                </div>
                <div class="size-10 rounded-xl bg-sky-500/20 text-sky-600 dark:text-sky-300 flex items-center justify-center font-bold">
                    <flux:icon name="academic-cap" class="size-5" />
                </div>
            </div>
            <p class="text-[11px] text-sky-600/70 dark:text-sky-400/70 mt-2">Role mahasiswa pembelajar</p>
        </div>

        <div class="p-4 rounded-xl border border-emerald-500/30 bg-emerald-500/5 dark:bg-emerald-500/10 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">Sudah Memilih Path</p>
                    <p class="text-2xl font-bold text-emerald-700 dark:text-emerald-300 mt-1">{{ $this->stats['enrolledStudents'] }}</p>
                </div>
                <div class="size-10 rounded-xl bg-emerald-500/20 text-emerald-600 dark:text-emerald-300 flex items-center justify-center font-bold">
                    <flux:icon name="check-circle" class="size-5" />
                </div>
            </div>
            <p class="text-[11px] text-emerald-600/70 dark:text-emerald-400/70 mt-2">Mahasiswa dengan alur aktif</p>
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="flex flex-col sm:flex-row gap-4 items-center justify-between">
        <div class="w-full sm:w-80">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Cari nama, email, jurusan, angkatan..."
                icon="magnifying-glass"
                size="sm"
                clearable
            />
        </div>

        <div class="flex items-center gap-3 w-full sm:w-auto">
            <div class="w-48">
                <flux:select wire:model.live="roleFilter" size="sm">
                    <option value="all">Semua Role</option>
                    <option value="admin">Administrator</option>
                    <option value="user">Peserta / Mahasiswa</option>
                </flux:select>
            </div>

            <div class="w-44">
                <flux:select wire:model.live="statusFilter" size="sm">
                    <option value="all">Semua Status</option>
                    <option value="verified">Terverifikasi</option>
                    <option value="unverified">Belum Verifikasi</option>
                </flux:select>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-zinc-50 dark:bg-zinc-800/60 text-zinc-600 dark:text-zinc-400 uppercase font-semibold border-b border-zinc-200 dark:border-zinc-800">
                    <tr>
                        <th class="px-5 py-3.5">Pengguna</th>
                        <th class="px-5 py-3.5">Role</th>
                        <th class="px-5 py-3.5">Jurusan & Angkatan</th>
                        <th class="px-5 py-3.5">Status Verifikasi</th>
                        <th class="px-5 py-3.5">Aktivitas Belajar</th>
                        <th class="px-5 py-3.5">Terdaftar</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->users as $u)
                        @php
                            $isCurrentUser = $u->id === auth()->id();
                            $isVerified = $u->hasVerifiedEmail();
                        @endphp
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-colors {{ $isCurrentUser ? 'bg-yellow-400/[0.03] dark:bg-yellow-400/[0.04]' : '' }}">
                            <!-- Pengguna -->
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="size-9 rounded-full {{ $u->isAdmin() ? 'bg-purple-500/20 text-purple-700 dark:text-purple-300' : 'bg-yellow-400/20 text-yellow-700 dark:text-yellow-300' }} font-bold flex items-center justify-center text-xs shrink-0">
                                        {{ $u->initials() }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <p class="font-bold text-zinc-900 dark:text-zinc-100 text-sm truncate">{{ $u->name }}</p>
                                            @if ($isCurrentUser)
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-zinc-200 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300">
                                                    Anda
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-zinc-400 text-[11px] truncate">{{ $u->email }}</p>
                                    </div>
                                </div>
                            </td>

                            <!-- Role -->
                            <td class="px-5 py-4">
                                @if ($u->isAdmin())
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20">
                                        <flux:icon name="shield-check" class="size-3" />
                                        <span>Administrator</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20">
                                        <flux:icon name="academic-cap" class="size-3" />
                                        <span>Peserta</span>
                                    </span>
                                @endif
                            </td>

                            <!-- Jurusan & Angkatan -->
                            <td class="px-5 py-4">
                                @if ($u->isAdmin())
                                    <p class="text-zinc-500 dark:text-zinc-400 italic text-[11px]">Staf / Admin CIT</p>
                                @else
                                    <p class="text-zinc-800 dark:text-zinc-200 font-medium">{{ $u->major ?? '-' }}</p>
                                    <p class="text-zinc-400 text-[11px]">Angkatan: {{ $u->batch_year ?? '-' }}</p>
                                @endif
                            </td>

                            <!-- Status Verifikasi -->
                            <td class="px-5 py-4">
                                @if ($isVerified)
                                    <button
                                        type="button"
                                        wire:click="toggleVerification({{ $u->id }})"
                                        title="Klik untuk mengubah status verifikasi"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20 transition-colors"
                                    >
                                        <flux:icon name="check-circle" class="size-3" />
                                        <span>Terverifikasi</span>
                                    </button>
                                @else
                                    <button
                                        type="button"
                                        wire:click="toggleVerification({{ $u->id }})"
                                        title="Klik untuk langsung memverifikasi email pengguna"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 hover:bg-amber-500/20 transition-colors"
                                    >
                                        <flux:icon name="exclamation-circle" class="size-3" />
                                        <span>Belum Verifikasi</span>
                                    </button>
                                @endif
                            </td>

                            <!-- Aktivitas Belajar -->
                            <td class="px-5 py-4">
                                @if ($u->isUser())
                                    <div class="space-y-0.5">
                                        <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $u->enrollments_count }}</span>
                                        <span class="text-zinc-400">Path Terpilih</span>
                                        <p class="text-[11px] text-zinc-400">{{ $u->completed_materials_count }} Modul Selesai</p>
                                    </div>
                                @else
                                    <span class="text-zinc-400 text-[11px]">-</span>
                                @endif
                            </td>

                            <!-- Terdaftar -->
                            <td class="px-5 py-4 text-zinc-500 dark:text-zinc-400">
                                {{ $u->created_at ? $u->created_at->translatedFormat('d M Y') : '-' }}
                            </td>

                            <!-- Aksi -->
                            <td class="px-5 py-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <flux:button wire:click="editUser({{ $u->id }})" variant="subtle" size="xs" icon="pencil-square">
                                        Edit
                                    </flux:button>
                                    @if (! $isCurrentUser)
                                        <flux:button
                                            wire:click="deleteUser({{ $u->id }})"
                                            wire:confirm="Apakah Anda yakin ingin menghapus pengguna '{{ $u->name }}'? Seluruh data pendaftaran dan progres belajar pengguna ini akan terhapus."
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
                                Tidak ada data pengguna yang cocok dengan kriteria pencarian atau filter.
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
                    <flux:text class="text-xs">Buat akun untuk Administrator atau Peserta Mahasiswa baru.</flux:text>
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

