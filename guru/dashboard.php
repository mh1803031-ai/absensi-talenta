<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/functions.php';
requireRole('guru');

$pageTitle  = 'Dashboard Guru';
$activePage = 'dashboard';
$base = '/TUGASPAKDANIL/ABSENSITALENTA';

$myStudents   = countTable('users', 'role = ?', ['siswa']);
$totalHadir   = countTable('attendance_records', 'DATE(attended_at) = ?', [date('Y-m-d')]);
$pendingJurnals= countTable('journals', 'status = ?', ['pending']);
$activeQuizzes = countTable('quizzes', 'is_active = 1');

$recentJournals = db()->query(
    "SELECT j.*, u.name AS student_name FROM journals j JOIN users u ON u.id=j.student_id
     ORDER BY j.submitted_at DESC LIMIT 5"
)->fetchAll();

// Recent announcements
$recentAnnouncements = db()->query(
    "SELECT a.*, u.name as author_name FROM announcements a 
     JOIN users u ON a.author_id = u.id 
     ORDER BY a.created_at DESC LIMIT 3"
)->fetchAll();

// Today's schedule for teacher
$currentDow = date('N');
$teacherId = currentUser()['id'];
$todaySchedule = db()->query("
    SELECT s.*, c.name as class_name 
    FROM schedules s 
    JOIN classes c ON c.id = s.class_id 
    WHERE s.teacher_id = $teacherId AND s.day_of_week = $currentDow 
    ORDER BY s.start_time ASC
")->fetchAll();

// Today Attendance Recap per Class
$todayAttendance = db()->query(
    "SELECT c.name as class_name, 
            COUNT(DISTINCT u.id) as total_siswa,
            COUNT(DISTINCT r.student_id) as total_hadir
     FROM classes c
     LEFT JOIN users u ON u.class_id = c.id AND u.role = 'siswa'
     LEFT JOIN attendance_records r ON r.student_id = u.id AND DATE(r.attended_at) = CURDATE()
     GROUP BY c.id
     ORDER BY total_hadir DESC"
)->fetchAll();

include $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/header.php';
?>
<style>
.announcement-card { background: linear-gradient(135deg, rgba(69, 123, 157, 0.15), rgba(69, 123, 157, 0.05)); border: 1px solid rgba(69, 123, 157, 0.3); border-radius: var(--radius-lg); padding: 1.2rem 1.5rem; margin-bottom: 1rem; }
.announcement-title { font-weight: 700; color: var(--info); font-size: 1.1rem; margin-bottom: 0.3rem; }
.announcement-meta { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.8rem; }
.announcement-content { font-size: 0.9rem; line-height: 1.6; }
</style>

<?php if ($recentAnnouncements): ?>
<div style="margin-bottom: 1.5rem;">
  <h3 style="font-size:1.1rem; margin-bottom:1rem;"><i class="fas fa-bullhorn text-info"></i> Pengumuman Terbaru</h3>
  <?php foreach ($recentAnnouncements as $a): ?>
  <div class="announcement-card">
    <div class="announcement-title"><?= clean($a['title']) ?></div>
    <div class="announcement-meta"><i class="fas fa-user-circle"></i> <?= clean($a['author_name']) ?> &nbsp;&bull;&nbsp; <i class="fas fa-clock"></i> <?= formatDateTime($a['created_at']) ?></div>
    <div class="announcement-content"><?= nl2br(clean($a['content'])) ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="stat-grid">
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-user-graduate"></i></div><div><div class="stat-value"><?= $myStudents ?></div><div class="stat-label">Total Siswa</div></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div><div class="stat-value"><?= $totalHadir ?></div><div class="stat-label">Hadir Hari Ini</div></div></div>
  <div class="stat-card"><div class="stat-icon yellow"><i class="fas fa-clock"></i></div><div><div class="stat-value"><?= $pendingJurnals ?></div><div class="stat-label">Jurnal Pending</div></div></div>
  <div class="stat-card"><div class="stat-icon red"><i class="fas fa-clipboard-list"></i></div><div><div class="stat-value"><?= $activeQuizzes ?></div><div class="stat-label">Ulangan Aktif</div></div></div>
</div>

<div style="margin-bottom:1.5rem">
  <!-- Today Attendance Recap Widget -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fas fa-calendar-check text-accent"></i> Kehadiran Hari Ini Secara Live</h3>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Kelas</th><th>Total Siswa</th><th>Telah Hadir</th><th>% Hadir Hari Ini</th></tr></thead>
        <tbody>
          <?php foreach ($todayAttendance as $cs): 
            $pct = $cs['total_siswa'] > 0 ? round($cs['total_hadir']*100/$cs['total_siswa']) : 0; 
          ?>
          <tr>
            <td><strong><?= clean($cs['class_name']) ?></strong></td>
            <td><?= $cs['total_siswa'] ?></td>
            <td><?= $cs['total_hadir'] ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:.5rem">
                <div style="flex:1;height:6px;background:var(--border);border-radius:3px">
                  <div style="width:<?= $pct ?>%;height:100%;background:<?= $pct>=80?'var(--success)':($pct>=50?'var(--warning)':'var(--danger)') ?>;border-radius:3px"></div>
                </div>
                <span style="font-size:.8rem;font-weight:600"><?= $pct ?>%</span>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">
  
  <div class="card">
    <div class="card-header">
      <h3><i class="fas fa-book-open text-accent"></i> Jurnal Terbaru</h3>
      <a href="<?= $base ?>/guru/journals.php" class="btn btn-ghost btn-sm">Lihat Semua</a>
    </div>
    <?php if ($recentJournals): ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Siswa</th><th>Judul</th><th>Status</th><th>Waktu</th></tr></thead>
        <tbody>
          <?php foreach ($recentJournals as $j): ?>
          <tr>
            <td><strong><?= clean($j['student_name']) ?></strong></td>
            <td><?= clean(substr($j['title'],0,30)) ?>...</td>
            <td><?= statusBadge($j['status']) ?></td>
            <td class="text-muted" style="font-size:.8rem"><?= formatDateTime($j['submitted_at']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="empty-state"><i class="fas fa-inbox"></i>Belum ada jurnal.</div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-header">
        <h3><i class="fas fa-calendar-alt text-primary"></i> Jadwal Mengajar Hari Ini</h3>
    </div>
    <div style="padding:1rem;">
      <?php if ($todaySchedule): ?>
          <div style="display:flex; flex-direction:column; gap:1rem;">
              <?php foreach ($todaySchedule as $sch): ?>
              <div style="display:flex; gap:1rem; align-items:center; padding: .8rem; background: var(--bg-body); border: 1px solid var(--border); border-radius: var(--radius);">
                  <div style="background:var(--primary); color:#fff; padding:.5rem; border-radius:var(--radius); text-align:center; min-width:60px; font-weight:bold; font-size:.85rem;">
                      <?= date('H:i', strtotime($sch['start_time'])) ?>
                  </div>
                  <div>
                      <div style="font-weight:700; color:var(--text); margin-bottom:.2rem;"><?= clean($sch['subject']) ?></div>
                      <div style="font-size:.8rem; color:var(--text-muted);"><i class="fas fa-users"></i> Kelas: <?= clean($sch['class_name']) ?></div>
                  </div>
              </div>
              <?php endforeach; ?>
          </div>
      <?php else: ?>
          <div class="empty-state" style="padding:2rem 1rem;">Tidak ada jadwal mengajar hari ini.</div>
      <?php endif; ?>
    </div>
  </div>

</div>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/footer.php'; ?>
