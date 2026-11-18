<?php

namespace Tests\Feature;

use App\Models\AiConfiguration;
use App\Models\Schedule;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FridayDatabaseAnswersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_identity_comes_from_the_logged_in_account(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Romi Admin']);

        $reply = $this->ask($admin, 'Siapa saya?');

        $this->assertStringContainsString('Nama Anda Romi Admin', $reply);
        $this->assertStringContainsString('Admin DailyMate', $reply);
        $this->assertStringNotContainsString('statistik', $reply);
    }

    public function test_user_identity_comes_from_the_logged_in_account(): void
    {
        $user = User::factory()->create(['name' => 'Romi User']);

        $reply = $this->ask($user, 'Siapa saya?');

        $this->assertStringContainsString('Nama Anda Romi User', $reply);
        $this->assertStringContainsString('User DailyMate', $reply);
    }

    public function test_friday_answers_questions_about_its_own_identity(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $adminReply = $this->ask($admin, 'Siapa anda?');

        $this->assertStringContainsString('Saya adalah Friday', $adminReply);
        $this->assertStringContainsString('asisten AI resmi DailyMate', $adminReply);
        $this->assertStringContainsString('statistik sistem', $adminReply);
        $this->assertStringNotContainsString('Saya hanya dapat membantu pertanyaan seputar DailyMate', $adminReply);

        $userReply = $this->ask($user, 'Siapa kamu?');

        $this->assertStringContainsString('Saya adalah Friday', $userReply);
        $this->assertStringContainsString('data akun Anda', $userReply);
        $this->assertStringNotContainsString('Saya hanya dapat membantu pertanyaan seputar DailyMate', $userReply);

        $englishReply = $this->ask($user, 'Who are you?');

        $this->assertStringContainsString('Saya adalah Friday', $englishReply);

        $introduction = $this->ask($user, 'Perkenalkan diri Anda');

        $this->assertStringContainsString('Saya adalah Friday', $introduction);
        $this->assertStringContainsString('DailyMate', $introduction);
    }

    public function test_admin_who_questions_list_names_while_how_many_questions_return_numbers(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Romi Admin']);
        User::factory()->create(['name' => 'Alya Putri', 'status' => 'active']);
        User::factory()->create(['name' => 'Budi Santoso', 'status' => 'active']);
        User::factory()->create(['name' => 'Citra Lestari', 'status' => 'inactive']);

        $allUsers = $this->ask($admin, 'Siapa saja user yang terdaftar?');

        $this->assertStringContainsString('Daftar pengguna terdaftar (3):', $allUsers);
        $this->assertStringContainsString('Alya Putri', $allUsers);
        $this->assertStringContainsString('Budi Santoso', $allUsers);
        $this->assertStringContainsString('Citra Lestari', $allUsers);
        $this->assertStringNotContainsString('Romi Admin', $allUsers);

        $activeUsers = $this->ask($admin, 'Siapa user yang aktif?');

        $this->assertStringContainsString('Daftar pengguna aktif (2):', $activeUsers);
        $this->assertStringContainsString('Alya Putri', $activeUsers);
        $this->assertStringContainsString('Budi Santoso', $activeUsers);
        $this->assertStringNotContainsString('Citra Lestari', $activeUsers);

        $count = $this->ask($admin, 'Berapa jumlah user?');

        $this->assertStringContainsString('Total pengguna: 3 User', $count);
        $this->assertStringNotContainsString('Alya Putri', $count);
    }

    public function test_admin_can_identify_the_month_with_the_most_registered_users(): void
    {
        $admin = User::factory()->admin()->create();

        User::factory()->create(['name' => 'User September', 'created_at' => '2026-09-10 10:00:00']);
        User::factory()->create(['name' => 'User Oktober Satu', 'created_at' => '2026-10-10 10:00:00']);
        User::factory()->create(['name' => 'User Oktober Dua', 'created_at' => '2026-10-11 10:00:00']);
        User::factory()->create(['name' => 'User November', 'created_at' => '2026-11-01 10:00:00']);

        $reply = $this->ask($admin, 'sebutkan dibulan berapa user yang paling banyak terdaftar');

        $this->assertStringContainsString('bulan Oktober 2026', $reply);
        $this->assertStringContainsString('2 user', $reply);
        $this->assertStringNotContainsString('Nama Anda', $reply);
        $this->assertStringNotContainsString('User Oktober Satu', $reply);
    }

    public function test_admin_can_request_monthly_registration_breakdown(): void
    {
        $admin = User::factory()->admin()->create();

        User::factory()->create(['name' => 'User September', 'created_at' => '2026-09-10 10:00:00']);
        User::factory()->create(['name' => 'User Oktober', 'created_at' => '2026-10-10 10:00:00']);

        $reply = $this->ask($admin, 'Sebutkan pendaftaran user per bulan');

        $this->assertStringContainsString('Pendaftaran user per bulan:', $reply);
        $this->assertStringContainsString('September 2026: 1 user', $reply);
        $this->assertStringContainsString('Oktober 2026: 1 user', $reply);
    }

    public function test_regular_user_cannot_list_registered_users(): void
    {
        $user = User::factory()->create(['name' => 'Alya Putri']);
        User::factory()->create(['name' => 'Budi Santoso']);

        $reply = $this->ask($user, 'Siapa saja user yang terdaftar?');

        $this->assertSame('Daftar pengguna hanya dapat diakses oleh Admin.', $reply);
        $this->assertStringNotContainsString('Budi Santoso', $reply);
    }

    public function test_admin_total_users_matches_the_database(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(2)->create(['status' => 'active']);
        User::factory()->create(['status' => 'inactive']);

        $reply = $this->ask($admin, 'Berapa total pengguna DailyMate?');

        $regular = User::query()->where('role', 'user')->count();
        $admins = User::query()->where('role', 'admin')->count();
        $total = User::query()->count();

        $this->assertStringContainsString("Total pengguna: {$regular} User", $reply);
        $this->assertStringContainsString("{$admins} Admin", $reply);
        $this->assertStringContainsString("{$total} akun terdaftar", $reply);
    }

    public function test_user_task_count_only_counts_their_own_tasks(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Task::factory()->count(3)->create(['user_id' => $user->id]);
        Task::factory()->count(4)->create(['user_id' => $other->id]);

        $reply = $this->ask($user, 'Berapa jumlah tugas milik saya?');

        $this->assertSame('Anda memiliki '.$user->tasks()->count().' tugas.', $reply);
        $this->assertStringContainsString('3 tugas', $reply);
    }

    public function test_user_task_status_counts_match_the_dashboard(): void
    {
        $user = User::factory()->create();
        Task::factory()->count(2)->create(['user_id' => $user->id, 'status' => 'todo']);
        Task::factory()->count(3)->create(['user_id' => $user->id, 'status' => 'in_progress']);
        Task::factory()->count(1)->create(['user_id' => $user->id, 'status' => 'completed']);

        $this->assertSame('Anda memiliki 6 tugas.', $this->ask($user, 'Berapa total Task'));
        $this->assertSame('Jumlah tugas pending: 2.', $this->ask($user, 'berapa total tugas yang pending'));
        $this->assertSame('Jumlah tugas in progress: 3.', $this->ask($user, 'berapa total tugas yang in progres'));
    }

    public function test_a_list_request_containing_the_word_total_returns_every_task(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 12) as $number) {
            Task::factory()->create([
                'user_id' => $user->id,
                'title' => 'Tugas uji '.$number,
                'status' => $number <= 5 ? 'completed' : ($number <= 9 ? 'in_progress' : 'todo'),
            ]);
        }

        $reply = $this->ask($user, 'Sebutkan apa saja dari total task');

        $this->assertStringContainsString('Daftar tugas Anda (12):', $reply);
        $this->assertStringContainsString('Tugas uji 1', $reply);
        $this->assertStringContainsString('Tugas uji 12', $reply);
        $this->assertStringNotContainsString('Anda memiliki 12 tugas.', $reply);
        $this->assertStringNotContainsString('Menampilkan 10 dari 12', $reply);

        $allTasks = $this->ask($user, 'sebutkan semua tugas');

        $this->assertStringContainsString('Daftar tugas Anda (12):', $allTasks);
        $this->assertStringContainsString('Tugas uji 12', $allTasks);
    }

    public function test_productivity_question_reads_the_statistics_page_data(): void
    {
        $user = User::factory()->create();

        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Tugas selesai hari ini',
            'status' => 'completed',
            'updated_at' => now(),
        ]);
        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Tugas pending',
            'status' => 'todo',
        ]);

        $reply = $this->ask($user, 'produktivitas saya');

        $this->assertStringContainsString('Statistik tugas Anda:', $reply);
        $this->assertStringContainsString('Total: 2', $reply);
        $this->assertStringContainsString('Tingkat penyelesaian: 50%', $reply);
        $this->assertStringContainsString('Selesai 14 hari terakhir: 1', $reply);
        $this->assertStringContainsString('Distribusi prioritas:', $reply);
        $this->assertStringContainsString('Kategori tugas:', $reply);
    }

    public function test_productivity_chart_question_returns_daily_completion_data(): void
    {
        $user = User::factory()->create();

        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Tugas selesai hari ini',
            'status' => 'completed',
            'updated_at' => now(),
        ]);

        $reply = $this->ask($user, 'tampilkan grafik produktivitas saya');

        $this->assertStringContainsString('Grafik tugas selesai 14 hari terakhir:', $reply);
        $this->assertStringContainsString(now()->format('d M Y').': 1', $reply);
    }

    public function test_completed_tasks_can_be_listed_by_a_specific_date(): void
    {
        $user = User::factory()->create();
        $year = (int) now()->format('Y');

        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Tugas selesai tanggal lima belas',
            'status' => 'completed',
            'category' => 'Work',
            'updated_at' => "{$year}-11-15 10:00:00",
        ]);
        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Tugas selesai tanggal empat belas',
            'status' => 'completed',
            'updated_at' => "{$year}-11-14 10:00:00",
        ]);

        $reply = $this->ask($user, 'sebutkan tugas yang selesai berdasarkan tanggal 15 november');

        $this->assertStringContainsString("Tugas yang selesai pada tanggal 15 November {$year} (1):", $reply);
        $this->assertStringContainsString('Tugas selesai tanggal lima belas', $reply);
        $this->assertStringNotContainsString('Tugas selesai tanggal empat belas', $reply);

        $count = $this->ask($user, 'berapa tugas yang selesai berdasarkan tanggal 15 november');

        $this->assertSame("Jumlah tugas yang selesai pada tanggal 15 November {$year}: 1.", $count);
        $this->assertStringNotContainsString('Tugas selesai tanggal lima belas', $count);
    }

    public function test_completed_tasks_can_be_listed_for_a_date_range(): void
    {
        $user = User::factory()->create();
        $year = (int) now()->format('Y');

        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Tugas awal rentang',
            'status' => 'completed',
            'updated_at' => "{$year}-11-01 09:00:00",
        ]);
        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Tugas akhir rentang',
            'status' => 'completed',
            'updated_at' => "{$year}-11-10 09:00:00",
        ]);
        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Tugas di luar rentang',
            'status' => 'completed',
            'updated_at' => "{$year}-11-20 09:00:00",
        ]);

        $reply = $this->ask($user, 'sebutkan tugas yang selesai dari tanggal 1 november sampai 10 november');

        $this->assertStringContainsString("rentang 1 November {$year} sampai 10 November {$year} (2):", $reply);
        $this->assertStringContainsString('Tugas awal rentang', $reply);
        $this->assertStringContainsString('Tugas akhir rentang', $reply);
        $this->assertStringNotContainsString('Tugas di luar rentang', $reply);
    }

    public function test_tasks_can_be_listed_and_counted_by_category(): void
    {
        $user = User::factory()->create();

        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Tugas Work satu',
            'category' => 'Work',
            'status' => 'todo',
        ]);
        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Tugas Work dua',
            'category' => 'Work',
            'status' => 'completed',
        ]);
        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Tugas Study',
            'category' => 'Study',
            'status' => 'in_progress',
        ]);

        $reply = $this->ask($user, 'sebutkan Tugas Berdasarkan kategori');

        $this->assertStringContainsString('Tugas berdasarkan kategori (total 3):', $reply);
        $this->assertStringContainsString('Work (2):', $reply);
        $this->assertStringContainsString('Study (1):', $reply);
        $this->assertStringContainsString('Tugas Work satu', $reply);
        $this->assertStringContainsString('Tugas Work dua', $reply);
        $this->assertStringContainsString('Tugas Study', $reply);

        $allReply = $this->ask($user, 'sebutkan semua tugas berdasarkan kategori');

        $this->assertStringContainsString('Tugas berdasarkan kategori (total 3):', $allReply);
        $this->assertStringContainsString('Tugas Study', $allReply);

        $count = $this->ask($user, 'berapa jumlah tugas berdasarkan kategori');

        $this->assertStringContainsString('Jumlah tugas berdasarkan kategori:', $count);
        $this->assertStringContainsString('Work: 2', $count);
        $this->assertStringContainsString('Study: 1', $count);
        $this->assertStringNotContainsString('Tugas Work satu', $count);
    }

    public function test_tasks_can_be_listed_and_counted_by_priority_distribution(): void
    {
        $user = User::factory()->create();

        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Tugas prioritas tinggi',
            'priority' => 'high',
        ]);
        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Tugas prioritas sedang',
            'priority' => 'medium',
        ]);
        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Tugas prioritas rendah',
            'priority' => 'low',
        ]);

        $reply = $this->ask($user, 'Sebutkan tugas distribusi prioritas');

        $this->assertStringContainsString('Tugas berdasarkan distribusi prioritas (total 3):', $reply);
        $this->assertStringContainsString('Prioritas Tinggi (1):', $reply);
        $this->assertStringContainsString('Prioritas Sedang (1):', $reply);
        $this->assertStringContainsString('Prioritas Rendah (1):', $reply);
        $this->assertStringContainsString('Tugas prioritas tinggi', $reply);
        $this->assertStringContainsString('Tugas prioritas rendah', $reply);

        $count = $this->ask($user, 'berapa tugas distribusi prioritas');

        $this->assertStringContainsString('Jumlah tugas berdasarkan distribusi prioritas:', $count);
        $this->assertStringContainsString('Tinggi: 1', $count);
        $this->assertStringContainsString('Sedang: 1', $count);
        $this->assertStringContainsString('Rendah: 1', $count);
        $this->assertStringNotContainsString('Tugas prioritas tinggi', $count);
    }

    public function test_friday_orders_open_tasks_by_overdue_deadline_then_priority(): void
    {
        $user = User::factory()->create();

        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Tugas sudah terlambat',
            'status' => 'todo',
            'priority' => 'medium',
            'deadline' => now()->subDay(),
        ]);
        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Tugas deadline tiga hari',
            'status' => 'todo',
            'priority' => 'low',
            'deadline' => now()->addDays(3),
        ]);
        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Tugas tanpa deadline',
            'status' => 'todo',
            'priority' => 'high',
            'deadline' => null,
        ]);
        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Tugas yang sudah selesai',
            'status' => 'completed',
            'deadline' => now()->subDays(2),
        ]);

        $reply = $this->ask($user, 'Sebutkan tugas yang harus dikerjakan terlebih dahulu');

        $this->assertStringContainsString('Tugas yang harus dikerjakan terlebih dahulu (3):', $reply);
        $this->assertStringContainsString('sudah melewati deadline, kerjakan segera', $reply);
        $this->assertStringContainsString('deadline 3 hari lagi', $reply);
        $this->assertStringContainsString('tanpa deadline; kerjakan setelah tugas bertenggat', $reply);
        $this->assertStringNotContainsString('Tugas yang sudah selesai', $reply);

        $overduePosition = strpos($reply, 'Tugas sudah terlambat');
        $tomorrowPosition = strpos($reply, 'Tugas deadline tiga hari');
        $noDeadlinePosition = strpos($reply, 'Tugas tanpa deadline');

        $this->assertIsInt($overduePosition);
        $this->assertIsInt($tomorrowPosition);
        $this->assertIsInt($noDeadlinePosition);
        $this->assertLessThan($tomorrowPosition, $overduePosition);
        $this->assertLessThan($noDeadlinePosition, $tomorrowPosition);

        $count = $this->ask($user, 'berapa tugas yang harus dikerjakan terlebih dahulu');

        $this->assertSame('Jumlah tugas aktif yang perlu diurutkan: 3.', $count);
    }

    public function test_sebutkan_returns_every_matching_task_and_berapa_returns_the_exact_count(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 12) as $number) {
            Task::factory()->create([
                'user_id' => $user->id,
                'title' => 'Tugas prioritas tinggi '.$number,
                'status' => $number <= 6 ? 'todo' : 'in_progress',
                'priority' => 'high',
            ]);
        }

        $reply = $this->ask($user, 'sebutkan tugas prioritas tinggi');

        $this->assertStringContainsString('Tugas prioritas tinggi (12):', $reply);
        $this->assertStringContainsString('Tugas prioritas tinggi 1', $reply);
        $this->assertStringContainsString('Tugas prioritas tinggi 12', $reply);
        $this->assertStringNotContainsString('Masih ada', $reply);

        $count = $this->ask($user, 'berapa jumlah tugas prioritas tinggi');

        $this->assertSame('Jumlah tugas prioritas tinggi yang belum selesai: 12.', $count);
    }

    public function test_user_can_read_task_statistics_and_chart_data_from_the_database(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Tugas selesai',
            'status' => 'completed',
            'priority' => 'high',
            'category' => 'Work',
        ]);
        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Tugas pending',
            'status' => 'todo',
            'priority' => 'medium',
            'category' => 'Study',
        ]);
        Task::factory()->create([
            'user_id' => $other->id,
            'title' => 'Tugas rahasia pengguna lain',
            'status' => 'todo',
        ]);

        $reply = $this->ask($user, 'Tampilkan statistik dan grafik tugas saya');

        $this->assertStringContainsString('Statistik tugas Anda:', $reply);
        $this->assertStringContainsString('Total: 2', $reply);
        $this->assertStringContainsString('Selesai: 1', $reply);
        $this->assertStringContainsString('Pending: 1', $reply);
        $this->assertStringContainsString('Tingkat penyelesaian: 50%', $reply);
        $this->assertStringContainsString('Kategori tugas:', $reply);
        $this->assertStringContainsString('Work: 1', $reply);
        $this->assertStringNotContainsString('Tugas rahasia pengguna lain', $reply);
    }

    public function test_admin_can_read_aggregate_task_statistics_without_private_task_titles(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Judul tugas privat',
            'status' => 'completed',
        ]);
        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Tugas privat lain',
            'status' => 'todo',
        ]);

        $reply = $this->ask($admin, 'Tampilkan statistik dan grafik tugas');

        $this->assertStringContainsString('Total tugas: 2', $reply);
        $this->assertStringContainsString('Status tugas: Pending 1, In progress 0, Selesai 1', $reply);
        $this->assertStringContainsString('Tingkat penyelesaian tugas: 50%', $reply);
        $this->assertStringNotContainsString('Judul tugas privat', $reply);
        $this->assertStringNotContainsString('Tugas privat lain', $reply);
    }

    public function test_admin_can_read_registration_chart_by_month(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['created_at' => '2026-09-10 10:00:00']);
        User::factory()->count(2)->create(['created_at' => '2026-10-10 10:00:00']);

        $reply = $this->ask($admin, 'Tampilkan grafik pendaftaran pengguna');

        $this->assertStringContainsString('Pendaftaran user per bulan:', $reply);
        $this->assertStringContainsString('September 2026: 1 user', $reply);
        $this->assertStringContainsString('Oktober 2026: 2 user', $reply);
    }

    public function test_programming_requests_are_rejected_with_a_maaf_prefix(): void
    {
        $user = User::factory()->create();

        $reply = $this->ask($user, 'Tuliskan koding python untuk membuat aplikasi');

        $this->assertStringStartsWith('Maaf,', $reply);
        $this->assertStringContainsString('hanya dapat membantu pertanyaan seputar DailyMate', $reply);
    }

    public function test_user_today_schedule_is_read_from_the_database_and_scoped(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $schedule = Schedule::create([
            'user_id' => $user->id,
            'title' => 'Rapat pagi',
            'schedule_date' => today()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ]);

        Schedule::create([
            'user_id' => $other->id,
            'title' => 'Jadwal rahasia pengguna lain',
            'schedule_date' => today()->toDateString(),
            'start_time' => '11:00:00',
            'end_time' => '12:00:00',
        ]);

        $reply = $this->ask($user, 'Apa jadwal saya hari ini?');

        $this->assertStringContainsString($schedule->title, $reply);
        $this->assertStringContainsString('09.00-10.00', $reply);
        $this->assertStringNotContainsString('Jadwal rahasia pengguna lain', $reply);
    }

    public function test_user_open_task_list_excludes_completed_and_other_users_tasks(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Task::factory()->create(['user_id' => $user->id, 'title' => 'Tugas terbuka', 'status' => 'todo']);
        Task::factory()->create(['user_id' => $user->id, 'title' => 'Tugas selesai', 'status' => 'completed']);
        Task::factory()->create(['user_id' => $other->id, 'title' => 'Tugas rahasia pengguna lain', 'status' => 'todo']);

        $reply = $this->ask($user, 'Tugas apa saja yang belum selesai?');

        $this->assertStringContainsString('Tugas terbuka', $reply);
        $this->assertStringNotContainsString('Tugas selesai', $reply);
        $this->assertStringNotContainsString('Tugas rahasia pengguna lain', $reply);
    }

    public function test_admin_active_user_count_matches_the_database(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(2)->create(['status' => 'active']);
        User::factory()->create(['status' => 'inactive']);

        $reply = $this->ask($admin, 'Berapa akun yang aktif?');

        $activeRegular = User::query()->where('role', 'user')->where('status', 'active')->count();
        $activeAdmins = User::query()->where('role', 'admin')->where('status', 'active')->count();
        $activeTotal = User::query()->where('status', 'active')->count();

        $this->assertStringContainsString("{$activeRegular} User", $reply);
        $this->assertStringContainsString("{$activeAdmins} Admin", $reply);
        $this->assertStringContainsString("{$activeTotal} akun aktif", $reply);
    }

    public function test_admin_qwen_status_uses_configuration_without_leaking_the_key(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertStringContainsString(
            'belum dikonfigurasi',
            $this->ask($admin, 'Apakah Qwen AI sudah terhubung?')
        );

        $config = AiConfiguration::create([
            'provider' => 'qwen',
            'endpoint' => AiConfiguration::DEFAULT_ENDPOINT,
            'model' => 'qwen-plus',
            'api_key' => 'super-secret-qwen-key',
            'is_active' => true,
        ]);

        $reply = $this->ask($admin, 'Apakah Qwen AI sudah terhubung?');

        $this->assertStringContainsString('sudah dikonfigurasi dan aktif', $reply);
        $this->assertStringContainsString('Koneksi belum diuji', $reply);
        $this->assertStringNotContainsString('super-secret-qwen-key', $reply);

        $config->update(['last_test_status' => 'success']);

        $this->assertStringContainsString(
            'sudah terhubung dan aktif',
            $this->ask($admin, 'Apakah Qwen AI sudah terhubung?')
        );
    }

    public function test_empty_user_database_is_reported_honestly(): void
    {
        $user = User::factory()->create();

        $this->assertSame('Anda memiliki 0 tugas.', $this->ask($user, 'Berapa jumlah tugas saya?'));
        $this->assertSame('Tidak ada jadwal untuk hari ini.', $this->ask($user, 'Apa jadwal saya hari ini?'));
        $this->assertSame('Tidak ada tugas yang belum selesai.', $this->ask($user, 'Tugas apa saja yang belum selesai?'));
    }

    public function test_user_can_check_their_own_whatsapp_notification_status(): void
    {
        $user = User::factory()->create();

        $reply = $this->ask($user, 'Apakah notifikasi WhatsApp saya aktif?');

        $this->assertStringContainsString('Notifikasi WhatsApp Anda belum aktif', $reply);
        $this->assertStringNotContainsString('hanya dapat diakses oleh Admin', $reply);
    }

    public function test_out_of_scope_question_is_rejected_in_indonesian_without_calling_qwen(): void
    {
        $user = User::factory()->create();
        $this->configureQwen();
        Http::fake();

        $reply = $this->ask($user, 'Bagaimana cuaca besok?');

        $this->assertStringStartsWith('Maaf,', $reply);
        $this->assertStringContainsString('hanya dapat membantu pertanyaan seputar DailyMate', $reply);
        Http::assertNothingSent();
    }

    public function test_qwen_english_answer_is_replaced_with_indonesian_fallback(): void
    {
        $this->configureQwen();
        $user = User::factory()->create();
        Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Tugas fokus',
            'status' => 'todo',
            'priority' => 'high',
        ]);

        Http::fake([
            'dashscope-intl.aliyuncs.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'You should work on your tasks first.']]],
            ], 200),
        ]);

        $reply = $this->ask($user, 'Bantu saya menyusun rencana hari ini');

        $this->assertStringNotContainsString('You should work', $reply);
        $this->assertStringContainsString('Prioritas yang disarankan', $reply);
        $this->assertStringContainsString('Tugas fokus', $reply);
    }

    public function test_roles_cannot_read_data_outside_their_permissions(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $other = User::factory()->create();

        Task::factory()->create(['user_id' => $user->id, 'title' => 'Tugas pribadi user']);
        Task::factory()->create(['user_id' => $other->id, 'title' => 'Tugas rahasia lain']);

        $userAdminStats = $this->ask($user, 'Berapa total pengguna DailyMate?');
        $this->assertStringContainsString('hanya dapat diakses oleh Admin', $userAdminStats);
        $this->assertStringNotContainsString('Total pengguna:', $userAdminStats);

        $adminTasks = $this->ask($admin, 'Tampilkan semua tugas pengguna');
        $this->assertStringContainsString('tidak dapat melihat judul atau detail tugas pribadi', $adminTasks);
        $this->assertStringNotContainsString('Tugas pribadi user', $adminTasks);
        $this->assertStringNotContainsString('Tugas rahasia lain', $adminTasks);

        $adminChats = $this->ask($admin, 'Tampilkan isi percakapan AI User');
        $this->assertStringContainsString('tidak dapat membaca isi percakapan AI', $adminChats);
    }

    public function test_user_menu_guide_explains_how_to_add_tasks_and_schedules(): void
    {
        $user = User::factory()->create();

        $reply = $this->ask($user, 'sebutkan cara untuk menambah tugas dan jadwal');

        $this->assertStringContainsString('Untuk menambah tugas:', $reply);
        $this->assertStringContainsString('menu My Tasks', $reply);
        $this->assertStringContainsString('Add New Task', $reply);
        $this->assertStringContainsString('Create Task', $reply);
        $this->assertStringContainsString('Untuk menambah jadwal:', $reply);
        $this->assertStringContainsString('menu My Schedule', $reply);
        $this->assertStringContainsString('Add Schedule', $reply);
        $this->assertStringContainsString('Create Schedule', $reply);
        $this->assertStringNotContainsString('Nama Anda', $reply);
    }

    public function test_user_menu_guide_covers_statistics_notifications_feedback_and_profile(): void
    {
        $user = User::factory()->create();

        $statistics = $this->ask($user, 'bagaimana cara melihat statistik produktivitas');
        $this->assertStringContainsString('Productivity Statistics', $statistics);
        $this->assertStringContainsString('Tasks Completed by Date', $statistics);
        $this->assertStringContainsString('Completion Rate', $statistics);

        $notifications = $this->ask($user, 'bagaimana cara mengatur notifikasi WhatsApp');
        $this->assertStringContainsString('menu Notifications', $notifications);
        $this->assertStringContainsString('WhatsApp Notifications', $notifications);
        $this->assertStringContainsString('Save WhatsApp Settings', $notifications);

        $feedback = $this->ask($user, 'bagaimana cara mengirim feedback');
        $this->assertStringContainsString('menu Feedback', $feedback);
        $this->assertStringContainsString('Send Feedback', $feedback);

        $profile = $this->ask($user, 'bagaimana cara mengubah profil saya');
        $this->assertStringContainsString('menu Profile', $profile);
        $this->assertStringContainsString('Save Changes', $profile);
        $this->assertStringContainsString('Update Password', $profile);
        $this->assertStringNotContainsString('Nama Anda', $profile);
    }

    public function test_user_menu_guide_does_not_reveal_admin_actions(): void
    {
        $user = User::factory()->create();

        $reply = $this->ask($user, 'cara menambah user dan mengelola pengguna');

        $this->assertStringContainsString('hanya dapat digunakan oleh akun Admin', $reply);
        $this->assertStringContainsString('User Management', $reply);
        $this->assertStringNotContainsString('Tombol Add User', $reply);
    }

    public function test_admin_menu_guide_covers_management_reports_whatsapp_and_ai_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $reply = $this->ask($admin, 'sebutkan cara mengelola pengguna, mengirim laporan, mengatur WhatsApp API, dan Qwen');

        $this->assertStringContainsString('Untuk mengelola pengguna:', $reply);
        $this->assertStringContainsString('User Management', $reply);
        $this->assertStringContainsString('Search users', $reply);
        $this->assertStringContainsString('menonaktifkan atau mengaktifkan akun', $reply);
        $this->assertStringContainsString('Admin Reports', $reply);
        $this->assertStringContainsString('Save Settings', $reply);
        $this->assertStringContainsString('Send Report Now', $reply);
        $this->assertStringContainsString('WhatsApp API', $reply);
        $this->assertStringContainsString('Save Configuration', $reply);
        $this->assertStringContainsString('Test Connection', $reply);
        $this->assertStringContainsString('Friday AI Settings', $reply);
        $this->assertStringContainsString('Qwen API key', $reply);
    }

    public function test_admin_is_told_task_and_schedule_menus_are_user_only(): void
    {
        $admin = User::factory()->admin()->create();

        $reply = $this->ask($admin, 'sebutkan cara menambah tugas dan jadwal');

        $this->assertStringContainsString('Akun Admin tidak memiliki menu My Tasks', $reply);
        $this->assertStringContainsString('data pribadi milik akun User', $reply);
        $this->assertStringContainsString('gunakan akun User', $reply);
        $this->assertStringNotContainsString('Klik tombol Add New Task', $reply);
        $this->assertStringNotContainsString('Klik tombol Add Schedule', $reply);
    }

    private function ask(User $user, string $message): string
    {
        return (string) $this->actingAs($user)
            ->postJson(route('friday.send'), ['message' => $message])
            ->assertOk()
            ->json('response');
    }

    private function configureQwen(): AiConfiguration
    {
        return AiConfiguration::create([
            'provider' => 'qwen',
            'endpoint' => AiConfiguration::DEFAULT_ENDPOINT,
            'model' => 'qwen-plus',
            'api_key' => 'test-qwen-key',
            'is_active' => true,
        ]);
    }
}
