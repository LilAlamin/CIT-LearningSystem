<?php

use App\Models\AppSetting;
use Flux\Flux;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pengaturan SMTP Email - Admin')] class extends Component {
    public string $mail_mailer = 'smtp';
    public string $mail_host = '';
    public string $mail_port = '587';
    public string $mail_encryption = 'tls';
    public string $mail_username = '';
    public string $mail_password = '';
    public string $mail_from_address = '';
    public string $mail_from_name = '';

    public string $test_recipient = '';
    public ?string $testResultStatus = null;
    public ?string $testResultMessage = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        /** @var \Illuminate\Support\Collection<string, string|null> $settings */
        $settings = AppSetting::where('group', 'mail')->pluck('value', 'key');

        $this->mail_mailer = (string) ($settings->get('mail_mailer') ?? config('mail.default', 'smtp'));
        $this->mail_host = (string) ($settings->get('mail_host') ?? config('mail.mailers.smtp.host', 'smtp-relay.brevo.com'));
        $this->mail_port = (string) ($settings->get('mail_port') ?? config('mail.mailers.smtp.port', '587'));
        $this->mail_encryption = (string) ($settings->get('mail_encryption') ?? config('mail.mailers.smtp.encryption', 'tls'));
        $this->mail_username = (string) ($settings->get('mail_username') ?? config('mail.mailers.smtp.username', ''));
        $this->mail_password = (string) ($settings->get('mail_password') ?? config('mail.mailers.smtp.password', ''));
        $this->mail_from_address = (string) ($settings->get('mail_from_address') ?? config('mail.from.address', ''));
        $this->mail_from_name = (string) ($settings->get('mail_from_name') ?? config('mail.from.name', 'CIT Learning Systems'));

        $this->test_recipient = auth()->user()?->email ?? '';
    }

    /**
     * Save SMTP settings to database.
     */
    public function save(): void
    {
        $this->validate([
            'mail_mailer' => 'required|string|max:50',
            'mail_host' => 'required|string|max:255',
            'mail_port' => 'required|numeric|between:1,65535',
            'mail_encryption' => 'nullable|string|in:tls,ssl,none',
            'mail_username' => 'nullable|string|max:255',
            'mail_password' => 'nullable|string|max:500',
            'mail_from_address' => 'required|email|max:255',
            'mail_from_name' => 'required|string|max:255',
        ]);

        $settings = [
            'mail_mailer' => $this->mail_mailer,
            'mail_host' => $this->mail_host,
            'mail_port' => $this->mail_port,
            'mail_encryption' => $this->mail_encryption === 'none' ? '' : $this->mail_encryption,
            'mail_username' => $this->mail_username,
            'mail_password' => $this->mail_password,
            'mail_from_address' => $this->mail_from_address,
            'mail_from_name' => $this->mail_from_name,
        ];

        foreach ($settings as $key => $value) {
            AppSetting::set($key, $value, 'mail');
        }

        // Apply immediately to current request runtime
        config([
            'mail.default' => $this->mail_mailer,
            'mail.mailers.smtp.host' => $this->mail_host,
            'mail.mailers.smtp.port' => (int) $this->mail_port,
            'mail.mailers.smtp.encryption' => $this->mail_encryption === 'none' ? null : $this->mail_encryption,
            'mail.mailers.smtp.username' => $this->mail_username,
            'mail.mailers.smtp.password' => $this->mail_password,
            'mail.from.address' => $this->mail_from_address,
            'mail.from.name' => $this->mail_from_name,
        ]);

        Mail::purge('smtp');

        Flux::toast(variant: 'success', heading: 'Pengaturan Disimpan', text: 'Konfigurasi SMTP berhasil disimpan ke database.');
    }

    /**
     * Send test email to verify credentials and connectivity.
     */
    public function sendTestEmail(): void
    {
        $this->validate([
            'test_recipient' => 'required|email',
        ]);

        $this->testResultStatus = null;
        $this->testResultMessage = null;

        try {
            // Apply current form values dynamically for testing
            config([
                'mail.default' => $this->mail_mailer,
                'mail.mailers.smtp.host' => $this->mail_host,
                'mail.mailers.smtp.port' => (int) $this->mail_port,
                'mail.mailers.smtp.encryption' => $this->mail_encryption === 'none' ? null : $this->mail_encryption,
                'mail.mailers.smtp.username' => $this->mail_username,
                'mail.mailers.smtp.password' => $this->mail_password,
                'mail.from.address' => $this->mail_from_address,
                'mail.from.name' => $this->mail_from_name,
            ]);

            Mail::purge('smtp');

            $senderName = $this->mail_from_name;
            $recipient = $this->test_recipient;
            $operatorName = auth()->user()?->name ?? 'Administrator';

            Mail::to($recipient)->send(new \App\Mail\TestSmtpConnectionMail(
                senderName: $senderName,
                operatorName: $operatorName,
                smtpHost: $this->mail_host,
                smtpPort: $this->mail_port,
            ));

            $this->testResultStatus = 'success';
            $this->testResultMessage = "Email uji coba berhasil dikirim ke {$recipient}. Silakan periksa kotak masuk (inbox atau spam).";

            Flux::toast(variant: 'success', heading: 'Email Berhasil Terkirim', text: "Terkirim ke {$recipient}");
        } catch (\Throwable $e) {
            $this->testResultStatus = 'error';
            $this->testResultMessage = 'Koneksi gagal: '.$e->getMessage();

            Flux::toast(variant: 'danger', heading: 'Gagal Mengirim Email', text: $e->getMessage());
        }
    }

    /**
     * Apply quick presets for common SMTP providers.
     */
    public function applyPreset(string $provider): void
    {
        match ($provider) {
            'brevo' => [
                $this->mail_mailer = 'smtp',
                $this->mail_host = 'smtp-relay.brevo.com',
                $this->mail_port = '587',
                $this->mail_encryption = 'tls',
            ],
            'gmail' => [
                $this->mail_mailer = 'smtp',
                $this->mail_host = 'smtp.gmail.com',
                $this->mail_port = '587',
                $this->mail_encryption = 'tls',
            ],
            'campus' => [
                $this->mail_mailer = 'smtp',
                $this->mail_host = 'mail.namakampus.ac.id',
                $this->mail_port = '465',
                $this->mail_encryption = 'ssl',
            ],
            'mailgun' => [
                $this->mail_mailer = 'smtp',
                $this->mail_host = 'smtp.mailgun.org',
                $this->mail_port = '587',
                $this->mail_encryption = 'tls',
            ],
            'sendgrid' => [
                $this->mail_mailer = 'smtp',
                $this->mail_host = 'smtp.sendgrid.net',
                $this->mail_port = '587',
                $this->mail_encryption = 'tls',
            ],
            default => null,
        };

        $providerName = match ($provider) {
            'brevo' => 'Brevo',
            'gmail' => 'Gmail / Google Workspace',
            'campus' => 'Webmail Kampus (cPanel)',
            'mailgun' => 'Mailgun',
            'sendgrid' => 'SendGrid',
            default => ucfirst($provider),
        };

        Flux::toast(variant: 'info', heading: 'Preset Diterapkan', text: "Host & Port untuk {$providerName} berhasil dimuat.");
    }
}; ?>

