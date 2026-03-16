<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin','guru');

$pageTitle  = 'Kelola Kelas';
$activePage = 'classes';
$base = defined('APP_BASE_PATH') ? APP_BASE_PATH : '/TUGASPAKDANIL/ABSENSITALENTA';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $name = clean($_POST['name']);
        $desc = clean($_POST['description']);
        db()->prepare("INSERT INTO classes (name,description) VALUES (?,?)")->execute([$name,$desc]);
        setFlash('success',"Kelas '$name' berhasil dibuat.");
    } elseif ($action === 'edit') {
        db()->prepare("UPDATE classes SET name=?,description=? WHERE id=?")
           ->execute([clean($_POST['name']),clean($_POST['description']),(int)$_POST['class_id']]);
        setFlash('success','Kelas berhasil diperbarui.');
    } elseif ($action === 'delete') {
        db()->prepare("DELETE FROM classes WHERE id=?")->execute([(int)$_POST['class_id']]);
        setFlash('success','Kelas berhasil dihapus.');
    }
    header("Location: $base/admin/manage_classes.php"); exit;
}

$classes = db()->query(
    "SELECT c.*, COUNT(u.id) AS student_count
     FROM classes c LEFT JOIN users u ON u.class_id = c.id AND u.role = 'siswa'
     GROUP BY c.id ORDER BY c.name"
)->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <div class="card-header">
    <h3><i class="fas fa-chalkboard text-accent"></i> Daftar Kelas</h3>
    <button class="btn btn-accent" onclick="openModal('modalCreate')"><i class="fas fa-plus"></i> Tambah Kelas</button>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Nama Kelas</th><th>Deskripsi</th><th>Jumlah Siswa</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php if ($classes): foreach ($classes as $i=>$c): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><strong><?= clean($c['name']) ?></strong></td>
          <td><?= $c['description'] ? clean($c['description']) : '<span class="text-muted">—</span>' ?></td>
          <td><span class="badge badge-primary"><?= $c['student_count'] ?> siswa</span></td>
          <td>
            <div style="display:flex;gap:.4rem">
              <button class="btn btn-info btn-sm" onclick="openEditModal(<?= htmlspecialchars(json_encode($c)) ?>)"><i class="fas fa-edit"></i></button>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="class_id" value="<?= $c['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm"
                  data-confirm="Hapus kelas '<?= clean($c['name']) ?>'? Siswa di kelas ini tidak akan terhapus.">
                  <i class="fas fa-trash"></i>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="5" class="text-center text-muted" style="padding:2rem">Belum ada kelas.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Create -->
<div class="modal-overlay" id="modalCreate" style="display:none">
  <div class="modal-box">
    <div class="modal-icon"><i class="fas fa-chalkboard"></i></div>
    <div class="modal-title">Tambah Kelas Baru</div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="form-group"><label class="form-label">Nama Kelas</label><input type="text" name="name" class="form-control" required></div>
      <div class="form-group"><label class="form-label">Deskripsi</label><textarea name="description" class="form-control" rows="3"></textarea></div>
      <div style="display:flex;gap:.8rem;justify-content:flex-end">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modalCreate')">Batal</button>
        <button type="submit" class="btn btn-accent"><i class="fas fa-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit -->
<div class="modal-overlay" id="modalEdit" style="display:none">
  <div class="modal-box">
    <div class="modal-icon" style="background:rgba(69,123,157,.15);color:#7ec8e3"><i class="fas fa-edit"></i></div>
    <div class="modal-title">Edit Kelas</div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="class_id" id="editId">
      <div class="form-group"><label class="form-label">Nama Kelas</label><input type="text" name="name" id="editName" class="form-control" required></div>
      <div class="form-group"><label class="form-label">Deskripsi</label><textarea name="description" id="editDesc" class="form-control" rows="3"></textarea></div>
      <div style="display:flex;gap:.8rem;justify-content:flex-end">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modalEdit')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Perbarui</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(c){
  document.getElementById('editId').value=c.id;
  document.getElementById('editName').value=c.name;
  document.getElementById('editDesc').value=c.description||'';
  openModal('modalEdit');
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>