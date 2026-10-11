<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', 'login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('learning-paths', 'pages::learning-paths.index')->name('learning-paths.index');
    Route::livewire('meetings', 'pages::meetings.index')->name('meetings.index');
    Route::livewire('milestones/meetings', 'pages::meetings.index')->name('milestones.meetings');
    Route::livewire('milestones/achievements', 'pages::milestones.achievements')->name('milestones.achievements');
    Route::livewire('materials', 'pages::materials.index')->name('materials.index');
    Route::livewire('materials/{path:slug}/{material:slug}', 'pages::materials.show')->name('materials.show');
    Route::livewire('announcements', 'pages::announcements.index')->name('announcements.index');

    // Admin routes
    Route::middleware(['admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::livewire('learning-paths', 'pages::admin.learning-paths.index')->name('learning-paths.index');
        Route::livewire('meetings', 'pages::admin.meetings.index')->name('meetings.index');
        Route::livewire('materials', 'pages::admin.materials.index')->name('materials.index');
        Route::livewire('announcements', 'pages::admin.announcements.index')->name('announcements.index');
        Route::livewire('enrollments', 'pages::admin.enrollments.index')->name('enrollments.index');
        Route::livewire('certificates', 'pages::admin.certificates.index')->name('certificates.index');
        Route::livewire('calendar-events', 'pages::admin.calendar-events.index')->name('calendar-events.index');
    });
});

require __DIR__.'/settings.php';
