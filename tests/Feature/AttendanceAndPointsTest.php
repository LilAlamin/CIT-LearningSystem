<?php

use App\Models\Certificate;
use App\Models\LearningPath;
use App\Models\LearningPathEnrollment;
use App\Models\MeetingAttendance;
use App\Models\PathMeeting;
use App\Models\User;
use Livewire\Livewire;

test('admin and pj can record student attendance with hadir izin and alpha points', function () {
    $pj = User::factory()->pj()->create();

    $path = LearningPath::create([
        'title' => 'Basic Mobile Development',
        'slug' => 'basic-mobile-development',
        'description' => 'Mobile dev path',
        'icon' => 'device-phone-mobile',
        'is_active' => true,
    ]);

    $pj->managedLearningPaths()->attach($path->id);

    $studentHadir = User::factory()->create(['name' => 'Mahasiswa Hadir']);
    $studentIzin = User::factory()->create(['name' => 'Mahasiswa Izin']);
    $studentAlpha = User::factory()->create(['name' => 'Mahasiswa Alpha']);

    foreach ([$studentHadir, $studentIzin, $studentAlpha] as $student) {
        LearningPathEnrollment::create([
            'user_id' => $student->id,
            'learning_path_id' => $path->id,
            'enrolled_at' => now(),
        ]);
    }

    $meeting = PathMeeting::create([
        'learning_path_id' => $path->id,
        'meeting_number' => 1,
        'title' => 'Pertemuan 1: Pengenalan Flutter',
        'meeting_date' => now(),
    ]);

    $this->actingAs($pj);

    Livewire::test('pages::admin.meetings.index')
        ->call('openAttendanceModal', $meeting->id)
        ->assertSet('showAttendanceModal', true)
        ->set("attendanceStatuses.{$studentHadir->id}", MeetingAttendance::STATUS_HADIR)
        ->set("attendanceStatuses.{$studentIzin->id}", MeetingAttendance::STATUS_IZIN)
        ->set("attendanceNotes.{$studentIzin->id}", 'Sakit dengan surat dokter')
        ->set("attendanceStatuses.{$studentAlpha->id}", MeetingAttendance::STATUS_ALPHA)
        ->call('saveAttendances')
        ->assertSet('showAttendanceModal', false);

    expect($studentHadir->fresh()->pointsForPath($path->id))->toBe(100);
    expect($studentIzin->fresh()->pointsForPath($path->id))->toBe(50);
    expect($studentAlpha->fresh()->pointsForPath($path->id))->toBe(0);

    $izinAttendance = MeetingAttendance::where('path_meeting_id', $meeting->id)
        ->where('user_id', $studentIzin->id)
        ->first();

    expect($izinAttendance)->not->toBeNull();
    expect($izinAttendance->status)->toBe(MeetingAttendance::STATUS_IZIN);
    expect($izinAttendance->points)->toBe(50);
    expect($izinAttendance->notes)->toBe('Sakit dengan surat dokter');
    expect($izinAttendance->recorded_by)->toBe($pj->id);
});

test('pj cannot record attendance for unmanaged learning path', function () {
    $pj = User::factory()->pj()->create();

    $unmanagedPath = LearningPath::create([
        'title' => 'Basic Website Development',
        'slug' => 'basic-website-development',
        'description' => 'Web dev path',
        'icon' => 'code-bracket',
        'is_active' => true,
    ]);

    $meeting = PathMeeting::create([
        'learning_path_id' => $unmanagedPath->id,
        'meeting_number' => 1,
        'title' => 'Pertemuan 1: HTML & CSS',
        'meeting_date' => now(),
    ]);

    $this->actingAs($pj);

    Livewire::test('pages::admin.meetings.index')
        ->call('openAttendanceModal', $meeting->id)
        ->assertForbidden();
});

