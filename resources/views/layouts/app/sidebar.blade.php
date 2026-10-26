<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                @if (auth()->user()?->isAdmin())
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
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

                        <flux:sidebar.item icon="cog-6-tooth" :href="route('admin.settings.mail')" :current="request()->routeIs('admin.settings.*')" wire:navigate>
                            {{ __('Pengaturan SMTP') }}
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
                        <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
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

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
