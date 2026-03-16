<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/functions.php';
requireRole('admin');

$pageTitle = 'Kelola Jadwal Pelajaran';
$activePage = 'schedules';
$base = '/TUGASPAKDANIL/ABSENSITALENTA';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? null;
        $class_id = $_POST['class_id'];
        $teacher_id = $_POST['teacher_id'];
        $subject = clean($_POST['subject']);
        $day = $_POST['day_of_week'];
        $start = $_POST['start_time'];
        $end = $_POST['end_time'];

        if ($action === 'add') {
            $pdo->prepare("INSERT INTO schedules (class_id, teacher_id, subject, day_of_week, start_time, end_time) VALUES (?,?,?,?,?,?)")
                ->execute([$class_id, $teacher_id, $subject, $day, $start, $end]);
            setFlash('success', 'Jadwal berhasil ditambahkan.');
        } else {
            $pdo->prepare("UPDATE schedules SET class_id=?, teacher_id=?, subject=?, day_of_week=?, start_time=?, end_time=? WHERE id=?")
                ->execute([$class_id, $teacher_id, $subject, $day, $start, $end, $id]);
            setFlash('success', 'Jadwal berhasil diperbarui.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM schedules WHERE id=?")->execute([$_POST['id']]);
        setFlash('success', 'Jadwal berhasil dihapus.');
    }
    header('Location: ' . $base . '/admin/schedules.php');
    exit;
}

$classes = $pdo->query("SELECT * FROM classes ORDER BY name")->fetchAll();
$teachers = $pdo->query("SELECT * FROM users WHERE role IN ('guru','instruktur') ORDER BY name")->fetchAll();

$filterClass = $_GET['class_id'] ?? '';
$sql = "SELECT s.*, c.name as class_name, u.name as teacher_name 
        FROM schedules s
        JOIN classes c ON c.id = s.class_id
        JOIN users u ON u.id = s.teacher_id ";
$params = [];
if ($filterClass) {
    $sql .= " WHERE s.class_id = ? ";
    $params[] = $filterClass;
}
$sql .= " ORDER BY s.day_of_week ASC, s.start_time ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$schedules = $stmt->fetchAll();

$days = [1=>'Senin', 2=>'Selasa', 3=>'Rabu', 4=>'Kamis', 5=>'Jumat', 6=>'Sabtu', 7=>'Minggu'];

include $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/header.php';
?>