test('activity points are capped at maximum 600 points per learning path', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->create();

    $path = LearningPath::create([
        'title' => 'Basic Mobile Development',
        'slug' => 'basic-mobile-development',
        'description' => 'Mobile dev path',
        'icon' => 'device-phone-mobile',
        'is_active' => true,
    ]);

    LearningPathEnrollment::create([
        'user_id' => $student->id,
        'learning_path_id' => $path->id,
        'enrolled_at' => now(),
    ]);

    for ($i = 1; $i <= 7; $i++) {
        $meeting = PathMeeting::create([
            'learning_path_id' => $path->id,
            'meeting_number' => $i,
            'title' => "Pertemuan {$i}",
            'meeting_date' => now()->subDays(8 - $i),
        ]);

        MeetingAttendance::create([
            'path_meeting_id' => $meeting->id,
            'user_id' => $student->id,
            'status' => MeetingAttendance::STATUS_HADIR,
            'points' => MeetingAttendance::POINTS_HADIR,
            'recorded_by' => $admin->id,
        ]);
    }

    expect($student->rawPointsForPath($path->id))->toBe(700);
    expect($student->pointsForPath($path->id))->toBe(600);
    expect($student->isEligibleForCertificate($path->id))->toBeTrue();
});

test('certificate issuance requires minimum 500 points and blocks students below 500 points', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->create(['name' => 'Budi Santoso']);

    $path = LearningPath::create([
        'title' => 'Basic Website Development',
        'slug' => 'basic-website-development',
        'description' => 'Web dev path',
        'icon' => 'code-bracket',
        'is_active' => true,
    ]);

    LearningPathEnrollment::create([
        'user_id' => $student->id,
        'learning_path_id' => $path->id,
        'enrolled_at' => now(),
    ]);

    // 4x Hadir (400 pt) + 1x Izin (50 pt) = 450 pt (< 500 pt)
    for ($i = 1; $i <= 5; $i++) {
        $meeting = PathMeeting::create([
            'learning_path_id' => $path->id,
            'meeting_number' => $i,
            'title' => "Pertemuan {$i}",
            'meeting_date' => now()->subDays(10 - $i),
        ]);

        $status = $i <= 4 ? MeetingAttendance::STATUS_HADIR : MeetingAttendance::STATUS_IZIN;

        MeetingAttendance::create([
            'path_meeting_id' => $meeting->id,
            'user_id' => $student->id,
            'status' => $status,
            'points' => MeetingAttendance::pointsForStatus($status),
            'recorded_by' => $admin->id,
        ]);
    }

    expect($student->pointsForPath($path->id))->toBe(450);
    expect($student->isEligibleForCertificate($path->id))->toBeFalse();

    $this->actingAs($admin);

    // Attempt to open issue modal when student has 450 points -> blocked
    Livewire::test('pages::admin.certificates.index')
        ->call('openIssueModal', $student->id, $path->id)
        ->assertSet('showIssueModal', false);

    // Add 6th meeting with Izin (+50 pt) -> total 500 pt (eligible)
    $meeting6 = PathMeeting::create([
        'learning_path_id' => $path->id,
        'meeting_number' => 6,
        'title' => 'Pertemuan 6',
        'meeting_date' => now()->subDay(),
    ]);

    MeetingAttendance::create([
        'path_meeting_id' => $meeting6->id,
        'user_id' => $student->id,
        'status' => MeetingAttendance::STATUS_IZIN,
        'points' => MeetingAttendance::POINTS_IZIN,
        'recorded_by' => $admin->id,
    ]);

    expect($student->fresh()->pointsForPath($path->id))->toBe(500);
    expect($student->fresh()->isEligibleForCertificate($path->id))->toBeTrue();

    // Now issuing certificate succeeds
    Livewire::test('pages::admin.certificates.index')
        ->call('openIssueModal', $student->id, $path->id)
        ->assertSet('showIssueModal', true)
        ->set('certificate_number', 'CIT-CERT/2026/BASI/0001')
        ->set('title', 'Sertifikat Kelulusan - Basic Website Development')
        ->set('grade', 'Lulus (B)')
        ->set('issued_at', now()->format('Y-m-d\TH:i'))
        ->call('saveCertificate')
        ->assertSet('showIssueModal', false);

    expect(Certificate::where('user_id', $student->id)->where('learning_path_id', $path->id)->exists())->toBeTrue();
});
