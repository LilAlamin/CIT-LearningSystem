<?php

use App\Models\LearningPath;
use App\Models\LearningPathEnrollment;
use App\Models\Material;
use App\Models\PathMeeting;
use App\Models\User;
use Livewire\Livewire;

test('authenticated user can view meetings alur page', function () {
    $user = User::factory()->create();
    $path = LearningPath::create([
        'title' => 'Web Development',
        'slug' => 'web-development',
        'description' => 'Learn web',
        'icon' => 'code-bracket',
        'is_active' => true,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('meetings.index'));
    $response->assertOk();
});

test('meetings timeline displays meeting details and recap when past', function () {
    $user = User::factory()->create();
    $path = LearningPath::create([
        'title' => 'Frontend Master',
        'slug' => 'frontend-master',
        'description' => 'Frontend path',
        'icon' => 'device-phone-mobile',
        'is_active' => true,
    ]);

    // Past meeting with recap
    $pastMeeting = PathMeeting::create([
        'learning_path_id' => $path->id,
        'meeting_number' => 1,
        'title' => 'Kickoff & HTML Semantic',
        'description' => 'Sesi pembuka alur belajar',
        'meeting_date' => now()->subDays(2),
        'end_time' => '21:00 WIB',
        'location' => 'Lab Komputer 3',
        'notes' => 'Siapkan laptop',
        'recap_summary' => 'Peserta berhasil membuat layout semantic HTML5.',
        'recap_slides_url' => 'https://slides.example.com/p1',
        'recap_recording_url' => 'https://youtube.com/watch?v=recap1',
        'is_completed' => true,
    ]);

    // Upcoming meeting
    $upcomingMeeting = PathMeeting::create([
        'learning_path_id' => $path->id,
        'meeting_number' => 2,
        'title' => 'CSS Tailwind & Responsive',
        'description' => 'Belajar styling modern',
        'meeting_date' => now()->addDays(3),
        'end_time' => '21:00 WIB',
        'location' => 'Zoom Online',
        'notes' => 'Install Node LTS',
        'is_completed' => false,
    ]);

    LearningPathEnrollment::create([
        'user_id' => $user->id,
        'learning_path_id' => $path->id,
        'enrolled_at' => now(),
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::meetings.index', ['selectedPathId' => $path->id]);
    $component->assertSee('Kickoff & HTML Semantic')
        ->assertSee('Peserta berhasil membuat layout semantic HTML5.')
        ->assertSee('CSS Tailwind & Responsive');
});

test('meetings page only displays learning paths chosen by the student', function () {
    $user = User::factory()->create();
    $enrolledPath = LearningPath::create([
        'title' => 'Enrolled Path ABC',
        'slug' => 'enrolled-path-abc',
        'description' => 'Enrolled description',
        'icon' => 'code-bracket',
        'is_active' => true,
    ]);

    $otherPath = LearningPath::create([
        'title' => 'Other Unchosen Path XYZ',
        'slug' => 'other-unchosen-path-xyz',
        'description' => 'Unchosen description',
        'icon' => 'shield-check',
        'is_active' => true,
    ]);

    LearningPathEnrollment::create([
        'user_id' => $user->id,
        'learning_path_id' => $enrolledPath->id,
        'enrolled_at' => now(),
    ]);

    $this->actingAs($user);

    $response = $this->get(route('meetings.index'));
    $response->assertOk()
        ->assertSee('Enrolled Path ABC')
        ->assertDontSee('Other Unchosen Path XYZ');
});

test('meetings page displays empty state prompt when user has not enrolled in any path', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->get(route('meetings.index'));
    $response->assertOk()
        ->assertSee('Belum Ada Path Learning yang Dipilih')
        ->assertSee('Pilih Path Learning Sekarang');
});

test('material show page displays meeting source information', function () {
    $user = User::factory()->create();
    $path = LearningPath::create([
        'title' => 'Backend Laravel',
        'slug' => 'backend-laravel',
        'description' => 'Laravel path',
        'icon' => 'server-stack',
        'is_active' => true,
    ]);

    $meeting = PathMeeting::create([
        'learning_path_id' => $path->id,
        'meeting_number' => 1,
        'title' => 'Arsitektur MVC & Eloquent',
        'meeting_date' => now()->subDay(),
        'is_completed' => true,
    ]);

    $material = Material::create([
        'learning_path_id' => $path->id,
        'path_meeting_id' => $meeting->id,
        'title' => 'Pengenalan Eloquent ORM',
        'slug' => 'pengenalan-eloquent-orm',
        'summary' => 'Belajar dasar relasi dan query di Eloquent.',
        'content' => 'Eloquent membuat query database menjadi sangat intuitif dan ekspresif.',
        'order' => 1,
        'read_time_minutes' => 7,
        'is_published' => true,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('materials.show', [$path->slug, $material->slug]));
    $response->assertOk()
        ->assertSee('Disampaikan pada Pertemuan 1: Arsitektur MVC & Eloquent')
        ->assertSee('Sudah Terlaksana');
});

test('dashboard shows next upcoming meeting widget', function () {
    $user = User::factory()->create();
    $path = LearningPath::create([
        'title' => 'Cloud Architecture',
        'slug' => 'cloud-architecture',
        'description' => 'Cloud path',
        'icon' => 'cloud',
        'is_active' => true,
    ]);

    LearningPathEnrollment::create([
        'user_id' => $user->id,
        'learning_path_id' => $path->id,
        'enrolled_at' => now(),
    ]);

    $upcomingMeeting = PathMeeting::create([
        'learning_path_id' => $path->id,
        'meeting_number' => 1,
        'title' => 'Pengenalan Cloud Server & Linux',
        'meeting_date' => now()->addDays(2)->setTime(19, 30),
        'end_time' => '21:30 WIB',
        'location' => 'Zoom Room CIT',
        'notes' => 'Siapkan akun AWS free tier',
        'is_completed' => false,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk()
        ->assertSee('Pertemuan Selanjutnya')
        ->assertSee('Pengenalan Cloud Server & Linux')
        ->assertSee('Cloud Architecture');
});

test('admin can create and update meeting with recap details', function () {
    $admin = User::factory()->admin()->create();
    $path = LearningPath::create([
        'title' => 'Cyber Security',
        'slug' => 'cyber-security',
        'description' => 'Security path',
        'icon' => 'shield-check',
        'is_active' => true,
    ]);

    $this->actingAs($admin);

    // Create meeting
    Livewire::test('pages::admin.meetings.index')
        ->set('learning_path_id', $path->id)
        ->set('meeting_number', 1)
        ->set('title', 'Introduction to Ethical Hacking')
        ->set('description', 'Memahami dasar penetration testing')
        ->set('meeting_date', now()->addDays(4)->format('Y-m-d\TH:i'))
        ->set('end_time', '21:00 WIB')
        ->set('location', 'Lab Keamanan Siber')
        ->set('notes', 'Unduh VM Kali Linux')
        ->call('createMeeting');

    $meeting = PathMeeting::where('learning_path_id', $path->id)->where('meeting_number', 1)->first();
    expect($meeting)->not->toBeNull();
    expect($meeting->title)->toBe('Introduction to Ethical Hacking');

    // Update with recap
    Livewire::test('pages::admin.meetings.index')
        ->call('editMeeting', $meeting->id)
        ->set('recap_summary', 'Peserta berhasil mempraktikkan reconnaissance dasar.')
        ->set('recap_slides_url', 'https://slides.example.com/cyber1')
        ->set('recap_recording_url', 'https://youtube.com/watch?v=rec123')
        ->set('is_completed', true)
        ->call('updateMeeting');

    $meeting->refresh();
    expect($meeting->is_completed)->toBeTrue();
    expect($meeting->recap_summary)->toBe('Peserta berhasil mempraktikkan reconnaissance dasar.');
    expect($meeting->recap_slides_url)->toBe('https://slides.example.com/cyber1');
});
