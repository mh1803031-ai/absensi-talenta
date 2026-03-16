<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('siswa','admin','guru','instruktur');

$base   = '/TUGASPAKDANIL/ABSENSITALENTA';
$myRole = currentRole();

// If staff viewing a student profile
$viewId = (int)($_GET['id'] ?? currentUser()['id']);
// Students can only view their own
if ($myRole === 'siswa') $viewId = currentUser()['id'];

$stmt = db()->prepare("SELECT u.*, c.name AS class_name FROM users u LEFT JOIN classes c ON c.id = u.class_id WHERE u.id = ?");
$stmt->execute([$viewId]);
$profile = $stmt->fetch();
if (!$profile) { header("Location: $base/login.php"); exit; }

// Stats
$totalHadir   = countTable('attendance_records','student_id=?',[$viewId]);
$totalJurnals = countTable('journals','student_id=?',[$viewId]);
$approvedJ    = countTable('journals','student_id=? AND status=?',[$viewId,'approved']);
$avgScoreStmt = db()->prepare("SELECT AVG(score) FROM quiz_answers WHERE student_id=?");
$avgScoreStmt->execute([$viewId]);
$avgScore   = $avgScoreStmt->fetchColumn();

// Journals
$journals = db()->query(
    "SELECT j.*, u.name AS reviewer_name FROM journals j
     LEFT JOIN users u ON u.id = j.reviewed_by
     WHERE j.student_id = $viewId
     ORDER BY j.submitted_at DESC LIMIT 10"
)->fetchAll();

// Attendance this month
$attendance = db()->query(
    "SELECT r.attended_at, r.status, t.valid_date FROM attendance_records r
     JOIN attendance_tokens t ON t.id = r.token_id
     WHERE r.student_id = $viewId
     ORDER BY r.attended_at DESC LIMIT 30"
)->fetchAll();

// Quiz scores
$quizScores = db()->query(
    "SELECT a.score, a.submitted_at, q.title, q.type FROM quiz_answers a
     JOIN quizzes q ON q.id = a.quiz_id
     WHERE a.student_id = $viewId
     ORDER BY a.submitted_at DESC LIMIT 10"
)->fetchAll();

$pageTitle  = 'Profil: ' . $profile['name'];
$activePage = 'profile';
include __DIR__ . '/../includes/header.php';
?>

<!-- Profile Header -->
<div class="card mb-3" style="background:linear-gradient(135deg,var(--primary),var(--card-bg))">
  <div style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap">
    <div class="avatar" style="width:80px;height:80px;font-size:1.6rem;border:3px solid rgba(244,162,97,.4);flex-shrink:0">
      <?= $profile['photo'] ? "<img src='{$base}/uploads/photos/{$profile['photo']}'>" : avatarInitials($profile['name']) ?>
    </div>
    <div>
      <h2 style="font-size:1.5rem;font-weight:800;margin-bottom:.3rem"><?= clean($profile['name']) ?></h2>
      <div style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:center">
        <code style="color:var(--text-muted);font-size:.88rem">@<?= clean($profile['username']) ?></code>
        <?= roleBadge($profile['role']) ?>
        <?php if ($profile['class_name']): ?>
        <span class="badge badge-info"><i class="fas fa-chalkboard"></i> <?= clean($profile['class_name']) ?></span>
        <?php endif; ?>
        <?= $profile['is_active'] ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-danger">Nonaktif</span>' ?>
      </div>
    </div>
    <?php if (isAdminOrGuru()): ?>
    <div style="margin-left:auto">
      <a href="<?= $base ?>/admin/manage_users.php" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Stat Strip -->
<div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1.5rem">
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-calendar-check"></i></div><div><div class="stat-value"><?= $totalHadir ?></div><div class="stat-label">Total Hadir</div></div></div>
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-book"></i></div><div><div class="stat-value"><?= $totalJurnals ?></div><div class="stat-label">Total Jurnal</div></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div><div class="stat-value"><?= $approvedJ ?></div><div class="stat-label">Jurnal Disetujui</div></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-star"></i></div><div><div class="stat-value"><?= $avgScore ? round($avgScore,1) : '—' ?></div><div class="stat-label">Rata-rata Nilai</div></div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">
  <!-- Journals -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fas fa-book-open text-accent"></i> Jurnal Terakhir</h3>
    </div>
    <?php if ($journals): foreach ($journals as $j): ?>
    <div style="padding:.7rem;border-radius:var(--radius);border:1px solid var(--border);margin-bottom:.5rem">
      <div style="display:flex;align-items:center;justify-content:space-between">
        <div>
          <strong style="font-size:.88rem"><?= clean($j['title']) ?></strong>
          <div style="font-size:.75rem;color:var(--text-muted)"><?= formatDate($j['submitted_at']) ?></div>
        </div>
        <?= statusBadge($j['status']) ?>
      </div>
      <?php if ($j['review_note']): ?>
      <div style="font-size:.78rem;color:var(--text-muted);margin-top:.4rem;border-top:1px solid var(--border);padding-top:.4rem">
        <i class="fas fa-comment-alt" style="color:var(--accent)"></i> <?= clean(substr($j['review_note'],0,80)) ?>...
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; else: ?>
    <div class="empty-state"><i class="fas fa-book"></i>Belum ada jurnal.</div>
    <?php endif; ?>
  </div>

  <div>
    <!-- Attendance -->
    <div class="card mb-3">
      <div class="card-header">
        <h3><i class="fas fa-calendar text-accent"></i> Kehadiran Terakhir</h3>
      </div>
      <?php if ($attendance): ?>
      <div style="max-height:220px;overflow-y:auto">
        <?php foreach ($attendance as $a): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid var(--border)">
          <div style="font-size:.84rem"><?= formatDate($a['valid_date']) ?></div>
          <?= statusBadge($a['status']) ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="empty-state" style="padding:1.5rem"><i class="fas fa-calendar-times"></i>Belum ada data.</div>
      <?php endif; ?>
    </div>

    <!-- Quiz Scores -->
    <div class="card">
      <div class="card-header">
        <h3><i class="fas fa-chart-bar text-accent"></i> Nilai Ulangan</h3>
      </div>
      <?php if ($quizScores): ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Ulangan</th><th>Nilai</th><th>Tanggal</th></tr></thead>
          <tbody>
            <?php foreach ($quizScores as $qs): ?>
            <tr>
              <td><strong><?= clean($qs['title']) ?></strong><br><small class="text-muted"><?= ucfirst($qs['type']) ?></small></td>
              <td><span style="font-size:1.1rem;font-weight:800;color:<?= $qs['score']>=70?'var(--success)':'var(--danger)' ?>"><?= $qs['score'] ?></span></td>
              <td style="font-size:.78rem;color:var(--text-muted)"><?= formatDate($qs['submitted_at']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="empty-state" style="padding:1.5rem"><i class="fas fa-clipboard-list"></i>Belum ada nilai.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
