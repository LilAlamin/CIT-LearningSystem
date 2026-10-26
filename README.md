# CIT LMS (Learning Management System) — Version 1.00

> **Developed by Academic Division CIT**  
> Sistem manajemen pembelajaran modern, adaptif, dan terintegrasi untuk mahasiswa dan administrator Club of Information Technology (CIT).

---

## 📌 Ringkasan Proyek

**CIT LMS Version 1.00** dibangun menggunakan ekosistem terbaru **Laravel 12 / 13**, **Livewire 4**, dan **Flux UI** untuk memberikan pengalaman belajar yang dinamis, interaktif, dan responsif. Aplikasi ini memisahkan alur kerja antara **Peserta (Siswa/Mahasiswa)** dan **Administrator** secara komprehensif, dilengkapi dengan sistem notifikasi email via Brevo SMTP serta desain antarmuka modern bertema khas CIT.

---

## 🚀 Fitur Unggulan

### 👨‍🎓 1. Platform Pembelajaran Siswa (Student Learning Platform)
* **Dashboard Interaktif**:
  * Informasi status jalur belajar yang diikuti, mentor, dan tanggal bergabung.
  * Kartu KPI progress: Sesi Pertemuan yang diikuti, Modul Materi yang dipelajari, dan Status Sertifikat kelulusan.
  * Kalender interaktif dinamis dengan penanda agenda belajar real-time.
  * Widget agenda mendatang dan pengumuman terbaru.
* **Katalog & Pemilihan Path Learning**:
  * Menampilkan berbagai jalur pembelajaran (Web Development, Mobile Apps, UI/UX Design, Data Science, AI, dll).
  * Status kapasitas kuota, periode pendaftaran, dan status enrollment.
* **Milestone & Pertemuan**:
  * **Alur & Pertemuan**: Timeline pertemuan bertahap, status kehadiran, link meeting online (Zoom/Google Meet), dan materi per sesi.
  * **Capaian Saya**: Pelacakan milestone pencapaian belajar dan sertifikat kompetensi.
* **Modul & Materi Pembelajaran**:
  * Akses materi lengkap berdasarkan path, file dokumen pendukung, modul bacaan, video tutorial, serta tautan eksternal.
* **Pusat Pengumuman & Info**:
  * Informasi penting akademik CIT, filter kategori, dan pencarian pengumuman.

---

### 🛡️ 2. Panel Administrator (Admin Management)
* **Admin Dashboard Overview**:
  * **Ringkasan KPI Global**: Total Jalur Belajar aktif, Total Mahasiswa Terdaftar, Modul Materi terbit, Sesi Pertemuan terjadwal, dan Total Sertifikat diterbitkan.
  * **Pusat Pintasan Navigasi Cepat** ke semua modul kelola admin.
  * **Monitoring Kapasitas Jalur Pembelajaran**: Progress bar kuota peserta real-time dan status pendaftaran.
  * **Daftar Pendaftar Terbaru**: Log pendaftaran siswa terbaru dengan aksi cepat.
  * **Aksi Cepat Agenda & Pengumuman**: Tombol tambah agenda langsung dari kalender dashboard.
* **Kelola Path (Learning Paths)**: CRUD jalur belajar, kuota maksimal, periode registrasi, dan mentor penanggung jawab.
* **Kelola Pertemuan (Meetings)**: Penjadwalan sesi pertemuan, penentuan tanggal, waktu, link virtual meeting, dan lokasi fisik.
* **Kelola Materi (Materials)**: Unggah dan organisasi modul belajar, kategori, urutan pembelajaran, dan lampiran file.
* **Data Pendaftar (Enrollments)**: Manajemen pendaftaran siswa ke learning path (Pending, Approved, Rejected, Completed).
* **Kelola Sertifikat (Certificates)**: Verifikasi kelulusan dan penerbitan sertifikat digital dengan kode unik sertifikat.
* **Pengumuman & Email (Announcements & Mass Email)**: Pembuatan pengumuman terintegrasi dengan pengiriman email massal otomatis kepada peserta.
* **Agenda Kalender (Calendar Events)**: Manajemen jadwal agenda akademik dan kegiatan CIT.
* **Pengaturan SMTP & Email Server (Database-Driven)**: Konfigurasi pengiriman email Brevo langsung dari antarmuka web Admin tanpa perlu menyunting file `.env` di server kampus, dilengkapi fitur uji coba koneksi kirim email instan.
* **Pratinjau Tampilan Siswa**: Navigasi khusus bagi Admin untuk menginspeksi alur belajar mahasiswa secara langsung.

