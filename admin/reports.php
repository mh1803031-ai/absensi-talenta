<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin','guru');

$pageTitle  = 'Laporan & Rekap';
$activePage = 'reports';
$base = defined('APP_BASE_PATH') ? APP_BASE_PATH : '/TUGASPAKDANIL/ABSENSITALENTA';

$filterMonth = $_GET['month'] ?? date('Y-m');
$filterClass = $_GET['class_id'] ?? '';

// Attendance by class this month
$classStats = db()->query(
    "SELECT c.name AS class_name,
            COUNT(DISTINCT r.student_id) AS total_hadir,
            COUNT(DISTINCT u.id) AS total_siswa
     FROM classes c
     LEFT JOIN users u ON u.class_id = c.id AND u.role = 'siswa'
     LEFT JOIN attendance_records r ON r.student_id = u.id AND DATE_FORMAT(r.attended_at,'%Y-%m') = '$filterMonth'
     GROUP BY c.id ORDER BY c.name"
)->fetchAll();

// Top absent students
$absentees = db()->query(
    "SELECT u.name, u.username, c.name AS class_name,
            COUNT(r.id) AS hadir_count
     FROM users u
     LEFT JOIN classes c ON c.id = u.class_id
     LEFT JOIN attendance_records r ON r.student_id = u.id AND DATE_FORMAT(r.attended_at,'%Y-%m') = '$filterMonth'
     WHERE u.role = 'siswa'
     GROUP BY u.id ORDER BY hadir_count ASC LIMIT 10"
)->fetchAll();

// Journal stats
$journalStats = db()->query(
    "SELECT status, COUNT(*) as cnt FROM journals
     WHERE DATE_FORMAT(submitted_at,'%Y-%m') = '$filterMonth'
     GROUP BY status"
)->fetchAll();
$jMap = [];
foreach ($journalStats as $js) $jMap[$js['status']] = $js['cnt'];

// Quiz scores
$quizScores = db()->query(
    "SELECT q.title, AVG(a.score) AS avg_score, MAX(a.score) AS max_score, MIN(a.score) AS min_score, COUNT(a.id) AS participants
     FROM quiz_answers a JOIN quizzes q ON q.id = a.quiz_id
     WHERE DATE_FORMAT(a.submitted_at,'%Y-%m') = '$filterMonth'
     GROUP BY q.id ORDER BY q.created_at DESC LIMIT 10"
)->fetchAll();

// ── CSV Export Handler ───────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Build a full attendance export for the month
    $exportMonth = $filterMonth;
    $exportData = db()->query(
        "SELECT u.name AS nama_siswa, u.username, c.name AS kelas,
                COUNT(r.id) AS total_hadir,
                MAX(t.valid_date) AS terakhir_hadir
         FROM users u
         LEFT JOIN classes c ON c.id = u.class_id
         LEFT JOIN attendance_records r ON r.student_id = u.id
         LEFT JOIN attendance_tokens t ON t.id = r.token_id AND DATE_FORMAT(t.valid_date,'%Y-%m') = '$exportMonth'
         WHERE u.role = 'siswa'
         GROUP BY u.id ORDER BY c.name, u.name"
    )->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="laporan_kehadiran_' . $exportMonth . '.csv"');
    header('Pragma: no-cache');
    // BOM for Excel compatibility
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Nama Siswa','Username','Kelas','Total Hadir (hari)','Terakhir Hadir'], ';');
    foreach ($exportData as $row) {
        fputcsv($out, [$row['nama_siswa'],$row['username'],$row['kelas'] ?? '-',$row['total_hadir'],$row['terakhir_hadir'] ?? '-'], ';');
    }
    fclose($out); exit;
}

