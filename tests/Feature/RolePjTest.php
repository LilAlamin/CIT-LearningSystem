<?php

use App\Models\Announcement;
use App\Models\CalendarEvent;
use App\Models\LearningPath;
use App\Models\LearningPathEnrollment;
use App\Models\Material;
use App\Models\PathMeeting;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->pathA = LearningPath::create([
        'title' => 'Web Development',
        'slug' => 'web-development',
        'description' => 'Web path',
        'icon' => 'code-bracket',
        'quota' => 30,
        'is_active' => true,
    ]);

    $this->pathB = LearningPath::create([
        'title' => 'Mobile Development',
        'slug' => 'mobile-development',
        'description' => 'Mobile path',
        'icon' => 'device-phone-mobile',
        'quota' => 30,
        'is_active' => true,
    ]);

    $this->pjA = User::factory()->pj()->create();
    $this->pjA->managedLearningPaths()->attach($this->pathA->id);
});

test('pj cannot access strictly admin-only routes', function () {
    $this->actingAs($this->pjA);

    // Forbidden routes for PJ
    $this->get(route('admin.users.index'))->assertForbidden();
    $this->get(route('admin.settings.mail'))->assertForbidden();
    $this->get(route('admin.learning-paths.index'))->assertForbidden();
    $this->get(route('admin.certificates.index'))->assertForbidden();
});

test('pj can access meetings management scoped to their managed path', function () {
    $meetingA = PathMeeting::create([
        'learning_path_id' => $this->pathA->id,
        'meeting_number' => 1,
        'title' => 'Web Sesi 1',
        'meeting_date' => now()->addDay(),
    ]);

    $meetingB = PathMeeting::create([
        'learning_path_id' => $this->pathB->id,
        'meeting_number' => 1,
        'title' => 'Mobile Sesi 1',
        'meeting_date' => now()->addDay(),
    ]);

    $this->actingAs($this->pjA);

    $response = $this->get(route('admin.meetings.index'));
    $response->assertOk();

    Livewire::test('pages::admin.meetings.index')
        ->assertSee('Web Sesi 1')
        ->assertDontSee('Mobile Sesi 1');
});

test('pj cannot create or delete meetings for a path they do not manage', function () {
    $meetingB = PathMeeting::create([
        'learning_path_id' => $this->pathB->id,
        'meeting_number' => 1,
        'title' => 'Mobile Sesi 1',
        'meeting_date' => now()->addDay(),
    ]);

    $this->actingAs($this->pjA);

    // Try creating for path B
    Livewire::test('pages::admin.meetings.index')
        ->set('learning_path_id', $this->pathB->id)
        ->set('meeting_number', 2)
        ->set('title', 'Unauthorized Meeting')
        ->set('meeting_date', now()->addDays(2)->format('Y-m-d\TH:i'))
        ->call('createMeeting')
        ->assertForbidden();

    // Try deleting meeting of path B
    Livewire::test('pages::admin.meetings.index')
        ->call('deleteMeeting', $meetingB->id)
        ->assertForbidden();
});

test('pj can access materials scoped to their managed path', function () {
    $matA = Material::create([
        'learning_path_id' => $this->pathA->id,
        'title' => 'HTML & CSS Dasar',
        'slug' => 'html-css-dasar',
        'content' => 'Materi HTML & CSS',
        'order' => 1,
        'read_time_minutes' => 10,
        'is_published' => true,
    ]);

    $matB = Material::create([
        'learning_path_id' => $this->pathB->id,
        'title' => 'Flutter Dasar',
        'slug' => 'flutter-dasar',
        'content' => 'Materi Flutter',
        'order' => 1,
        'read_time_minutes' => 10,
        'is_published' => true,
    ]);

    $this->actingAs($this->pjA);

    Livewire::test('pages::admin.materials.index')
        ->assertSee('HTML & CSS Dasar')
        ->assertDontSee('Flutter Dasar');

    // Try creating material for path B
    Livewire::test('pages::admin.materials.index')
        ->set('learning_path_id', $this->pathB->id)
        ->set('title', 'Unauthorized Flutter')
        ->set('slug', 'unauthorized-flutter')
        ->set('content', 'Content')
        ->set('order', 2)
        ->set('read_time_minutes', 15)
        ->call('createMaterial')
        ->assertForbidden();
});