---

## 🛠️ Tech Stack & Ekosistem

* **Framework Backend**: [Laravel 13](https://laravel.com/) (PHP 8.3 / 8.4)
* **Frontend Reaktif**: [Livewire 4](https://livewire.laravel.com/) + Alpine.js
* **UI Components & Styling**: [Flux UI](https://fluxui.dev/) + Tailwind CSS
* **Autentikasi**: [Laravel Fortify](https://laravel.com/docs/fortify)
* **Database**: MySQL / PostgreSQL / SQLite
* **Email Service**: Brevo SMTP (Sendinblue)
* **Testing Suite**: [Pest PHP 5](https://pestphp.com/) (60 Automated Feature & Unit Tests)
* **Code Formatter**: [Laravel Pint](https://laravel.com/docs/pint)

---

## 📋 Persyaratan Sistem

* PHP `>= 8.3` (Disarankan PHP 8.4)
* Composer `>= 2.2`
* Node.js `>= 20.x` & npm
* Ekstensi PHP: `pdo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `curl`

---

## ⚡ Panduan Instalasi Cepat

### 1. Clone Repository
```bash
git clone https://github.com/LilAlamin/CIT-LearningSystem.git
cd CIT-LearningSystem
```

### 2. Pasang Dependensi
```bash
composer install
npm install
```

### 3. Konfigurasi Environment (`.env`)
Salin file `.env.example` ke `.env`:
```bash
cp .env.example .env
php artisan key:generate
```

Sesuaikan konfigurasi database dan kredensial mailer di `.env`:
```env
APP_NAME="CIT Learning System"
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cit_learning_system
DB_USERNAME=root
DB_PASSWORD=

MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=your_brevo_username
MAIL_PASSWORD=your_brevo_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="academic@cit.or.id"
MAIL_FROM_NAME="CIT Academic Division"
```

### 4. Migrasi & Seeder Database
Jalankan migrasi beserta data awal (Administrator, Mahasiswa contoh, Path, Pertemuan, Materi, Pengumuman):
```bash
php artisan migrate --seed
```

> **Kredensial Default Login:**
> * **Administrator**: `admin@cit.ac.id` / `password`
> * **Peserta / Mahasiswa**: `student@cit.ac.id` / `password`

### 5. Jalankan Server Pengembangan
Jalankan server aplikasi dan Vite bundler secara bersamaan:
```bash
composer run dev
```
Akses aplikasi melalui peramban di: `http://localhost:8000`

---

## 🧪 Pengujian Otomatis (Automated Testing)

CIT LMS dilengkapi dengan suite pengujian Pest PHP untuk memastikan stabilitas dan regresi fitur:

```bash
# Menjalankan seluruh test suite
php artisan test --compact

# Menjalankan test modul dashboard
php artisan test --filter=DashboardTest
```

---

## 🎨 Standar Kode

Proyek ini menggunakan Laravel Pint untuk memastikan format dan standar kode konsisten:
```bash
composer run lint
# atau
vendor/bin/pint --format agent
```

---

## 👥 Tim Pengembang

* **Divisi**: Academic Division CIT
* **Versi**: Cit LMS Version 1.00
* **Organisasi**: Club of Information Technology (CIT)

---

## 📄 Lisensi

Aplikasi ini dilisensikan di bawah lisensi [MIT](LICENSE).