<div class="space-y-6">
    <!-- Breadcrumbs & Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:breadcrumbs class="mb-2">
                <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" wire:navigate>Dashboard</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ __('Admin Management') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ __('Pengaturan SMTP') }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white flex items-center gap-2">
                <flux:icon name="envelope" class="size-7 text-amber-500" />
                <span>Pengaturan Email & SMTP Server</span>
            </h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                Konfigurasi pengiriman email tersimpan di database. Anda bebas menggunakan provider apa pun (Brevo, Gmail, Webmail Kampus, Mailgun, SendGrid) dan mengubahnya kapan saja tanpa perlu menyunting berkas <code class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-xs text-amber-600 dark:text-amber-400 font-mono">.env</code> di server kampus.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                <span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                Universal SMTP Ready
            </span>
        </div>
    </div>

    <!-- Alert Banner -->
    <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-4 dark:border-amber-900/50 dark:bg-amber-950/20">
        <div class="flex gap-3">
            <flux:icon name="information-circle" class="size-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
            <div class="text-xs text-amber-800 dark:text-amber-300 leading-relaxed">
                <strong class="font-semibold">Kemudahan Deploy Server Kampus & Fleksibilitas Provider:</strong> Pengaturan di bawah ini disimpan langsung di database. Nilai default awal telah diisi kredensial Brevo Anda melalui seeder. Kapan pun Anda ingin beralih ke Gmail, Webmail Kampus, atau provider lain, cukup ubah isian form di bawah ini dan klik Simpan.
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Form (2 Cols) -->
        <div class="lg:col-span-2">
            <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs overflow-hidden">
                <!-- Card Header -->
                <div class="p-6 border-b border-zinc-100 dark:border-zinc-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                                <flux:icon name="server" class="size-5 text-amber-500" />
                                <span>Kredensial Server SMTP</span>
                            </h3>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Mendukung protokol standar SMTP untuk semua provider email.</p>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                            Database Managed
                        </span>
                    </div>
                </div>

                <!-- Form -->
                <form wire:submit="save">
                    <div class="p-6 space-y-6">
                        <!-- Provider Quick Presets -->
                        <div class="p-4 bg-zinc-50/80 dark:bg-zinc-800/40 rounded-xl border border-zinc-200/70 dark:border-zinc-800 space-y-2.5">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                                <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300 flex items-center gap-1.5">
                                    <flux:icon name="bolt" class="size-3.5 text-amber-500" />
                                    <span>Pilihan Cepat Provider (Preset):</span>
                                </span>
                                <span class="text-[11px] text-zinc-400">Klik tombol untuk mengisi otomatis Host & Port</span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" wire:click="applyPreset('brevo')" class="px-2.5 py-1 text-xs rounded-lg font-medium border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:border-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/30 text-zinc-800 dark:text-zinc-200 transition-colors shadow-2xs">
                                    Brevo (Sendinblue)
                                </button>
                                <button type="button" wire:click="applyPreset('gmail')" class="px-2.5 py-1 text-xs rounded-lg font-medium border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:border-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/30 text-zinc-800 dark:text-zinc-200 transition-colors shadow-2xs">
                                    Gmail / Google Workspace
                                </button>
                                <button type="button" wire:click="applyPreset('campus')" class="px-2.5 py-1 text-xs rounded-lg font-medium border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:border-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/30 text-zinc-800 dark:text-zinc-200 transition-colors shadow-2xs">
                                    Webmail Kampus (cPanel)
                                </button>
                                <button type="button" wire:click="applyPreset('mailgun')" class="px-2.5 py-1 text-xs rounded-lg font-medium border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:border-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/30 text-zinc-800 dark:text-zinc-200 transition-colors shadow-2xs">
                                    Mailgun
                                </button>
                                <button type="button" wire:click="applyPreset('sendgrid')" class="px-2.5 py-1 text-xs rounded-lg font-medium border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:border-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/30 text-zinc-800 dark:text-zinc-200 transition-colors shadow-2xs">
                                    SendGrid
                                </button>
                            </div>
                        </div>

                        <!-- Server & Port -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <flux:field>
                                <flux:label>Mail Driver / Mailer</flux:label>
                                <flux:input wire:model="mail_mailer" placeholder="smtp" required />
                                <flux:error name="mail_mailer" />
                            </flux:field>

                            <flux:field>
                                <flux:label>SMTP Host</flux:label>
                                <flux:input wire:model="mail_host" placeholder="smtp-relay.brevo.com" required />
                                <flux:description>Contoh: smtp-relay.brevo.com, smtp.gmail.com, atau mail.namakampus.ac.id</flux:description>
                                <flux:error name="mail_host" />
                            </flux:field>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <flux:field>
                                <flux:label>Port</flux:label>
                                <flux:input wire:model="mail_port" placeholder="587" required />
                                <flux:error name="mail_port" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Enkripsi</flux:label>
                                <flux:select wire:model="mail_encryption">
                                    <option value="tls">TLS (Direkomendasikan - Port 587)</option>
                                    <option value="ssl">SSL (Port 465)</option>
                                    <option value="none">Tanpa Enkripsi (Port 25)</option>
                                </flux:select>
                                <flux:error name="mail_encryption" />
                            </flux:field>
                        </div>

                        <flux:separator />

                        <!-- Authentication -->
                        <div class="space-y-4">
                            <flux:field>
                                <flux:label>SMTP Username / Login Key</flux:label>
                                <flux:input wire:model="mail_username" placeholder="abf316001@smtp-brevo.com atau email@domain.com" />
                                <flux:description>Username login SMTP atau alamat email akun Anda.</flux:description>
                                <flux:error name="mail_username" />
                            </flux:field>

                            <flux:field>
                                <flux:label>SMTP Password / Master Key / App Password</flux:label>
                                <flux:input type="password" wire:model="mail_password" placeholder="Password atau API/App key" viewable />
                                <flux:description>Master/SMTP Key (Brevo), App Password (Gmail), atau password akun email (cPanel/Hosting).</flux:description>
                                <flux:error name="mail_password" />
                            </flux:field>
                        </div>

                        <flux:separator />

                        <!-- Sender Info -->
                        <div class="space-y-4">
                            <div>
                                <h4 class="text-sm font-semibold text-zinc-900 dark:text-white">Identitas Pengirim (Sender Info)</h4>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Nama dan alamat email yang akan muncul di kotak masuk penerima.</p>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <flux:field>
                                    <flux:label>Alamat Email Pengirim (From Address)</flux:label>
                                    <flux:input type="email" wire:model="mail_from_address" placeholder="gsatigaserangkai@gmail.com" required />
                                    <flux:description>Pastikan email ini diizinkan atau terverifikasi di provider email yang Anda gunakan.</flux:description>
                                    <flux:error name="mail_from_address" />
                                </flux:field>

                                <flux:field>
                                    <flux:label>Nama Pengirim (From Name)</flux:label>
                                    <flux:input wire:model="mail_from_name" placeholder="CIT Learning Systems" required />
                                    <flux:error name="mail_from_name" />
                                </flux:field>
                            </div>
                        </div>
                    </div>

                    <!-- Clean Form Footer inside Card -->
                    <div class="px-6 py-4 bg-zinc-50 dark:bg-zinc-800/50 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-between">
                        <span class="text-xs text-zinc-500 dark:text-zinc-400 flex items-center gap-1.5">
                            <flux:icon name="shield-check" class="size-4 text-emerald-500" />
                            <span>Perubahan langsung tersimpan ke database</span>
                        </span>
                        <flux:button type="submit" variant="primary" class="gap-2 shadow-sm font-bold">
                            <flux:icon name="check" class="size-4" />
                            <span>Simpan Konfigurasi</span>
                        </flux:button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sidebar: Test Email Card & Info (1 Col) -->
        <div class="space-y-6">
            <!-- Test Email Card -->
            <div class="rounded-2xl border-2 border-amber-300/80 dark:border-amber-700/60 bg-white dark:bg-zinc-900 p-6 shadow-xs space-y-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="flex size-8 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
                            <flux:icon name="paper-airplane" class="size-4.5" />
                        </span>
                        <div>
                            <h3 class="font-bold text-zinc-900 dark:text-white">Uji Coba Kirim Email</h3>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Tes koneksi real-time ke server SMTP Anda</p>
                        </div>
                    </div>
                </div>

                <form wire:submit="sendTestEmail" class="space-y-4">
                    <flux:field>
                        <flux:label>Kirim Ke Alamat Email</flux:label>
                        <flux:input type="email" wire:model="test_recipient" placeholder="nama@email.com" required />
                        <flux:error name="test_recipient" />
                    </flux:field>

                    <flux:button type="submit" variant="filled" class="w-full justify-center gap-2 bg-amber-500 hover:bg-amber-600 text-zinc-950 font-bold shadow-sm" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="sendTestEmail" class="flex items-center gap-2">
                            <flux:icon name="paper-airplane" class="size-4" />
                            <span>Kirim Email Tes Sekarang</span>
                        </span>
                        <span wire:loading wire:target="sendTestEmail" class="flex items-center gap-2">
                            <span class="size-4 border-2 border-zinc-950 border-t-transparent rounded-full animate-spin"></span>
                            <span>Menghubungi Server SMTP...</span>
                        </span>
                    </flux:button>
                </form>

                @if ($testResultStatus === 'success')
                    <div class="rounded-xl bg-emerald-50 dark:bg-emerald-950/40 p-4 border border-emerald-200 dark:border-emerald-800 text-xs text-emerald-800 dark:text-emerald-300 flex items-start gap-2.5">
                        <flux:icon name="check-circle" class="size-4.5 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" />
                        <div>
                            <div class="font-bold text-sm">Sukses Terkirim!</div>
                            <div class="mt-1 leading-relaxed">{{ $testResultMessage }}</div>
                        </div>
                    </div>
                @elseif ($testResultStatus === 'error')
                    <div class="rounded-xl bg-rose-50 dark:bg-rose-950/40 p-4 border border-rose-200 dark:border-rose-800 text-xs text-rose-800 dark:text-rose-300 flex items-start gap-2.5">
                        <flux:icon name="exclamation-circle" class="size-4.5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" />
                        <div>
                            <div class="font-bold text-sm">Koneksi Gagal</div>
                            <div class="mt-1 leading-relaxed break-words">{{ $testResultMessage }}</div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Guide Card -->
            <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/70 dark:bg-zinc-900/60 p-6 space-y-3">
                <div class="flex items-center gap-2">
                    <flux:icon name="light-bulb" class="size-4.5 text-amber-500" />
                    <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">Panduan SMTP & Provider</h4>
                </div>
                <ul class="text-xs text-zinc-600 dark:text-zinc-400 space-y-2.5 list-disc pl-4 leading-relaxed">
                    <li>
                        <strong>Bebas Ganti Provider:</strong> Sistem ini menggunakan protokol standar SMTP. Kapan saja Anda dapat beralih ke Gmail, Webmail cPanel kampus, Mailgun, SendGrid, Amazon SES, dll cukup dari form ini.
                    </li>
                    <li>
                        <strong>Gmail / Google Workspace:</strong> Host <code class="text-amber-600 dark:text-amber-400 font-mono font-semibold">smtp.gmail.com</code> (Port 587 TLS). Password-nya adalah <em>App Password</em> (16 digit) dari menu Keamanan Akun Google, bukan password akun Google biasa.
                    </li>
                    <li>
                        <strong>Webmail Kampus (cPanel):</strong> Biasanya menggunakan Host <code class="text-amber-600 dark:text-amber-400 font-mono font-semibold">mail.namakampus.ac.id</code> dengan Port <strong>465 (SSL)</strong> atau <strong>587 (TLS)</strong>.
                    </li>
                    <li>
                        <strong>Port 587 vs Port 465:</strong> Sebagian firewall jaringan kampus memblokir port 25. Gunakan port 587 (TLS) atau 465 (SSL) yang standar dan diizinkan.
                    </li>
                    <li>
                        <strong>Prioritas:</strong> Konfigurasi di halaman ini otomatis meng-override nilai di berkas <code class="text-amber-600 dark:text-amber-400 font-mono font-semibold">.env</code>.
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