test('pj sees enrollments data for their path in read-only mode and cannot manage users', function () {
    $studentA = User::factory()->create(['name' => 'Siswa Web']);
    LearningPathEnrollment::create([
        'user_id' => $studentA->id,
        'learning_path_id' => $this->pathA->id,
        'enrolled_at' => now(),
    ]);

    $studentB = User::factory()->create(['name' => 'Siswa Mobile']);
    LearningPathEnrollment::create([
        'user_id' => $studentB->id,
        'learning_path_id' => $this->pathB->id,
        'enrolled_at' => now(),
    ]);

    $this->actingAs($this->pjA);

    Livewire::test('pages::admin.enrollments.index')
        ->assertSee('Siswa Web')
        ->assertDontSee('Siswa Mobile')
        ->assertDontSee('Tambah Pengguna Baru');

    // User management action forbidden for PJ
    Livewire::test('pages::admin.enrollments.index')
        ->call('openCreateModal')
        ->assertForbidden();

    Livewire::test('pages::admin.enrollments.index')
        ->call('deleteUser', $studentA->id)
        ->assertForbidden();
});

test('pj can manage announcements and calendar events for their path', function () {
    $this->actingAs($this->pjA);

    // Announcement for path A
    Livewire::test('pages::admin.announcements.index')
        ->set('title', 'Pengumuman Web Dev')
        ->set('content', 'Halo peserta web dev!')
        ->set('learning_path_id', $this->pathA->id)
        ->set('priority', 'normal')
        ->set('sendEmailImmediately', false)
        ->call('createAnnouncement');

    expect(Announcement::where('title', 'Pengumuman Web Dev')->exists())->toBeTrue();

    // Announcement for unauthorized path B forbidden
    Livewire::test('pages::admin.announcements.index')
        ->set('title', 'Pengumuman Mobile Dev')
        ->set('content', 'Halo peserta mobile dev!')
        ->set('learning_path_id', $this->pathB->id)
        ->set('priority', 'normal')
        ->set('sendEmailImmediately', false)
        ->call('createAnnouncement')
        ->assertForbidden();

    // Calendar Event for path A
    Livewire::test('pages::admin.calendar-events.index')
        ->set('title', 'Workshop Web Dev')
        ->set('description', 'Live coding HTML/CSS')
        ->set('event_date', now()->addDays(3)->toDateString())
        ->set('event_type', 'workshop')
        ->set('learning_path_id', $this->pathA->id)
        ->call('createEvent');

    expect(CalendarEvent::where('title', 'Workshop Web Dev')->exists())->toBeTrue();

    // Calendar Event for unauthorized path B forbidden
    Livewire::test('pages::admin.calendar-events.index')
        ->set('title', 'Workshop Mobile Dev')
        ->set('event_date', now()->addDays(3)->toDateString())
        ->set('event_type', 'workshop')
        ->set('learning_path_id', $this->pathB->id)
        ->call('createEvent')
        ->assertForbidden();
});

test('admin can create a user with pj role and assign learning paths', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test('pages::admin.users.index')
        ->set('name', 'Budi Penanggung Jawab')
        ->set('email', 'pj.budi@cit.ac.id')
        ->set('role', 'pj')
        ->set('selected_paths', [$this->pathA->id])
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('createUser');

    $newPj = User::where('email', 'pj.budi@cit.ac.id')->first();
    expect($newPj)->not->toBeNull();
    expect($newPj->isPj())->toBeTrue();
    expect($newPj->canManagePath($this->pathA->id))->toBeTrue();
    expect($newPj->canManagePath($this->pathB->id))->toBeFalse();
});
