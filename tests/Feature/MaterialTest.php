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

test('material reader properly parses markdown headings, lists, bold text, and code into html', function () {
    $user = User::factory()->create();
    $path = LearningPath::create([
        'title' => 'Markdown Test Path',
        'slug' => 'markdown-test-path',
        'description' => 'Desc',
        'is_active' => true,
    ]);

    $markdownContent = <<<MD
# Pengenalan Android

Berikut adalah daftar poin:
* **Linux Kernel:** Mengelola driver perangkat keras.
* **Android Runtime (ART):** Mesin eksekusi aplikasi.

```kotlin
fun main() {
    println("Hello World")
}
```
MD;

    $material = Material::create([
        'learning_path_id' => $path->id,
        'title' => 'Modul Markdown',
        'slug' => 'modul-markdown',
        'content' => $markdownContent,
        'order' => 1,
        'read_time_minutes' => 5,
        'is_published' => true,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('materials.show', [$path->slug, $material->slug]));
    $response->assertOk();

    // Verify converted HTML tags instead of raw markdown text
    $response->assertSee('<h1>Pengenalan Android</h1>', false);
    $response->assertSee('<strong>Linux Kernel:</strong>', false);
    $response->assertSee('<li>', false);
    $response->assertSee('<pre><code', false);
    $response->assertSee('println(&quot;Hello World&quot;)', false);
});
