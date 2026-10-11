<?php

use App\Mail\AnnouncementNotificationMail;
use App\Models\Announcement;
use App\Models\LearningPath;
use App\Models\LearningPathEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

test('authenticated user can view announcements', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('announcements.index'));
    $response->assertOk();
});

test('admin can create announcement and send email notification to students', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create();
    $student1 = User::factory()->create(['role' => 'user']);
    $student2 = User::factory()->create(['role' => 'user']);

    $this->actingAs($admin);

    Livewire::test('pages::admin.announcements.index')
        ->set('title', 'Info Penting Jadwal CIT')
        ->set('content', 'Berikut pengumuman jadwal kegiatan penting...')
        ->set('priority', 'urgent')
        ->set('sendEmailImmediately', true)
        ->call('createAnnouncement');

    expect(Announcement::where('title', 'Info Penting Jadwal CIT')->exists())->toBeTrue();

    // Verify email was sent to students
    Mail::assertSent(AnnouncementNotificationMail::class, function ($mail) use ($student1) {
        return $mail->hasTo($student1->email);
    });

    Mail::assertSent(AnnouncementNotificationMail::class, function ($mail) use ($student2) {
        return $mail->hasTo($student2->email);
    });
});

test('announcement targeted to specific path only sends email to enrolled students', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create();
    $path = LearningPath::create([
        'title' => 'Mobile Dev',
        'slug' => 'mobile-dev',
        'description' => 'Mobile path',
        'is_active' => true,
    ]);

    $enrolledStudent = User::factory()->create(['role' => 'user']);
    LearningPathEnrollment::create([
        'user_id' => $enrolledStudent->id,
        'learning_path_id' => $path->id,
        'enrolled_at' => now(),
    ]);

    $otherStudent = User::factory()->create(['role' => 'user']);

    $this->actingAs($admin);

    Livewire::test('pages::admin.announcements.index')
        ->set('title', 'Info Khusus Mobile Dev')
        ->set('content', 'Pengumuman khusus mobile...')
        ->set('learning_path_id', $path->id)
        ->set('priority', 'important')
        ->set('sendEmailImmediately', true)
        ->call('createAnnouncement');

    // Should send to enrolled student
    Mail::assertSent(AnnouncementNotificationMail::class, function ($mail) use ($enrolledStudent) {
        return $mail->hasTo($enrolledStudent->email);
    });

    // Should NOT send to other student
    Mail::assertNotSent(AnnouncementNotificationMail::class, function ($mail) use ($otherStudent) {
        return $mail->hasTo($otherStudent->email);
    });
});
