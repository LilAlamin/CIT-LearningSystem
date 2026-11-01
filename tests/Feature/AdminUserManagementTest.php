<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('admin can view users management page', function () {
    $admin = User::factory()->admin()->create(['name' => 'Super Admin']);
    $student = User::factory()->create(['name' => 'Andi Wijaya', 'role' => 'user']);

    $this->actingAs($admin);

    $response = $this->get(route('admin.users.index'));
    $response->assertOk()
        ->assertSee('Kelola Pengguna Sistem')
        ->assertSee('Super Admin')
        ->assertSee('Andi Wijaya');
});

test('admin can create a new student user with verified email', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test('pages::admin.users.index')
        ->set('name', 'Budi Santoso')
        ->set('email', 'budi.santoso@cit.ac.id')
        ->set('role', 'user')
        ->set('major', 'Teknik Informatika')
        ->set('batch_year', '2026')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('is_verified', true)
        ->call('createUser')
        ->assertHasNoErrors();

    $user = User::where('email', 'budi.santoso@cit.ac.id')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Budi Santoso');
    expect($user->role)->toBe('user');
    expect($user->major)->toBe('Teknik Informatika');
    expect($user->batch_year)->toBe('2026');
    expect($user->hasVerifiedEmail())->toBeTrue();
    expect(Hash::check('password123', $user->password))->toBeTrue();
});

test('admin can create a new admin user', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test('pages::admin.users.index')
        ->set('name', 'Admin Baru')
        ->set('email', 'admin.baru@cit.ac.id')
        ->set('role', 'admin')
        ->set('password', 'AdminSecret123!')
        ->set('password_confirmation', 'AdminSecret123!')
        ->set('is_verified', true)
        ->call('createUser')
        ->assertHasNoErrors();

    $newAdmin = User::where('email', 'admin.baru@cit.ac.id')->first();
    expect($newAdmin)->not->toBeNull();
    expect($newAdmin->isAdmin())->toBeTrue();
    expect($newAdmin->hasVerifiedEmail())->toBeTrue();
});

test('admin can edit an existing user profile and change role', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->create([
        'name' => 'Citra Dewi',
        'email' => 'citra@cit.ac.id',
        'role' => 'user',
        'major' => 'Sistem Informasi',
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.users.index')
        ->call('editUser', $student->id)
        ->set('edit_name', 'Citra Dewi S.Kom')
        ->set('edit_email', 'citra.dewi@cit.ac.id')
        ->set('edit_role', 'admin')
        ->set('edit_major', 'Manajemen Informatika')
        ->set('edit_batch_year', '2025')
        ->call('updateUser')
        ->assertHasNoErrors();

    $student->refresh();
    expect($student->name)->toBe('Citra Dewi S.Kom');
    expect($student->email)->toBe('citra.dewi@cit.ac.id');
    expect($student->role)->toBe('admin');
    expect($student->isAdmin())->toBeTrue();
});

test('admin can update user password during edit', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->create(['password' => 'oldpassword123']);

    $this->actingAs($admin);

    Livewire::test('pages::admin.users.index')
        ->call('editUser', $student->id)
        ->set('edit_password', 'NewSecurePassword123')
        ->set('edit_password_confirmation', 'NewSecurePassword123')
        ->call('updateUser')
        ->assertHasNoErrors();

    $student->refresh();
    expect(Hash::check('NewSecurePassword123', $student->password))->toBeTrue();
});

test('admin can toggle user verification status', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->unverified()->create();

    expect($student->hasVerifiedEmail())->toBeFalse();

    $this->actingAs($admin);

    Livewire::test('pages::admin.users.index')
        ->call('toggleVerification', $student->id);

    expect($student->fresh()->hasVerifiedEmail())->toBeTrue();

    Livewire::test('pages::admin.users.index')
        ->call('toggleVerification', $student->id);

    expect($student->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('admin can delete another user', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->create(['name' => 'User To Delete']);

    $this->actingAs($admin);

    Livewire::test('pages::admin.users.index')
        ->call('deleteUser', $student->id);

    expect(User::find($student->id))->toBeNull();
});

test('admin cannot delete their own account', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('pages::admin.users.index')
        ->call('deleteUser', $admin->id);

    expect(User::find($admin->id))->not->toBeNull();
});

test('admin can create, edit, and delete user directly from data pendaftar page', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    // 1. Create directly from enrollments page
    Livewire::test('pages::admin.enrollments.index')
        ->call('openCreateModal')
        ->assertSet('showCreateModal', true)
        ->set('name', 'Direct Student')
        ->set('email', 'direct.student@cit.ac.id')
        ->set('role', 'user')
        ->set('major', 'Bisnis Digital')
        ->set('batch_year', '2026')
        ->set('password', 'secret1234')
        ->set('password_confirmation', 'secret1234')
        ->set('is_verified', true)
        ->call('createUser')
        ->assertHasNoErrors()
        ->assertSet('showCreateModal', false);

    $created = User::where('email', 'direct.student@cit.ac.id')->first();
    expect($created)->not->toBeNull();
    expect($created->major)->toBe('Bisnis Digital');

    // 2. Edit directly from enrollments page
    Livewire::test('pages::admin.enrollments.index')
        ->call('editUser', $created->id)
        ->assertSet('showEditModal', true)
        ->set('edit_name', 'Direct Student Updated')
        ->set('edit_role', 'admin')
        ->call('updateUser')
        ->assertHasNoErrors()
        ->assertSet('showEditModal', false);

    expect($created->fresh()->name)->toBe('Direct Student Updated');
    expect($created->fresh()->role)->toBe('admin');

    // 3. Delete directly from enrollments page
    Livewire::test('pages::admin.enrollments.index')
        ->call('deleteUser', $created->id);

    expect(User::find($created->id))->toBeNull();
});