$classes = db()->query("SELECT * FROM classes ORDER BY name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<!-- Filter -->
<div class="card mb-3">
  <form method="GET" style="display:flex;gap:.8rem;align-items:center;flex-wrap:wrap">
    <div class="flex flex-center gap-1">
      <i class="fas fa-calendar text-accent"></i>
      <label class="form-label" style="margin:0">Bulan:</label>
    </div>
    <input type="month" name="month" class="form-control" value="<?= $filterMonth ?>" style="width:160px">
    <select name="class_id" class="form-control" style="width:170px">
      <option value="">Semua Kelas</option>
      <?php foreach ($classes as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $filterClass==$c['id']?'selected':'' ?>><?= clean($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
    <a href="?month=<?= $filterMonth ?>&class_id=<?= $filterClass ?>&export=csv" class="btn btn-success"><i class="fas fa-file-csv"></i> Export CSV</a>
    <a href="javascript:window.print()" class="btn btn-ghost"><i class="fas fa-print"></i> Cetak</a>
  </form>
</div>

<!-- Summary Stat Row -->
<div class="stat-grid mb-3">
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-check"></i></div><div><div class="stat-value"><?= $jMap['approved'] ?? 0 ?></div><div class="stat-label">Jurnal Disetujui</div></div></div>
  <div class="stat-card"><div class="stat-icon yellow"><i class="fas fa-clock"></i></div><div><div class="stat-value"><?= $jMap['pending'] ?? 0 ?></div><div class="stat-label">Jurnal Pending</div></div></div>
  <div class="stat-card"><div class="stat-icon red"><i class="fas fa-redo"></i></div><div><div class="stat-value"><?= $jMap['revision'] ?? 0 ?></div><div class="stat-label">Jurnal Revisi</div></div></div>
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-clipboard-list"></i></div><div><div class="stat-value"><?= count($quizScores) ?></div><div class="stat-label">Ulangan Bulan Ini</div></div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem">
  <!-- Kehadiran Per Kelas -->
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-chalkboard text-accent"></i> Kehadiran Per Kelas</h3></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Kelas</th><th>Total Siswa</th><th>Pernah Hadir</th><th>%</th></tr></thead>
        <tbody>
          <?php foreach ($classStats as $cs): $pct = $cs['total_siswa'] > 0 ? round($cs['total_hadir']*100/$cs['total_siswa']) : 0; ?>
          <tr>
            <td><strong><?= clean($cs['class_name']) ?></strong></td>
            <td><?= $cs['total_siswa'] ?></td>
            <td><?= $cs['total_hadir'] ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:.5rem">
                <div style="flex:1;height:6px;background:var(--border);border-radius:3px">
                  <div style="width:<?= $pct ?>%;height:100%;background:var(--success);border-radius:3px"></div>
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

  <!-- Top Absensi -->
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-user-times text-accent"></i> Kehadiran Terendah</h3></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Siswa</th><th>Kelas</th><th>Hari Hadir</th></tr></thead>
        <tbody>
          <?php foreach ($absentees as $a): ?>
          <tr>
            <td><strong><?= clean($a['name']) ?></strong></td>
            <td><?= $a['class_name'] ? clean($a['class_name']) : '—' ?></td>
            <td><span class="badge <?= $a['hadir_count'] < 10 ? 'badge-danger' : 'badge-warning' ?>"><?= $a['hadir_count'] ?> hari</span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Quiz Scores -->
<div class="card">
  <div class="card-header"><h3><i class="fas fa-chart-bar text-accent"></i> Rata-rata Nilai Ulangan</h3></div>
  <?php if ($quizScores): ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Judul Ulangan</th><th>Peserta</th><th>Rata-rata</th><th>Tertinggi</th><th>Terendah</th></tr></thead>
      <tbody>
        <?php foreach ($quizScores as $qs): ?>
        <tr>
          <td><strong><?= clean($qs['title']) ?></strong></td>
          <td><?= $qs['participants'] ?> siswa</td>
          <td>
            <span style="font-size:1.1rem;font-weight:700;color:<?= $qs['avg_score']>=70?'var(--success)':'var(--warning)' ?>"><?= round($qs['avg_score'],1) ?></span>
          </td>
          <td><span class="badge badge-success"><?= round($qs['max_score'],1) ?></span></td>
          <td><span class="badge badge-danger"><?= round($qs['min_score'],1) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="empty-state"><i class="fas fa-chart-bar"></i>Belum ada data ulangan bulan ini.</div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
