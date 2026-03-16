<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin', 'guru', 'instruktur');

$quizId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$quizId) {
    header("Location: " . '/TUGASPAKDANIL/ABSENSITALENTA/admin/quizzes.php');
    exit;
}

// Get Quiz details
$stmt = db()->prepare("SELECT q.*, c.name AS class_name FROM quizzes q LEFT JOIN classes c ON c.id = q.class_id WHERE q.id = ?");
$stmt->execute([$quizId]);
$quiz = $stmt->fetch();

if (!$quiz) {
    die("Ulangan tidak ditemukan.");
}

$pageTitle = 'Hasil Ulangan: ' . clean($quiz['title']);
$activePage = 'quizzes';

// Get Questions mapping ID => expected correctly
$stmt = db()->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY id");
$stmt->execute([$quizId]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalQuestions = count($questions);

// Fetch answers & results
$stmt = db()->prepare("
    SELECT qa.*, u.name AS student_name, u.username
    FROM quiz_answers qa
    JOIN users u ON u.id = qa.student_id
    WHERE qa.quiz_id = ?
    ORDER BY qa.score DESC, qa.submitted_at ASC
");
$stmt->execute([$quizId]);
$answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalParticipants = count($answers);

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="hasil_ulangan_' . substr(preg_replace('/[^a-zA-Z0-9_-]/', '_', $quiz['title']),0,20) . '_' . date('Ymd') . '.csv"');
    header('Pragma: no-cache');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Nama Siswa', 'Username', 'Kelas', 'Nilai', 'Waktu Pengerjaan'], ';');
    foreach ($answers as $a) {
        fputcsv($out, [
            $a['student_name'], 
            $a['username'], 
            $quiz['class_name'] ?? 'Semua Kelas', 
            number_format($a['score'], 2), 
            $a['submitted_at']
        ], ';');
    }
    fclose($out); exit;
}

$avgScore = 0;
$highestScore = 0;
$fastestTime = null;

if ($totalParticipants > 0) {
    $sum = 0;
    foreach ($answers as $a) {
        $sum += $a['score'];
        if ($a['score'] > $highestScore) $highestScore = $a['score'];
    }
    $avgScore = round($sum / $totalParticipants, 2);
}

include __DIR__ . '/../includes/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem">
  <h2 style="margin:0"><i class="fas fa-chart-bar text-primary"></i> Laporan Hasil Ulangan</h2>
  <div style="display:flex; gap:.8rem;">
    <a href="?id=<?= $quizId ?>&export=csv" class="btn btn-success"><i class="fas fa-file-csv"></i> Export CSV</a>
    <a href="/TUGASPAKDANIL/ABSENSITALENTA/admin/quizzes.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Kembali</a>
  </div>
</div>

<!-- INFO CARD -->
<div class="card mb-3">
  <div class="card-body">
    <h3 style="margin-bottom:.5rem"><?= clean($quiz['title']) ?></h3>
    <div style="display:flex; gap:1rem; flex-wrap:wrap; color:var(--text-muted); font-size:.9rem; margin-bottom:1rem">
      <span><i class="fas fa-users"></i> Kelas: <?= $quiz['class_name'] ? clean($quiz['class_name']) : 'Semua Kelas' ?></span>
      <span><i class="fas fa-clock"></i> Dimulai: <?= date('d M Y H:i', strtotime($quiz['start_time'])) ?></span>
      <span><i class="fas fa-file-alt"></i> Soal: <?= $totalQuestions ?></span>
    </div>
    
    <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <div class="stat-label">Siswa Mengerjakan</div>
                <div class="stat-value"><?= $totalParticipants ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fas fa-chart-line"></i></div>
            <div class="stat-info">
                <div class="stat-label">Rata-rata Kelas</div>
                <div class="stat-value"><?= $avgScore ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-trophy"></i></div>
            <div class="stat-info">
                <div class="stat-label">Nilai Tertinggi</div>
                <div class="stat-value"><?= number_format($highestScore, 2) ?></div>
            </div>
        </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3><i class="fas fa-list text-accent"></i> Daftar Peserta Ulangan</h3>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Peringkat</th>
          <th>Nama Siswa</th>
          <th>Username</th>
          <th>Waktu Pengerjaan</th>
          <th>Nilai</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($totalParticipants > 0): ?>
          <?php $rank = 1; foreach ($answers as $a): ?>
          <tr>
            <td><strong>#<?= $rank++ ?></strong></td>
            <td>
              <div style="display:flex;align-items:center;gap:.7rem">
                <div class="avatar" style="width:34px;height:34px;font-size:.75rem"><?= avatarInitials($a['student_name']) ?></div>
                <strong><?= clean($a['student_name']) ?></strong>
              </div>
            </td>
            <td><code><?= clean($a['username']) ?></code></td>
            <td style="font-size:.85rem;color:var(--text-muted)"><?= date('d/m/Y H:i:s', strtotime($a['submitted_at'])) ?></td>
            <td>
                <?php
                    $scoreClass = 'success';
                    if ($a['score'] < 50) $scoreClass = 'danger';
                    elseif ($a['score'] < 75) $scoreClass = 'warning';
                ?>
                <span class="badge badge-<?= $scoreClass ?>" style="font-size:1rem"><?= number_format($a['score'], 2) ?></span>
            </td>
            <td>
                <button class="btn btn-info btn-sm" onclick='showDetailModal(<?= json_encode($a["student_name"]) ?>, <?= $a["answers"] ?>)'><i class="fas fa-eye"></i> Detail</button>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="6" class="text-center text-muted" style="padding:2rem">Belum ada siswa yang mengerjakan ulangan ini.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Detail Jawaban -->
<div class="modal-overlay" id="modalDetail" style="display:none">
  <div class="modal-box" style="width: min(800px, 95vw); max-height: 90vh; overflow-y: auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.5rem;">
        <h3 style="margin:0;"><i class="fas fa-search text-primary"></i> Detail Jawaban</h3>
        <button type="button" class="btn btn-ghost" onclick="closeModal('modalDetail')" style="padding:.3rem .6rem"><i class="fas fa-times"></i></button>
    </div>
    
    <div style="margin-bottom: 1.5rem; padding: 1rem; background: var(--card2-bg); border-radius: var(--radius); border: 1px solid var(--border);">
        <strong id="detailStudentName" style="font-size: 1.1rem; display:block; margin-bottom: .3rem;">Nama Siswa</strong>
        <span class="text-muted" style="font-size: .85rem;">Membandingkan rincian jawaban siswa dengan kunci jawaban benar.</span>
    </div>

    <div id="detailContent"></div>

  </div>
</div>

<script>
// Pass initial questions data to Javascript
const quizQuestions = <?= json_encode($questions) ?>;

function showDetailModal(studentName, studentAnswers) {
    document.getElementById('detailStudentName').innerText = studentName;
    const container = document.getElementById('detailContent');
    container.innerHTML = '';

    if (!studentAnswers || Object.keys(studentAnswers).length === 0) {
        container.innerHTML = '<div class="alert alert-warning">Siswa belum/tidak menyimpan jawaban.</div>';
        openModal('modalDetail');
        return;
    }

    let html = '<div style="display:flex; flex-direction:column; gap:1rem;">';

    quizQuestions.forEach((q, index) => {
        const qId = q.id;
        const userAnswer = studentAnswers[qId] || null;
        const correct = q.correct_answer;
        const isCorrect = (userAnswer === correct);

        // Styling based on correctness
        const borderColor = isCorrect ? 'var(--success)' : 'var(--danger)';
        const bgColor = isCorrect ? 'rgba(42, 157, 143, 0.05)' : 'rgba(230, 57, 70, 0.05)';
        const iconHtml = isCorrect ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-times-circle text-danger"></i>';

        html += `
            <div style="border-left: 4px solid ${borderColor}; background: ${bgColor}; padding: 1rem; border-radius: 0 var(--radius) var(--radius) 0;">
                <div style="display:flex; gap: .8rem; margin-bottom: .5rem;">
                    <strong>Soal ${index + 1}.</strong>
                    <div style="flex: 1;">${q.question}</div>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:.5rem; margin-left:2.5rem; font-size:.85rem;">
                    <div style="padding:.5rem; background:var(--card-bg); border: 1px solid var(--border); border-radius:var(--radius);">
                        <strong class="text-muted">Jawaban Siswa:</strong> 
                        <span style="display:block; margin-top:.3rem; color: ${isCorrect ? 'var(--success)' : 'var(--danger)'}">
                            ${userAnswer ? `<b>${userAnswer.toUpperCase()}:</b> ${q['option_'+userAnswer]}` : '<i>Kosong</i>'}
                        </span>
                    </div>
                    <div style="padding:.5rem; background:var(--card-bg); border: 1px solid var(--border); border-radius:var(--radius);">
                        <strong class="text-muted">Kunci Jawaban:</strong> 
                        <span style="display:block; margin-top:.3rem; color: var(--success);">
                            <b>${correct.toUpperCase()}:</b> ${q['option_'+correct]}
                        </span>
                    </div>
                </div>
                <div style="margin-left:2.5rem; margin-top:.8rem;">
                    ${iconHtml} ${isCorrect ? '<span class="text-success">Benar</span>' : '<span class="text-danger">Salah</span>'}
                </div>
            </div>
        `;
    });

    html += '</div>';
    container.innerHTML = html;
    openModal('modalDetail');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
