<x-layouts::auth :title="__('Register')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Name -->
            <flux:input
                name="name"
                :label="__('Name')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('Full name')"
            />

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Jurusan -->
            <flux:input
                name="major"
                :label="__('Jurusan')"
                :value="old('major')"
                type="text"
                required
                autocomplete="organization-title"
                placeholder="Contoh: Teknik Informatika"
            />

            <!-- Tahun Angkatan -->
            <flux:select
                name="batch_year"
                :label="__('Tahun Angkatan')"
                placeholder="{{ __('Pilih tahun angkatan') }}"
                required
            >
                @php
                    $currentYear = (int) date('Y');
                @endphp
                @for ($year = $currentYear; $year >= $currentYear - 7; $year--)
                    <option value="{{ $year }}" {{ old('batch_year') == (string) $year ? 'selected' : '' }}>
                        {{ $year }}
                    </option>
                @endfor
            </flux:select>

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Confirm password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <!-- Password Hint Box -->
            <div class="rounded-xl border border-yellow-400/30 bg-yellow-400/5 dark:bg-yellow-400/10 p-3.5 text-xs space-y-1.5 -mt-2">
                <div class="flex items-center gap-2 font-bold text-zinc-900 dark:text-zinc-100">
                    <flux:icon name="shield-check" class="size-4 text-yellow-500 shrink-0" />
                    <span>Petunjuk Kata Sandi:</span>
                </div>
                <ul class="list-disc list-inside space-y-1 text-zinc-600 dark:text-zinc-400 pl-0.5 text-[11px]">
                    <li>Minimal <strong>8 karakter</strong></li>
                    <li>Wajib kombinasi <strong>huruf dan angka</strong> (contoh: <span class="font-mono font-semibold text-zinc-800 dark:text-zinc-200">Mahasiswa2026</span>)</li>
                    <li>Kolom <strong>Confirm password</strong> harus sama persis dengan Password</li>
                </ul>
            </div>

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                    {{ __('Create account') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
