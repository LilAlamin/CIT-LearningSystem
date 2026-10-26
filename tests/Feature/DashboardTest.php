<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('Peserta CIT Learning System');
    $response->assertSee('Learning Platform');
    $response->assertSee('Pilih Path Learning');
});

test('administrator can visit the dashboard with dedicated admin overview', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('Administrator Panel Aktif');
    $response->assertSee('Pusat Pintasan Navigasi Admin');
    $response->assertSee('Kapasitas & Status Jalur Pembelajaran', false);
    $response->assertSee('Admin Management');
    $response->assertSee('Pratinjau Tampilan Siswa');
    $response->assertSee('Cit LMS Version 1.00');
    $response->assertSee('Develop By Academic Division CIT');
    $response->assertDontSee('Anda Belum Memilih Path Learning!');
    $response->assertDontSee('Pilih Path Learning');
});
