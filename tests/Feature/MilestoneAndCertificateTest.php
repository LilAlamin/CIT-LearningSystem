<?php

use App\Models\Certificate;
use App\Models\LearningPath;
use App\Models\LearningPathEnrollment;
use App\Models\User;
use Livewire\Livewire;

test('authenticated user can view capaian saya achievements page', function () {
    $user = User::factory()->create();
    $path = LearningPath::create([
        'title' => 'Web Development Bootcamp',
        'slug' => 'web-development-bootcamp',
        'description' => 'Web description',
        'icon' => 'code-bracket',
        'is_active' => true,
    ]);

    LearningPathEnrollment::create([
        'user_id' => $user->id,
        'learning_path_id' => $path->id,
        'enrolled_at' => now(),
    ]);

    $this->actingAs($user);

    $response = $this->get(route('milestones.achievements'));
    $response->assertOk()
        ->assertSee('Capaian Saya & Kelulusan')
        ->assertSee('Web Development Bootcamp');
});

test('capaian saya shows certificate when issued and allows previewing digital certificate', function () {
    $user = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $path = LearningPath::create([
        'title' => 'Data Science & AI',
        'slug' => 'data-science-ai',
        'description' => 'DS description',
        'icon' => 'chart-bar',
        'is_active' => true,
    ]);

    LearningPathEnrollment::create([
        'user_id' => $user->id,
        'learning_path_id' => $path->id,
        'enrolled_at' => now(),
    ]);

    $cert = Certificate::create([
        'user_id' => $user->id,
        'learning_path_id' => $path->id,
        'certificate_number' => 'CIT-CERT/2026/DATA/0007',
        'title' => 'Sertifikat Kelulusan - Data Science & AI',
        'status' => 'issued',
        'grade' => 'Lulus dengan Sangat Baik (A)',
        'notes' => 'Luar biasa dalam menyelesaikan proyek capstone machine learning.',
        'issued_at' => now(),
        'issued_by' => $admin->id,
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::milestones.achievements');
    $component->assertSee('CIT-CERT/2026/DATA/0007')
        ->assertSee('LULUS')
        ->assertSee('BERSERTIFIKAT')
        ->call('viewCertificate', $cert->id)
        ->assertSee('SERTIFIKAT KELULUSAN');
});

test('admin can view certificates management page and issue certificate to student', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->create(['name' => 'Dewi Sartika']);

    $path = LearningPath::create([
        'title' => 'Cloud Computing Expert',
        'slug' => 'cloud-computing-expert',
        'description' => 'Cloud description',
        'icon' => 'cloud',
        'is_active' => true,
    ]);

    LearningPathEnrollment::create([
        'user_id' => $student->id,
        'learning_path_id' => $path->id,
        'enrolled_at' => now(),
    ]);

    $this->actingAs($admin);

    $response = $this->get(route('admin.certificates.index'));
    $response->assertOk()
        ->assertSee('Kelola Sertifikat & Kelulusan')
        ->assertSee('Dewi Sartika');

    // Issue certificate
    Livewire::test('pages::admin.certificates.index')
        ->call('openIssueModal', $student->id, $path->id)
        ->set('certificate_number', 'CIT-CERT/2026/CLOUD/0088')
        ->set('title', 'Sertifikat Kelulusan - Cloud Computing Expert')
        ->set('grade', 'Lulus dengan Pujian (A)')
        ->set('notes', 'Berhasil melakukan deployment multi-tier pada cloud infrastructure.')
        ->set('issued_at', now()->format('Y-m-d\TH:i'))
        ->call('saveCertificate');

    $cert = Certificate::where('user_id', $student->id)->where('learning_path_id', $path->id)->first();
    expect($cert)->not->toBeNull();
    expect($cert->certificate_number)->toBe('CIT-CERT/2026/CLOUD/0088');
    expect($cert->grade)->toBe('Lulus dengan Pujian (A)');
});

test('admin can revoke an issued certificate', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->create();

    $path = LearningPath::create([
        'title' => 'UI/UX Master',
        'slug' => 'ui-ux-master',
        'description' => 'UI/UX description',
        'icon' => 'paint-brush',
        'is_active' => true,
    ]);

    $cert = Certificate::create([
        'user_id' => $student->id,
        'learning_path_id' => $path->id,
        'certificate_number' => 'CIT-CERT/2026/UI/0099',
        'title' => 'Sertifikat Kelulusan - UI/UX Master',
        'status' => 'issued',
        'issued_at' => now(),
        'issued_by' => $admin->id,
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.certificates.index')
        ->call('revokeCertificate', $cert->id);

    expect(Certificate::find($cert->id))->toBeNull();
});
