<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('siswa');

$base       = '/TUGASPAKDANIL/ABSENSITALENTA';
$user       = currentUser();
$studentId  = $user['id'];
$attended   = hasAttendedToday($studentId);
$submitted  = $attended && hasSubmittedJournalToday($studentId);
$attendanceId = $attended ? getTodayAttendanceId($studentId) : null;

$myJournals = db()->query(
    "SELECT j.*, t.token FROM journals j
     JOIN attendance_records r ON r.id = j.attendance_id
     JOIN attendance_tokens t ON t.id = r.token_id
     WHERE j.student_id = $studentId
     ORDER BY j.submitted_at DESC LIMIT 5"
)->fetchAll();

$myClassId = $user['class_id'];
$quizQry = "SELECT q.* FROM quizzes q
            WHERE q.is_active = 1 AND NOW() BETWEEN q.start_time AND q.end_time
            AND (q.class_id IS NULL OR q.class_id = ?)
            AND q.id NOT IN (SELECT quiz_id FROM quiz_answers WHERE student_id = ?)
            LIMIT 3";
$quizStmt = db()->prepare($quizQry);
$quizStmt->execute([$myClassId, $studentId]);
$activeQuizzes = $quizStmt->fetchAll();

$recentAnnouncements = db()->query(
    "SELECT a.*, u.name as author_name FROM announcements a 
     JOIN users u ON a.author_id = u.id 
     ORDER BY a.created_at DESC LIMIT 3"
)->fetchAll();

$currentDow = date('N');
$todaySchedule = [];
if ($myClassId) {
    $todaySchedule = db()->query("
        SELECT s.*, u.name as teacher_name 
        FROM schedules s 
        JOIN users u ON u.id = s.teacher_id 
        WHERE s.class_id = $myClassId AND s.day_of_week = $currentDow 
        ORDER BY s.start_time ASC
    ")->fetchAll();
}

$pageTitle  = 'Beranda Siswa';
$activePage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Beranda — Talenta</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= $base ?>/assets/css/style.css">
<style>
/* Token modal shake animation */
@keyframes shake {
  0%,100%{transform:translateX(0)}
  20%{transform:translateX(-8px)}
  40%{transform:translateX(8px)}
  60%{transform:translateX(-5px)}
  80%{transform:translateX(5px)}
}
.shake { animation: shake .4s ease; }

.token-status {
  text-align:center;
  padding:.6rem;
  border-radius:var(--radius);
  font-size:.85rem;
  font-weight:600;
  margin-bottom:1rem;
  display:none;
}
.token-status.error   { background:rgba(230,57,70,.15); color:#ff6b78; border:1px solid rgba(230,57,70,.3); }
.token-status.success { background:rgba(42,157,143,.15); color:#4ecdc4; border:1px solid rgba(42,157,143,.3); }

.leave-banner {
  background:linear-gradient(135deg,rgba(42,157,143,.2),rgba(42,157,143,.05));
  border:1px solid rgba(42,157,143,.3);
  border-radius:var(--radius-lg);
  padding:1.5rem;
  text-align:center;
  margin-bottom:1.5rem;
}
</style>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

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

<?php if (!$attended): ?>
<!-- ═══════════════════════════════════════════════
     NON-DISMISSIBLE TOKEN MODAL
═══════════════════════════════════════════════ -->
<div class="modal-overlay" id="tokenModal" style="display:flex !important">
  <div class="modal-box">
    <div class="modal-icon"><i class="fas fa-key"></i></div>
    <div class="modal-title">Token Absensi Diperlukan</div>
    <div class="modal-subtitle">
      Masukkan token absensi dari Admin, Guru, atau Instruktur untuk melanjutkan.<br>
      <strong style="color:var(--warning)">⚠️ Anda tidak dapat menutup dialog ini sebelum memasukkan token yang benar.</strong>
    </div>

    <div id="tokenStatus" class="token-status"></div>

    <input
      type="text"
      id="tokenInput"
      class="token-full-input"
      placeholder="XXXXXX"
      maxlength="6"
      autocomplete="off"
      spellcheck="false"
    >
    <div style="font-size:.78rem;color:var(--text-muted);text-align:center;margin-bottom:1.2rem">
      Token terdiri dari 6 karakter. Hubungi Guru atau Instruktur jika belum mendapatkan token.
    </div>
    <button class="btn btn-accent btn-block" id="submitTokenBtn" onclick="submitToken()">
      <i class="fas fa-unlock"></i> &nbsp;Masukkan Token
    </button>

    <div style="text-align:center;margin-top:1rem">
      <a href="<?= $base ?>/logout.php" style="color:var(--text-muted);font-size:.82rem;text-decoration:none">
        <i class="fas fa-sign-out-alt"></i> Keluar dari akun
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Stat row -->
<div class="stat-grid" style="grid-template-columns:repeat(3,1fr)">
  <div class="stat-card">
    <div class="stat-icon green"><i class="fas fa-calendar-check"></i></div>
    <div>
      <div class="stat-value"><?= countTable('attendance_records','student_id=?',[$studentId]) ?></div>
      <div class="stat-label">Total Kehadiran</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fas fa-book"></i></div>
    <div>
      <div class="stat-value"><?= countTable('journals','student_id=?',[$studentId]) ?></div>
      <div class="stat-label">Total Jurnal</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="fas fa-star"></i></div>
    <div>
      <?php
        $avgScore = db()->prepare("SELECT AVG(score) FROM quiz_answers WHERE student_id=?");
        $avgScore->execute([$studentId]);
        $avg = $avgScore->fetchColumn();
      ?>
      <div class="stat-value"><?= $avg ? round($avg,1) : '—' ?></div>
      <div class="stat-label">Rata-rata Nilai</div>
    </div>
  </div>
</div>

<!-- Actions -->
<?php if ($attended): ?>
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px,1fr));gap:1.2rem;margin-bottom:1.5rem">
  <!-- Jurnal -->
  <?php if (!$submitted): ?>
  <a href="<?= $base ?>/siswa/journal_form.php" class="card" style="text-decoration:none;cursor:pointer;border-color:rgba(244,162,97,.3);transition:transform .2s" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
    <div style="text-align:center;padding:1rem">
      <div style="width:52px;height:52px;background:rgba(244,162,97,.15);border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto .8rem;font-size:1.4rem;color:var(--accent)"><i class="fas fa-pen"></i></div>
      <strong style="color:var(--text)">Isi Jurnal Harian</strong>
      <p class="text-muted" style="font-size:.82rem;margin-top:.3rem">Jurnal hari ini belum diisi</p>
    </div>
  </a>
  <?php else: ?>
  <div class="card" style="border-color:rgba(42,157,143,.3)">
    <div style="text-align:center;padding:1rem">
      <div style="width:52px;height:52px;background:rgba(42,157,143,.15);border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto .8rem;font-size:1.4rem;color:var(--success)"><i class="fas fa-check"></i></div>
      <strong style="color:var(--success)">Jurnal Sudah Dikirim</strong>
      <p class="text-muted" style="font-size:.82rem;margin-top:.3rem">Menunggu tinjauan</p>
    </div>
  </div>
  <?php endif; ?>

  <!-- Ulangan -->
  <a href="<?= $base ?>/siswa/quiz.php" class="card" style="text-decoration:none;cursor:pointer;border-color:rgba(69,123,157,.3);transition:transform .2s" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
    <div style="text-align:center;padding:1rem">
      <div style="width:52px;height:52px;background:rgba(69,123,157,.15);border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto .8rem;font-size:1.4rem;color:#7ec8e3"><i class="fas fa-clipboard-list"></i></div>
      <strong style="color:var(--text)">Ulangan</strong>
      <p class="text-muted" style="font-size:.82rem;margin-top:.3rem"><?= count($activeQuizzes) ?> ulangan tersedia</p>
    </div>
  </a>

</div>
<?php endif; ?>

<!-- Recent Journals -->
<div class="card">
  <div class="card-header">
    <h3><i class="fas fa-book-open text-accent"></i> Jurnal Terakhir</h3>
    <a href="<?= $base ?>/siswa/journal_history.php" class="btn btn-ghost btn-sm">Semua</a>
  </div>
  <?php if ($myJournals): ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Tanggal</th><th>Judul</th><th>Status</th><th>Catatan Reviewer</th></tr></thead>
      <tbody>
        <?php foreach ($myJournals as $j): ?>
        <tr>
          <td style="font-size:.82rem;color:var(--text-muted)"><?= formatDate($j['submitted_at']) ?></td>
          <td><strong><?= clean($j['title']) ?></strong></td>
          <td><?= statusBadge($j['status']) ?></td>
          <td style="font-size:.82rem;color:var(--text-muted)"><?= $j['review_note'] ? clean(substr($j['review_note'],0,50)).'...' : '—' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="empty-state"><i class="fas fa-book"></i>Belum ada jurnal.</div>
  <?php endif; ?>
</div>

<script>
const tokenModal = document.getElementById('tokenModal');
if (tokenModal) {
  tokenModal.addEventListener('click', function(e) {
    if (e.target === tokenModal) {
      const box = tokenModal.querySelector('.modal-box');
      box.classList.remove('shake');
      void box.offsetWidth;
      box.classList.add('shake');
    }
  });
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') e.preventDefault();
  });
  setTimeout(() => document.getElementById('tokenInput')?.focus(), 300);
}

