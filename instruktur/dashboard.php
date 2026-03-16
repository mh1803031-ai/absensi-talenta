<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/functions.php';
requireRole('instruktur');

$pageTitle  = 'Dashboard Instruktur';
$activePage = 'dashboard';
$base = '/TUGASPAKDANIL/ABSENSITALENTA';

$totalHadir    = countTable('attendance_records', 'DATE(attended_at) = ?', [date('Y-m-d')]);
$pendingJurnals= countTable('journals', 'status = ?', ['pending']);
$activeQuizzes = countTable('quizzes', 'is_active = 1 AND created_by = ?', [currentUser()['id']]);
$leaveToday    = countTable('leave_permissions', 'DATE(approved_at) = ?', [date('Y-m-d')]);

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
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div><div class="stat-value"><?= $totalHadir ?></div><div class="stat-label">Hadir Hari Ini</div></div></div>
  <div class="stat-card"><div class="stat-icon yellow"><i class="fas fa-clock"></i></div><div><div class="stat-value"><?= $pendingJurnals ?></div><div class="stat-label">Jurnal Pending</div></div></div>
  <div class="stat-card"><div class="stat-icon red"><i class="fas fa-clipboard-list"></i></div><div><div class="stat-value"><?= $activeQuizzes ?></div><div class="stat-label">Ulangan Saya</div></div></div>
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-door-open"></i></div><div><div class="stat-value"><?= $leaveToday ?></div><div class="stat-label">Izin Pulang Hari Ini</div></div></div>
</div>

<div class="flex gap-2 mt-2 mb-3">
  <a href="<?= $base ?>/instruktur/generate_token.php" class="btn btn-accent"><i class="fas fa-key"></i> Generate Token</a>
  <a href="<?= $base ?>/instruktur/leave_permission.php" class="btn btn-success"><i class="fas fa-door-open"></i> Izin Pulang</a>
  <a href="<?= $base ?>/instruktur/quizzes.php" class="btn btn-primary"><i class="fas fa-clipboard-list"></i> Buat Ulangan</a>
</div>

<div class="card">
  <div class="card-header">
    <h3><i class="fas fa-book-open text-accent"></i> Jurnal Terbaru</h3>
    <a href="<?= $base ?>/instruktur/journals.php" class="btn btn-ghost btn-sm">Lihat Semua</a>
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
<?php include $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/footer.php'; ?>
