<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\AppSetting;
use App\Models\CalendarEvent;
use App\Models\LearningPath;
use App\Models\LearningPathEnrollment;
use App\Models\Material;
use App\Models\PathMeeting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@cit.id'],
            [
                'name' => 'Administrator CIT',
                'role' => 'admin',
                'major' => 'Teknik Informatika',
                'batch_year' => '2024',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]
        );

        // 2. Create Regular Students
        $student1 = User::firstOrCreate(
            ['email' => 'student@cit.id'],
            [
                'name' => 'Ahmad Fauzi',
                'role' => 'user',
                'major' => 'Sistem Informasi',
                'batch_year' => '2025',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]
        );

        $student2 = User::firstOrCreate(
            ['email' => 'budi@cit.id'],
            [
                'name' => 'Budi Santoso',
                'role' => 'user',
                'major' => 'Teknik Informatika',
                'batch_year' => '2025',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]
        );

        $student3 = User::firstOrCreate(
            ['email' => 'siti@cit.id'],
            [
                'name' => 'Siti Rahma',
                'role' => 'user',
                'major' => 'Bisnis Digital',
                'batch_year' => '2025',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]
        );

        // 3. Create Learning Paths
        $webPath = LearningPath::updateOrCreate(
            ['slug' => 'basic-website-development'],
            [
                'title' => 'Basic Website Development',
                'description' => 'Kuasai dasar pengembangan website modern dari struktur antarmuka hingga pemrograman backend menggunakan Laravel, Livewire, dan Tailwind CSS.',
                'icon' => 'code-bracket',
                'color' => 'yellow',
                'quota' => null,
                'registration_start_at' => '2026-11-02 00:00:00',
                'registration_end_at' => '2026-11-07 23:59:00',
                'is_active' => true,
                'created_at' => now()->subMinute(),
            ]
        );

        $mobilePath = LearningPath::updateOrCreate(
            ['slug' => 'basic-mobile-development'],
            [
                'title' => 'Basic Mobile Development',
                'description' => 'Bangun aplikasi mobile multiplatform untuk Android dan iOS menggunakan Flutter, manajemen state modern, dan integrasi REST API.',
                'icon' => 'device-phone-mobile',
                'color' => 'yellow',
                'quota' => null,
                'registration_start_at' => '2026-11-02 00:00:00',
                'registration_end_at' => '2026-11-07 23:59:00',
                'is_active' => true,
                'created_at' => now(),
            ]
        );

        // 3.5. Enroll Sample Students into Learning Paths
        LearningPathEnrollment::firstOrCreate(
            ['user_id' => $student1->id, 'learning_path_id' => $webPath->id],
            ['enrolled_at' => now()->subHours(5)]
        );

        LearningPathEnrollment::firstOrCreate(
            ['user_id' => $student2->id, 'learning_path_id' => $webPath->id],
            ['enrolled_at' => now()->subHours(3)]
        );

        LearningPathEnrollment::firstOrCreate(
            ['user_id' => $student3->id, 'learning_path_id' => $mobilePath->id],
            ['enrolled_at' => now()->subHours(2)]
        );

        // 4. Seed Path Meetings
        $webMeeting1 = PathMeeting::updateOrCreate(
            ['learning_path_id' => $webPath->id, 'meeting_number' => 1],
            [
                'title' => 'Pertemuan 1: Kickoff & Fondasi Website Development',
                'description' => 'Sesi pembuka alur belajar Basic Website Development. Membahas arsitektur HTTP, client-server, dan setup workspace lokal.',
                'meeting_date' => now()->addDays(2)->setTime(19, 30),
                'end_time' => '21:30 WIB',
                'location' => 'Zoom Online',
                'meeting_url' => 'https://zoom.us/j/cit-web-01',
                'notes' => 'Bawa laptop yang sudah terpasang Git dan text editor.',
                'is_completed' => false,
            ]
        );

        $mobileMeeting1 = PathMeeting::updateOrCreate(
            ['learning_path_id' => $mobilePath->id, 'meeting_number' => 1],
            [
                'title' => 'Pertemuan 1: Flutter Architecture & Widget Fundamentals',
                'description' => 'Sesi perkenalan Flutter SDK, bahasa Dart modern, pemahaman Widget Tree (Stateless vs Stateful), dan pembuatan layout mobile pertama.',
                'meeting_date' => now()->addDays(3)->setTime(19, 0),
                'end_time' => '21:00 WIB',
                'location' => 'Zoom Online',
                'meeting_url' => 'https://zoom.us/j/cit-mobile-01',
                'notes' => 'Install Flutter SDK terbaru dan Android Studio / VS Code dengan extension Flutter.',
                'is_completed' => false,
            ]
        );

        // 5. Create Materials (1 Modul per Path)
        Material::updateOrCreate(
            [
                'learning_path_id' => $webPath->id,
                'slug' => 'fondasi-web-modern',
            ],
            [
                'path_meeting_id' => $webMeeting1->id,
                'title' => 'Fondasi Web Modern & Pengenalan Ekosistem CIT',
                'summary' => 'Mempelajari cara kerja web modern, arsitektur client-server, HTTP request lifecycle, dan standar pengembangan web terkini.',
                'content' => "## Pengenalan Arsitektur Web\n\nWeb modern dibangun di atas protokol standar HTTP/HTTPS yang menghubungkan klien (browser pengguna) dengan server backend. Dalam modul pertama ini, kita akan memahami alur kerja request-response, bagaimana DNS memetakan domain ke server IP, serta peranan penting status code (200, 404, 500).\n\n### Komponen Utama Sistem Web:\n1. **Front-End**: Bertanggung jawab atas visual, styling responsif, dan interaktivitas pengguna.\n2. **Back-End API**: Memproses logika bisnis, validasi masukan, otentikasi sesi, dan interaksi basis data.\n3. **Database Relasional**: Menyimpan entitas data secara konsisten dengan integritas relasi tabel.\n\n### Checklist Belajar:\n- Memahami siklus HTTP Request & Response\n- Mengetahui perbedaan Server-Side Rendering (SSR) dan Client-Side Rendering (CSR)\n- Menyiapkan environment lokal: PHP 8.4, Composer, Node.js, dan Git.",
                'order' => 1,
                'read_time_minutes' => 10,
                'resource_url' => 'https://developer.mozilla.org/id/',
                'is_published' => true,
            ]
        );

        Material::updateOrCreate(
            [
                'learning_path_id' => $mobilePath->id,
                'slug' => 'arsitektur-mobile-flutter',
            ],
            [
                'path_meeting_id' => $mobileMeeting1->id,
                'title' => 'Arsitektur Mobile App & Pengenalan Flutter SDK',
                'summary' => 'Dasar pemrograman Dart, konsep Widget pohon (Widget Tree), dan siklus hidup aplikasi mobile.',
                'content' => "## Flutter: Satu Basis Kode untuk Banyak Platform\n\nFlutter adalah framework open-source oleh Google untuk membuat aplikasi terkompilasi native yang indah untuk perangkat mobile, web, dan desktop.\n\n### Konsep Kunci:\n- **Semua adalah Widget**: Text, Container, Row, Column, hingga Scaffold adalah widget.\n- **Stateless vs Stateful**: Bedakan antara UI statis dengan widget yang memiliki perubahan data secara dinamis.",
                'order' => 1,
                'read_time_minutes' => 15,
                'resource_url' => 'https://flutter.dev',
                'is_published' => true,
            ]
        );

        // 6. Seed Calendar Events
        $events = [
            [
                'title' => 'Sosialisasi & Kickoff CIT Learning System 2026',
                'description' => 'Sesi perkenalan program pembelajaran, silabus per path, dan petunjuk teknis sistem pembelajaran online.',
                'event_date' => '2026-11-02',
                'event_time' => '09:00 - 11:30 WIB',
                'event_type' => 'event',
                'learning_path_id' => null,
            ],
            [
                'title' => 'Batas Akhir Pemilihan Path Basic Mobile Development',
                'description' => 'Pastikan telah mengunci pilihan path sebelum waktu pemilihan ditutup oleh sistem.',
                'event_date' => '2026-11-07',
                'event_time' => '23:59 WIB',
                'event_type' => 'deadline',
                'learning_path_id' => $mobilePath->id,
            ],
            [
                'title' => 'Batas Akhir Pemilihan Path Basic Website Development',
                'description' => 'Batas akhir konfirmasi pilihan kelas Basic Website Development.',
                'event_date' => '2026-11-07',
                'event_time' => '23:59 WIB',
                'event_type' => 'deadline',
                'learning_path_id' => $webPath->id,
            ],
        ];

        foreach ($events as $ev) {
            CalendarEvent::firstOrCreate(
                [
                    'title' => $ev['title'],
                    'event_date' => $ev['event_date'],
                ],
                $ev
            );
        }

        // 7. Seed Announcements
        $announcements = [
            [
                'title' => 'Selamat Datang di CIT Learning System 2026!',
                'content' => "Halo rekan-rekan pembelajar CIT!\n\nSelamat datang di platform pembelajaran CIT Learning System. Silakan pilih Learning Path yang diminati sebelum batas waktu pendaftaran berakhir. Modul dan materi pembelajaran dapat dibaca kapan saja secara mandiri melalui menu Materi.",
                'priority' => 'important',
                'learning_path_id' => null,
                'is_sent_to_email' => true,
                'sent_at' => now()->subDays(1),
                'created_by' => $admin->id,
            ],
            [
                'title' => 'Pemberitahuan: Batas Waktu Pemilihan Path Learning',
                'content' => 'Diingatkan kepada seluruh peserta bahwa pemilihan Learning Path memiliki batas waktu (deadline) hingga 07 Nov 2026 pukul 23:59 WIB. Jika masa pemilihan telah lewat, sistem akan mengunci formulir pemilihan dan peserta tidak dapat memilih atau mengubah path kembali.',
                'priority' => 'urgent',
                'learning_path_id' => null,
                'is_sent_to_email' => true,
                'sent_at' => now()->subHours(12),
                'created_by' => $admin->id,
            ],
        ];

        foreach ($announcements as $ann) {
            Announcement::firstOrCreate(
                ['title' => $ann['title']],
                $ann
            );
        }

        // 11. Seed Default SMTP Mail Settings
        $defaultMailSettings = [
            'mail_mailer' => (string) config('mail.default', 'smtp'),
            'mail_host' => (string) config('mail.mailers.smtp.host', 'smtp-relay.brevo.com'),
            'mail_port' => (string) config('mail.mailers.smtp.port', '587'),
            'mail_encryption' => (string) (config('mail.mailers.smtp.encryption') ?? 'tls'),
            'mail_username' => (string) (config('mail.mailers.smtp.username') ?? 'abf316001@smtp-brevo.com'),
            'mail_password' => (string) (config('mail.mailers.smtp.password') ?? ''),
            'mail_from_address' => (string) (config('mail.from.address') ?? 'gsatigaserangkai@gmail.com'),
            'mail_from_name' => (string) (config('mail.from.name') ?? 'CIT Learning Systems'),
        ];

        foreach ($defaultMailSettings as $key => $val) {
            AppSetting::firstOrCreate(
                ['key' => $key],
                ['value' => $val, 'group' => 'mail']
            );
        }
    }
}
