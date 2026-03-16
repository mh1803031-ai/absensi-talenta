<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/functions.php';
requireRole('admin','guru','instruktur');

$pageTitle  = 'Kelola Ulangan';
$activePage = 'quizzes';
$base = '/TUGASPAKDANIL/ABSENSITALENTA';
$role = currentRole();
$rolePath = $role === 'admin' ? 'admin' : ($role === 'guru' ? 'guru' : 'instruktur');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $stmt = db()->prepare(
            "INSERT INTO quizzes (title,description,type,created_by,class_id,start_time,end_time,is_active)
             VALUES (?,?,?,?,?,?,?,1)"
        );
        $stmt->execute([
            clean($_POST['title']),
            clean($_POST['description']),
            $_POST['type'],
            currentUser()['id'],
            $_POST['class_id'] ?: null,
            $_POST['start_time'],
            $_POST['end_time']
        ]);
        $qid = db()->lastInsertId();
        // Add questions
        $questions = $_POST['questions'] ?? [];
        foreach ($questions as $q) {
            if (empty($q['question'])) continue;
            db()->prepare(
                "INSERT INTO quiz_questions (quiz_id,question,option_a,option_b,option_c,option_d,correct_answer)
                 VALUES (?,?,?,?,?,?,?)"
            )->execute([$qid, $q['question'], $q['a'], $q['b'], $q['c'], $q['d'], $q['answer']]);
        }
        setFlash('success', "Ulangan '{$_POST['title']}' berhasil dibuat.");
    }

    if ($action === 'toggle') {
        $qid = (int)$_POST['quiz_id'];
        db()->prepare("UPDATE quizzes SET is_active = NOT is_active WHERE id = ?")->execute([$qid]);
        setFlash('success', 'Status ulangan berhasil diubah.');
    }

    if ($action === 'delete') {
        db()->prepare("DELETE FROM quizzes WHERE id = ?")->execute([(int)$_POST['quiz_id']]);
        setFlash('success', 'Ulangan berhasil dihapus.');
    }

    if ($action === 'grant_access') {
        $qid = (int)$_POST['quiz_id'];
        $sid = (int)$_POST['student_id'];
        // Remove old answer so they can retake
        db()->prepare("DELETE FROM quiz_answers WHERE quiz_id=? AND student_id=?")->execute([$qid,$sid]);
        // Grant access record
        $stmt = db()->prepare("SELECT id FROM quiz_access WHERE quiz_id=? AND student_id=?");
        $stmt->execute([$qid,$sid]);
        if (!$stmt->fetch()) {
            db()->prepare("INSERT INTO quiz_access (quiz_id,student_id,granted_by) VALUES (?,?,?)")
               ->execute([$qid,$sid,currentUser()['id']]);
        } else {
            db()->prepare("UPDATE quiz_access SET is_used=0,granted_at=NOW() WHERE quiz_id=? AND student_id=?")
               ->execute([$qid,$sid]);
        }
        setFlash('success','Akses ulangan berhasil diberikan ulang ke siswa.');
    }

    header("Location: $base/$rolePath/quizzes.php"); exit;
}

$quizzes = db()->query(
    "SELECT q.*, u.name AS creator_name, c.name AS class_name,
            (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) AS q_count,
            (SELECT COUNT(*) FROM quiz_answers WHERE quiz_id = q.id) AS a_count
     FROM quizzes q JOIN users u ON u.id = q.created_by
     LEFT JOIN classes c ON c.id = q.class_id
     ORDER BY q.created_at DESC"
)->fetchAll();

$classes  = db()->query("SELECT * FROM classes ORDER BY name")->fetchAll();
$students = db()->query("SELECT id, name, username FROM users WHERE role='siswa' ORDER BY name")->fetchAll();

include $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/header.php';
?>

<div class="card mb-3">
  <div class="card-header">
    <h3><i class="fas fa-clipboard-list text-accent"></i> Daftar Ulangan</h3>
    <button class="btn btn-accent" onclick="openModal('modalCreate')"><i class="fas fa-plus"></i> Buat Ulangan</button>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Judul</th><th>Tipe</th><th>Kelas</th><th>Soal</th><th>Peserta</th><th>Waktu</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php if ($quizzes): foreach ($quizzes as $q): ?>
        <tr>
          <td><strong><?= clean($q['title']) ?></strong><br><small class="text-muted">Oleh <?= clean($q['creator_name']) ?></small></td>
          <td><span class="badge badge-<?= $q['type']==='harian'?'info':'purple' ?>"><?= ucfirst($q['type']) ?></span></td>
          <td><?= $q['class_name'] ? clean($q['class_name']) : 'Semua' ?></td>
          <td><span class="badge badge-primary"><?= $q['q_count'] ?> soal</span></td>
          <td><span class="badge badge-success"><?= $q['a_count'] ?> siswa</span></td>
          <td style="font-size:.78rem;color:var(--text-muted)"><?= formatDateTime($q['start_time']) ?>&nbsp;–&nbsp;<?= date('d/m H:i',strtotime($q['end_time'])) ?></td>
          <td><?= $q['is_active'] ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-danger">Nonaktif</span>' ?></td>
          <td>
            <div style="display:flex;gap:.3rem;flex-wrap:wrap">
              <a href="<?= $base ?>/admin/quiz_results.php?id=<?= $q['id'] ?>" class="btn btn-primary btn-sm" title="Lihat Hasil"><i class="fas fa-chart-bar"></i></a>
              <button class="btn btn-info btn-sm" onclick="openGrantModal(<?= $q['id'] ?>)" title="Beri Akses Ulang"><i class="fas fa-user-check"></i></button>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="quiz_id" value="<?= $q['id'] ?>">
                <button class="btn btn-warning btn-sm"><i class="fas fa-power-off"></i></button>
              </form>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="quiz_id" value="<?= $q['id'] ?>">
                <button class="btn btn-danger btn-sm" data-confirm="Hapus ulangan '<?= clean($q['title']) ?>'?"><i class="fas fa-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="8" class="text-center text-muted" style="padding:2rem">Belum ada ulangan.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Create Quiz -->
