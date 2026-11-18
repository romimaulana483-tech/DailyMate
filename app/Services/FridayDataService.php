<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\AiConfiguration;
use App\Models\ChatHistory;
use App\Models\Feedback;
use App\Models\Schedule;
use App\Models\Task;
use App\Models\User;
use App\Models\WhatsAppConfiguration;
use App\Models\WhatsAppNotification;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class FridayDataService
{
    private const MONTHS = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    private const PRIORITIES = [
        'low' => 'Rendah',
        'medium' => 'Sedang',
        'high' => 'Tinggi',
    ];

    private const STATUSES = [
        'todo' => 'Belum dikerjakan',
        'in_progress' => 'Sedang dikerjakan',
        'completed' => 'Selesai',
    ];

    /**
     * Resolve a user question into an intent, authoritative database facts, and
     * either a direct Indonesian answer or a context payload for Qwen.
     *
     * @return array{mode: string, intent: string, answer: ?string, context: string, data: array}
     */
    public function resolve(User $user, string $message): array
    {
        $text = $this->normalize($message);

        if ($text === '') {
            return $this->unclear();
        }

        // Identity questions must win before the out-of-scope fallback so
        // "siapa anda" is always answered as Friday, not as an unknown topic.
        if ($this->asksAssistantIdentity($text)) {
            return $this->assistantIdentity($user);
        }

        if ($this->isOutOfScope($text)) {
            return $this->outOfScope();
        }

        if ($this->isGreeting($text)) {
            return $this->direct(
                'greeting',
                'Halo. Saya Friday, asisten DailyMate. Saya dapat membantu mengecek tugas, jadwal, statistik, notifikasi, dan fitur DailyMate.'
            );
        }

        if ($this->asksMenuHowTo($text)) {
            return $this->menuHowTo($user, $text);
        }

        if ($this->asksIdentity($text)) {
            return $this->identity($user, $text);
        }

        if ($this->asksReminderHelp($text)) {
            return $this->reminderHelp();
        }

        if ($this->asksIntegrationStatus($text)) {
            return $this->integrationStatus($user, $text);
        }

        if ($user->isAdmin()) {
            $adminIntent = $this->adminIntent($user, $text);

            if ($adminIntent !== null) {
                return $adminIntent;
            }
        } else {
            $adminStatsIntent = $this->userAdminStatsDenied($text);

            if ($adminStatsIntent !== null) {
                return $adminStatsIntent;
            }
        }

        if (! $user->isAdmin()) {
            // Task breakdown questions must win before the general statistics
            // intent so "distribusi prioritas" returns task groups, not just counts.
            if ($this->asksTaskCompletionDate($text)) {
                return $this->tasksCompletedByDate($user, $text);
            }

            if ($this->asksTasksByCategory($text)) {
                return $this->tasksByCategory($user, $text);
            }

            if ($this->asksPriorityDistribution($text)) {
                return $this->tasksByPriority($user, $text);
            }

            if ($this->asksTaskOrder($text)) {
                return $this->taskOrder($user, $text);
            }
        }

        if (! $user->isAdmin() && $this->asksUserStatistics($text)) {
            return $this->userStatistics($user, $text);
        }

        $taskIntent = $this->taskIntent($user, $text);

        if ($taskIntent !== null) {
            return $taskIntent;
        }

        $scheduleIntent = $this->scheduleIntent($user, $text);

        if ($scheduleIntent !== null) {
            return $scheduleIntent;
        }

        $feedbackIntent = $this->feedbackIntent($user, $text);

        if ($feedbackIntent !== null) {
            return $feedbackIntent;
        }

        $notificationIntent = $this->notificationIntent($user, $text);

        if ($notificationIntent !== null) {
            return $notificationIntent;
        }

        if ($this->asksForPlan($text)) {
            return $this->planning($user);
        }

        if ($this->asksForHelp($text)) {
            return $this->help($user);
        }

        if ($this->containsDomainWord($text)) {
            return $this->unclear();
        }

        return $this->outOfScope();
    }

    private function assistantIdentity(User $user): array
    {
        $help = $user->isAdmin()
            ? 'Saya dapat membantu statistik sistem, pengelolaan pengguna, feedback, laporan, dan status integrasi.'
            : 'Saya dapat membantu mengecek tugas, jadwal, deadline, notifikasi, dan data akun Anda.';

        return $this->direct(
            'assistant_identity',
            "Saya adalah Friday, asisten AI resmi DailyMate. {$help}"
        );
    }

    private function identity(User $user, string $text): array
    {
        $role = $user->isAdmin() ? 'Admin' : 'User';

        if ($this->containsAny($text, ['email', 'surel'])) {
            return $this->direct('identity_email', "Email akun Anda adalah {$user->email}.");
        }

        if ($this->containsAny($text, ['login sebagai', 'role', 'peran', 'hak akses', 'my role', 'which role', 'logged in as'])) {
            return $this->direct('identity_role', "Anda login sebagai {$role} DailyMate.");
        }

        if ($this->containsAny($text, ['status akun', 'akun aktif', 'akun nonaktif', 'account status'])) {
            $status = $user->isActive() ? 'aktif' : 'tidak aktif';

            return $this->direct('identity_status', "Status akun Anda {$status}.");
        }

        if ($this->containsAny($text, ['terdaftar', 'registrasi', 'bergabung', 'tanggal daftar', 'when did i register'])) {
            return $this->direct(
                'identity_registration',
                'Anda terdaftar di DailyMate pada '.$this->formatDate($user->created_at).'.'
            );
        }

        if ($this->containsAny($text, ['akun saya', 'profil saya', 'data saya', 'my account', 'my profile'])) {
            return $this->direct(
                'identity_account',
                "Nama: {$user->name}\nEmail: {$user->email}\nRole: {$role}\nStatus: ".($user->isActive() ? 'Aktif' : 'Tidak aktif')
                    ."\nTerdaftar: ".$this->formatDate($user->created_at)
            );
        }

        return $this->direct('identity_name', "Nama Anda {$user->name}. Anda login sebagai {$role} DailyMate.");
    }

    private function asksMenuHowTo(string $text): bool
    {
        if (! $this->containsAny($text, [
            'cara', 'bagaimana', 'langkah', 'tutorial', 'panduan', 'how to', 'steps',
        ])) {
            return false;
        }

        $menuAction = $this->containsAny($text, [
            'menambah', 'tambah', 'membuat', 'buat', 'mengubah', 'ubah', 'mengedit', 'edit',
            'menghapus', 'hapus', 'melihat', 'lihat', 'membuka', 'buka', 'mengakses', 'akses',
            'mengirim', 'kirim', 'mengelola', 'kelola', 'mengaktifkan', 'aktifkan',
            'menonaktifkan', 'nonaktifkan', 'mengisi', 'isi', 'menyimpan', 'simpan',
            'menggunakan', 'gunakan', 'memakai', 'pakai',
            'add', 'create', 'delete', 'view', 'open', 'access', 'send', 'manage',
            'enable', 'disable', 'fill', 'save', 'use', 'configure', 'change', 'update',
        ]);

        $settingsAction = $this->containsAny($text, ['mengatur', 'atur', 'setting', 'pengaturan'])
            && $this->containsAny($text, [
                'notifikasi', 'pengingat', 'profil', 'akun', 'password', 'whatsapp',
                'laporan', 'report', 'ai', 'qwen', 'jadwal', 'schedule', 'reminder',
            ]);

        if (! $menuAction && ! $settingsAction) {
            return false;
        }

        return $this->containsAny($text, [
            'dailymate', 'menu', 'fitur', 'feature', 'aplikasi',
            'tugas', 'task', 'jadwal', 'schedule', 'kalender', 'calendar',
            'statistik', 'statistics', 'produktivitas', 'productivity',
            'notifikasi', 'notification', 'pengingat', 'reminder',
            'profil', 'profile', 'akun', 'account', 'password', 'sandi',
            'feedback', 'masukan', 'saran', 'umpan balik',
            'friday', 'asisten ai', 'ai assistant', 'chat ai',
            'pengguna', 'user', 'member', 'laporan', 'report',
            'whatsapp', 'fonnte', 'qwen', 'api', 'dashboard', 'dasbor', 'pengaturan',
        ]);
    }

    private function menuHowTo(User $user, string $text): array
    {
        $isAdmin = $user->isAdmin();

        $asksTasks = $this->containsAny($text, ['tugas', 'task']);
        $asksSchedules = $this->containsAny($text, ['jadwal', 'schedule', 'agenda']);
        $asksCalendar = $this->containsAny($text, ['kalender', 'calendar']);
        $asksProductivity = $this->containsAny($text, ['produktivitas', 'productivity', 'statistik produktivitas']);
        $asksStats = $this->containsAny($text, ['statistik', 'statistics']);
        $asksNotifications = $this->containsAny($text, ['notifikasi', 'notification', 'pengingat', 'reminder']);
        $asksProfile = $this->containsAny($text, ['profil', 'profile', 'password', 'sandi', 'profil saya', 'akun saya', 'my profile', 'my account'])
            || (! $isAdmin && $this->containsAny($text, ['akun', 'account']));
        $asksFeedback = $this->containsAny($text, ['feedback', 'masukan', 'umpan balik', 'saran']);
        $asksFriday = $this->containsAny($text, ['friday', 'asisten ai', 'ai assistant', 'chat ai']);
        $asksWhatsapp = $this->containsAny($text, ['whatsapp', 'fonnte', 'api wa', 'wa api']);
        $asksUsers = $this->containsAny($text, [
            'pengguna', 'user management', 'manajemen pengguna', 'kelola user',
            'kelola pengguna', 'member', 'akun user', 'tambah user',
        ]) || ($isAdmin && $this->containsAny($text, ['akun', 'account'])
            && ! $this->containsAny($text, ['profil', 'password', 'sandi', 'profil saya', 'akun saya']));
        $asksReports = $this->containsAny($text, ['laporan', 'report']);
        $asksAiSettings = $this->containsAny($text, [
            'pengaturan ai', 'konfigurasi ai', 'friday ai settings', 'qwen', 'api ai', 'model ai',
        ]);
        $asksSystemStats = $this->containsAny($text, [
            'statistik sistem', 'system statistics', 'statistik admin', 'statistik pengguna',
        ]);
        $asksMenu = $this->containsAny($text, ['menu', 'fitur', 'feature', 'aplikasi', 'dailymate']);

        $sections = [];

        if ($isAdmin) {
            if ($asksTasks || $asksSchedules || $asksCalendar || $asksProductivity) {
                $sections[] = implode("\n", [
                    'Akun Admin tidak memiliki menu My Tasks (Tugas Saya), My Schedule (Jadwal Saya), Calendar, atau Productivity Statistics.',
                    'Tugas dan jadwal adalah data pribadi milik akun User, sehingga Admin tidak dapat membuat atau melihat detailnya.',
                    'Jika perlu menambah tugas atau jadwal, gunakan akun User. Sebagai Admin, gunakan menu User Management, System Statistics, Admin Reports, Friday AI Settings, atau WhatsApp API sesuai kebutuhan.',
                ]);
            }

            if ($asksUsers) {
                $sections[] = implode("\n", [
                    'Untuk mengelola pengguna:',
                    '1. Buka menu User Management (Manajemen Pengguna) di sidebar Admin.',
                    '2. Gunakan Search users atau filter Account status, lalu klik Apply.',
                    '3. Klik ikon mata untuk melihat detail akun.',
                    '4. Klik ikon person-dash/person-check untuk menonaktifkan atau mengaktifkan akun.',
                    '5. Klik ikon hapus untuk menghapus akun. Tindakan ini permanen dan ikut menghapus data privat terkait.',
                    'Admin tidak memiliki tombol tambah user. Akun baru dibuat melalui halaman Register publik, lalu dikelola dari User Management.',
                ]);
            }

            if ($asksSystemStats || ($asksStats && ! $asksProductivity && ! $asksUsers)) {
                $sections[] = implode("\n", [
                    'Untuk melihat System Statistics (Statistik Sistem):',
                    '1. Buka menu System Statistics di sidebar Admin.',
                    '2. Lihat Total Users, Active Users, Inactive Users, Tasks Created, Schedules Created, dan AI Messages Sent.',
                    '3. Grafik Registrations per Month menampilkan pendaftaran 12 bulan terakhir.',
                    '4. Grafik Account Status dan Feature Usage Summary menampilkan ringkasan status akun serta penggunaan fitur.',
                ]);
            }

            if ($asksReports) {
                $sections[] = implode("\n", [
                    'Untuk mengatur dan mengirim laporan:',
                    '1. Buka menu Admin Reports (Laporan Admin).',
                    '2. Isi Recipient WhatsApp number dengan format internasional dan tentukan Send time.',
                    '3. Aktifkan Enable scheduled reports, lalu pilih Daily report, Weekly report, dan/atau Monthly report.',
                    '4. Klik Save Settings.',
                    '5. Untuk mengirim sekarang, klik Send Report Now. Riwayat pengiriman ada di Report delivery history.',
                ]);
            }

            if ($asksFeedback) {
                $sections[] = implode("\n", [
                    'Untuk mengelola feedback User:',
                    '1. Buka menu Feedback (Masukan).',
                    '2. Gunakan Search, Category, atau Status untuk memfilter feedback.',
                    '3. Klik ikon mata untuk membuka detail feedback.',
                    '4. Ubah Status, tulis Response to user, lalu klik Save Feedback.',
                    'Balasan Admin akan terlihat oleh User pada riwayat Feedback miliknya.',
                ]);
            }

            if ($asksNotifications) {
                $sections[] = implode("\n", [
                    'Untuk menggunakan notifikasi Admin:',
                    '1. Buka menu Notifications (Notifikasi) di sidebar Admin.',
                    '2. Halaman ini menampilkan notifikasi sistem dan status pengiriman.',
                    '3. Untuk mengatur koneksi pengiriman, buka menu WhatsApp API.',
                ]);
            }

            if ($asksWhatsapp) {
                $sections[] = implode("\n", [
                    'Untuk mengatur WhatsApp API:',
                    '1. Buka menu WhatsApp API (API WhatsApp) di sidebar Admin.',
                    '2. Pilih API Provider, lalu isi API Endpoint / URL dan API Token / Access Token.',
                    '3. Untuk Meta Cloud API, isi juga Phone Number ID dan data pendukung yang diminta.',
                    '4. Klik Save Configuration.',
                    '5. Klik Test Connection untuk menguji kredensial. Gunakan Test Notification untuk mengirim pesan uji.',
                    'Token disimpan terenkripsi; biarkan kolom token kosong jika tidak ingin menggantinya.',
                ]);
            }

            if ($asksAiSettings) {
                $sections[] = implode("\n", [
                    'Untuk mengatur Friday AI:',
                    '1. Buka menu Friday AI Settings (Pengaturan Friday AI) di sidebar Admin.',
                    '2. Isi Qwen API endpoint, Model, dan Qwen API key.',
                    '3. Aktifkan Enable Friday AI for both roles jika Friday AI ingin digunakan oleh Admin dan User.',
                    '4. Klik Save Configuration.',
                    '5. Klik Test Connection untuk memastikan konfigurasi Qwen benar.',
                ]);
            }

            if ($asksFriday && ! $asksAiSettings) {
                $sections[] = implode("\n", [
                    'Untuk menggunakan Friday AI sebagai Admin:',
                    '1. Buka menu Friday AI di sidebar.',
                    '2. Tulis pertanyaan tentang data agregat, statistik sistem, cara memakai menu, atau fitur DailyMate.',
                    '3. Kirim pesan dan baca jawaban Friday.',
                    'Friday Admin hanya menggunakan data agregat dan tidak dapat membuka detail tugas, jadwal, nomor, atau percakapan User.',
                ]);
            }

            if ($asksProfile) {
                $sections[] = implode("\n", [
                    'Untuk mengubah profil Admin:',
                    '1. Buka menu Profile (Profil) di sidebar Admin.',
                    '2. Pada Personal Information, ubah Full name atau Email address.',
                    '3. Klik Save Changes.',
                    '4. Untuk mengganti password, isi Current password, New password, dan Confirm new password, lalu klik Update Password.',
                ]);
            }

            if ($asksMenu || $sections === []) {
                $sections[] = implode("\n", [
                    'Menu akun Admin:',
                    '- Dashboard: ringkasan kondisi sistem.',
                    '- User Management (Manajemen Pengguna): cari, lihat, aktif/nonaktifkan, dan hapus akun.',
                    '- System Statistics (Statistik Sistem): statistik agregat platform.',
                    '- Notifications (Notifikasi): notifikasi sistem.',
                    '- Feedback (Masukan): review dan balas feedback User.',
                    '- Admin Reports (Laporan Admin): atur dan kirim laporan agregat.',
                    '- Friday AI: tanya data agregat dan cara memakai menu.',
                    '- Friday AI Settings (Pengaturan Friday AI): konfigurasi Qwen.',
                    '- WhatsApp API (API WhatsApp): konfigurasi provider dan tes koneksi.',
                    '- Profile (Profil): ubah data akun dan password.',
                    'Admin tidak memiliki menu My Tasks atau My Schedule; data tersebut bersifat pribadi milik User.',
                ]);
            }

            return $this->direct('menu_how_to', implode("\n\n", $sections));
        }

        $asksAdminOnly = $asksUsers
            || $asksReports
            || $asksAiSettings
            || $asksSystemStats
            || ($asksWhatsapp && $this->containsAny($text, ['api', 'token', 'fonnte', 'integrasi']));

        if ($asksAdminOnly) {
            $sections[] = 'Menu User Management, System Statistics, Admin Reports, Friday AI Settings, dan WhatsApp API hanya dapat digunakan oleh akun Admin. Akun User hanya dapat mengakses menu tugas, jadwal, kalender, statistik produktivitas, notifikasi, feedback, profil, dan Friday AI miliknya sendiri.';
        }

        if ($asksTasks) {
            $sections[] = implode("\n", [
                'Untuk menambah tugas:',
                '1. Buka menu My Tasks (Tugas Saya) di sidebar.',
                '2. Klik tombol Add New Task.',
                '3. Isi Task title, Description, Category, Deadline, Priority, dan Status. Task title, Priority, dan Status wajib diisi.',
                '4. Klik Create Task.',
                'Untuk mengubah atau menghapus tugas, gunakan ikon Edit atau Hapus pada daftar My Tasks. Untuk menandai tugas selesai, klik ikon centang.',
            ]);
        }

        if ($asksSchedules) {
            $sections[] = implode("\n", [
                'Untuk menambah jadwal:',
                '1. Buka menu My Schedule (Jadwal Saya) di sidebar.',
                '2. Klik tombol Add Schedule.',
                '3. Isi Schedule title, Description, Date, Start time, dan End time. End time harus lebih lambat dari Start time.',
                '4. Klik Create Schedule.',
                '5. Jadwal akan tampil di My Schedule dan Calendar. Gunakan ikon Edit atau Hapus pada daftar jadwal untuk mengubah atau menghapusnya.',
            ]);
        }

        if ($asksCalendar) {
            $sections[] = implode("\n", [
                'Untuk menggunakan Calendar (Kalender):',
                '1. Buka menu Calendar di sidebar.',
                '2. Gunakan tombol panah kiri/kanan untuk berpindah bulan, atau tombol Today untuk kembali ke bulan ini.',
                '3. Klik tanggal untuk melihat aktivitas pada hari tersebut.',
                '4. Klik Add Schedule untuk membuat jadwal pada tanggal itu, lalu isi detail jadwal dan klik Create Schedule.',
            ]);
        }

        if ($asksProductivity || $asksStats) {
            $sections[] = implode("\n", [
                'Untuk melihat Statistik Produktivitas:',
                '1. Buka menu Productivity Statistics (Statistik Produktivitas) di sidebar.',
                '2. Lihat Total Tasks, Completed Tasks, Pending Tasks, dan Completion Rate.',
                '3. Grafik Tasks Completed by Date menampilkan tugas selesai selama 14 hari terakhir.',
                '4. Grafik Priority Distribution, Status Breakdown, dan Tasks by Category menampilkan rincian tugas Anda.',
                '5. Klik Manage tasks untuk kembali ke daftar tugas.',
            ]);
        }

        if ($asksNotifications) {
            $sections[] = implode("\n", [
                'Untuk menggunakan notifikasi:',
                '1. Buka menu Notifications (Notifikasi) untuk melihat daftar notifikasi.',
                '2. Buka item notifikasi atau gunakan Mark as read untuk menandainya sudah dibaca.',
                '3. Untuk pengingat WhatsApp, buka Profile > WhatsApp Notifications.',
                '4. Isi WhatsApp number dengan format internasional, aktifkan Enable WhatsApp reminders, pilih Deadline reminders, centang persetujuan, lalu klik Save WhatsApp Settings.',
            ]);
        }

        if ($asksWhatsapp && ! $asksNotifications) {
            $sections[] = implode("\n", [
                'Untuk mengatur pengingat WhatsApp:',
                '1. Buka menu Profile (Profil).',
                '2. Gulir ke bagian WhatsApp Notifications.',
                '3. Isi WhatsApp number dengan format internasional, misalnya +6281234567890.',
                '4. Aktifkan Enable WhatsApp reminders dan pilih Deadline reminders yang diinginkan.',
                '5. Centang persetujuan, lalu klik Save WhatsApp Settings.',
            ]);
        }

        if ($asksFeedback) {
            $sections[] = implode("\n", [
                'Untuk mengirim feedback:',
                '1. Buka menu Feedback (Masukan) di sidebar.',
                '2. Isi Subject, pilih Category, dan tulis Message.',
                '3. Klik Send Feedback.',
                '4. Riwayat feedback dan balasan Admin dapat dilihat pada halaman Feedback.',
            ]);
        }

        if ($asksFriday) {
            $sections[] = implode("\n", [
                'Untuk menggunakan Friday AI:',
                '1. Buka menu Friday AI di sidebar.',
                '2. Tulis pertanyaan pada kolom chat, misalnya tentang tugas, jadwal, statistik, atau cara memakai menu.',
                '3. Kirim pesan dan baca jawaban Friday.',
                'Friday hanya membaca data milik akun Anda sendiri.',
            ]);
        }

        if ($asksProfile) {
            if ($this->containsAny($text, ['hapus akun', 'menghapus akun', 'delete account'])) {
                $sections[] = 'Akun User tidak memiliki tombol hapus akun. Penghapusan akun hanya dapat dilakukan oleh Admin melalui menu User Management.';
            } else {
                $sections[] = implode("\n", [
                    'Untuk mengubah profil:',
                    '1. Buka menu Profile (Profil) di sidebar.',
                    '2. Pada Personal Information, ubah Full name atau Email address.',
                    '3. Klik Save Changes.',
                    '4. Untuk mengganti password, isi Current password, New password, dan Confirm new password, lalu klik Update Password.',
                ]);
            }
        }

        if ($asksMenu || $sections === []) {
            $sections[] = implode("\n", [
                'Menu akun User:',
                '- Dashboard: ringkasan tugas dan jadwal.',
                '- My Tasks (Tugas Saya): tambah, cari, filter, edit, selesaikan, atau hapus tugas.',
                '- My Schedule (Jadwal Saya): tambah, filter, edit, atau hapus jadwal.',
                '- Calendar (Kalender): lihat jadwal per tanggal dan tambah jadwal.',
                '- Productivity Statistics (Statistik Produktivitas): lihat grafik produktivitas.',
                '- Friday AI: tanyakan data tugas, jadwal, statistik, dan cara memakai menu.',
                '- Notifications (Notifikasi): lihat notifikasi dan pengaturan pengingat.',
                '- Feedback (Masukan): kirim saran atau masukan.',
                '- Profile (Profil): ubah data akun, password, dan pengaturan WhatsApp.',
                'Untuk langkah detail, tanyakan misalnya "cara menambah tugas" atau "cara menambah jadwal".',
            ]);
        }

        return $this->direct('menu_how_to', implode("\n\n", $sections));
    }

    private function adminIntent(User $user, string $text): ?array
    {
        if ($this->containsAny($text, ['aktivitas', 'activity log', 'audit log', 'log sistem'])) {
            return $this->adminActivity($text);
        }

        if ($this->containsAny($text, ['feedback', 'masukan'])) {
            return $this->adminFeedback($text);
        }

        if ($this->containsAny($text, ['isi percakapan', 'isi chat', 'baca percakapan', 'baca chat'])
            || ($this->containsAny($text, ['percakapan ai', 'chat ai', 'riwayat ai'])
                && ! $this->containsAny($text, ['berapa', 'jumlah', 'total']))) {
            return $this->direct(
                'admin_private_chats_denied',
                'Admin tidak dapat membaca isi percakapan AI User. Saya hanya dapat menampilkan jumlah percakapan.'
            );
        }

        if ($this->containsAny($text, ['nomor whatsapp pengguna', 'nomor hp pengguna', 'nomor pribadi pengguna'])) {
            return $this->direct(
                'admin_private_phone_denied',
                'Admin tidak dapat menampilkan nomor WhatsApp pribadi User.'
            );
        }

        if ($this->asksMonthlyRegistrationStats($text)) {
            return $this->adminMonthlyRegistrationStats($text);
        }

        if ($this->asksUserNameList($text)) {
            return $this->adminUserList($text);
        }

        $asksUserNumbers = $this->containsAny($text, ['pengguna', 'user', 'akun', 'accounts', 'pendaftaran', 'registrasi', 'registration'])
            && $this->containsAny($text, ['berapa', 'jumlah', 'total', 'aktif', 'tidak aktif', 'nonaktif', 'bulan ini', 'how many', 'count', 'active', 'inactive', 'this month']);

        if ($asksUserNumbers) {
            return $this->adminUserStats($text);
        }

        $aggregateWords = ['berapa', 'jumlah', 'total', 'tercatat', 'how many', 'count', 'recorded'];

        if ($this->containsAny($text, ['tugas', 'task'])
            && $this->containsAny($text, $aggregateWords)
            && ! $this->containsAny($text, ['saya', 'milik saya'])) {
            return $this->direct('admin_task_total', 'Total tugas yang tercatat di DailyMate: '.Task::count().'.');
        }

        if ($this->containsAny($text, ['jadwal', 'schedule'])
            && $this->containsAny($text, $aggregateWords)
            && ! $this->containsAny($text, ['saya', 'milik saya'])) {
            return $this->direct('admin_schedule_total', 'Total jadwal yang tercatat di DailyMate: '.Schedule::count().'.');
        }

        if ($this->containsAny($text, ['chat', 'percakapan ai', 'riwayat ai'])
            && $this->containsAny($text, $aggregateWords)) {
            return $this->direct('admin_chat_total', 'Total percakapan AI yang tercatat: '.ChatHistory::count().'.');
        }

        if ($this->containsAny($text, ['statistik', 'grafik', 'chart', 'ringkasan sistem', 'overview', 'kondisi sistem', 'status sistem'])) {
            return $this->adminOverview();
        }

        return null;
    }

    private function adminUserStats(string $text): array
    {
        $all = User::query();
        $total = (clone $all)->count();
        $regular = (clone $all)->where('role', 'user')->count();
        $admins = (clone $all)->where('role', 'admin')->count();
        $active = (clone $all)->where('status', 'active')->count();
        $activeRegular = (clone $all)->where('role', 'user')->where('status', 'active')->count();
        $activeAdmins = (clone $all)->where('role', 'admin')->where('status', 'active')->count();
        $inactive = (clone $all)->where('status', 'inactive')->count();
        $newThisMonth = (clone $all)->where('created_at', '>=', now()->startOfMonth())->count();
        $regularNewThisMonth = (clone $all)->where('role', 'user')->where('created_at', '>=', now()->startOfMonth())->count();

        if ($this->containsAny($text, ['tidak aktif', 'nonaktif', 'inactive'])) {
            return $this->direct('admin_inactive_users', "Jumlah akun tidak aktif: {$inactive}.");
        }

        if ($this->containsAny($text, ['bulan ini', 'pendaftaran', 'registrasi baru', 'this month', 'registration'])) {
            return $this->direct(
                'admin_new_users',
                "Pendaftaran bulan ini: {$regularNewThisMonth} User dan ".($newThisMonth - $regularNewThisMonth)
                    ." Admin ({$newThisMonth} akun)."
            );
        }

        if ($this->containsAny($text, ['aktif', 'active'])) {
            return $this->direct(
                'admin_active_users',
                "Jumlah pengguna aktif: {$activeRegular} User dan {$activeAdmins} Admin ({$active} akun aktif)."
            );
        }

        return $this->direct(
            'admin_total_users',
            "Total pengguna: {$regular} User dan {$admins} Admin ({$total} akun terdaftar)."
        );
    }

    private function asksMonthlyRegistrationStats(string $text): bool
    {
        $aboutUsers = $this->containsAny($text, [
            'user', 'pengguna', 'akun', 'pendaftaran', 'registrasi', 'terdaftar',
            'registration', 'registered',
        ]);
        $aboutMonths = $this->containsAny($text, [
            'bulan', 'month', 'bulanan', 'monthly', 'per bulan', 'tiap bulan',
            'setiap bulan', 'kapan',
        ]);
        $superlative = $this->containsAny($text, [
            'paling banyak', 'terbanyak', 'tertinggi', 'paling tinggi', 'terbesar',
            'puncak', 'most', 'highest', 'maximum',
        ]);
        $breakdown = $this->containsAny($text, [
            'per bulan', 'tiap bulan', 'setiap bulan', 'bulanan', 'monthly',
            'tren pendaftaran', 'tren registrasi', 'riwayat pendaftaran',
            'grafik pendaftaran', 'grafik registrasi', 'statistik pendaftaran',
            'statistik registrasi',
        ]);

        return $aboutUsers && ($breakdown || ($aboutMonths && $superlative));
    }

    private function adminMonthlyRegistrationStats(string $text): array
    {
        $counts = [];

        User::query()
            ->where('role', 'user')
            ->orderBy('created_at')
            ->get(['created_at'])
            ->each(function (User $user) use (&$counts): void {
                if (! $user->created_at) {
                    return;
                }

                $key = $user->created_at->format('Y-m');
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            });

        if ($counts === []) {
            return $this->direct('admin_monthly_registrations', 'Belum ada user yang terdaftar.');
        }

        ksort($counts);

        if ($this->containsAny($text, [
            'per bulan', 'tiap bulan', 'setiap bulan', 'bulanan', 'monthly',
            'tren pendaftaran', 'tren registrasi', 'riwayat pendaftaran',
            'grafik pendaftaran', 'grafik registrasi', 'statistik pendaftaran',
            'statistik registrasi',
        ])) {
            $lines = ['Pendaftaran user per bulan:'];

            foreach ($counts as $month => $total) {
                $lines[] = '- '.$this->monthLabel($month).': '.$total.' user';
            }

            return $this->direct('admin_monthly_registrations', implode("\n", $lines));
        }

        $maximum = max($counts);
        $peaks = array_keys($counts, $maximum, true);
        $months = array_map(fn (string $month) => $this->monthLabel($month), $peaks);
        $monthText = implode(', ', $months);

        if (count($peaks) > 1) {
            return $this->direct(
                'admin_monthly_registrations',
                "Pendaftaran user paling banyak terjadi pada bulan {$monthText} dengan jumlah yang sama, yaitu {$maximum} user."
            );
        }

        return $this->direct(
            'admin_monthly_registrations',
            "Pendaftaran user paling banyak terjadi pada bulan {$monthText}, yaitu {$maximum} user."
        );
    }

    private function asksUserNameList(string $text): bool
    {
        if ($this->containsAny($text, ['tugas', 'task', 'jadwal', 'schedule', 'chat', 'percakapan', 'feedback', 'aktivitas', 'deadline'])) {
            return false;
        }

        $aboutPeople = $this->containsAny($text, ['user', 'pengguna', 'akun', 'accounts', 'admin']);
        $asksWho = $this->containsAny($text, ['siapa', 'nama', 'sebutkan', 'daftar', 'tampilkan', 'show', 'list', 'all']);

        return $aboutPeople && $asksWho;
    }

    private function adminUserList(string $text): array
    {
        $query = User::query();
        $wantsAdmin = $this->containsAny($text, ['admin']);
        $wantsUser = $this->containsAny($text, ['pengguna', 'user']);

        if ($wantsAdmin && ! $wantsUser) {
            $query->where('role', 'admin');
            $label = 'admin';
        } elseif ($wantsUser && ! $wantsAdmin) {
            $query->where('role', 'user');
            $label = 'pengguna';
        } else {
            $label = 'akun';
        }

        if ($this->containsAny($text, ['tidak aktif', 'nonaktif', 'inactive'])) {
            $query->where('status', 'inactive');
            $statusLabel = 'tidak aktif';
        } elseif ($this->containsAny($text, ['aktif', 'active'])) {
            $query->where('status', 'active');
            $statusLabel = 'aktif';
        } else {
            $statusLabel = null;
        }

        $total = (clone $query)->count();
        $users = (clone $query)->orderBy('name')->limit(50)->get();

        if ($users->isEmpty()) {
            return $this->direct(
                'admin_user_list',
                'Tidak ada '.$label.($statusLabel ? ' '.$statusLabel : '').' yang terdaftar.'
            );
        }

        $title = 'Daftar '.$label.($statusLabel ? ' '.$statusLabel : ' terdaftar').' ('.$total.'):';
        $lines = [$title];

        foreach ($users->values() as $index => $user) {
            $line = ($index + 1).'. '.$user->name;

            if ($label === 'akun') {
                $line .= ' ('.($user->isAdmin() ? 'Admin' : 'User').')';
            }

            $lines[] = $line;
        }

        if ($total > $users->count()) {
            $lines[] = 'Menampilkan '.$users->count().' dari '.$total.' akun.';
        }

        return $this->direct('admin_user_list', implode("\n", $lines));
    }

    private function userAdminStatsDenied(string $text): ?array
    {
        $asksForPeopleList = $this->containsAny($text, [
            'siapa saja user', 'siapa user', 'user siapa', 'siapa saja pengguna',
            'siapa pengguna', 'pengguna siapa', 'siapa saja akun', 'siapa akun',
            'akun siapa', 'siapa admin', 'siapa saja admin', 'admin siapa', 'daftar pengguna',
            'daftar user', 'daftar akun', 'nama user', 'nama pengguna',
        ]);

        if ($asksForPeopleList) {
            return $this->direct(
                'admin_user_list_denied',
                'Daftar pengguna hanya dapat diakses oleh Admin.'
            );
        }

        $adminOnly = $this->containsAny($text, [
            'pengguna', 'user lain', 'akun aktif', 'akun tidak aktif',
            'pendaftaran', 'registrasi', 'statistik sistem', 'statistik admin',
            'aktivitas sistem', 'activity log', 'audit log',
        ]);

        if (! $adminOnly) {
            return null;
        }

        return $this->direct(
            'admin_stats_denied',
            'Statistik dan data seluruh pengguna hanya dapat diakses oleh Admin.'
        );
    }

    private function adminFeedback(string $text): array
    {
        $total = Feedback::count();
        $pending = Feedback::where('status', 'pending')->count();
        $resolved = Feedback::where('status', 'resolved')->count();
        $inProgress = Feedback::where('status', 'in_progress')->count();

        if ($total === 0) {
            return $this->direct('admin_feedback', 'Belum ada feedback yang masuk.');
        }

        if ($this->containsAny($text, ['pending', 'menunggu'])) {
            return $this->direct('admin_feedback_pending', "Jumlah feedback berstatus pending: {$pending}.");
        }

        if ($this->containsAny($text, ['resolved', 'selesai'])) {
            return $this->direct('admin_feedback_resolved', "Jumlah feedback yang sudah diselesaikan: {$resolved}.");
        }

        return $this->direct(
            'admin_feedback',
            "Total feedback: {$total}.\n- Pending: {$pending}\n- In progress: {$inProgress}\n- Resolved: {$resolved}"
        );
    }

    private function adminActivity(string $text): array
    {
        $logs = ActivityLog::with('admin')->latest('id')->limit(5)->get();

        if ($logs->isEmpty()) {
            return $this->direct('admin_activity', 'Belum ada aktivitas admin yang tercatat.');
        }

        $lines = $logs->map(fn (ActivityLog $log) => '- '.$log->action
            .' ('.($log->admin?->name ?? 'Sistem').', '.$this->formatDateTime($log->created_at).')')->implode("\n");

        return $this->direct('admin_activity', "Aktivitas admin terbaru:\n{$lines}");
    }

    private function adminOverview(): array
    {
        $totalUsers = User::count();
        $regularUsers = User::where('role', 'user')->count();
        $admins = User::where('role', 'admin')->count();
        $active = User::where('status', 'active')->count();
        $inactive = User::where('status', 'inactive')->count();
        $newThisMonth = User::where('created_at', '>=', now()->startOfMonth())->count();
        $ai = AiConfiguration::current();
        $whatsapp = WhatsAppConfiguration::current();
        $pendingDeliveries = WhatsAppNotification::where('status', 'pending')->count();
        $failedDeliveries = WhatsAppNotification::where('status', 'failed')->count();

        $totalTasks = Task::count();
        $pendingTasks = Task::where('status', 'todo')->count();
        $inProgressTasks = Task::where('status', 'in_progress')->count();
        $completedTasks = Task::where('status', 'completed')->count();
        $overdueTasks = Task::where('status', '!=', 'completed')
            ->whereNotNull('deadline')
            ->where('deadline', '<', now())
            ->count();
        $completionRate = $totalTasks > 0 ? (int) round(($completedTasks / $totalTasks) * 100) : 0;

        return $this->direct(
            'admin_overview',
            "Ringkasan sistem DailyMate:\n"
            ."- Total akun: {$totalUsers} (User: {$regularUsers}, Admin: {$admins})\n"
            ."- Akun aktif: {$active}\n"
            ."- Akun tidak aktif: {$inactive}\n"
            ."- Pendaftaran bulan ini: {$newThisMonth}\n"
            ."- Total tugas: {$totalTasks}\n"
            ."- Status tugas: Pending {$pendingTasks}, In progress {$inProgressTasks}, Selesai {$completedTasks}\n"
            ."- Tugas terlambat: {$overdueTasks}\n"
            ."- Tingkat penyelesaian tugas: {$completionRate}%\n"
            .'- Total jadwal: '.Schedule::count()."\n"
            .'- Total percakapan AI: '.ChatHistory::count()."\n"
            .'- Qwen AI: '.($ai?->isReady() ? 'terhubung' : 'belum siap')."\n"
            .'- WhatsApp: '.($whatsapp && $whatsapp->hasToken() && $whatsapp->isReadyToSend() ? 'siap' : 'belum siap')."\n"
            ."- Pengiriman WhatsApp pending: {$pendingDeliveries}\n"
            ."- Pengiriman WhatsApp gagal: {$failedDeliveries}"
        );
    }

    private function asksUserStatistics(string $text): bool
    {
        if ($this->containsAny($text, [
            'pengguna lain', 'user lain', 'orang lain', 'akun lain', 'other user',
        ])) {
            return false;
        }

        // The Statistics page is labelled "Statistik Produktivitas" in Indonesian,
        // so asking just "produktivitas saya" must also read the database.
        if ($this->containsAny($text, ['produktivitas', 'productivity'])) {
            return true;
        }

        $stats = $this->containsAny($text, [
            'statistik', 'grafik', 'chart', 'persentase', 'persen',
            'tingkat penyelesaian', 'completion rate',
            'tren', 'trend', 'analisis', 'breakdown',
            'distribusi', 'distribution',
        ]);
        $aboutTasks = $this->containsAny($text, ['tugas', 'task', 'pekerjaan']);
        $personalStats = $this->containsAny($text, [
            'statistik saya', 'statistik akun saya', 'ringkasan saya',
            'grafik saya', 'statistik pribadi', 'my statistics',
        ]);

        return $stats && ($aboutTasks || $personalStats);
    }

    private function asksTaskCompletionDate(string $text): bool
    {
        if (! $this->containsAny($text, ['tugas', 'task', 'pekerjaan'])) {
            return false;
        }

        if (! $this->containsAny($text, ['selesai', 'completed', 'finished', 'done'])) {
            return false;
        }

        return $this->extractDateRange($text) !== null;
    }

    private function tasksCompletedByDate(User $user, string $text): array
    {
        $range = $this->extractDateRange($text);

        if ($range === null) {
            return $this->direct(
                'task_completed_date',
                'Sebutkan tanggal yang ingin dicek, misalnya "tugas selesai tanggal 15 November".'
            );
        }

        $tasks = $user->tasks()
            ->where('status', 'completed')
            ->whereBetween('updated_at', [$range['start'], $range['end']])
            ->orderBy('updated_at')
            ->orderBy('id')
            ->get();

        $asksCount = $this->containsAny($text, ['berapa', 'jumlah', 'total', 'banyak', 'how many', 'count']);
        $asksList = $this->containsAny($text, [
            'sebutkan', 'apa saja', 'daftar', 'tampilkan', 'lihat', 'tunjukkan',
            'list', 'show', 'what are',
        ]);

        $dateLabel = $range['single']
            ? 'tanggal '.$range['label']
            : 'rentang '.$range['label'];

        if ($asksCount && ! $asksList) {
            return $this->direct(
                'task_completed_date',
                "Jumlah tugas yang selesai pada {$dateLabel}: {$tasks->count()}."
            );
        }

        if ($tasks->isEmpty()) {
            return $this->direct(
                'task_completed_date',
                "Tidak ada tugas yang selesai pada {$dateLabel}."
            );
        }

        $lines = ["Tugas yang selesai pada {$dateLabel} ({$tasks->count()}):"];

        foreach ($tasks->values() as $index => $task) {
            $lines[] = ($index + 1).'. '.$task->title
                .' - Kategori '.($task->category ?: 'Tanpa kategori')
                .' - Prioritas '.$this->priorityLabel($task->priority)
                .' - Diselesaikan '.$this->formatDateTime($task->updated_at);
        }

        return $this->direct('task_completed_date', implode("\n", $lines));
    }

    private function asksTasksByCategory(string $text): bool
    {
        if (! $this->containsAny($text, ['tugas', 'task', 'pekerjaan'])) {
            return false;
        }

        return $this->containsAny($text, [
            'berdasarkan kategori', 'tugas per kategori', 'kategori tugas',
            'tugas berdasarkan kategori', 'tasks by category', 'task by category',
            'kelompokkan tugas', 'group tasks',
        ]);
    }

    private function tasksByCategory(User $user, string $text): array
    {
        $tasks = $user->tasks()
            ->orderBy('category')
            ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->orderByRaw('deadline IS NULL, deadline ASC')
            ->get();

        if ($tasks->isEmpty()) {
            return $this->direct('task_by_category', 'Anda belum memiliki tugas.');
        }

        $groups = $tasks
            ->groupBy(fn (Task $task) => $task->category ?: 'Tanpa kategori')
            ->sortByDesc(fn (Collection $group) => $group->count());

        $asksCount = $this->containsAny($text, ['berapa', 'jumlah', 'total', 'banyak', 'how many', 'count']);
        $asksList = $this->containsAny($text, [
            'sebutkan', 'apa saja', 'daftar', 'tampilkan', 'lihat', 'tunjukkan',
            'list', 'show', 'what are',
        ]);

        if ($asksCount && ! $asksList) {
            $lines = ['Jumlah tugas berdasarkan kategori:'];

            foreach ($groups as $category => $group) {
                $lines[] = "- {$category}: {$group->count()}";
            }

            return $this->direct('task_by_category', implode("\n", $lines));
        }

        $lines = ["Tugas berdasarkan kategori (total {$tasks->count()}):"];

        foreach ($groups as $category => $group) {
            $lines[] = "{$category} ({$group->count()}):";

            foreach ($group->values() as $index => $task) {
                $lines[] = '  '.$this->taskDetailedLine($task, $index + 1);
            }
        }

        return $this->direct('task_by_category', implode("\n", $lines));
    }

    private function asksPriorityDistribution(string $text): bool
    {
        if (! $this->containsAny($text, ['tugas', 'task', 'pekerjaan'])) {
            return false;
        }

        return $this->containsAny($text, [
            'distribusi prioritas', 'sebaran prioritas', 'priority distribution',
            'tugas berdasarkan prioritas', 'tugas per prioritas', 'prioritas tugas',
            'kelompokkan prioritas', 'group by priority',
        ]);
    }

    private function tasksByPriority(User $user, string $text): array
    {
        $tasks = $user->tasks()
            ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->orderByRaw('deadline IS NULL, deadline ASC')
            ->get();

        if ($tasks->isEmpty()) {
            return $this->direct('task_by_priority', 'Anda belum memiliki tugas.');
        }

        $groups = [
            'high' => $tasks->where('priority', 'high')->values(),
            'medium' => $tasks->where('priority', 'medium')->values(),
            'low' => $tasks->where('priority', 'low')->values(),
        ];

        $asksCount = $this->containsAny($text, ['berapa', 'jumlah', 'total', 'banyak', 'how many', 'count']);
        $asksList = $this->containsAny($text, [
            'sebutkan', 'apa saja', 'daftar', 'tampilkan', 'lihat', 'tunjukkan',
            'list', 'show', 'what are',
        ]);

        if ($asksCount && ! $asksList) {
            $lines = ['Jumlah tugas berdasarkan distribusi prioritas:'];

            foreach ($groups as $priority => $group) {
                $lines[] = '- '.$this->priorityLabel($priority).": {$group->count()}";
            }

            return $this->direct('task_by_priority', implode("\n", $lines));
        }

        $lines = ["Tugas berdasarkan distribusi prioritas (total {$tasks->count()}):"];

        foreach ($groups as $priority => $group) {
            $lines[] = 'Prioritas '.$this->priorityLabel($priority)." ({$group->count()}):";

            if ($group->isEmpty()) {
                $lines[] = '  Tidak ada tugas.';

                continue;
            }

            foreach ($group as $index => $task) {
                $lines[] = '  '.$this->taskDetailedLine($task, $index + 1);
            }
        }

        return $this->direct('task_by_priority', implode("\n", $lines));
    }

    private function asksTaskOrder(string $text): bool
    {
        return $this->containsAny($text, [
            'harus dikerjakan terlebih dahulu', 'dikerjakan terlebih dahulu',
            'kerjakan terlebih dahulu', 'harus didahulukan', 'kerjakan dulu',
            'dikerjakan dulu', 'tugas mana dulu', 'tugas mana yang harus',
            'urutan pengerjaan', 'urutan tugas', 'prioritas pengerjaan',
            'mana yang harus dikerjakan',
        ]);
    }

    private function taskOrder(User $user, string $text): array
    {
        $tasks = $user->tasks()
            ->where('status', '!=', 'completed')
            ->get()
            ->sortBy(function (Task $task) {
                $overdue = $task->deadline && $task->deadline->isPast() ? 0 : 1;
                $deadline = $task->deadline ? $task->deadline->timestamp : PHP_INT_MAX;
                $priority = ['high' => 0, 'medium' => 1, 'low' => 2][$task->priority] ?? 3;

                return [$overdue, $deadline, $priority];
            })
            ->values();

        $asksCount = $this->containsAny($text, ['berapa', 'jumlah', 'total', 'banyak', 'how many', 'count']);
        $asksList = $this->containsAny($text, [
            'sebutkan', 'apa saja', 'daftar', 'tampilkan', 'lihat', 'tunjukkan',
            'list', 'show', 'what are',
        ]);

        if ($asksCount && ! $asksList) {
            return $this->direct(
                'task_order',
                "Jumlah tugas aktif yang perlu diurutkan: {$tasks->count()}."
            );
        }

        if ($tasks->isEmpty()) {
            return $this->direct('task_order', 'Tidak ada tugas aktif yang perlu dikerjakan.');
        }

        $lines = [
            "Tugas yang harus dikerjakan terlebih dahulu ({$tasks->count()}):",
            'Urutan: tugas terlambat, deadline terdekat, lalu prioritas.',
        ];

        foreach ($tasks as $index => $task) {
            $lines[] = ($index + 1).'. '.$this->taskDetailedLine($task)
                .' ('.$this->taskOrderReason($task).')';
        }

        return $this->direct('task_order', implode("\n", $lines));
    }

    private function taskOrderReason(Task $task): string
    {
        if (! $task->deadline) {
            return 'tanpa deadline; kerjakan setelah tugas bertenggat';
        }

        if ($task->deadline->isPast()) {
            return 'sudah melewati deadline, kerjakan segera';
        }

        if ($task->deadline->isToday()) {
            return 'jatuh tempo hari ini';
        }

        return 'deadline '.$this->relativeTime($task->deadline);
    }

    /**
     * @return array{start: \Carbon\Carbon, end: \Carbon\Carbon, label: string, single: bool}|null
     */
    private function extractDateRange(string $text): ?array
    {
        $relative = [
            ['hari ini', 'today'],
            ['kemarin', 'yesterday'],
            ['minggu ini', 'pekan ini', 'this week'],
            ['bulan ini', 'this month'],
        ];

        foreach ($relative as $needles) {
            if (! $this->containsAny($text, $needles)) {
                continue;
            }

            $first = $needles[0];

            if ($first === 'hari ini' || $first === 'today') {
                return $this->dateRange(today(), today());
            }

            if ($first === 'kemarin' || $first === 'yesterday') {
                return $this->dateRange(today()->subDay(), today()->subDay());
            }

            if ($first === 'minggu ini' || $first === 'this week') {
                return $this->dateRange(now()->startOfWeek(), now()->endOfWeek());
            }

            return $this->dateRange(now()->startOfMonth(), now()->endOfMonth());
        }

        $months = [
            'januari' => 1, 'january' => 1, 'jan' => 1,
            'februari' => 2, 'february' => 2, 'feb' => 2,
            'maret' => 3, 'march' => 3, 'mar' => 3,
            'april' => 4, 'apr' => 4,
            'mei' => 5, 'may' => 5,
            'juni' => 6, 'june' => 6, 'jun' => 6,
            'juli' => 7, 'july' => 7, 'jul' => 7,
            'agustus' => 8, 'august' => 8, 'agu' => 8, 'aug' => 8,
            'september' => 9, 'sep' => 9,
            'oktober' => 10, 'october' => 10, 'okt' => 10, 'oct' => 10,
            'november' => 11, 'nov' => 11,
            'desember' => 12, 'december' => 12, 'des' => 12, 'dec' => 12,
        ];

        $monthPattern = implode('|', array_keys($months));
        $year = (int) now()->format('Y');
        $dates = [];

        if (preg_match_all('/\b(\d{1,2})\s+('.$monthPattern.')\s*(\d{4})?\b/u', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $day = (int) $match[1];
                $month = $months[$match[2]] ?? null;
                $dateYear = ! empty($match[3]) ? (int) $match[3] : $year;

                if ($month === null || ! checkdate($month, $day, $dateYear)) {
                    continue;
                }

                $dates[] = now()->setDate($dateYear, $month, $day)->startOfDay();
            }
        }

        if ($dates !== []) {
            $start = $dates[0]->copy()->startOfDay();
            $end = $dates[count($dates) - 1]->copy()->endOfDay();

            return $this->dateRange($start, $end);
        }

        if (preg_match('/\b(?:bulan\s+)?('.$monthPattern.')\s*(\d{4})?\b/u', $text, $match)) {
            $month = $months[$match[1]] ?? null;
            $dateYear = ! empty($match[2]) ? (int) $match[2] : $year;

            if ($month !== null) {
                return $this->dateRange(
                    now()->setDate($dateYear, $month, 1)->startOfDay(),
                    now()->setDate($dateYear, $month, 1)->endOfMonth()
                );
            }
        }

        return null;
    }

    private function dateRange(CarbonInterface $start, CarbonInterface $end): array
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();
        $single = $start->toDateString() === $end->toDateString();

        return [
            'start' => $start,
            'end' => $end,
            'label' => $single
                ? $this->formatDate($start)
                : $this->formatDate($start).' sampai '.$this->formatDate($end),
            'single' => $single,
        ];
    }

    private function userStatistics(User $user, string $text): array
    {
        $base = $user->tasks();
        $total = (clone $base)->count();
        $completed = (clone $base)->where('status', 'completed')->count();
        $pending = (clone $base)->where('status', 'todo')->count();
        $inProgress = (clone $base)->where('status', 'in_progress')->count();
        $overdue = (clone $base)
            ->where('status', '!=', 'completed')
            ->whereNotNull('deadline')
            ->where('deadline', '<', now())
            ->count();
        $completionRate = $total > 0 ? (int) round(($completed / $total) * 100) : 0;
        $completedThisWeek = (clone $base)
            ->where('status', 'completed')
            ->where('updated_at', '>=', now()->startOfWeek())
            ->count();

        $chartStart = now()->subDays(13)->startOfDay();
        $completedByDate = (clone $base)
            ->where('status', 'completed')
            ->where('updated_at', '>=', $chartStart)
            ->get()
            ->groupBy(fn (Task $task) => $task->updated_at->toDateString())
            ->map->count();
        $completedLast14 = $completedByDate->sum();

        $priority = [
            'high' => (clone $base)->where('priority', 'high')->count(),
            'medium' => (clone $base)->where('priority', 'medium')->count(),
            'low' => (clone $base)->where('priority', 'low')->count(),
        ];

        $categories = (clone $base)
            ->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(6)
            ->pluck('total', 'category');

        $lines = [
            'Statistik tugas Anda:',
            "- Total: {$total}",
            "- Pending: {$pending}",
            "- In progress: {$inProgress}",
            "- Selesai: {$completed}",
            "- Terlambat: {$overdue}",
            "- Tingkat penyelesaian: {$completionRate}%",
            "- Selesai minggu ini: {$completedThisWeek}",
            "- Selesai 14 hari terakhir: {$completedLast14}",
            'Distribusi prioritas:',
            "- Tinggi: {$priority['high']}",
            "- Sedang: {$priority['medium']}",
            "- Rendah: {$priority['low']}",
        ];

        if ($categories->isNotEmpty()) {
            $lines[] = 'Kategori tugas:';

            foreach ($categories as $category => $count) {
                $lines[] = '- '.($category ?: 'Tanpa kategori').": {$count}";
            }
        }

        if ($this->containsAny($text, ['grafik', 'chart', 'tren', 'trend', 'per tanggal', 'harian'])) {
            $lines[] = 'Grafik tugas selesai 14 hari terakhir:';

            if ($completedLast14 === 0) {
                $lines[] = '- Tidak ada tugas selesai dalam 14 hari terakhir.';
            } else {
                for ($i = 13; $i >= 0; $i--) {
                    $date = now()->subDays($i);
                    $count = (int) $completedByDate->get($date->toDateString(), 0);

                    if ($count > 0) {
                        $lines[] = '- '.$date->format('d M Y').": {$count}";
                    }
                }
            }
        }

        return $this->direct('user_statistics', implode("\n", $lines));
    }

    private function taskIntent(User $user, string $text): ?array
    {
        if (! $this->containsAny($text, ['tugas', 'task', 'pekerjaan', 'deadline', 'tenggat', 'prioritas', 'due'])) {
            return null;
        }

        if ($user->isAdmin()) {
            if ($this->containsAny($text, ['saya', 'milik saya'])) {
                return $this->direct('admin_personal_tasks', 'Akun Admin tidak memiliki daftar tugas pribadi di DailyMate.');
            }

            return $this->direct(
                'admin_private_tasks_denied',
                'Admin tidak dapat melihat judul atau detail tugas pribadi pengguna. Saya hanya dapat menampilkan jumlah tugas agregat.'
            );
        }

        if ($this->containsAny($text, ['pengguna lain', 'user lain', 'orang lain', 'akun lain', 'other user'])) {
            return $this->direct(
                'user_private_tasks_denied',
                'Saya hanya dapat membaca data tugas milik akun Anda sendiri.'
            );
        }

        $base = $user->tasks();
        $open = (clone $base)->where('status', '!=', 'completed');
        $asksCount = $this->containsAny($text, ['berapa', 'jumlah', 'total', 'banyak', 'how many', 'count']);
        $asksList = $this->containsAny($text, ['apa saja', 'daftar', 'tampilkan', 'lihat', 'tunjukkan', 'sebutkan', 'list', 'show', 'what are']);
        $asksItems = $this->containsAny($text, ['apa saja', 'daftar', 'tampilkan', 'lihat', 'tunjukkan', 'list', 'show', 'what are']);

        if ($this->containsAny($text, ['terlambat', 'overdue', 'lewat deadline', 'melewati deadline'])) {
            $overdueQuery = (clone $open)->whereNotNull('deadline')
                ->where('deadline', '<', now())
                ->orderBy('deadline');
            $overdueCount = (clone $overdueQuery)->count();

            if ($asksCount && ! $asksList) {
                return $this->direct(
                    'task_overdue',
                    "Jumlah tugas yang terlambat: {$overdueCount}."
                );
            }

            $tasks = $asksList ? $overdueQuery->get() : $overdueQuery->limit(10)->get();

            if ($tasks->isEmpty()) {
                return $this->direct('task_overdue', 'Tidak ada tugas yang terlambat.');
            }

            $message = $this->taskListMessage('Tugas yang terlambat', $tasks);

            if ($tasks->count() < $overdueCount) {
                $message .= "\nMasih ada ".($overdueCount - $tasks->count()).' tugas terlambat lainnya.';
            }

            return $this->direct('task_overdue', $message);
        }

        if ($this->containsAny($text, ['deadline terdekat', 'deadline paling dekat', 'tenggat terdekat', 'tugas terdekat', 'next deadline'])
            || ($this->containsAny($text, ['deadline', 'tenggat', 'due']) && $this->containsAny($text, ['kapan', 'terdekat', 'paling dekat', 'berikutnya', 'next']))) {
            $task = (clone $open)->whereNotNull('deadline')->orderBy('deadline')->first();

            if (! $task) {
                return $this->direct('task_nearest_deadline', 'Tidak ada tugas aktif yang memiliki deadline.');
            }

            return $this->direct(
                'task_nearest_deadline',
                "Tugas dengan deadline terdekat:\n".$this->taskLine($task, 1)
                    ."\nWaktu tersisa: ".$this->relativeTime($task->deadline).'.'
            );
        }

        if ($this->containsAny($text, ['prioritas tinggi', 'high priority', 'highest priority', 'high-priority'])) {
            $highQuery = (clone $open)->where('priority', 'high')
                ->orderByRaw('deadline IS NULL, deadline ASC');
            $highCount = (clone $highQuery)->count();

            if ($highCount === 0) {
                return $this->direct('task_high_priority', 'Tidak ada tugas prioritas tinggi yang belum selesai.');
            }

            if ($asksCount && ! $asksList) {
                return $this->direct(
                    'task_high_priority',
                    "Jumlah tugas prioritas tinggi yang belum selesai: {$highCount}."
                );
            }

            $tasks = $asksList ? $highQuery->get() : $highQuery->limit(10)->get();
            $message = $this->taskListMessage('Tugas prioritas tinggi', $tasks);

            if ($tasks->count() < $highCount) {
                $message .= "\nMasih ada ".($highCount - $tasks->count()).' tugas prioritas tinggi lainnya.';
            }

            return $this->direct('task_high_priority', $message);
        }

        if ($this->containsAny($text, ['pending', 'tertunda', 'menunggu', 'belum dikerjakan', 'to do', 'todo'])) {
            $count = (clone $base)->where('status', 'todo')->count();
            $title = 'Tugas pending';

            if ($asksList || ! $asksCount) {
                $query = (clone $base)->where('status', 'todo')
                    ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
                    ->orderByRaw('deadline IS NULL, deadline ASC');
                $tasks = $asksList ? $query->get() : $query->limit(10)->get();

                if ($tasks->isEmpty()) {
                    return $this->direct('task_pending', 'Tidak ada tugas pending.');
                }

                $message = $this->taskListMessage($title, $tasks);
                $remaining = $count - $tasks->count();

                return $this->direct('task_pending', $message.($remaining > 0 ? "\nMasih ada {$remaining} tugas pending lainnya." : ''));
            }

            return $this->direct('task_pending', "Jumlah tugas pending: {$count}.");
        }

        if ($this->containsAny($text, ['in progress', 'in progres', 'in_progress', 'sedang dikerjakan', 'sedang berjalan', 'progress', 'progres', 'dikerjakan'])) {
            $count = (clone $base)->where('status', 'in_progress')->count();
            $title = 'Tugas in progress';

            if ($asksList || ! $asksCount) {
                $query = (clone $base)->where('status', 'in_progress')
                    ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
                    ->orderByRaw('deadline IS NULL, deadline ASC');
                $tasks = $asksList ? $query->get() : $query->limit(10)->get();

                if ($tasks->isEmpty()) {
                    return $this->direct('task_in_progress', 'Tidak ada tugas in progress.');
                }

                $message = $this->taskListMessage($title, $tasks);
                $remaining = $count - $tasks->count();

                return $this->direct('task_in_progress', $message.($remaining > 0 ? "\nMasih ada {$remaining} tugas in progress lainnya." : ''));
            }

            return $this->direct('task_in_progress', "Jumlah tugas in progress: {$count}.");
        }

        if ($this->containsAny($text, ['belum selesai', 'open task', 'incomplete', 'unfinished', 'not completed'])) {
            $count = (clone $open)->count();

            if ($asksList || ! $asksCount) {
                $query = (clone $open)
                    ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
                    ->orderByRaw('deadline IS NULL, deadline ASC');
                $tasks = $asksList ? $query->get() : $query->limit(10)->get();

                if ($tasks->isEmpty()) {
                    return $this->direct('task_open', 'Tidak ada tugas yang belum selesai.');
                }

                $message = $this->taskListMessage('Tugas yang belum selesai', $tasks);
                $remaining = $count - $tasks->count();

                return $this->direct('task_open', $message.($remaining > 0 ? "\nMasih ada {$remaining} tugas lainnya." : ''));
            }

            return $this->direct('task_open', "Jumlah tugas yang belum selesai: {$count}.");
        }

        if ($this->containsAny($text, ['sudah selesai', 'completed', 'selesai', 'finished'])) {
            $count = (clone $base)->where('status', 'completed')->count();

            if ($asksList || ! $asksCount) {
                $query = (clone $base)->where('status', 'completed')
                    ->orderByDesc('deadline')->orderByDesc('id');
                $tasks = $asksList ? $query->get() : $query->limit(10)->get();

                if ($tasks->isEmpty()) {
                    return $this->direct('task_completed', 'Belum ada tugas yang sudah selesai.');
                }

                $message = $this->taskListMessage('Tugas yang sudah selesai', $tasks);
                $remaining = $count - $tasks->count();

                return $this->direct('task_completed', $message.($remaining > 0 ? "\nMasih ada {$remaining} tugas selesai lainnya." : ''));
            }

            return $this->direct('task_completed', "Jumlah tugas yang sudah selesai: {$count}.");
        }

        if ($this->containsAny($text, ['deadline', 'tenggat', 'due'])) {
            $deadlineCount = (clone $open)->whereNotNull('deadline')->count();

            if ($asksCount && ! $asksList) {
                return $this->direct(
                    'task_deadlines',
                    "Jumlah tugas aktif yang memiliki deadline: {$deadlineCount}."
                );
            }

            $query = (clone $open)->whereNotNull('deadline')->orderBy('deadline');
            $deadlineTasks = $asksList ? $query->get() : $query->limit(10)->get();

            if ($deadlineTasks->isEmpty()) {
                return $this->direct('task_deadlines', 'Tidak ada tugas aktif yang memiliki deadline.');
            }

            $message = $this->taskListMessage('Deadline tugas Anda', $deadlineTasks);

            if ($deadlineTasks->count() < $deadlineCount) {
                $message .= "\nMasih ada ".($deadlineCount - $deadlineTasks->count()).' tugas berdeadline lainnya.';
            }

            return $this->direct('task_deadlines', $message);
        }

        if ($asksCount && ! $asksItems) {
            return $this->direct('task_total', 'Anda memiliki '.$user->tasks()->count().' tugas.');
        }

        if ($asksList || $this->containsAny($text, ['tugas apa'])) {
            $showAll = $asksList || $asksCount || $this->containsAny($text, ['semua', 'seluruh', 'all']);

            $query = (clone $base)
                ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
                ->orderByRaw('deadline IS NULL, deadline ASC');

            $tasks = $showAll ? $query->get() : $query->limit(10)->get();

            if ($tasks->isEmpty()) {
                return $this->direct('task_list', 'Anda belum memiliki tugas.');
            }

            $message = $this->taskListMessage('Daftar tugas Anda', $tasks);
            $total = (clone $base)->count();

            if ($tasks->count() < $total) {
                $message .= "\nMenampilkan {$tasks->count()} dari {$total} tugas.";
            }

            return $this->direct('task_list', $message);
        }

        $total = (clone $base)->count();
        $completed = (clone $base)->where('status', 'completed')->count();
        $pending = (clone $base)->where('status', 'todo')->count();
        $inProgress = (clone $base)->where('status', 'in_progress')->count();
        $overdue = (clone $open)->whereNotNull('deadline')->where('deadline', '<', now())->count();
        $completionRate = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

        return $this->direct(
            'task_summary',
            "Ringkasan tugas Anda:\n- Total: {$total}\n- Pending: {$pending}\n- In progress: {$inProgress}\n- Selesai: {$completed}\n- Terlambat: {$overdue}\n- Tingkat penyelesaian: {$completionRate}%"
        );
    }

    private function scheduleIntent(User $user, string $text): ?array
    {
        if (! $this->containsAny($text, ['jadwal', 'schedule', 'kegiatan', 'agenda', 'calendar'])) {
            return null;
        }

        if ($user->isAdmin()) {
            if ($this->containsAny($text, ['saya', 'milik saya'])) {
                return $this->direct('admin_personal_schedules', 'Akun Admin tidak memiliki jadwal pribadi di DailyMate.');
            }

            return $this->direct(
                'admin_private_schedules_denied',
                'Admin tidak dapat melihat detail jadwal pribadi pengguna. Saya hanya dapat menampilkan jumlah jadwal agregat.'
            );
        }

        if ($this->containsAny($text, ['pengguna lain', 'user lain', 'orang lain', 'akun lain', 'other user'])) {
            return $this->direct(
                'user_private_schedules_denied',
                'Saya hanya dapat membaca data jadwal milik akun Anda sendiri.'
            );
        }

        if ($this->containsAny($text, ['besok', 'tomorrow'])) {
            return $this->scheduleList($user, 'besok', today()->addDay(), 'Tidak ada jadwal untuk besok.');
        }

        if ($this->containsAny($text, ['minggu ini', 'pekan ini', 'this week'])) {
            $schedules = $user->schedules()
                ->whereBetween('schedule_date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])
                ->orderBy('schedule_date')->orderBy('start_time')->get();

            if ($schedules->isEmpty()) {
                return $this->direct('schedule_week', 'Tidak ada jadwal untuk minggu ini.');
            }

            return $this->direct('schedule_week', $this->scheduleListMessage('Jadwal minggu ini', $schedules));
        }

        if ($this->containsAny($text, ['berikutnya', 'selanjutnya', 'next'])) {
            $schedule = $this->nextSchedule($user);

            if (! $schedule) {
                return $this->direct('schedule_next', 'Tidak ada jadwal berikutnya.');
            }

            return $this->direct(
                'schedule_next',
                'Jadwal berikutnya: '.$schedule->title.' pada '.$this->formatDate($schedule->schedule_date)
                    .', pukul '.$this->formatTimeRange($schedule).'.'
            );
        }

        if ($this->containsAny($text, ['hari ini', 'today'])) {
            return $this->scheduleList($user, 'hari ini', today(), 'Tidak ada jadwal untuk hari ini.');
        }

        $schedules = $user->schedules()
            ->where('schedule_date', '>=', today())
            ->orderBy('schedule_date')->orderBy('start_time')->limit(5)->get();

        if ($schedules->isEmpty()) {
            return $this->direct('schedule_upcoming', 'Tidak ada jadwal mendatang.');
        }

        return $this->direct('schedule_upcoming', $this->scheduleListMessage('Jadwal mendatang', $schedules));
    }

    private function scheduleList(User $user, string $label, CarbonInterface $date, string $emptyMessage): array
    {
        $schedules = $user->schedules()
            ->whereDate('schedule_date', $date->toDateString())
            ->orderBy('start_time')
            ->get();

        if ($schedules->isEmpty()) {
            return $this->direct('schedule_'.$label, $emptyMessage);
        }

        return $this->direct('schedule_'.$label, $this->scheduleListMessage('Jadwal '.$label, $schedules));
    }

    private function nextSchedule(User $user): ?Schedule
    {
        return $user->schedules()
            ->where(function ($query): void {
                $query->whereDate('schedule_date', '>', today())
                    ->orWhere(function ($query): void {
                        $query->whereDate('schedule_date', today())
                            ->where('end_time', '>=', now()->format('H:i:s'));
                    });
            })
            ->orderBy('schedule_date')->orderBy('start_time')->first();
    }

    private function feedbackIntent(User $user, string $text): ?array
    {
        if (! $this->containsAny($text, ['feedback', 'masukan', 'saran saya', 'balasan admin'])) {
            return null;
        }

        if ($user->isAdmin()) {
            return null;
        }

        $total = $user->feedbacks()->count();
        $pending = $user->feedbacks()->where('status', 'pending')->count();
        $resolved = $user->feedbacks()->where('status', 'resolved')->count();
        $responded = $user->feedbacks()->whereNotNull('admin_response')->count();

        return $this->direct(
            'user_feedback',
            "Feedback Anda:\n- Total: {$total}\n- Pending: {$pending}\n- Resolved: {$resolved}\n- Sudah dibalas admin: {$responded}"
        );
    }

    private function notificationIntent(User $user, string $text): ?array
    {
        if (! $this->containsAny($text, ['notifikasi', 'notification', 'pengingat', 'reminder', 'whatsapp'])) {
            return null;
        }

        if (! $this->containsAny($text, ['status', 'aktif', 'nonaktif', 'mati', 'berapa', 'jumlah', 'terkirim', 'gagal', 'pending', 'nomor'])) {
            return null;
        }

        if ($user->isAdmin()) {
            $sent = WhatsAppNotification::where('status', 'sent')->count();
            $pending = WhatsAppNotification::where('status', 'pending')->count();
            $failed = WhatsAppNotification::where('status', 'failed')->count();

            return $this->direct(
                'admin_notifications',
                "Status notifikasi WhatsApp:\n- Terkirim: {$sent}\n- Pending: {$pending}\n- Gagal: {$failed}"
            );
        }

        if (! $user->hasWhatsAppNotifications()) {
            return $this->direct(
                'user_notifications',
                'Notifikasi WhatsApp Anda belum aktif. Aktifkan nomor, izin, dan jadwal pengingat di menu Notifications.'
            );
        }

        $offsets = collect($user->reminderOffsets())->map(fn (int $offset) => match ($offset) {
            1440 => '1 hari',
            720 => '12 jam',
            180 => '3 jam',
            60 => '1 jam',
            default => $offset.' menit',
        })->implode(', ');

        return $this->direct(
            'user_notifications',
            "Notifikasi WhatsApp Anda aktif. Pengingat deadline dipilih: {$offsets} sebelum deadline."
        );
    }

    private function integrationStatus(User $user, string $text): array
    {
        $asksQwen = $this->containsAny($text, ['qwen', 'friday ai', 'integrasi ai', 'api ai', 'ai terhubung']);
        $asksWhatsApp = $this->containsAny($text, ['fonnte', 'whatsapp', 'integrasi wa', 'api wa']);

        if (! $asksQwen && ! $asksWhatsApp) {
            return $this->direct('integration_unknown', 'Informasi integrasi yang tersedia hanya Qwen AI dan WhatsApp/Fonnte.');
        }

        if (! $user->isAdmin()) {
            return $this->direct(
                'integration_denied',
                'Informasi konfigurasi sistem hanya dapat diakses oleh Admin.'
            );
        }

        if ($asksQwen) {
            $config = AiConfiguration::current();

            if (! $config || ! $config->hasApiKey()) {
                return $this->direct('qwen_status', 'Qwen AI belum dikonfigurasi.');
            }

            if (! $config->isReady()) {
                return $this->direct('qwen_status', 'Qwen AI sudah dikonfigurasi tetapi belum siap digunakan. Periksa status aktif, endpoint, dan model.');
            }

            if ($config->last_test_status === 'failed') {
                return $this->direct('qwen_status', 'Qwen AI sudah dikonfigurasi dan aktif, tetapi tes koneksi terakhir gagal.');
            }

            if ($config->last_test_status === 'success') {
                return $this->direct('qwen_status', 'Qwen AI sudah terhubung dan aktif.');
            }

            return $this->direct('qwen_status', 'Qwen AI sudah dikonfigurasi dan aktif. Koneksi belum diuji.');
        }

        $config = WhatsAppConfiguration::current();

        if (! $config) {
            return $this->direct('whatsapp_status', 'Fonnte belum dikonfigurasi.');
        }

        if ($config->provider === 'fonnte') {
            $ready = $config->hasToken() && $config->isReadyToSend();

            return $this->direct(
                'whatsapp_status',
                $ready
                    ? 'Fonnte sudah dikonfigurasi dan aktif.'
                    : 'Fonnte sudah dikonfigurasi tetapi belum siap. Periksa token dan status device.'
            );
        }

        return $this->direct(
            'whatsapp_status',
            'Fonnte belum dikonfigurasi. Provider WhatsApp yang digunakan saat ini: '.$config->providerLabel().'.'
        );
    }

    private function reminderHelp(): array
    {
        return $this->direct(
            'reminder_help',
            "Cara mengatur pengingat:\n"
            ."1. Buka menu Notifications atau Profile.\n"
            ."2. Isi nomor WhatsApp dengan format internasional, misalnya +6281234567890.\n"
            ."3. Aktifkan notifikasi dan setujui izin pengiriman.\n"
            ."4. Pilih waktu pengingat: 1 hari, 12 jam, 3 jam, dan/atau 1 jam sebelum deadline.\n"
            ."5. Simpan pengaturan."
        );
    }

    private function planning(User $user): array
    {
        $context = $this->advisoryContext($user);

        if ($user->isAdmin()) {
            return $this->guided(
                'admin_planning',
                "Untuk memulai, saya sarankan memeriksa ringkasan sistem terlebih dahulu.\n".$this->adminOverview()['answer'],
                $context
            );
        }

        $tasks = $user->tasks()
            ->where('status', '!=', 'completed')
            ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->orderByRaw('deadline IS NULL, deadline ASC')
            ->limit(5)
            ->get();

        $schedules = $user->schedules()
            ->whereDate('schedule_date', today())
            ->orderBy('start_time')
            ->get();

        $lines = [];

        if ($schedules->isNotEmpty()) {
            $lines[] = 'Jadwal hari ini:';
            foreach ($schedules as $schedule) {
                $lines[] = '- '.$schedule->title.' pukul '.$this->formatTimeRange($schedule);
            }
        } else {
            $lines[] = 'Tidak ada jadwal tetap hari ini.';
        }

        if ($tasks->isEmpty()) {
            $lines[] = 'Tidak ada tugas aktif. Anda dapat mulai dengan merencanakan tugas baru.';
        } else {
            $lines[] = 'Prioritas yang disarankan:';
            foreach ($tasks as $index => $task) {
                $lines[] = ($index + 1).'. '.$this->taskLine($task, 0, false);
            }
            $lines[] = 'Mulai dari tugas pertama, lalu kerjakan dalam blok fokus 25 menit.';
        }

        return $this->guided('planning', implode("\n", $lines), $context);
    }

    private function help(User $user): array
    {
        $roleNote = $user->isAdmin()
            ? 'Sebagai Admin, saya juga dapat membantu statistik agregat, status integrasi, feedback, laporan, dan aktivitas sistem.'
            : 'Saya hanya dapat membaca data milik akun Anda sendiri.';

        return $this->direct(
            'help',
            "Saya dapat membantu:\n"
            ."- Identitas dan status akun\n"
            ."- Jumlah dan daftar tugas\n"
            ."- Deadline terdekat dan prioritas tinggi\n"
            ."- Jadwal hari ini, besok, minggu ini, atau berikutnya\n"
            ."- Statistik dan status sistem sesuai role\n"
            ."- Pengaturan pengingat\n\n"
            .$roleNote
        );
    }

    private function advisoryContext(User $user): string
    {
        if ($user->isAdmin()) {
            return "Data resmi agregat dari database (hanya untuk Admin):\n".$this->adminOverview()['answer'];
        }

        $tasks = $user->tasks()
            ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->orderByRaw('deadline IS NULL, deadline ASC')
            ->limit(10)
            ->get();

        $schedules = $user->schedules()
            ->where('schedule_date', '>=', today())
            ->orderBy('schedule_date')->orderBy('start_time')
            ->limit(10)
            ->get();

        $lines = [
            'Data resmi dari database untuk akun yang sedang login:',
            "Nama: {$user->name}",
            "Email: {$user->email}",
            'Role: User',
            'Status akun: '.($user->isActive() ? 'Aktif' : 'Tidak aktif'),
            'Total tugas: '.$user->tasks()->count(),
            'Tugas belum selesai: '.$user->tasks()->where('status', '!=', 'completed')->count(),
            'Tugas selesai: '.$user->tasks()->where('status', 'completed')->count(),
            'Tugas terlambat: '.$user->tasks()->where('status', '!=', 'completed')->whereNotNull('deadline')->where('deadline', '<', now())->count(),
            'Tugas:',
        ];

        if ($tasks->isEmpty()) {
            $lines[] = '- Tidak ada tugas.';
        } else {
            foreach ($tasks as $task) {
                $lines[] = '- '.$this->taskLine($task, 0, false);
            }
        }

        $lines[] = 'Jadwal mendatang:';

        if ($schedules->isEmpty()) {
            $lines[] = '- Tidak ada jadwal.';
        } else {
            foreach ($schedules as $schedule) {
                $lines[] = '- '.$schedule->title.', '.$this->formatDate($schedule->schedule_date).', pukul '.$this->formatTimeRange($schedule);
            }
        }

        $lines[] = 'Waktu sekarang: '.$this->formatDateTime(now()).'.';
        $lines[] = 'Gunakan hanya data di atas. Jangan menambah angka atau data yang tidak ada.';

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, Task>  $tasks
     */
    private function taskListMessage(string $title, Collection $tasks): string
    {
        $lines = [$title.' ('.$tasks->count().'):'];

        foreach ($tasks->values() as $index => $task) {
            $lines[] = $this->taskLine($task, $index + 1);
        }

        return implode("\n", $lines);
    }

    private function taskLine(Task $task, int $number = 0, bool $withPriority = true): string
    {
        $line = $task->title;

        if ($withPriority) {
            $line .= ' - Prioritas '.$this->priorityLabel($task->priority);
        }

        $line .= $task->deadline
            ? ' - Deadline '.$this->formatDateTime($task->deadline)
            : ' - Tanpa deadline';

        return $number > 0 ? $number.'. '.$line : $line;
    }

    private function taskDetailedLine(Task $task, int $number = 0): string
    {
        $line = $task->title
            .' - Status '.$this->statusLabel($task->status)
            .' - Prioritas '.$this->priorityLabel($task->priority)
            .($task->deadline
                ? ' - Deadline '.$this->formatDateTime($task->deadline)
                : ' - Tanpa deadline');

        return $number > 0 ? $number.'. '.$line : $line;
    }

    /**
     * @param  Collection<int, Schedule>  $schedules
     */
    private function scheduleListMessage(string $title, Collection $schedules): string
    {
        $lines = [$title.' ('.$schedules->count().'):'];

        foreach ($schedules->values() as $index => $schedule) {
            $lines[] = ($index + 1).'. '.$schedule->title.' - '.$this->formatDate($schedule->schedule_date)
                .', pukul '.$this->formatTimeRange($schedule);
        }

        return implode("\n", $lines);
    }

    private function priorityLabel(?string $priority): string
    {
        return self::PRIORITIES[$priority] ?? 'Sedang';
    }

    private function statusLabel(?string $status): string
    {
        return self::STATUSES[$status] ?? 'Tidak diketahui';
    }

    private function monthLabel(string $monthKey): string
    {
        [$year, $month] = array_pad(explode('-', $monthKey, 2), 2, '');

        return (self::MONTHS[(int) $month] ?? $month).' '.$year;
    }

    private function formatDate(?CarbonInterface $date): string
    {
        if (! $date) {
            return '-';
        }

        return $date->format('j').' '.self::MONTHS[(int) $date->format('n')].' '.$date->format('Y');
    }

    private function formatDateTime(?CarbonInterface $date): string
    {
        if (! $date) {
            return '-';
        }

        return $this->formatDate($date).' pukul '.$date->format('H.i');
    }

    private function formatTimeRange(Schedule $schedule): string
    {
        return str_replace(':', '.', substr($schedule->start_time, 0, 5))
            .'-'.str_replace(':', '.', substr($schedule->end_time, 0, 5));
    }

    private function relativeTime(CarbonInterface $date): string
    {
        if ($date->isPast()) {
            return 'sudah melewati deadline';
        }

        $minutes = (int) now()->diffInMinutes($date);

        if ($minutes < 60) {
            return $minutes.' menit lagi';
        }

        if ($minutes < 1440) {
            return (int) ceil($minutes / 60).' jam lagi';
        }

        return (int) ceil($minutes / 1440).' hari lagi';
    }

    private function asksAssistantIdentity(string $text): bool
    {
        return $this->containsAny($text, [
            'siapa anda', 'anda siapa', 'siapa kamu', 'kamu siapa', 'siapa engkau',
            'engkau siapa', 'siapa friday', 'friday siapa', 'apa itu friday',
            'friday itu apa', 'siapa dirimu', 'siapa diri anda', 'kamu itu siapa',
            'anda itu siapa', 'perkenalkan diri', 'siapa nama anda', 'siapa nama kamu',
            'what is your name', 'tell me about yourself', 'who are you', 'what are you',
            'who is friday', 'what is friday',
        ]);
    }

    private function asksIdentity(string $text): bool
    {
        return $this->containsAny($text, [
            'siapa saya', 'siapa nama', 'nama saya', 'email saya', 'akun saya',
            'profil saya', 'data saya', 'login sebagai', 'role saya', 'peran saya',
            'hak akses saya', 'status akun', 'tanggal daftar', 'saya terdaftar',
            'kapan saya terdaftar', 'saya bergabung', 'registrasi saya',
            'who am i', 'my name', 'my email',
            'my account', 'my profile', 'my role', 'which role', 'logged in as',
            'account status', 'when did i register',
        ]);
    }

    private function asksReminderHelp(string $text): bool
    {
        $action = $this->containsAny($text, ['cara', 'atur', 'setting', 'pengaturan', 'aktifkan', 'matikan', 'ubah', 'pilih', 'how', 'set', 'enable', 'disable', 'change']);

        return $this->containsAny($text, ['pengingat', 'reminder'])
            && $action
            || $this->containsAny($text, ['notifikasi', 'notification'])
            && $this->containsAny($text, ['cara', 'atur', 'setting', 'pengaturan', 'aktifkan', 'matikan', 'ubah', 'pilih', 'enable', 'disable', 'configure']);
    }

    private function asksIntegrationStatus(string $text): bool
    {
        $asksQwen = $this->containsAny($text, ['qwen', 'friday ai', 'integrasi ai', 'api ai']);
        $ownWhatsApp = $this->containsAny($text, ['saya', 'nomor', 'notifikasi'])
            && ! $this->containsAny($text, ['fonnte', 'integrasi', 'api', 'konfigurasi']);

        $asksWhatsApp = ! $ownWhatsApp
            && $this->containsAny($text, ['fonnte', 'integrasi wa', 'api wa', 'status whatsapp', 'whatsapp terhubung']);

        $status = $this->containsAny($text, ['status', 'terhubung', 'aktif', 'siap', 'konfigurasi', 'configured', 'connect', 'belum']);

        return ($asksQwen || $asksWhatsApp) && $status;
    }

    private function asksForPlan(string $text): bool
    {
        return $this->containsAny($text, [
            'rencana', 'plan', 'prioritas', 'priorit', 'kerjakan dulu', 'fokus', 'focus on', 'produktif',
            'tips', 'saran', 'mulai dari mana', 'start with', 'what should i work', 'what should i do first',
            'bantu saya hari ini', 'susun jadwal',
        ]);
    }

    private function asksForHelp(string $text): bool
    {
        return $this->containsAny($text, ['bantuan', 'help', 'bisa apa', 'fitur', 'features', 'what can you do', 'cara pakai', 'how to use', 'apa itu dailymate', 'panduan']);
    }

    private function isGreeting(string $text): bool
    {
        return (bool) preg_match('/^(halo|hai|hi|hello|selamat pagi|selamat siang|selamat malam|pagi|siang|malam)[\s!.,]*$/u', $text);
    }

    private function containsDomainWord(string $text): bool
    {
        return $this->containsAny($text, [
            'dailymate', 'tugas', 'task', 'jadwal', 'schedule', 'calendar', 'akun', 'accounts', 'pengguna', 'user',
            'admin', 'statistik', 'notifikasi', 'notification', 'pengingat', 'whatsapp', 'fonnte', 'qwen',
            'friday', 'feedback', 'aktivitas', 'deadline', 'due', 'tenggat', 'prioritas', 'produktivitas',
            'profil', 'password', 'email', 'laporan', 'reminder',
        ]);
    }

    private function isOutOfScope(string $text): bool
    {
        $creative = preg_match('/\b(buatkan|tulis|karang|terjemahkan)\b.*\b(puisi|lelucon|cerita|esai|lagu|surat cinta)\b/u', $text);

        if ($creative) {
            return true;
        }

        // Programming/code requests are outside DailyMate's assistant scope.
        if (preg_match('/\b(python|javascript|typescript|java|php|html|css|sql|coding|koding|programming|program|script|source code|algoritma|algorithm)\b/u', $text)) {
            return true;
        }

        if ($this->containsDomainWord($text)) {
            return false;
        }

        return (bool) preg_match(
            '/\b(cuaca|lelucon|resep|puisi|film|lagu|bola|saham|crypto|politik|medis|diagnosis|hukum|terjemahkan|berita|gosip|pacar|cinta|meme|wisata|hotel|tiket|konser|joke|weather|forecast|recipe|poem|movie|song|football|stock|crypto|politics|medical|legal|translate|news|homework|essay)\b/iu',
            $text
        );
    }

    private function normalize(string $message): string
    {
        $text = mb_strtolower(trim($message));
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($text, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    private function direct(string $intent, string $answer): array
    {
        return [
            'mode' => 'direct',
            'intent' => $intent,
            'answer' => $answer,
            'context' => '',
            'data' => [],
        ];
    }

    private function guided(string $intent, string $fallback, string $context): array
    {
        return [
            'mode' => 'guided',
            'intent' => $intent,
            'answer' => $fallback,
            'context' => $context,
            'data' => [],
        ];
    }

    private function unclear(): array
    {
        return [
            'mode' => 'unclear',
            'intent' => 'unclear',
            'answer' => 'Pertanyaan Anda belum cukup jelas. Silakan sebutkan data DailyMate yang ingin dicek, misalnya jumlah tugas, jadwal hari ini, atau statistik akun.',
            'context' => '',
            'data' => [],
        ];
    }

    private function outOfScope(): array
    {
        return [
            'mode' => 'out_of_scope',
            'intent' => 'out_of_scope',
            'answer' => 'Maaf, saya hanya dapat membantu pertanyaan seputar DailyMate dan produktivitas. Silakan tanyakan tentang tugas, jadwal, statistik, akun, atau pengaturan DailyMate.',
            'context' => '',
            'data' => [],
        ];
    }
}
