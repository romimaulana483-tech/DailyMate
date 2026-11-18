# DailyMate — Your Smart Everyday Assistant

Nama : Muhammad Romi Maulana
NIM : 41257216
Prodi : Teknik Informatika
Kelas : IF - 2025
Link Dokumen : https://drive.google.com/file/d/1iJFw1G-QFdubYEnt2mkNbpWztryzlgCU/view?usp=sharing
Link Video : 

DailyMate adalah aplikasi web manajemen produktivitas harian berbasis **Laravel 12** dengan **Blade**, **Bootstrap 5**, **MySQL**, **Chart.js**, integrasi **Qwen AI (Friday)**, dan pengiriman WhatsApp melalui **Fonnte**. Aplikasi memiliki dua peran terpisah - **User** dan **Admin** - dengan dashboard, hak akses, dan data yang terisolasi.

Halaman utama: landing page profesional, autentikasi, dashboard pengguna, manajemen tugas, jadwal, kalender bulanan, statistik produktivitas, Friday AI, notifikasi, feedback, profil, serta panel admin untuk mengelola akun, konfigurasi AI/WhatsApp, feedback, laporan agregat, dan statistik sistem.

---

## Daftar Isi

- [Fitur](#fitur)
- [Teknologi](#teknologi)
- [Kebutuhan Sistem](#kebutuhan-sistem)
- [Instalasi](#instalasi)
  - [Opsi A — XAMPP / Apache](#opsi-a--xampp--apache)
  - [Opsi B — Laravel Development Server](#opsi-b--laravel-development-server)
- [Akun Demo](#akun-demo)
- [Konfigurasi Friday (Qwen AI)](#konfigurasi-friday-qwen-ai)
- [Konfigurasi WhatsApp (Fonnte)](#konfigurasi-whatsapp-fonnte)
- [Scheduler dan Queue](#scheduler-dan-queue)
- [Menjalankan Pengujian](#menjalankan-pengujian)
- [Keamanan & Isolasi Data](#keamanan--isolasi-data)
- [Struktur Penting](#struktur-penting)
- [Troubleshooting](#troubleshooting)

---

## Fitur

### Pengguna (User)

| Fitur | Keterangan |
|---|---|
| Dashboard | Ringkasan total, selesai, pending, in-progress, overdue, completion rate, grafik 7 hari, jadwal terdekat, dan tugas terbaru |
| Manajemen Tugas | CRUD lengkap, pencarian, filter status/prioritas/kategori, sorting, deadline, tandai selesai, dan deteksi overdue |
| Manajemen Jadwal | CRUD jadwal, filter berdasarkan tanggal, mini calendar, dan daftar aktivitas |
| Kalender | Tampilan bulanan dengan navigasi bulan, penanda aktivitas, dan panel detail per tanggal |
| Statistik Produktivitas | Grafik tugas selesai 14 hari, distribusi prioritas, status, dan kategori |
| Friday AI | Chat interaktif untuk User dan Admin, riwayat pribadi per akun, saran cepat, hapus riwayat, fallback assistant saat Qwen belum dikonfigurasi, serta penolakan pertanyaan di luar cakupan DailyMate |
| Notifikasi | Riwayat pengingat pribadi, status belum dibaca, tandai satu/semua sudah dibaca, dan pengaturan pengingat WhatsApp 1 hari, 12 jam, 3 jam, serta 1 jam sebelum deadline |
| Feedback | Kirim kritik, saran, laporan bug, atau permintaan fitur; lihat status dan balasan admin |
| Profil | Ubah nama/email, ganti password dengan verifikasi password lama, dan pengaturan nomor WhatsApp |

### Admin

| Fitur | Keterangan |
|---|---|
| Dashboard Admin | Statistik akun, grafik registrasi 6 bulan, usage platform agregat, dan registrasi terbaru |
| User Management | Daftar akun user, pencarian, filter status, aktivasi/deaktivasi, detail akun, dan hapus akun |
| Audit Log | Mencatat aksi admin (aktivasi, deaktivasi, penghapusan akun, perubahan konfigurasi, feedback, laporan) |
| System Statistics | Total user, akun aktif/nonaktif, total tasks/schedules/AI messages, tren registrasi 12 bulan |
| Friday AI Settings | Simpan endpoint, model, dan API key Qwen yang dienkripsi server-side; Test Connection memakai API asli |
| WhatsApp API | Konfigurasi provider, token terenkripsi, Test Connection Fonnte melalui `/device`, dan pengiriman uji |
| Notifications | Status teknis integrasi, delivery WhatsApp, alert sistem, dan aktivitas admin tanpa konten privat user |
| Admin Reports | Laporan agregat manual serta jadwal harian/mingguan/bulanan melalui WhatsApp |
| Feedback Management | Filter, baca, ubah status, dan balas feedback user |
| Profil Admin | Ubah profil dan password |

> **Privasi:** Admin **tidak dapat** melihat daftar tugas, detail jadwal, catatan pribadi, atau isi percakapan AI milik user. Panel admin hanya menampilkan data akun dan agregat sistem.

---

## Teknologi

- **Laravel 12.72** + PHP 8.2
- **MySQL** (utf8mb4)
- **Blade Template Engine**
- **Bootstrap 5.3** + Bootstrap Icons
- **Chart.js 4**
- **Qwen API** (OpenAI-compatible DashScope endpoint, dikonfigurasi oleh Admin)
- **Fonnte WhatsApp API** (`https://api.fonnte.com/send` dan `/device`)
- **Laravel Auth**, middleware role, CSRF protection, validasi server-side
- **Queue** untuk pengiriman WhatsApp (opsional; dapat dimatikan untuk shared hosting)
- Aset frontend di-vendor ke `public/vendor` agar tetap andal secara lokal

---

## Kebutuhan Sistem

- PHP **8.2+** dengan ekstensi standar Laravel (`pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`)
- Composer 2
- MySQL 5.7+ / MariaDB (XAMPP sudah mencukupi)
- Web server Apache (XAMPP) **atau** Laravel development server

---

## Instalasi

### Opsi A — XAMPP / Apache

1. **Salin project** ke folder web XAMPP, misalnya `C:\xampp\htdocs\DailyMate`.

2. **Jalankan Apache dan MySQL** dari XAMPP Control Panel.

3. **Buat database** (bisa via phpMyAdmin atau terminal):

   ```bash
   C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE dailymate CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```

4. **Install dependency PHP** dari root project:

   ```bash
   cd C:\xampp\htdocs\DailyMate
   C:\xampp\php\php.exe C:\xampp\php\composer.phar install
   ```

5. **Siapkan file environment**:

   ```bash
   copy .env.example .env
   C:\xampp\php\php.exe artisan key:generate
   ```

   Sesuaikan bagian database di `.env`:

   ```dotenv
   APP_NAME=DailyMate
   APP_URL=http://localhost:81/DailyMate/public

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=dailymate
   DB_USERNAME=root
   DB_PASSWORD=
   ```

   Jika MySQL Anda memakai password, isi `DB_PASSWORD` sesuai konfigurasi.

6. **Migrasi dan seed data demo**:

   ```bash
   C:\xampp\php\php.exe artisan migrate:fresh --seed
   ```

7. **Bersihkan cache konfigurasi/view** (opsional tetapi disarankan):

   ```bash
   C:\xampp\php\php.exe artisan optimize:clear
   ```

8. **Buka aplikasi**:

   ```text
   http://localhost:81/DailyMate/public
   ```

   > Konfigurasi ini mengikuti Apache XAMPP yang berjalan di **port 81**. Jika Apache Anda memakai port lain, sesuaikan `APP_URL` dan alamat di browser.

### Opsi B — Laravel Development Server

Jika tidak ingin melalui Apache, setelah langkah database, `.env`, dan migrasi di atas:

```bash
C:\xampp\php\php.exe artisan serve --host=127.0.0.1 --port=8000
```

Buka:

```text
http://127.0.0.1:8000
```

Laravel akan menyesuaikan URL aplikasi dengan alamat request, sehingga link tetap bekerja.

---

## Akun Demo

Data demo dibuat oleh `AdminSeeder` dan `DemoDataSeeder`.

| Peran | Email | Password |
|---|---|---|
| Admin | `admin@dailymate.test` | `Admin12345` |
| User | `user@dailymate.test` | `User12345` |
| User kedua (uji isolasi data) | `budi@dailymate.test` | `User12345` |

> Setelah login, user diarahkan ke `/user/dashboard` dan admin ke `/admin/dashboard`.

---

## Konfigurasi Friday (Qwen AI)

Friday menggunakan Qwen melalui endpoint OpenAI-compatible. API key **tidak** disimpan di `.env`, tidak ditulis ke JavaScript/localStorage, dan tidak pernah dikirim kembali ke browser.

1. Login sebagai Admin.
2. Buka **Friday AI Settings** (`/admin/ai`).
3. Isi endpoint Qwen, model, dan API key baru.
4. Klik **Test Connection** untuk memverifikasi key dan model melalui request API asli.
5. Aktifkan Friday dan simpan konfigurasi.

Nilai awal yang disediakan:

```text
Endpoint: https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions
Model:    qwen-plus
```

Gunakan endpoint dan model yang tersedia di region Qwen Anda. API key dienkripsi di tabel `ai_configurations` menggunakan encrypted cast Laravel. Jika key pernah dibagikan di chat atau tempat lain, revoke key tersebut dan buat key baru.

### Mode Fallback

Jika Qwen belum dikonfigurasi, key tidak valid, atau API gagal diakses, DailyMate otomatis memakai **built-in Friday assistant**. Fallback ini:

- Memberi saran prioritas berdasarkan tugas user yang masih terbuka.
- Menyusun rencana harian berdasarkan jadwal hari ini.
- Memberi tips manajemen waktu.
- Menolak pertanyaan yang jelas di luar cakupan DailyMate.
- Tetap menyimpan percakapan ke riwayat akun yang sedang login.

Admin hanya menerima konteks agregat. Friday Admin tidak dapat mengakses judul tugas, detail jadwal, nomor telepon, atau isi percakapan AI milik user.

## Konfigurasi WhatsApp (Fonnte)

1. Login sebagai Admin.
2. Buka **WhatsApp API** (`/admin/whatsapp`).
3. Pilih provider **Fonnte**.
4. Isi endpoint `https://api.fonnte.com/send` dan token Fonnte.
5. Klik **Test Connection**. DailyMate memanggil `POST https://api.fonnte.com/device` dan memastikan `status=true` serta `device_status=connect`.
6. Simpan konfigurasi dan kirim pesan uji.

Token dienkripsi di database dan tidak ditampilkan kembali. Pengiriman memakai field form resmi Fonnte: `target`, `message`, dan `countryCode=0`. Respons HTTP 200 dengan `status=false` tetap dianggap gagal.

Pengguna mengaktifkan pengingat dan memilih offset 1 hari, 12 jam, 3 jam, dan/atau 1 jam di halaman **Notifications** atau **Profile**. Hanya tugas yang belum selesai yang memicu pengingat, dan setiap offset hanya dikirim sekali per deadline.

## Scheduler dan Queue

Jalankan scheduler Laravel setiap menit:

```bash
C:\xampp\php\php.exe artisan schedule:run
```

Atau gunakan Task Scheduler Windows untuk menjalankan perintah tersebut setiap menit.

Perintah terjadwal:

- `whatsapp:send-reminders` setiap 5 menit.
- `whatsapp:send-reminders --high-priority` setiap hari pukul 08:00.
- `reports:send-due` setiap menit untuk laporan admin yang jatuh tempo.

Jika `WHATSAPP_QUEUE=true`, jalankan queue worker:

```bash
C:\xampp\php\php.exe artisan queue:work
```

---

## Menjalankan Pengujian

Test menggunakan SQLite in-memory (dikonfigurasi di `phpunit.xml`), sehingga **tidak** mengubah database MySQL Anda.

```bash
C:\xampp\php\php.exe artisan test --compact
```

Cakupan pengujian (116 test, 511 assertion):

- Registrasi, login, logout, akun nonaktif tidak bisa login.
- Proteksi route berdasarkan role (user tidak bisa masuk admin, admin tidak bisa masuk workspace user).
- CRUD tugas dan jadwal, validasi, pencarian/filter/sorting.
- Isolasi data: user tidak dapat melihat/mengubah/menghapus data user lain.
- Kalender dan statistik hanya memakai data milik user.
- Friday AI: konteks user vs admin, penolakan pertanyaan di luar cakupan, isolasi riwayat, fallback, dan konfigurasi Qwen terenkripsi.
- Notifikasi: isolasi per akun, tandai dibaca, offset 1 hari/12 jam/3 jam/1 jam, deduplikasi, dan pengingat in-app saat WhatsApp belum siap.
- Fonnte: payload resmi, Test Connection `/device`, device terputus, dan `status=false`.
- Feedback: isolasi user, validasi, filter admin, balasan admin, dan audit log.
- Laporan admin: konten agregat tanpa data privat, manual, terjadwal, dan log pengiriman.
- Admin: manajemen akun, aktivasi/deaktivasi, penghapusan cascade, audit log.
- Admin tidak dapat melihat konten privat user.
- Profil dan perubahan password.

---

## Keamanan & Isolasi Data

- **Role middleware** (`EnsureUserRole`) memblokir akses lintas peran dan memutus sesi akun yang dinonaktifkan.
- **Ownership scope**: semua query tugas, jadwal, chat, dan notifikasi memakai relasi `auth()->user()->...`, bukan ID bebas.
- **Admin privacy**: daftar user hanya berisi akun dengan `role = user`; halaman detail akun tidak memuat data tugas/jadwal/chat. Friday Admin hanya menerima agregat, dan halaman notifikasi/laporan admin tidak memuat konten privat user.
- **Secret encryption**: API key Qwen dan token WhatsApp dienkripsi di database, disembunyikan dari serialisasi, dan tidak dikirim ke browser.
- **CSRF** aktif pada seluruh form dan request AJAX.
- **Validasi server-side** untuk semua input.
- **XSS mitigation**: output Blade di-escape; format Markdown AI dibersihkan lebih dulu melalui `App\Support\AiText`.
- **Password hashing** bawaan Laravel (bcrypt).
- **Audit log** untuk aksi administratif tanpa menyimpan konten pribadi user.
- **Cascade delete** saat akun user dihapus: tugas, jadwal, riwayat AI, notifikasi, dan feedback ikut terhapus.

---

## Struktur Penting

```text
app/
├── Console/Commands/           # Reminder dan laporan terjadwal
├── Http/
│   ├── Controllers/
│   │   ├── Admin/              # Dashboard, users, AI, WhatsApp, feedback, reports, notifications
│   │   ├── Auth/               # Login, register, logout
│   │   ├── User/               # Dashboard, tasks, schedules, calendar, stats, notifications, feedback
│   │   ├── FridayController.php
│   │   ├── LandingController.php
│   │   └── ProfileController.php
│   └── Middleware/EnsureUserRole.php
├── Models/                     # User, Task, Schedule, ChatHistory, AiConfiguration, ReportSetting, Feedback, dll.
├── Services/
│   ├── QwenService.php         # Friday, konteks role-aware, scope guard, fallback
│   ├── WhatsAppService.php     # Fonnte/Meta/custom, send + test connection
│   ├── WhatsAppReminderService.php
│   └── AdminReportService.php
└── Support/AiText.php          # Safe AI markdown formatting

database/
├── migrations/
├── factories/
└── seeders/                    # AdminSeeder, DemoDataSeeder

resources/views/
├── landing.blade.php
├── layouts/                    # app, guest
├── partials/                   # sidebar, topbar, floating Friday
├── components/                 # stat-card, badges, empty-state
├── auth/
├── friday/                     # Halaman Friday bersama
├── user/                       # dashboard, tasks, schedules, calendar, stats, notifications, feedback
├── admin/                      # dashboard, users, ai, whatsapp, notifications, feedback, reports
└── profile/

public/
├── css/app.css                 # Design system
├── js/app.js                   # Sidebar, alerts, confirmations
├── js/floating-ai.js           # Floating Friday
└── vendor/                     # Bootstrap, Icons, Chart.js (lokal)

routes/web.php                  # Route web + middleware role
routes/console.php              # Scheduler pengingat dan laporan
```

---

## Troubleshooting

| Masalah | Solusi |
|---|---|
| `SQLSTATE[HY000] [1049] Unknown database 'dailymate'` | Buat database sesuai langkah instalasi. |
| `Access denied for user 'root'` | Sesuaikan `DB_USERNAME` / `DB_PASSWORD` di `.env`. |
| Halaman tampil tanpa CSS/JS | Pastikan folder `public/vendor` ada dan `APP_URL` benar. |
| Perubahan `.env` tidak terbaca | Jalankan `php artisan config:clear` atau `php artisan optimize:clear`. |
| AI selalu menjawab dalam mode bawaan | Buka **Friday AI Settings**, isi API key Qwen baru, lalu Test Connection. |
| Qwen error 401/403 | Key salah, sudah dicabut, atau model/endpoint tidak sesuai region. Simpan key baru. |
| Fonnte Test Connection gagal | Pastikan token valid, device terhubung, dan `device_status=connect`. |
| WhatsApp tidak terkirim | Cek konfigurasi WhatsApp, status device Fonnte, dan queue worker jika `WHATSAPP_QUEUE=true`. |
| Pengingat tidak muncul | Aktifkan notifikasi, isi nomor, pilih offset, dan pastikan scheduler berjalan setiap menit. |
| Laporan terjadwal tidak terkirim | Isi recipient, aktifkan jadwal, dan jalankan `php artisan schedule:run`. |
| Port 8000 dipakai | Jalankan `php artisan serve --port=8001`. |
| Ingin reset data demo | Jalankan `php artisan migrate:fresh --seed`. |

---

## Lisensi

Project ini dibuat untuk keperluan pembelajaran dan demonstrasi. Laravel sendiri dirilis di bawah lisensi MIT.
