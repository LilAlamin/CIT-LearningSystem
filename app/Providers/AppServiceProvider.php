<?php

namespace App\Providers;

use App\Models\AppSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMailSettings();
    }

    /**
     * Configure mail settings from database if available.
     */
    protected function configureMailSettings(): void
    {
        try {
            if (Schema::hasTable('app_settings')) {
                /** @var Collection<string, string|null> $mailSettings */
                $mailSettings = AppSetting::where('group', 'mail')->pluck('value', 'key');

                $host = $mailSettings->get('mail_host');
                if ($mailSettings->isNotEmpty() && ! empty($host)) {
                    config([
                        'mail.default' => $mailSettings->get('mail_mailer') ?: config('mail.default'),
                        'mail.mailers.smtp.host' => $host,
                        'mail.mailers.smtp.port' => $mailSettings->get('mail_port') ? (int) $mailSettings->get('mail_port') : config('mail.mailers.smtp.port'),
                        'mail.mailers.smtp.encryption' => $mailSettings->get('mail_encryption') ?: config('mail.mailers.smtp.encryption'),
                        'mail.mailers.smtp.username' => $mailSettings->get('mail_username') ?: config('mail.mailers.smtp.username'),
                        'mail.mailers.smtp.password' => $mailSettings->get('mail_password') ?: config('mail.mailers.smtp.password'),
                        'mail.from.address' => $mailSettings->get('mail_from_address') ?: config('mail.from.address'),
                        'mail.from.name' => $mailSettings->get('mail_from_name') ?: config('mail.from.name'),
                    ]);
                }
            }
        } catch (\Throwable) {
            // Silently fall back to .env if database is unavailable
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