<div class="modal-overlay" id="modalCreate" style="display:none">
  <div class="modal-box" style="max-width:700px;max-height:90vh;overflow-y:auto">
    <div class="modal-icon"><i class="fas fa-clipboard-list"></i></div>
    <div class="modal-title">Buat Ulangan Baru</div>
    <form method="POST" id="quizForm">
      <input type="hidden" name="action" value="create">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Judul Ulangan</label>
          <input type="text" name="title" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Tipe</label>
          <select name="type" class="form-control">
            <option value="harian">Harian</option>
            <option value="bulanan">Bulanan</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Deskripsi</label>
        <textarea name="description" class="form-control" rows="2"></textarea>
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Kelas</label>
          <select name="class_id" class="form-control">
            <option value="">Semua Kelas</option>
            <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>"><?= clean($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Waktu Mulai</label>
          <input type="datetime-local" name="start_time" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Waktu Selesai</label>
          <input type="datetime-local" name="end_time" class="form-control" required>
        </div>
      </div>

      <div class="divider"></div>
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
        <strong style="font-size:.9rem">Daftar Soal</strong>
        <button type="button" class="btn btn-accent btn-sm" onclick="addQuestion()"><i class="fas fa-plus"></i> Tambah Soal</button>
      </div>
      <div id="questionsContainer"></div>

      <div style="display:flex;gap:.8rem;justify-content:flex-end;margin-top:1rem">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modalCreate')">Batal</button>
        <button type="submit" class="btn btn-accent"><i class="fas fa-save"></i> Simpan Ulangan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Grant Access -->
<div class="modal-overlay" id="modalGrant" style="display:none">
  <div class="modal-box">
    <div class="modal-icon" style="background:rgba(69,123,157,.15);color:#7ec8e3"><i class="fas fa-user-check"></i></div>
    <div class="modal-title">Beri Akses Ulang</div>
    <div class="modal-subtitle">Aktifkan ulangan untuk siswa tertentu yang belum mengikuti.</div>
    <form method="POST">
      <input type="hidden" name="action" value="grant_access">
      <input type="hidden" name="quiz_id" id="grantQuizId">
      <div class="form-group">
        <label class="form-label">Pilih Siswa</label>
        <select name="student_id" class="form-control" required>
          <option value="">— Pilih siswa —</option>
          <?php foreach ($students as $s): ?>
          <option value="<?= $s['id'] ?>"><?= clean($s['name']) ?> (<?= clean($s['username']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;gap:.8rem;justify-content:flex-end">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modalGrant')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-unlock"></i> Berikan Akses</button>
      </div>
    </form>
  </div>
</div>

<script>
let qIdx = 0;
function addQuestion(){
  const i = qIdx++;
  const html = `
  <div class="card mb-2" style="background:var(--card2-bg);padding:1rem">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem">
      <strong style="font-size:.85rem">Soal ${i+1}</strong>
      <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.card').remove()"><i class="fas fa-times"></i></button>
    </div>
    <div class="form-group"><label class="form-label">Pertanyaan</label>
      <textarea name="questions[${i}][question]" class="form-control" rows="2" required></textarea></div>
    <div class="form-grid">
      <div class="form-group"><label class="form-label">A</label><input type="text" name="questions[${i}][a]" class="form-control" required></div>
      <div class="form-group"><label class="form-label">B</label><input type="text" name="questions[${i}][b]" class="form-control" required></div>
      <div class="form-group"><label class="form-label">C</label><input type="text" name="questions[${i}][c]" class="form-control" required></div>
      <div class="form-group"><label class="form-label">D</label><input type="text" name="questions[${i}][d]" class="form-control" required></div>
    </div>
    <div class="form-group"><label class="form-label">Jawaban Benar</label>
      <select name="questions[${i}][answer]" class="form-control">
        <option value="a">A</option><option value="b">B</option><option value="c">C</option><option value="d">D</option>
      </select>
    </div>
  </div>`;
  document.getElementById('questionsContainer').insertAdjacentHTML('beforeend',html);
}
function openGrantModal(qid){
  document.getElementById('grantQuizId').value = qid;
  openModal('modalGrant');
}
// Add first question by default
addQuestion();
</script>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/footer.php'; ?>
