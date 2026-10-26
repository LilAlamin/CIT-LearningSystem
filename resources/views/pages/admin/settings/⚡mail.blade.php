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
                Konfigurasi akun pengiriman email (Brevo) tersimpan di database. Anda dapat mengubah akun pengirim kapan saja tanpa perlu menyunting berkas <code class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-xs text-amber-600 dark:text-amber-400 font-mono">.env</code> di server kampus.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                <span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                Database Managed
            </span>
        </div>
    </div>

    <!-- Alert Banner -->
    <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-4 dark:border-amber-900/50 dark:bg-amber-950/20">
        <div class="flex gap-3">
            <flux:icon name="information-circle" class="size-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
            <div class="text-xs text-amber-800 dark:text-amber-300 leading-relaxed">
                <strong class="font-semibold">Kemudahan Deploy Server Kampus:</strong> Pengaturan di bawah ini disimpan langsung di database. Nilai default awal telah diisi kredensial Brevo Anda melalui seeder, sehingga saat Anda deploy di server kampus dan menjalankan <code class="px-1 py-0.5 rounded bg-amber-100 dark:bg-amber-900/60 font-mono text-[11px]">php artisan migrate --seed</code>, pengiriman email langsung otomatis berfungsi tanpa perlu membuka file manager ataupun SSH server.
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Form (2 Cols) -->
        <div class="lg:col-span-2">
            <flux:card class="space-y-6">
                <div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">Kredensial Server SMTP</h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Detail server pengiriman pesan keluar Brevo / SMTP kampus.</p>
                </div>

                <form wire:submit="save" class="space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>Mail Driver / Mailer</flux:label>
                            <flux:input wire:model="mail_mailer" placeholder="smtp" required />
                            <flux:error name="mail_mailer" />
                        </flux:field>

                        <flux:field>
                            <flux:label>SMTP Host</flux:label>
                            <flux:input wire:model="mail_host" placeholder="smtp-relay.brevo.com" required />
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

                    <div class="space-y-4">
                        <flux:field>
                            <flux:label>SMTP Username / Login Key</flux:label>
                            <flux:input wire:model="mail_username" placeholder="abf316001@smtp-brevo.com" />
                            <flux:error name="mail_username" />
                        </flux:field>

                        <flux:field>
                            <flux:label>SMTP Password / Master Key</flux:label>
                            <flux:input type="password" wire:model="mail_password" placeholder="xsmtpsib-..." viewable />
                            <flux:description>Gunakan Master Key / SMTP Key dari dasbor Brevo.</flux:description>
                            <flux:error name="mail_password" />
                        </flux:field>
                    </div>

                    <flux:separator />

                    <div>
                        <h3 class="text-base font-semibold text-zinc-900 dark:text-white">Identitas Pengirim (Sender Info)</h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Nama dan alamat email yang akan muncul di kotak masuk penerima.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>Alamat Email Pengirim (From Address)</flux:label>
                            <flux:input type="email" wire:model="mail_from_address" placeholder="gsatigaserangkai@gmail.com" required />
                            <flux:description>Pastikan email ini sudah terverifikasi sebagai sender di Brevo.</flux:description>
                            <flux:error name="mail_from_address" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Nama Pengirim (From Name)</flux:label>
                            <flux:input wire:model="mail_from_name" placeholder="CIT Learning Systems" required />
                            <flux:error name="mail_from_name" />
                        </flux:field>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                        <flux:button type="submit" variant="primary" class="gap-2">
                            <flux:icon name="check" class="size-4" />
                            <span>Simpan Konfigurasi</span>
                        </flux:button>
                    </div>
                </form>
            </flux:card>
        </div>

        <!-- Sidebar: Test Email Card & Info (1 Col) -->
        <div class="space-y-6">
            <!-- Test Email Card -->
            <flux:card class="space-y-4 border-2 border-amber-200/60 dark:border-amber-700/40">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="flex size-7 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400">
                            <flux:icon name="paper-airplane" class="size-4" />
                        </span>
                        <h3 class="font-semibold text-zinc-900 dark:text-white">Uji Coba Kirim Email</h3>
                    </div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                        Kirim email tes secara langsung untuk memastikan server kampus Anda tidak memblokir port SMTP dan kredensial Brevo valid.
                    </p>
                </div>

                <form wire:submit="sendTestEmail" class="space-y-3">
                    <flux:field>
                        <flux:label>Kirim Ke Alamat Email</flux:label>
                        <flux:input type="email" wire:model="test_recipient" placeholder="nama@email.com" required />
                        <flux:error name="test_recipient" />
                    </flux:field>

                    <flux:button type="submit" variant="filled" class="w-full justify-center gap-2 bg-amber-500 hover:bg-amber-600 text-zinc-900 font-semibold" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="sendTestEmail" class="flex items-center gap-2">
                            <flux:icon name="paper-airplane" class="size-4" />
                            <span>Kirim Email Tes Sekarang</span>
                        </span>
                        <span wire:loading wire:target="sendTestEmail" class="flex items-center gap-2">
                            <span class="size-4 border-2 border-zinc-900 border-t-transparent rounded-full animate-spin"></span>
                            <span>Menghubungi Server Brevo...</span>
                        </span>
                    </flux:button>
                </form>

                @if ($testResultStatus === 'success')
                    <div class="rounded-lg bg-emerald-50 p-3.5 border border-emerald-200 dark:bg-emerald-950/40 dark:border-emerald-800 text-xs text-emerald-800 dark:text-emerald-300 flex items-start gap-2">
                        <flux:icon name="check-circle" class="size-4 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" />
                        <div>
                            <div class="font-semibold">Sukses Terkirim!</div>
                            <div class="mt-0.5">{{ $testResultMessage }}</div>
                        </div>
                    </div>
                @elseif ($testResultStatus === 'error')
                    <div class="rounded-lg bg-rose-50 p-3.5 border border-rose-200 dark:bg-rose-950/40 dark:border-rose-800 text-xs text-rose-800 dark:text-rose-300 flex items-start gap-2">
                        <flux:icon name="exclamation-circle" class="size-4 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" />
                        <div>
                            <div class="font-semibold">Koneksi Gagal</div>
                            <div class="mt-0.5 break-words">{{ $testResultMessage }}</div>
                        </div>
                    </div>
                @endif
            </flux:card>

            <!-- Guide Card -->
            <flux:card class="space-y-3 bg-zinc-50/50 dark:bg-zinc-900/50">
                <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Tips Hosting Kampus</h4>
                <ul class="text-xs text-zinc-600 dark:text-zinc-400 space-y-2 list-disc pl-4 leading-relaxed">
                    <li>
                        <strong>Port 587 vs Port 465:</strong> Sebagian firewall jaringan kampus memblokir port 25. Brevo menggunakan port <strong>587 (TLS)</strong> yang biasanya selalu diizinkan.
                    </li>
                    <li>
                        <strong>Sender Verification:</strong> Alamat pengirim (<code class="text-amber-600 dark:text-amber-400 font-mono">gsatigaserangkai@gmail.com</code>) harus aktif di menu Senders & Domains pada akun Brevo Anda.
                    </li>
                    <li>
                        <strong>Prioritas:</strong> Konfigurasi di halaman ini otomatis meng-override nilai di berkas <code class="text-amber-600 dark:text-amber-400 font-mono">.env</code>.
                    </li>
                </ul>
            </flux:card>
        </div>
    </div>
</div>
