<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('siswa');

$base      = '/TUGASPAKDANIL/ABSENSITALENTA';
$studentId = currentUser()['id'];
$classId   = currentUser()['class_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quiz_id'])) {
    $quizId  = (int)$_POST['quiz_id'];
    $answers = $_POST['answers'] ?? [];

    $qStmt = db()->prepare("SELECT id, correct_answer FROM quiz_questions WHERE quiz_id = ?");
    $qStmt->execute([$quizId]);
    $questions = $qStmt->fetchAll();

    $correct = 0;
    $total   = count($questions);
    foreach ($questions as $q) {
        if (isset($answers[$q['id']]) && $answers[$q['id']] === $q['correct_answer']) {
            $correct++;
        }
    }
    $score = $total > 0 ? round(($correct / $total) * 100, 2) : 0;

    db()->prepare(
        "INSERT INTO quiz_answers (quiz_id, student_id, answers, score)
         VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE answers=VALUES(answers), score=VALUES(score), submitted_at=NOW()"
    )->execute([$quizId, $studentId, json_encode($answers), $score]);

    db()->prepare("UPDATE quiz_access SET is_used=1 WHERE quiz_id=? AND student_id=?")
       ->execute([$quizId, $studentId]);

    setFlash('success', "Ulangan berhasil dikumpulkan! Skor Anda: <strong>{$score}</strong>");
    header("Location: $base/siswa/quiz.php"); exit;
}

$viewQuizId = (int)($_GET['quiz_id'] ?? 0);
$quizData   = null;
$questions  = [];
if ($viewQuizId) {
    $qs = db()->prepare("SELECT * FROM quizzes WHERE id = ? AND is_active = 1");
    $qs->execute([$viewQuizId]);
    $quizData = $qs->fetch();
    if ($quizData) {
        $qsQ = db()->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ?");
        $qsQ->execute([$viewQuizId]);
        $questions = $qsQ->fetchAll();
    }
}

$availableQuizzes = db()->query(
    "SELECT q.*, (SELECT score FROM quiz_answers WHERE quiz_id=q.id AND student_id=$studentId) AS my_score,
            (SELECT id FROM quiz_access WHERE quiz_id=q.id AND student_id=$studentId AND is_used=0) AS has_grant
     FROM quizzes q
     WHERE q.is_active = 1 AND (q.class_id IS NULL OR q.class_id = " . (int)$classId . ")
     ORDER BY q.start_time DESC"
)->fetchAll();

$pageTitle  = 'Ulangan';
$activePage = 'quiz';
include __DIR__ . '/../includes/header.php';
?>

<?php if ($viewQuizId && $quizData && $questions): ?>
<!-- Quiz Taking View -->
<div style="max-width:760px;margin:0 auto">
  <div class="card mb-3" style="background:linear-gradient(135deg,var(--card-bg),var(--card2-bg))">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem">
      <div>
        <h2 style="font-size:1.3rem;font-weight:800"><?= clean($quizData['title']) ?></h2>
        <p class="text-muted" style="font-size:.85rem"><?= clean($quizData['description']) ?></p>
      </div>
      <div style="text-align:right">
        <div style="font-size:.8rem;color:var(--text-muted)">Selesai sebelum</div>
        <div style="font-weight:700;color:var(--warning)"><?= formatDateTime($quizData['end_time']) ?></div>
        <div id="countdown" style="font-size:1.1rem;font-weight:800;color:var(--danger);margin-top:.2rem"></div>
      </div>
    </div>
  </div>

  <form method="POST">
    <input type="hidden" name="quiz_id" value="<?= $quizData['id'] ?>">

    <?php foreach ($questions as $i => $q): ?>
    <div class="card mb-2">
      <div style="font-weight:700;margin-bottom:1rem;font-size:.95rem">
        <span style="background:var(--accent);color:#fff;border-radius:50%;width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center;font-size:.75rem;margin-right:.5rem"><?= $i+1 ?></span>
        <?= clean($q['question']) ?>
      </div>
      <?php foreach (['a','b','c','d'] as $opt): ?>
      <label style="display:flex;align-items:center;gap:.8rem;padding:.7rem;border-radius:var(--radius);cursor:pointer;border:1.5px solid transparent;margin-bottom:.4rem;transition:all .2s"
             onmouseover="this.style.borderColor='var(--border)'" onmouseout="this.style.borderColor='transparent'">
        <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $opt ?>" required
               style="accent-color:var(--accent);width:16px;height:16px">
        <span><strong style="color:var(--accent)"><?= strtoupper($opt) ?>.</strong> <?= clean($q['option_'.$opt]) ?></span>
      </label>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <div style="display:flex;gap:.8rem;justify-content:space-between;margin-top:1.5rem">
      <a href="<?= $base ?>/siswa/quiz.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Kembali</a>
      <button type="submit" class="btn btn-accent" onclick="return confirm('Yakin ingin mengumpulkan jawaban?')">
        <i class="fas fa-paper-plane"></i> Kumpulkan Jawaban
      </button>
    </div>
  </form>
</div>

<script>
const endTime = new Date('<?= $quizData['end_time'] ?>').getTime();
function updateCountdown(){
  const rem = endTime - Date.now();
  if(rem<=0){document.getElementById('countdown').textContent='Waktu Habis!';return;}
  const h=Math.floor(rem/3600000);
  const m=Math.floor((rem%3600000)/60000);
  const s=Math.floor((rem%60000)/1000);
  document.getElementById('countdown').textContent=`${h.toString().padStart(2,'0')}:${m.toString().padStart(2,'0')}:${s.toString().padStart(2,'0')}`;
}
updateCountdown();
setInterval(updateCountdown,1000);
</script>

<?php else: ?>
<!-- Quiz List View -->
<div class="card">
  <div class="card-header">
    <h3><i class="fas fa-clipboard-list text-accent"></i> Ulangan Saya</h3>
  </div>
  <?php if ($availableQuizzes): ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Judul</th><th>Tipe</th><th>Mulai</th><th>Selesai</th><th>Nilai</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($availableQuizzes as $q):
          $now = time();
          $start = strtotime($q['start_time']);
          $end   = strtotime($q['end_time']);
          $canTake = ($now >= $start && $now <= $end && $q['my_score'] === null) || $q['has_grant'];
        ?>
        <tr>
          <td><strong><?= clean($q['title']) ?></strong><br><small class="text-muted"><?= clean($q['description']) ?></small></td>
          <td><span class="badge badge-info"><?= ucfirst($q['type']) ?></span></td>
          <td style="font-size:.8rem"><?= formatDateTime($q['start_time']) ?></td>
          <td style="font-size:.8rem"><?= formatDateTime($q['end_time']) ?></td>
          <td>
            <?php if ($q['my_score'] !== null): ?>
            <span style="font-size:1.1rem;font-weight:800;color:<?= $q['my_score']>=70?'var(--success)':'var(--danger)' ?>"><?= $q['my_score'] ?></span>
            <?php else: ?>
            <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($q['my_score'] !== null && !$q['has_grant']): ?>
              <span class="badge badge-success"><i class="fas fa-check"></i> Selesai</span>
            <?php elseif ($canTake): ?>
              <a href="?quiz_id=<?= $q['id'] ?>" class="btn btn-accent btn-sm"><i class="fas fa-play"></i> Mulai</a>
            <?php elseif ($now < $start): ?>
              <span class="badge badge-warning">Belum Mulai</span>
            <?php else: ?>
              <span class="badge badge-danger">Berakhir</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="empty-state"><i class="fas fa-clipboard-list"></i>Belum ada ulangan yang tersedia.</div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>