<div class="card">
  <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
    <h3><i class="fas fa-calendar-alt text-primary"></i> Jadwal Pelajaran</h3>
    
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <form method="GET" style="display:flex; gap:.5rem;">
            <select name="class_id" class="form-control" onchange="this.form.submit()">
                <option value="">Semua Kelas</option>
                <?php foreach ($classes as $cls): ?>
                <option value="<?= $cls['id'] ?>" <?= $filterClass == $cls['id'] ? 'selected' : '' ?>><?= clean($cls['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <button class="btn btn-primary" onclick="openModal('modalAdd')"><i class="fas fa-plus"></i> Tambah Jadwal</button>
    </div>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Hari</th>
          <th>Jam</th>
          <th>Mata Pelajaran</th>
          <th>Kelas</th>
          <th>Pengajar</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($schedules): foreach ($schedules as $s): ?>
        <tr>
          <td><span class="badge badge-info"><?= $days[$s['day_of_week']] ?></span></td>
          <td><?= date('H:i', strtotime($s['start_time'])) ?> - <?= date('H:i', strtotime($s['end_time'])) ?></td>
          <td><strong><?= clean($s['subject']) ?></strong></td>
          <td><?= clean($s['class_name']) ?></td>
          <td><?= clean($s['teacher_name']) ?></td>
          <td>
            <button class="btn btn-warning btn-sm" onclick='editData(<?= json_encode($s) ?>)'><i class="fas fa-edit"></i></button>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $s['id'] ?>">
              <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete(this.form, 'Jadwal <?= clean($s['subject']) ?>')">
                <i class="fas fa-trash"></i>
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="6" class="text-center text-muted" style="padding:2rem;">Belum ada jadwal.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Tambah -->
<div class="modal-overlay" id="modalAdd">
  <div class="modal-box">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
      <h3>Tambah Jadwal</h3>
      <button class="btn-ghost" onclick="closeModal('modalAdd')" style="padding:.2rem .5rem"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      
      <div class="form-group mb-3">
        <label class="form-label">Hari</label>
        <select name="day_of_week" class="form-control" required>
            <?php foreach ($days as $k => $v): ?>
            <option value="<?= $k ?>"><?= $v ?></option>
            <?php endforeach; ?>
        </select>
      </div>

      <div style="display:flex; gap:1rem; margin-bottom:1rem;">
          <div class="form-group" style="flex:1">
            <label class="form-label">Jam Mulai</label>
            <input type="time" name="start_time" class="form-control" required>
          </div>
          <div class="form-group" style="flex:1">
            <label class="form-label">Jam Selesai</label>
            <input type="time" name="end_time" class="form-control" required>
          </div>
      </div>

      <div class="form-group mb-3">
        <label class="form-label">Mata Pelajaran</label>
        <input type="text" name="subject" class="form-control" required>
      </div>

      <div class="form-group mb-3">
        <label class="form-label">Kelas</label>
        <select name="class_id" class="form-control" required>
            <option value="">Pilih Kelas...</option>
            <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>"><?= clean($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group mb-4">
        <label class="form-label">Pengajar</label>
        <select name="teacher_id" class="form-control" required>
            <option value="">Pilih Pengajar...</option>
            <?php foreach ($teachers as $t): ?>
            <option value="<?= $t['id'] ?>"><?= clean($t['name']) ?></option>
            <?php endforeach; ?>
        </select>
      </div>
      
      <div style="display:flex;justify-content:flex-end;gap:.5rem">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modalAdd')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit -->
<div class="modal-overlay" id="modalEdit">
  <div class="modal-box">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
      <h3>Edit Jadwal</h3>
      <button class="btn-ghost" onclick="closeModal('modalEdit')" style="padding:.2rem .5rem"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit_id">
      
      <div class="form-group mb-3">
        <label class="form-label">Hari</label>
        <select name="day_of_week" id="edit_day" class="form-control" required>
            <?php foreach ($days as $k => $v): ?>
            <option value="<?= $k ?>"><?= $v ?></option>
            <?php endforeach; ?>
        </select>
      </div>

      <div style="display:flex; gap:1rem; margin-bottom:1rem;">
          <div class="form-group" style="flex:1">
            <label class="form-label">Jam Mulai</label>
            <input type="time" name="start_time" id="edit_start" class="form-control" required>
          </div>
          <div class="form-group" style="flex:1">
            <label class="form-label">Jam Selesai</label>
            <input type="time" name="end_time" id="edit_end" class="form-control" required>
          </div>
      </div>

      <div class="form-group mb-3">
        <label class="form-label">Mata Pelajaran</label>
        <input type="text" name="subject" id="edit_subject" class="form-control" required>
      </div>

      <div class="form-group mb-3">
        <label class="form-label">Kelas</label>
        <select name="class_id" id="edit_class" class="form-control" required>
            <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>"><?= clean($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group mb-4">
        <label class="form-label">Pengajar</label>
        <select name="teacher_id" id="edit_teacher" class="form-control" required>
            <?php foreach ($teachers as $t): ?>
            <option value="<?= $t['id'] ?>"><?= clean($t['name']) ?></option>
            <?php endforeach; ?>
        </select>
      </div>
      
      <div style="display:flex;justify-content:flex-end;gap:.5rem">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modalEdit')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<script>
function editData(data) {
  document.getElementById('edit_id').value = data.id;
  document.getElementById('edit_day').value = data.day_of_week;
  document.getElementById('edit_start').value = data.start_time;
  document.getElementById('edit_end').value = data.end_time;
  document.getElementById('edit_subject').value = data.subject;
  document.getElementById('edit_class').value = data.class_id;
  document.getElementById('edit_teacher').value = data.teacher_id;
  openModal('modalEdit');
}
</script>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/footer.php'; ?>