document.getElementById('tokenInput')?.addEventListener('input', function(){
  this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g,'');
});

function submitToken(){
  const token  = document.getElementById('tokenInput').value.trim();
  const status = document.getElementById('tokenStatus');
  const btn    = document.getElementById('submitTokenBtn');

  if (!token || token.length < 6) {
    showTokenMsg('Token harus 6 karakter.', 'error');
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';

  fetch('<?= $base ?>/ajax/validate_token.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'token=' + encodeURIComponent(token)
  })
  .then(r => {
    if (!r.ok) throw new Error('Network response was not ok');
    return r.json();
  })
  .then(data => {
    if (data.success) {
      showTokenMsg('✅ ' + data.message, 'success');
      btn.innerHTML = '<i class="fas fa-check"></i> Berhasil!';
      setTimeout(() => location.reload(), 1200);
    } else {
      showTokenMsg('❌ ' + data.message, 'error');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-unlock"></i> Masukkan Token';
      const box = tokenModal.querySelector('.modal-box');
      box.classList.remove('shake');
      void box.offsetWidth; box.classList.add('shake');
    }
  })
  .catch(err => {
    console.error(err);
    showTokenMsg('❌ Terjadi kesalahan sistem. Silakan muat ulang halaman atau lapor ke Admin.', 'error');
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-unlock"></i> Masukkan Token';
  });
}

function showTokenMsg(msg, type){
  const el = document.getElementById('tokenStatus');
  el.textContent = msg;
  el.className = 'token-status ' + type;
  el.style.display = 'block';
}

document.getElementById('tokenInput')?.addEventListener('keydown', function(e){
  if (e.key === 'Enter') submitToken();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>