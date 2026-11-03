<x-layouts::auth :title="__('Reset password')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Reset password')" :description="__('Please enter your new password below')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Token -->
            <input type="hidden" name="token" value="{{ request()->route('token') }}">

            <!-- Email Address -->
            <flux:input
                name="email"
                value="{{ request('email') }}"
                :label="__('Email')"
                type="email"
                required
                autocomplete="email"
            />

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
                <flux:button type="submit" variant="primary" class="w-full" data-test="reset-password-button">
                    {{ __('Reset password') }}
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts::auth>
