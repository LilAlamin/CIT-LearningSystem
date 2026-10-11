<?php

use App\Models\LearningPath;
use App\Models\Material;
use App\Models\User;
use Livewire\Livewire;

test('authenticated user can view materials listing', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('materials.index'));
    $response->assertOk();
});

test('user can read specific material reader view', function () {
    $user = User::factory()->create();
    $path = LearningPath::first() ?? LearningPath::create([
        'title' => 'Web Path',
        'slug' => 'web-path',
        'description' => 'Desc',
        'is_active' => true,
    ]);

    $material = Material::firstOrCreate(
        ['learning_path_id' => $path->id, 'slug' => 'sample-module'],
        [
            'title' => 'Sample Module',
            'summary' => 'Summary',
            'content' => 'Material text content',
            'order' => 1,
            'read_time_minutes' => 10,
            'is_published' => true,
        ]
    );

    $this->actingAs($user);

    $response = $this->get(route('materials.show', [$path->slug, $material->slug]));
    $response->assertOk();
    $response->assertSee('Sample Module');
});

test('user can toggle material completion status', function () {
    $user = User::factory()->create();
    $path = LearningPath::create([
        'title' => 'Web Path 2',
        'slug' => 'web-path-2',
        'description' => 'Desc',
        'is_active' => true,
    ]);
    $material = Material::create([
        'learning_path_id' => $path->id,
        'title' => 'Test Modul',
        'slug' => 'test-modul',
        'content' => 'Content here',
        'order' => 1,
        'read_time_minutes' => 5,
        'is_published' => true,
    ]);

    $this->actingAs($user);

    // Initial state: not completed
    expect($material->isCompletedBy($user))->toBeFalse();

    // Toggle complete
    Livewire::test('pages::materials.show', ['path' => $path, 'material' => $material])
        ->call('toggleComplete');

    expect($material->isCompletedBy($user->fresh()))->toBeTrue();

    // Toggle back to uncompleted
    Livewire::test('pages::materials.show', ['path' => $path, 'material' => $material])
        ->call('toggleComplete');

    expect($material->isCompletedBy($user->fresh()))->toBeFalse();
});
