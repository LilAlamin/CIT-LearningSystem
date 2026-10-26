<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\AppSetting;
use App\Models\CalendarEvent;
use App\Models\Certificate;
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
        $webPath = LearningPath::firstOrCreate(
            ['slug' => 'full-stack-web-development'],
            [
                'title' => 'Full-Stack Web Development',
                'description' => 'Kuasai arsitektur pengembangan web modern dari antarmuka dinamis hingga API backend menggunakan Laravel 13, Livewire, Tailwind CSS, dan relational database.',
                'icon' => 'code-bracket',
                'color' => 'yellow',
                'quota' => 45,
                'registration_start_at' => now()->subDays(5),
                'registration_end_at' => now()->addDays(10), // OPEN
                'is_active' => true,
            ]
        );

        $mobilePath = LearningPath::firstOrCreate(
            ['slug' => 'mobile-app-development'],
            [
                'title' => 'Mobile Application Development',
                'description' => 'Bangun aplikasi mobile multiplatform untuk iOS dan Android dengan Flutter, manajemen state modern, integrasi REST API, dan SQLite/Firebase.',
                'icon' => 'device-phone-mobile',
                'color' => 'emerald',
                'quota' => 30,
                'registration_start_at' => now()->subDays(5),
                'registration_end_at' => now()->addDays(7), // OPEN
                'is_active' => true,
            ]
        );

        $designPath = LearningPath::firstOrCreate(
            ['slug' => 'ui-ux-product-design'],
            [
                'title' => 'UI/UX & Product Design',
                'description' => 'Pelajari riset pengalaman pengguna (UX Research), prinsip visual antarmuka (UI Design), design system modern berbasis Figma, dan pengujian kegunaan (Usability Testing).',
                'icon' => 'paint-brush',
                'color' => 'indigo',
                'quota' => 25,
                'registration_start_at' => now()->subDays(5),
                'registration_end_at' => now()->addDays(5), // OPEN
                'is_active' => true,
            ]
        );

        $cloudPath = LearningPath::firstOrCreate(
            ['slug' => 'cloud-computing-devops'],
            [
                'title' => 'Cloud Computing & DevOps',
                'description' => 'Pelajari kontainerisasi dengan Docker, automated CI/CD pipeline, manajemen server Linux berbasis cloud, dan arsitektur microservices.',
                'icon' => 'server-stack',
                'color' => 'sky',
                'quota' => 20,
                'registration_start_at' => now()->subDays(15),
                'registration_end_at' => now()->subDays(1), // EXPIRED / PAST DEADLINE!
                'is_active' => true,
            ]
        );

        // 4. Enrollments (User can enroll in multiple paths!)
        // Student 1 enolls in Web Path AND Mobile Path
        LearningPathEnrollment::firstOrCreate(
            ['user_id' => $student1->id, 'learning_path_id' => $webPath->id],
            ['enrolled_at' => now()->subDays(2)]
        );

        LearningPathEnrollment::firstOrCreate(
            ['user_id' => $student1->id, 'learning_path_id' => $mobilePath->id],
            ['enrolled_at' => now()->subDays(1)]
        );

        // Student 2 enolls in Mobile Path
        LearningPathEnrollment::firstOrCreate(
            ['user_id' => $student2->id, 'learning_path_id' => $mobilePath->id],
            ['enrolled_at' => now()->subDays(1)]
        );

        // 5. Seed Path Meetings
        // Web Path Meetings:
        $webMeeting1 = PathMeeting::firstOrCreate(
            ['learning_path_id' => $webPath->id, 'meeting_number' => 1],
            [
                'title' => 'Pertemuan 1: Kickoff & Fondasi Web Modern',
                'description' => 'Sesi pembuka alur belajar Full-Stack Web. Membahas arsitektur HTTP, client-server, dan setup workspace lokal (PHP 8.4, Composer, Git).',
                'meeting_date' => now()->subDays(3)->setTime(19, 30),
                'end_time' => '21:30 WIB',
                'location' => 'Zoom Online',
                'meeting_url' => 'https://zoom.us/j/cit-web-01',
                'notes' => 'Bawa laptop yang sudah terpasang Git dan text editor. Clone repo starter CIT sebelum sesi dimulai.',
                'recap_summary' => "### Rangkuman Pertemuan 1\nPada pertemuan ini kita membahas:\n- Cara kerja DNS dan HTTP Request-Response lifecycle.\n- Verifikasi instalasi PHP 8.4 & Composer 2.\n- Inisialisasi Git repository dan standardisasi conventional commits.\n\nSemua peserta sukses melakukan setup awal dan testing artisan serve di lokal.",
                'recap_slides_url' => 'https://slides.cit.id/web-pertemuan-1',
                'recap_recording_url' => 'https://youtube.com/watch?v=cit-web-rec-01',
                'is_completed' => true,
            ]
        );

        $webMeeting2 = PathMeeting::firstOrCreate(
            ['learning_path_id' => $webPath->id, 'meeting_number' => 2],
            [
                'title' => 'Pertemuan 2: Routing, Controller, & Eloquent ORM',
                'description' => 'Membangun aplikasi backend modular dengan Laravel: deklarasi rute, pemisahan logika di controller, serta relasi database One-to-Many dan Many-to-Many dengan Eloquent.',
                'meeting_date' => now()->addDays(2)->setTime(19, 30), // UPCOMING (NEXT MEETING!)
                'end_time' => '21:30 WIB',
                'location' => 'Zoom Online & Lab Komputer 1',
                'meeting_url' => 'https://zoom.us/j/cit-web-02',
                'notes' => 'Baca Modul 2 terlebih dahulu. Siapkan database SQLite/MySQL lokal yang siap pakai.',
                'recap_summary' => null,
                'recap_slides_url' => null,
                'recap_recording_url' => null,
                'is_completed' => false,
            ]
        );

        $webMeeting3 = PathMeeting::firstOrCreate(
            ['learning_path_id' => $webPath->id, 'meeting_number' => 3],
            [
                'title' => 'Pertemuan 3: Antarmuka Reaktif Livewire & Flux UI',
                'description' => 'Implementasi single-file components (SFC), reactive properties, debounce search, modal dialogs, dan toast notifikasi dinamis.',
                'meeting_date' => now()->addDays(6)->setTime(19, 30),
                'end_time' => '21:30 WIB',
                'location' => 'Zoom Online',
                'meeting_url' => 'https://zoom.us/j/cit-web-03',
                'notes' => 'Siapkan branch fitur baru di repository project masing-masing.',
                'is_completed' => false,
            ]
        );

        $webMeeting4 = PathMeeting::firstOrCreate(
            ['learning_path_id' => $webPath->id, 'meeting_number' => 4],
            [
                'title' => 'Pertemuan 4: Keamanan, Testing, & Production Deployment',
                'description' => 'Menulis automated tests dengan Pest, proteksi CSRF/XSS/SQL Injection, optimasi caching production, dan deploy ke cloud.',
                'meeting_date' => now()->addDays(11)->setTime(19, 30),
                'end_time' => '21:30 WIB',
                'location' => 'Zoom Online',
                'meeting_url' => 'https://zoom.us/j/cit-web-04',
                'notes' => 'Pastikan seluruh modul dari pertemuan 1 s/d 3 sudah dipahami dan dipraktikkan.',
                'is_completed' => false,
            ]
        );

        // Mobile Path Meetings:
        $mobileMeeting1 = PathMeeting::firstOrCreate(
            ['learning_path_id' => $mobilePath->id, 'meeting_number' => 1],
            [
                'title' => 'Pertemuan 1: Flutter Architecture & Widget Fundamentals',
                'description' => 'Sesi perkenalan Flutter SDK, bahasa Dart modern, pemahaman Widget Tree (Stateless vs Stateful), dan pembuatan layout mobile pertama.',
                'meeting_date' => now()->subDays(2)->setTime(19, 0),
                'end_time' => '21:00 WIB',
                'location' => 'Zoom Online',
                'meeting_url' => 'https://zoom.us/j/cit-mobile-01',
                'notes' => 'Install Flutter SDK terbaru dan Android Studio / VS Code dengan extension Flutter.',
                'recap_summary' => "### Rangkuman Pertemuan 1 Mobile\n- Pengenalan Dart Null Safety & Object-Oriented Programming.\n- Pembuatan Scaffold, AppBar, ListView, dan Form input interaktif.\n- Running aplikasi di Android Emulator & Real Device.",
                'recap_slides_url' => 'https://slides.cit.id/mobile-pertemuan-1',
                'recap_recording_url' => 'https://youtube.com/watch?v=cit-mobile-rec-01',
                'is_completed' => true,
            ]
        );

        $mobileMeeting2 = PathMeeting::firstOrCreate(
            ['learning_path_id' => $mobilePath->id, 'meeting_number' => 2],
            [
                'title' => 'Pertemuan 2: Reactive State Management & API Integration',
                'description' => 'Mempelajari Provider / Riverpod untuk manajemen data global dan konsumsi RESTful API asynchronous menggunakan package http.',
                'meeting_date' => now()->addDays(4)->setTime(19, 0),
                'end_time' => '21:00 WIB',
                'location' => 'Zoom Online',
                'meeting_url' => 'https://zoom.us/j/cit-mobile-02',
                'notes' => 'Pastikan backend dummy atau endpoint API siap diakses.',
                'is_completed' => false,
            ]
        );

        // UI/UX Path Meetings:
        $designMeeting1 = PathMeeting::firstOrCreate(
            ['learning_path_id' => $designPath->id, 'meeting_number' => 1],
            [
                'title' => 'Pertemuan 1: Human-Centered Design & UX Research',
                'description' => 'Metodologi wawancara pengguna, pembuatan persona, empathy mapping, dan problem statement definition.',
                'meeting_date' => now()->addDays(3)->setTime(15, 0),
                'end_time' => '17:00 WIB',
                'location' => 'Lab Multimedia CIT & Zoom Online',
                'meeting_url' => 'https://zoom.us/j/cit-design-01',
                'notes' => 'Buat akun Figma gratis dan siapkan catatan untuk latihan studi kasus riset.',
                'is_completed' => false,
            ]
        );

        $designMeeting2 = PathMeeting::firstOrCreate(
            ['learning_path_id' => $designPath->id, 'meeting_number' => 2],
            [
                'title' => 'Pertemuan 2: Figma Design System, Auto-Layout, & Variables',
                'description' => 'Praktek merancang token warna, tipografi berskala, auto-layout responsif, dan interaktif komponen prototype.',
                'meeting_date' => now()->addDays(8)->setTime(15, 0),
                'end_time' => '17:00 WIB',
                'location' => 'Zoom Online',
                'meeting_url' => 'https://zoom.us/j/cit-design-02',
                'notes' => 'Pelajari shortcut dasar Figma.',
                'is_completed' => false,
            ]
        );

        // Cloud Path Meetings:
        $cloudMeeting1 = PathMeeting::firstOrCreate(
            ['learning_path_id' => $cloudPath->id, 'meeting_number' => 1],
            [
                'title' => 'Pertemuan 1: Linux Server Administration & SSH Hardening',
                'description' => 'Pengenalan terminal bash, manajemen user/grup, SSH key pair authentication, dan firewall UFW.',
                'meeting_date' => now()->subDays(5)->setTime(19, 0),
                'end_time' => '21:00 WIB',
                'location' => 'Zoom Online',
                'notes' => 'Siapkan terminal Linux / WSL2 / MacOS.',
                'recap_summary' => "### Rangkuman Pertemuan 1 Cloud\n- Akses remote server menggunakan SSH keys tanpa password.\n- Konfigurasi firewall UFW hanya mengizinkan port 22, 80, dan 443.\n- Monitoring CPU & RAM dengan top dan htop.",
                'recap_slides_url' => 'https://slides.cit.id/cloud-pertemuan-1',
                'recap_recording_url' => 'https://youtube.com/watch?v=cit-cloud-rec-01',
                'is_completed' => true,
            ]
        );

        // 6. Create Materials with path_meeting_id linked!
        $webMaterials = [
            [
                'title' => 'Fondasi Web Modern & Pengenalan Ekosistem CIT',
                'slug' => 'fondasi-web-modern',
                'path_meeting_id' => $webMeeting1->id,
                'summary' => 'Mempelajari cara kerja web modern, arsitektur client-server, HTTP request lifecycle, dan standar pengembangan web terkini.',
                'order' => 1,
                'read_time_minutes' => 10,
                'resource_url' => 'https://developer.mozilla.org/id/',
                'content' => "## Pengenalan Arsitektur Web\n\nWeb modern dibangun di atas protokol standar HTTP/HTTPS yang menghubungkan klien (browser pengguna) dengan server backend. Dalam modul pertama ini, kita akan memahami alur kerja request-response, bagaimana DNS memetakan domain ke server IP, serta peranan penting status code (200, 404, 500).\n\n### Komponen Utama Sistem Web:\n1. **Front-End**: Bertanggung jawab atas visual, styling responsif, dan interaktivitas pengguna.\n2. **Back-End API**: Memproses logika bisnis, validasi masukan, otentikasi sesi, dan interaksi basis data.\n3. **Database Relasional**: Menyimpan entitas data secara konsisten dengan integritas relasi tabel.\n\n### Checklist Belajar:\n- Memahami siklus HTTP Request & Response\n- Mengetahui perbedaan Server-Side Rendering (SSR) dan Client-Side Rendering (CSR)\n- Menyiapkan environment lokal: PHP 8.4, Composer, Node.js, dan Git.",
            ],
            [
                'title' => 'Fundamental Laravel: Routing, Controller, & Eloquent ORM',
                'slug' => 'fundamental-laravel-routing-eloquent',
                'path_meeting_id' => $webMeeting2->id,
                'summary' => 'Menjelajahi kerangka kerja Laravel dari deklarasi rute, pemisahan kontroler, migrasi skema tabel, hingga relasi Eloquent ORM.',
                'order' => 2,
                'read_time_minutes' => 20,
                'resource_url' => 'https://laravel.com/docs',
                'content' => "## Framework Laravel: The PHP Framework for Web Artisans\n\nLaravel menyediakan pengalaman pengembang yang elegan dengan fitur bawaan seperti dependency injection container, database migration, dan Eloquent ORM yang sangat ekspresif.\n\n### 1. Deklarasi Routing Bersih\n```php\nuse App\\Http\\Controllers\\PostController;\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get('/posts', [PostController::class, 'index'])->name('posts.index');\nRoute::post('/posts', [PostController::class, 'store'])->middleware('auth');\n```\n\n### 2. Eloquent Model & Relasi\nEloquent memperlakukan setiap baris tabel sebagai objek. Misalnya, relasi satu ke banyak antara Learning Path dan Materi:\n```php\npublic function materials(): HasMany\n{\n    return \$this->hasMany(Material::class)->orderBy('order');\n}\n```\n\n### Tips Penting:\nSelalu gunakan Form Request atau validasi ketat sebelum menyimpan data masukan untuk mencegah kerentanan Mass Assignment dan SQL Injection.",
            ],
            [
                'title' => 'Komponen Reaktif Real-Time dengan Livewire & Flux UI',
                'slug' => 'komponen-reaktif-livewire-flux',
                'path_meeting_id' => $webMeeting3->id,
                'summary' => 'Membangun antarmuka dinamis dan interaktif tanpa kerumitan SPA JavaScript murni, memanfaatkan Livewire 4 dan Flux UI.',
                'order' => 3,
                'read_time_minutes' => 25,
                'resource_url' => 'https://livewire.laravel.com',
                'content' => "## Mengapa Livewire?\n\nLivewire memungkinkan pengembang menulis kode antarmuka yang reaktif dan dinamis menggunakan PHP dan template Blade. Ketika interaksi pengguna terjadi (seperti klik tombol atau ketikan pencarian), Livewire mengirimkan AJAX request cerdas ke server dan memperbarui DOM secara selektif.\n\n### Contoh Komponen Single-File (SFC):\n```blade\n<?php\nuse Livewire\\Component;\n\nnew class extends Component {\n    public string \$search = '';\n    public function clear(): void { \$this->search = ''; }\n};\n?>\n\n<div>\n    <input type=\"text\" wire:model.live=\"search\" placeholder=\"Cari materi...\" />\n    <button wire:click=\"clear\">Reset</button>\n</div>\n```\n\nDengan Flux UI, kita mendapatkan komponen siap pakai yang konsisten, aksesibel, dan terintegrasi rapi dengan Tailwind CSS.",
            ],
            [
                'title' => 'Deployment, Keamanan & Best Practices Aplikasi Web',
                'slug' => 'deployment-keamanan-best-practices',
                'path_meeting_id' => $webMeeting4->id,
                'summary' => 'Panduan deployment ke server produksi, optimasi performa caching, proteksi CSRF, sanitasi masukan, dan CI/CD pipeline.',
                'order' => 4,
                'read_time_minutes' => 15,
                'resource_url' => 'https://laravel.com/docs/deployment',
                'content' => "## Menyiapkan Aplikasi untuk Lingkungan Produksi\n\nSebelum meluncurkan aplikasi ke pengguna publik, ada beberapa langkah standar industri yang wajib dilakukan:\n\n1. **Environment Config**: Pastikan `APP_DEBUG=false` dan `APP_ENV=production`.\n2. **Cache Optimizations**:\n   - `php artisan config:cache`\n   - `php artisan route:cache`\n   - `php artisan view:cache`\n3. **HTTPS & Security Headers**: Selalu enforce koneksi SSL dan atur CSP (Content Security Policy).\n4. **Database Indexing**: Berikan indeks pada kolom yang sering dicari atau dijadikan foreign key.",
            ],
        ];

        foreach ($webMaterials as $mat) {
            Material::updateOrCreate(
                [
                    'learning_path_id' => $webPath->id,
                    'slug' => $mat['slug'],
                ],
                [
                    'path_meeting_id' => $mat['path_meeting_id'],
                    'title' => $mat['title'],
                    'summary' => $mat['summary'],
                    'content' => $mat['content'],
                    'order' => $mat['order'],
                    'read_time_minutes' => $mat['read_time_minutes'],
                    'resource_url' => $mat['resource_url'],
                    'is_published' => true,
                ]
            );
        }

        // Materials for Mobile Path
        $mobileMaterials = [
            [
                'title' => 'Arsitektur Mobile App & Pengenalan Flutter SDK',
                'slug' => 'arsitektur-mobile-flutter',
                'path_meeting_id' => $mobileMeeting1->id,
                'summary' => 'Dasar pemrograman Dart, konsep Widget pohon (Widget Tree), dan siklus hidup aplikasi mobile.',
                'order' => 1,
                'read_time_minutes' => 15,
                'resource_url' => 'https://flutter.dev',
                'content' => "## Flutter: Satu Basis Kode untuk Banyak Platform\n\nFlutter adalah framework open-source oleh Google untuk membuat aplikasi terkompilasi native yang indah untuk perangkat mobile, web, dan desktop.\n\n### Konsep Kunci:\n- **Semua adalah Widget**: Text, Container, Row, Column, hingga Scaffold adalah widget.\n- **Stateless vs Stateful**: Bedakan antara UI statis dengan widget yang memiliki perubahan data secara dinamis.",
            ],
            [
                'title' => 'State Management Modern & Navigasi Mobile',
                'slug' => 'state-management-flutter',
                'path_meeting_id' => $mobileMeeting2->id,
                'summary' => 'Mengelola state aplikasi dengan Provider / Bloc / Riverpod untuk performa aplikasi yang terstruktur dan scalable.',
                'order' => 2,
                'read_time_minutes' => 20,
                'resource_url' => 'https://docs.flutter.dev/data-and-backend/state-mgmt',
                'content' => "## Manajemen State dalam Flutter\n\nSeiring berkembangnya kompleksitas aplikasi, mengalirkan state melalui constructor menjadi tidak efisien. Di modul ini kita mempelajari cara kerja Reactive State Management untuk memisahkan data layer dengan UI layer.",
            ],
        ];

        foreach ($mobileMaterials as $mat) {
            Material::updateOrCreate(
                [
                    'learning_path_id' => $mobilePath->id,
                    'slug' => $mat['slug'],
                ],
                [
                    'path_meeting_id' => $mat['path_meeting_id'],
                    'title' => $mat['title'],
                    'summary' => $mat['summary'],
                    'content' => $mat['content'],
                    'order' => $mat['order'],
                    'read_time_minutes' => $mat['read_time_minutes'],
                    'resource_url' => $mat['resource_url'],
                    'is_published' => true,
                ]
            );
        }

        // Materials for Design Path
        $designMaterials = [
            [
                'title' => 'Prinsip Human-Centered Design & UX Research',
                'slug' => 'human-centered-design-ux',
                'path_meeting_id' => $designMeeting1->id,
                'summary' => 'Memahami kebutuhan pengguna melalui wawancara, observasi, penyusunan User Persona, dan Empathy Mapping.',
                'order' => 1,
                'read_time_minutes' => 15,
                'resource_url' => 'https://www.nngroup.com',
                'content' => "## Dasar UX Research\n\nDesain bukan sekadar mempercantik tampilan, melainkan menyelesaikan masalah nyata pengguna dengan efisien dan intuitif.\n\n### Tahapan Design Thinking:\n1. Empathize: Pahami pengguna Anda\n2. Define: Rumuskan problem statement\n3. Ideate: Eksplorasi berbagai solusi kreatif\n4. Prototype: Buat prototipe cepat\n5. Test: Validasi dengan pengguna target.",
            ],
            [
                'title' => 'Membangun Design System Skalabel di Figma',
                'slug' => 'design-system-figma',
                'path_meeting_id' => $designMeeting2->id,
                'summary' => 'Pemanfaatan Color Tokens, Typography Hierarchy, Auto-Layout, Component Variants, dan Variables di Figma.',
                'order' => 2,
                'read_time_minutes' => 20,
                'resource_url' => 'https://figma.com',
                'content' => "## Figma Design System\n\nDesign system yang baik menjembatani komunikasi antara desainer dan insinyur perangkat lunak melalui tokenisasi desain, komponen modular yang reusable, serta dokumentasi panduan visual.",
            ],
        ];

        foreach ($designMaterials as $mat) {
            Material::updateOrCreate(
                [
                    'learning_path_id' => $designPath->id,
                    'slug' => $mat['slug'],
                ],
                [
                    'path_meeting_id' => $mat['path_meeting_id'],
                    'title' => $mat['title'],
                    'summary' => $mat['summary'],
                    'content' => $mat['content'],
                    'order' => $mat['order'],
                    'read_time_minutes' => $mat['read_time_minutes'],
                    'resource_url' => $mat['resource_url'],
                    'is_published' => true,
                ]
            );
        }

        // Materials for Cloud Path
        $cloudMaterials = [
            [
                'title' => 'Fundamental Linux Server & Virtualisasi',
                'slug' => 'fundamental-linux-server',
                'path_meeting_id' => $cloudMeeting1->id,
                'summary' => 'Manajemen hak akses file Linux, SSH key authentication, systemd service, firewall UFW, dan networking dasar.',
                'order' => 1,
                'read_time_minutes' => 15,
                'resource_url' => 'https://ubuntu.com/server/docs',
                'content' => "## Administrasi Linux Server\n\nSebagian besar infrastruktur web berjalan di atas distribusi Linux seperti Ubuntu Server atau Debian. Memahami shell script bash, pengelolaan proses, dan izin keamanan adalah pondasi mutlak DevOps.",
            ],
            [
                'title' => 'Kontainerisasi Modern dengan Docker & Compose',
                'slug' => 'kontainerisasi-docker-compose',
                'path_meeting_id' => null,
                'summary' => 'Membuat Dockerfile optimal multi-stage build, mengelola container image, volume, dan multi-service network dengan Compose.',
                'order' => 2,
                'read_time_minutes' => 20,
                'resource_url' => 'https://docker.com',
                'content' => "## Mengapa Docker?\n\nDocker mengatasi masalah 'it works on my machine' dengan mengemas kode, dependensi, dan runtime ke dalam satu container yang dapat dijalankan secara konsisten di mana saja.",
            ],
        ];

        foreach ($cloudMaterials as $mat) {
            Material::updateOrCreate(
                [
                    'learning_path_id' => $cloudPath->id,
                    'slug' => $mat['slug'],
                ],
                [
                    'path_meeting_id' => $mat['path_meeting_id'],
                    'title' => $mat['title'],
                    'summary' => $mat['summary'],
                    'content' => $mat['content'],
                    'order' => $mat['order'],
                    'read_time_minutes' => $mat['read_time_minutes'],
                    'resource_url' => $mat['resource_url'],
                    'is_published' => true,
                ]
            );
        }

        // 7. Seed student1 completing first material
        $firstMat = Material::where('learning_path_id', $webPath->id)->orderBy('order')->first();
        if ($firstMat) {
            $student1->completedMaterials()->syncWithoutDetaching([
                $firstMat->id => ['completed_at' => now()->subDay()],
            ]);
        }

        // 8. Seed Calendar Events
        $events = [
            [
                'title' => 'Sosialisasi & Kickoff CIT Learning System 2026',
                'description' => 'Sesi perkenalan program pembelajaran, silabus per path, dan petunjuk teknis sistem pembelajaran online.',
                'event_date' => now()->subDays(3)->toDateString(),
                'event_time' => '09:00 - 11:30 WIB',
                'event_type' => 'event',
                'learning_path_id' => null,
            ],
            [
                'title' => 'Deadline Pemilihan Path Cloud & DevOps',
                'description' => 'Batas waktu akhir pemilihan kuota registrasi untuk path Cloud Computing & DevOps.',
                'event_date' => now()->subDays(1)->toDateString(),
                'event_time' => '23:59 WIB',
                'event_type' => 'deadline',
                'learning_path_id' => $cloudPath->id,
            ],
            [
                'title' => 'Batas Akhir Pemilihan Path UI/UX Product Design',
                'description' => 'Batas akhir bagi peserta yang ingin memilih dan mendaftar pada kelas UI/UX Product Design.',
                'event_date' => now()->addDays(5)->toDateString(),
                'event_time' => '23:59 WIB',
                'event_type' => 'deadline',
                'learning_path_id' => $designPath->id,
            ],
            [
                'title' => 'Batas Akhir Pemilihan Path Mobile Development',
                'description' => 'Pastikan telah mengunci pilihan path sebelum waktu pemilihan ditutup oleh sistem.',
                'event_date' => now()->addDays(7)->toDateString(),
                'event_time' => '23:59 WIB',
                'event_type' => 'deadline',
                'learning_path_id' => $mobilePath->id,
            ],
            [
                'title' => 'Batas Akhir Pemilihan Path Full-Stack Web Development',
                'description' => 'Batas akhir konfirmasi pilihan kelas Full-Stack Web Development.',
                'event_date' => now()->addDays(10)->toDateString(),
                'event_time' => '23:59 WIB',
                'event_type' => 'deadline',
                'learning_path_id' => $webPath->id,
            ],
            [
                'title' => 'Pertemuan 2: Routing & Eloquent ORM (Web Dev)',
                'description' => 'Sesi live online bersama instruktur mengenai standardisasi MVC dan database relation.',
                'event_date' => now()->addDays(2)->toDateString(),
                'event_time' => '19:30 - 21:30 WIB',
                'event_type' => 'workshop',
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

        // 9. Seed Announcements
        $announcements = [
            [
                'title' => 'Selamat Datang di CIT Learning System 2026!',
                'content' => "Halo rekan-rekan pembelajar CIT!\n\nSelamat datang di platform pembelajaran CIT Learning System. Anda bebas memilih lebih dari 1 Learning Path yang diminati sebelum batas waktu pendaftaran berakhir. Modul dan materi pembelajaran dapat dibaca kapan saja secara mandiri melalui menu Materi.",
                'priority' => 'important',
                'learning_path_id' => null,
                'is_sent_to_email' => true,
                'sent_at' => now()->subDays(2),
                'created_by' => $admin->id,
            ],
            [
                'title' => 'Pemberitahuan: Batas Waktu Pemilihan Path Learning',
                'content' => 'Diingatkan kepada seluruh peserta bahwa pemilihan Learning Path memiliki batas waktu (deadline) yang telah ditentukan oleh Administrator. Jika masa pemilihan telah lewat, sistem akan mengunci formulir pemilihan dan peserta tidak dapat memilih atau mengubah path kembali.',
                'priority' => 'urgent',
                'learning_path_id' => null,
                'is_sent_to_email' => true,
                'sent_at' => now()->subDay(),
                'created_by' => $admin->id,
            ],
            [
                'title' => 'Modul Tambahan & Rekap Pertemuan 1 Telah Rilis',
                'content' => 'Halo peserta Full-Stack Web Development! Rangkuman materi pertemuan 1 serta slide tayang telah diunggah ke menu Alur & Pertemuan. Silakan pelajari kembali sebelum sesi pertemuan 2 dimulai.',
                'priority' => 'normal',
                'learning_path_id' => $webPath->id,
                'is_sent_to_email' => true,
                'sent_at' => now()->subHours(6),
                'created_by' => $admin->id,
            ],
        ];

        foreach ($announcements as $ann) {
            Announcement::firstOrCreate(
                ['title' => $ann['title']],
                $ann
            );
        }

        // 10. Seed Sample Certificate for Ahmad Fauzi (Full-Stack Web Development)
        Certificate::firstOrCreate(
            [
                'user_id' => $student1->id,
                'learning_path_id' => $webPath->id,
            ],
            [
                'certificate_number' => 'CIT-CERT/2026/WEB/0001',
                'title' => 'Sertifikat Kelulusan - Full-Stack Web Development',
                'status' => 'issued',
                'grade' => 'Lulus dengan Sangat Baik (A)',
                'notes' => 'Telah menunjukkan performa dan komitmen luar biasa dalam menyelesaikan seluruh modul serta rangkaian pertemuan Full-Stack Web.',
                'issued_at' => now()->subDay(),
                'issued_by' => $admin->id,
                'certificate_url' => 'https://drive.google.com/file/d/sample-cit-certificate/view',
            ]
        );

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
