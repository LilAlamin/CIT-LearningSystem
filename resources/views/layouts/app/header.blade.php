<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden mr-2" icon="bars-2" inset="left" />

            <x-app-logo href="{{ route('dashboard') }}" wire:navigate />

            <flux:navbar class="-mb-px max-lg:hidden">
                @if (auth()->user()?->isAdmin())
                    <flux:navbar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:navbar.item>

                    <flux:dropdown class="max-lg:hidden">
                        <flux:navbar.item icon="rectangle-stack" icon-trailing="chevron-down" :current="request()->routeIs('admin.*')">
                            {{ __('Kelola Admin') }}
                        </flux:navbar.item>

                        <flux:menu>
                            <flux:menu.item icon="rectangle-stack" :href="route('admin.learning-paths.index')" wire:navigate>
                                {{ __('Kelola Path') }}
                            </flux:menu.item>
                            <flux:menu.item icon="video-camera" :href="route('admin.meetings.index')" wire:navigate>
                                {{ __('Kelola Pertemuan') }}
                            </flux:menu.item>
                            <flux:menu.item icon="document-text" :href="route('admin.materials.index')" wire:navigate>
                                {{ __('Kelola Materi') }}
                            </flux:menu.item>
                            <flux:menu.item icon="users" :href="route('admin.enrollments.index')" wire:navigate>
                                {{ __('Data Pendaftar') }}
                            </flux:menu.item>
                            <flux:menu.item icon="trophy" :href="route('admin.certificates.index')" wire:navigate>
                                {{ __('Kelola Sertifikat') }}
                            </flux:menu.item>
                            <flux:menu.item icon="envelope" :href="route('admin.announcements.index')" wire:navigate>
                                {{ __('Pengumuman & Email') }}
                            </flux:menu.item>
                            <flux:menu.item icon="calendar-days" :href="route('admin.calendar-events.index')" wire:navigate>
                                {{ __('Agenda Kalender') }}
                            </flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>

                    <flux:dropdown class="max-lg:hidden">
                        <flux:navbar.item icon="eye" icon-trailing="chevron-down" :current="request()->routeIs('learning-paths.*') || request()->routeIs('materials.*') || request()->routeIs('meetings.*') || request()->routeIs('announcements.*')">
                            {{ __('Pratinjau Siswa') }}
                        </flux:navbar.item>

                        <flux:menu>
                            <flux:menu.item icon="academic-cap" :href="route('learning-paths.index')" wire:navigate>
                                {{ __('Katalog Path') }}
                            </flux:menu.item>
                            <flux:menu.item icon="calendar-days" :href="route('meetings.index')" wire:navigate>
                                {{ __('Alur Pertemuan') }}
                            </flux:menu.item>
                            <flux:menu.item icon="book-open" :href="route('materials.index')" wire:navigate>
                                {{ __('Modul & Materi') }}
                            </flux:menu.item>
                            <flux:menu.item icon="megaphone" :href="route('announcements.index')" wire:navigate>
                                {{ __('Papan Pengumuman') }}
                            </flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                @else
                    <flux:navbar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:navbar.item>

                    <flux:navbar.item icon="academic-cap" :href="route('learning-paths.index')" :current="request()->routeIs('learning-paths.*')" wire:navigate>
                        {{ __('Pilih Path') }}
                    </flux:navbar.item>

                    <flux:dropdown class="max-lg:hidden">
                        <flux:navbar.item icon="trophy" icon-trailing="chevron-down" :current="request()->routeIs('milestones.*') || request()->routeIs('meetings.*')">
                            {{ __('Milestone') }}
                        </flux:navbar.item>

                        <flux:menu>
                            <flux:menu.item icon="calendar-days" :href="route('meetings.index')" wire:navigate>
                                {{ __('Alur & Pertemuan') }}
                            </flux:menu.item>
                            <flux:menu.item icon="check-badge" :href="route('milestones.achievements')" wire:navigate>
                                {{ __('Capaian Saya') }}
                            </flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>

                    <flux:navbar.item icon="book-open" :href="route('materials.index')" :current="request()->routeIs('materials.*')" wire:navigate>
                        {{ __('Materi') }}
                    </flux:navbar.item>

                    <flux:navbar.item icon="megaphone" :href="route('announcements.index')" :current="request()->routeIs('announcements.*')" wire:navigate>
                        {{ __('Pengumuman') }}
                    </flux:navbar.item>
                @endif
            </flux:navbar>

            <flux:spacer />

            <flux:navbar class="me-1.5 space-x-0.5 rtl:space-x-reverse py-0!">
                <flux:tooltip :content="__('Search')" position="bottom">
                    <flux:navbar.item class="!h-10 [&>div>svg]:size-5" icon="magnifying-glass" href="#" :label="__('Search')" />
                </flux:tooltip>
                <div class="max-lg:hidden px-2 text-xs text-zinc-400 dark:text-zinc-500 flex flex-col justify-center leading-tight">
                    <span class="font-medium text-zinc-500 dark:text-zinc-400">Cit LMS Version 1.00</span>
                    <span class="text-[10px] text-zinc-400 dark:text-zinc-500">Develop By Academic Division CIT</span>
                </div>
            </flux:navbar>

            <x-desktop-user-menu />
        </flux:header>

        <!-- Mobile Menu -->
        <flux:sidebar collapsible="mobile" sticky class="lg:hidden border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                @if (auth()->user()?->isAdmin())
                    <flux:sidebar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>

                    <flux:sidebar.group :heading="__('Admin Management')" class="grid mt-3">
                        <flux:sidebar.item icon="rectangle-stack" :href="route('admin.learning-paths.index')" :current="request()->routeIs('admin.learning-paths.*')" wire:navigate>
                            {{ __('Kelola Path') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="video-camera" :href="route('admin.meetings.index')" :current="request()->routeIs('admin.meetings.*')" wire:navigate>
                            {{ __('Kelola Pertemuan') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="document-text" :href="route('admin.materials.index')" :current="request()->routeIs('admin.materials.*')" wire:navigate>
                            {{ __('Kelola Materi') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="users" :href="route('admin.enrollments.index')" :current="request()->routeIs('admin.enrollments.*')" wire:navigate>
                            {{ __('Data Pendaftar') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="trophy" :href="route('admin.certificates.index')" :current="request()->routeIs('admin.certificates.*')" wire:navigate>
                            {{ __('Kelola Sertifikat') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="envelope" :href="route('admin.announcements.index')" :current="request()->routeIs('admin.announcements.*')" wire:navigate>
                            {{ __('Pengumuman & Email') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="calendar-days" :href="route('admin.calendar-events.index')" :current="request()->routeIs('admin.calendar-events.*')" wire:navigate>
                            {{ __('Agenda Kalender') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group :expandable="true" :expanded="request()->routeIs('materials.*') || request()->routeIs('meetings.*') || request()->routeIs('learning-paths.*') || request()->routeIs('announcements.*')" :heading="__('Pratinjau Tampilan Siswa')" icon="eye" class="mt-4">
                        <flux:sidebar.item icon="academic-cap" :href="route('learning-paths.index')" :current="request()->routeIs('learning-paths.*')" wire:navigate>
                            {{ __('Katalog Path Belajar') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="calendar-days" :href="route('meetings.index')" :current="request()->routeIs('meetings.*')" wire:navigate>
                            {{ __('Alur Pertemuan Siswa') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="book-open" :href="route('materials.index')" :current="request()->routeIs('materials.*')" wire:navigate>
                            {{ __('Modul & Materi Siswa') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="megaphone" :href="route('announcements.index')" :current="request()->routeIs('announcements.*')" wire:navigate>
                            {{ __('Papan Pengumuman') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @else
                    <flux:sidebar.group :heading="__('Learning Platform')" class="grid">
                        <flux:sidebar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                            {{ __('Dashboard') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="academic-cap" :href="route('learning-paths.index')" :current="request()->routeIs('learning-paths.*')" wire:navigate>
                            {{ __('Pilih Path Learning') }}
                        </flux:sidebar.item>

                        <flux:sidebar.group :expandable="true" :expanded="request()->routeIs('milestones.*') || request()->routeIs('meetings.*')" :heading="__('Milestone')" icon="trophy">
                            <flux:sidebar.item icon="calendar-days" :href="route('meetings.index')" :current="request()->routeIs('meetings.*') || request()->routeIs('milestones.meetings')" wire:navigate>
                                {{ __('Alur & Pertemuan') }}
                            </flux:sidebar.item>

                            <flux:sidebar.item icon="check-badge" :href="route('milestones.achievements')" :current="request()->routeIs('milestones.achievements')" wire:navigate>
                                {{ __('Capaian Saya') }}
                            </flux:sidebar.item>
                        </flux:sidebar.group>

                        <flux:sidebar.item icon="book-open" :href="route('materials.index')" :current="request()->routeIs('materials.*')" wire:navigate>
                            {{ __('Materi Pembelajaran') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="megaphone" :href="route('announcements.index')" :current="request()->routeIs('announcements.*')" wire:navigate>
                            {{ __('Pengumuman & Info') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <div class="px-3 py-1.5 text-xs text-zinc-400 dark:text-zinc-500">
                <div class="font-medium text-zinc-500 dark:text-zinc-400">Cit LMS Version 1.00</div>
                <div class="text-[11px] text-zinc-400 dark:text-zinc-500 leading-snug">Develop By Academic Division CIT</div>
            </div>
        </flux:sidebar>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
