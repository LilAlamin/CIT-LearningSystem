<?php

use App\Mail\TestSmtpConnectionMail;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

test('regular users cannot access admin mail settings', function () {
    $user = User::factory()->create(['role' => 'user']);
    $this->actingAs($user);

    $this->get(route('admin.settings.mail'))->assertForbidden();
});

test('administrators can view admin mail settings page', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $response = $this->get(route('admin.settings.mail'));
    $response->assertOk();
    $response->assertSee('Pengaturan Email');
    $response->assertSee('smtp-relay.brevo.com');
});

test('administrators can save smtp settings to database', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test('pages::admin.settings.mail')
        ->set('mail_host', 'smtp.campus.ac.id')
        ->set('mail_port', '465')
        ->set('mail_encryption', 'ssl')
        ->set('mail_username', 'cit-admin@campus.ac.id')
        ->set('mail_password', 'CampusPass123!')
        ->set('mail_from_address', 'cit@campus.ac.id')
        ->set('mail_from_name', 'CIT Campus LMS')
        ->call('save')
        ->assertHasNoErrors();

    expect(AppSetting::get('mail_host'))->toBe('smtp.campus.ac.id')
        ->and(AppSetting::get('mail_port'))->toBe('465')
        ->and(AppSetting::get('mail_encryption'))->toBe('ssl')
        ->and(AppSetting::get('mail_username'))->toBe('cit-admin@campus.ac.id')
        ->and(AppSetting::get('mail_from_address'))->toBe('cit@campus.ac.id')
        ->and(AppSetting::get('mail_from_name'))->toBe('CIT Campus LMS');
});

test('administrators can send a test email', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create(['email' => 'admin@cit.id']);
    $this->actingAs($admin);

    Livewire::test('pages::admin.settings.mail')
        ->set('test_recipient', 'test@example.com')
        ->call('sendTestEmail')
        ->assertHasNoErrors()
        ->assertSet('testResultStatus', 'success');

    Mail::assertSent(TestSmtpConnectionMail::class, function ($mail) {
        return $mail->hasTo('test@example.com');
    });
});

test('administrators can apply presets for mail providers', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test('pages::admin.settings.mail')
        ->call('applyPreset', 'gmail')
        ->assertSet('mail_host', 'smtp.gmail.com')
        ->assertSet('mail_port', '587')
        ->assertSet('mail_encryption', 'tls')
        ->call('applyPreset', 'campus')
        ->assertSet('mail_host', 'mail.namakampus.ac.id')
        ->assertSet('mail_port', '465')
        ->assertSet('mail_encryption', 'ssl');
});
