<?php

use App\Models\User;

test('regular user cannot access admin routes', function () {
    $user = User::factory()->create(['role' => 'user']);
    $this->actingAs($user);

    $this->get(route('admin.learning-paths.index'))->assertForbidden();
    $this->get(route('admin.materials.index'))->assertForbidden();
    $this->get(route('admin.announcements.index'))->assertForbidden();
    $this->get(route('admin.enrollments.index'))->assertForbidden();
    $this->get(route('admin.calendar-events.index'))->assertForbidden();
});

test('admin user can access admin routes', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $this->get(route('admin.learning-paths.index'))->assertOk();
    $this->get(route('admin.materials.index'))->assertOk();
    $this->get(route('admin.announcements.index'))->assertOk();
    $this->get(route('admin.enrollments.index'))->assertOk();
    $this->get(route('admin.calendar-events.index'))->assertOk();
});
