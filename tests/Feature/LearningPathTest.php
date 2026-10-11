<?php

use App\Models\LearningPath;
use App\Models\LearningPathEnrollment;
use App\Models\User;
use Livewire\Livewire;

test('authenticated user can view learning paths list', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('learning-paths.index'));
    $response->assertOk();
});

test('user can select an active learning path within registration period', function () {
    $user = User::factory()->create();
    $path = LearningPath::create([
        'title' => 'Web Development Test',
        'slug' => 'web-development-test',
        'description' => 'Test description',
        'icon' => 'code-bracket',
        'quota' => 20,
        'registration_start_at' => now()->subDay(),
        'registration_end_at' => now()->addDays(5),
        'is_active' => true,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::learning-paths.index')
        ->call('selectPath', $path->id);

    expect(LearningPathEnrollment::where('user_id', $user->id)->where('learning_path_id', $path->id)->exists())->toBeTrue();
});

test('user cannot select learning path if registration deadline has passed', function () {
    $user = User::factory()->create();
    $expiredPath = LearningPath::create([
        'title' => 'Expired Path',
        'slug' => 'expired-path',
        'description' => 'Expired description',
        'icon' => 'server-stack',
        'quota' => 20,
        'registration_start_at' => now()->subDays(10),
        'registration_end_at' => now()->subDays(1), // PAST DEADLINE
        'is_active' => true,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::learning-paths.index')
        ->call('selectPath', $expiredPath->id);

    // User should NOT be enrolled
    expect(LearningPathEnrollment::where('user_id', $user->id)->where('learning_path_id', $expiredPath->id)->exists())->toBeFalse();
});

test('user cannot select learning path if quota is full', function () {
    $user = User::factory()->create();
    $fullPath = LearningPath::create([
        'title' => 'Full Path',
        'slug' => 'full-path',
        'description' => 'Full description',
        'icon' => 'paint-brush',
        'quota' => 1,
        'registration_start_at' => now()->subDay(),
        'registration_end_at' => now()->addDays(5),
        'is_active' => true,
    ]);

    // Fill quota with another user
    $otherUser = User::factory()->create();
    LearningPathEnrollment::create([
        'user_id' => $otherUser->id,
        'learning_path_id' => $fullPath->id,
        'enrolled_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::learning-paths.index')
        ->call('selectPath', $fullPath->id);

    // User should NOT be enrolled
    expect(LearningPathEnrollment::where('user_id', $user->id)->where('learning_path_id', $fullPath->id)->exists())->toBeFalse();
});

test('admin can create learning path with selection period', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test('pages::admin.learning-paths.index')
        ->set('title', 'DevOps & SRE')
        ->set('slug', 'devops-sre')
        ->set('description', 'Learn modern DevOps and Site Reliability Engineering')
        ->set('icon', 'server-stack')
        ->set('quota', 30)
        ->set('registration_start_at', now()->format('Y-m-d\TH:i'))
        ->set('registration_end_at', now()->addDays(14)->format('Y-m-d\TH:i'))
        ->set('is_active', true)
        ->call('createPath');

    expect(LearningPath::where('slug', 'devops-sre')->exists())->toBeTrue();
});

test('user can enroll in multiple learning paths freely', function () {
    $user = User::factory()->create();

    $path1 = LearningPath::create([
        'title' => 'Web Dev',
        'slug' => 'web-dev',
        'description' => 'Web path',
        'icon' => 'code-bracket',
        'quota' => 30,
        'registration_start_at' => now()->subDay(),
        'registration_end_at' => now()->addDays(5),
        'is_active' => true,
    ]);

    $path2 = LearningPath::create([
        'title' => 'Mobile Dev',
        'slug' => 'mobile-dev',
        'description' => 'Mobile path',
        'icon' => 'device-phone-mobile',
        'quota' => 30,
        'registration_start_at' => now()->subDay(),
        'registration_end_at' => now()->addDays(5),
        'is_active' => true,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::learning-paths.index')
        ->call('selectPath', $path1->id)
        ->call('selectPath', $path2->id);

    expect($user->fresh()->enrollments()->count())->toBe(2);
    expect($user->isEnrolledIn($path1->id))->toBeTrue();
    expect($user->isEnrolledIn($path2->id))->toBeTrue();
});

test('user can unenroll from a path within registration period', function () {
    $user = User::factory()->create();

    $path = LearningPath::create([
        'title' => 'UI/UX Design',
        'slug' => 'ui-ux-design',
        'description' => 'Design path',
        'icon' => 'paint-brush',
        'quota' => 30,
        'registration_start_at' => now()->subDay(),
        'registration_end_at' => now()->addDays(5),
        'is_active' => true,
    ]);

    LearningPathEnrollment::create([
        'user_id' => $user->id,
        'learning_path_id' => $path->id,
        'enrolled_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::learning-paths.index')
        ->call('unselectPath', $path->id);

    expect($user->isEnrolledIn($path->id))->toBeFalse();
});

test('user cannot unenroll from a path if registration deadline has passed', function () {
    $user = User::factory()->create();

    $path = LearningPath::create([
        'title' => 'Data Science',
        'slug' => 'data-science',
        'description' => 'Data path',
        'icon' => 'chart-bar',
        'quota' => 30,
        'registration_start_at' => now()->subDays(10),
        'registration_end_at' => now()->subDay(), // Expired
        'is_active' => true,
    ]);

    LearningPathEnrollment::create([
        'user_id' => $user->id,
        'learning_path_id' => $path->id,
        'enrolled_at' => now()->subDays(5),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::learning-paths.index')
        ->call('unselectPath', $path->id);

    // Should still be enrolled
    expect($user->isEnrolledIn($path->id))->toBeTrue();
});
