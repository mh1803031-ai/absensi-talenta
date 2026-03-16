<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/functions.php';
requireRole('admin','guru');

$pageTitle  = 'Kelola Pengguna';
$activePage = 'users';
$base = defined('APP_BASE_PATH') ? APP_BASE_PATH : '/TUGASPAKDANIL/ABSENSITALENTA';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name     = clean($_POST['name']);
        $username = clean($_POST['username']);
        $password = $_POST['password'];
        $role     = $_POST['role'];
        $classId  = $_POST['class_id'] ?: null;

        // Admin can create all; guru cannot create admin/guru
        if (isGuru() && in_array($role, ['admin','guru'])) {
            setFlash('danger', 'Anda tidak memiliki izin untuk membuat akun Admin atau Guru.');
        } else {
            $check = db()->prepare("SELECT id FROM users WHERE username = ?");
            $check->execute([$username]);
            if ($check->fetch()) {
                setFlash('danger', 'Username sudah digunakan.');
            } else {
                $stmt = db()->prepare("INSERT INTO users (name,username,password,role,class_id) VALUES (?,?,?,?,?)");
                $stmt->execute([$name, $username, hashPassword($password), $role, $classId]);
                setFlash('success', "Akun '$name' berhasil dibuat.");
            }
        }
    }

    if ($action === 'toggle') {
        $uid = (int)$_POST['user_id'];
        db()->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?")->execute([$uid]);
        setFlash('success', 'Status akun berhasil diubah.');
    }

    if ($action === 'delete') {
        $uid = (int)$_POST['user_id'];
        // Don't delete self
        if ($uid == currentUser()['id']) {
            setFlash('danger', 'Tidak dapat menghapus akun sendiri!');
        } else {
            try {
                // Begin transaction to safely remove dependencies
                db()->beginTransaction();
                
                // 1. Delete quiz answers & access
                db()->prepare("DELETE FROM quiz_answers WHERE student_id = ?")->execute([$uid]);
                db()->prepare("DELETE FROM quiz_access WHERE student_id = ? OR granted_by = ?")->execute([$uid, $uid]);
                
                // 2. Clear journal dependencies
                // Note: user as reviewer is handled by ON DELETE SET NULL, but we must delete their own journals.
                // We should also delete media for their journals first.
                $journals = db()->prepare("SELECT id FROM journals WHERE student_id = ?");
                $journals->execute([$uid]);
                while ($j = $journals->fetch()) {
                    db()->prepare("DELETE FROM journal_media WHERE journal_id = ?")->execute([$j['id']]);
                }
                db()->prepare("DELETE FROM journals WHERE student_id = ?")->execute([$uid]);
                
                // 3. Delete leave permissions
                db()->prepare("DELETE FROM leave_permissions WHERE student_id = ? OR approved_by = ?")->execute([$uid, $uid]);
                
                // 4. Delete attendance records & tokens
                db()->prepare("DELETE FROM attendance_records WHERE student_id = ?")->execute([$uid]);
                db()->prepare("DELETE FROM attendance_tokens WHERE generated_by = ?")->execute([$uid]);
                
                // 5. Delete announcements and materials
                db()->prepare("DELETE FROM announcements WHERE author_id = ?")->execute([$uid]);
                db()->prepare("DELETE FROM materials WHERE author_id = ?")->execute([$uid]);
                
                // Finally delete the user
                db()->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
                
                db()->commit();
                setFlash('success', 'Akun & seluruh datanya berhasil dihapus.');
            } catch (PDOException $e) {
                db()->rollBack();
                // Fallback error message
                setFlash('danger', 'Gagal menghapus akun: Akun ini memiliki data historis yang terkait erat dengan sistem.');
            }
        }
    }

    if ($action === 'edit') {
        $uid     = (int)$_POST['user_id'];
        $name    = clean($_POST['name']);
        $classId = $_POST['class_id'] ?: null;
        $isActive= isset($_POST['is_active']) ? 1 : 0;
        if (!empty($_POST['password'])) {
            db()->prepare("UPDATE users SET name=?,class_id=?,is_active=?,password=? WHERE id=?")
               ->execute([$name, $classId, $isActive, hashPassword($_POST['password']), $uid]);
        } else {
            db()->prepare("UPDATE users SET name=?,class_id=?,is_active=? WHERE id=?")
               ->execute([$name, $classId, $isActive, $uid]);
        }
        setFlash('success', 'Data pengguna berhasil diperbarui.');
    }

    header("Location: $base/admin/manage_users.php");
    exit;
}

// Filter
$filterRole  = $_GET['role'] ?? '';
$filterClass = $_GET['class_id'] ?? '';
$search      = $_GET['q'] ?? '';

$sql    = "SELECT u.*, c.name AS class_name FROM users u LEFT JOIN classes c ON c.id = u.class_id WHERE 1=1";
$params = [];
if ($filterRole) { $sql .= " AND u.role = ?"; $params[] = $filterRole; }
if ($filterClass) { $sql .= " AND u.class_id = ?"; $params[] = $filterClass; }
if ($search) { $sql .= " AND (u.name LIKE ? OR u.username LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if (isGuru()) { $sql .= " AND u.role NOT IN ('admin','guru')"; }
$sql .= " ORDER BY u.role, u.name";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$classes = db()->query("SELECT * FROM classes ORDER BY name")->fetchAll();

include $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/header.php';
?>

<div class="card mb-3">
  <div class="card-header">
    <h3><i class="fas fa-users text-accent"></i> Daftar Pengguna</h3>
    <button class="btn btn-accent" onclick="openModal('modalCreate')"><i class="fas fa-plus"></i> Tambah Pengguna</button>
  </div>

  <!-- Filter Bar -->
  <form method="GET" style="display:flex;gap:.8rem;flex-wrap:wrap;margin-bottom:1.2rem">
    <input type="text" name="q" class="form-control" placeholder="Cari nama atau username..." value="<?= clean($search) ?>" style="flex:1;min-width:180px">
    <select name="role" class="form-control" style="width:150px">
      <option value="">Semua Peran</option>
      <option value="admin" <?= $filterRole==='admin'?'selected':'' ?>>Admin</option>
      <option value="guru"  <?= $filterRole==='guru'?'selected':'' ?>>Guru</option>
      <option value="instruktur" <?= $filterRole==='instruktur'?'selected':'' ?>>Instruktur</option>
      <option value="siswa" <?= $filterRole==='siswa'?'selected':'' ?>>Siswa</option>
    </select>
    <select name="class_id" class="form-control" style="width:170px">
      <option value="">Semua Kelas</option>
      <?php foreach ($classes as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $filterClass==$c['id']?'selected':'' ?>><?= clean($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
    <a href="<?= $base ?>/admin/manage_users.php" class="btn btn-ghost">Reset</a>
  </form>

  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Nama</th><th>Username</th><th>Peran</th><th>Kelas</th><th>Status</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        <?php if ($users): foreach ($users as $u): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:.7rem">
              <div class="avatar" style="width:34px;height:34px;font-size:.75rem"><?= avatarInitials($u['name']) ?></div>
              <strong><?= clean($u['name']) ?></strong>
            </div>
          </td>
          <td><code style="color:var(--text-muted)"><?= clean($u['username']) ?></code></td>
          <td><?= roleBadge($u['role']) ?></td>
          <td><?= $u['class_name'] ? clean($u['class_name']) : '<span class="text-muted">—</span>' ?></td>
          <td>
            <?php if ($u['is_active']): ?>
              <span class="badge badge-success"><i class="fas fa-circle" style="font-size:.5rem"></i> Aktif</span>
            <?php else: ?>
              <span class="badge badge-danger"><i class="fas fa-circle" style="font-size:.5rem"></i> Nonaktif</span>
            <?php endif; ?>
          </td>
          <td>
            <div style="display:flex;gap:.4rem">
              <button class="btn btn-info btn-sm" onclick="openEditModal(<?= htmlspecialchars(json_encode($u)) ?>)"><i class="fas fa-edit"></i></button>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-warning btn-sm" title="Aktif/Nonaktif"><i class="fas fa-power-off"></i></button>
              </form>
              <?php if ($u['id'] != currentUser()['id']): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm" data-confirm="Yakin ingin menghapus akun '<?= clean($u['name']) ?>'?"><i class="fas fa-trash"></i></button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="6" class="text-center text-muted" style="padding:2rem">Tidak ada pengguna ditemukan.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Create -->
<div class="modal-overlay" id="modalCreate" style="display:none">
  <div class="modal-box">
    <div class="modal-icon"><i class="fas fa-user-plus"></i></div>
    <div class="modal-title">Tambah Pengguna Baru</div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Nama Lengkap</label>
          <input type="text" name="name" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Username</label>
          <input type="text" name="username" class="form-control" required>
        </div>
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Peran</label>
          <select name="role" class="form-control" required>
            <?php if (isAdmin()): ?>
            <option value="admin">Admin</option>
            <option value="guru">Guru</option>
            <?php endif; ?>
            <option value="instruktur">Instruktur</option>
            <option value="siswa" selected>Siswa</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Kelas</label>
        <select name="class_id" class="form-control">
          <option value="">— Tidak ada —</option>
          <?php foreach ($classes as $c): ?>
          <option value="<?= $c['id'] ?>"><?= clean($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
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
    <div class="modal-icon" style="background:rgba(69,123,157,.15);color:#7ec8e3"><i class="fas fa-user-edit"></i></div>
    <div class="modal-title">Edit Pengguna</div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="user_id" id="editUserId">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Nama Lengkap</label>
          <input type="text" name="name" id="editName" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Password Baru <span class="text-muted">(kosong = tidak ubah)</span></label>
          <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak ubah">
        </div>
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Kelas</label>
          <select name="class_id" id="editClassId" class="form-control">
            <option value="">— Tidak ada —</option>
            <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>"><?= clean($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <div style="display:flex;align-items:center;gap:.7rem;margin-top:.5rem">
            <input type="checkbox" name="is_active" id="editActive" style="width:18px;height:18px;accent-color:var(--success)">
            <label for="editActive" style="font-size:.9rem">Aktif</label>
          </div>
        </div>
      </div>
      <div style="display:flex;gap:.8rem;justify-content:flex-end">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modalEdit')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Perbarui</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(u){
  document.getElementById('editUserId').value  = u.id;
  document.getElementById('editName').value    = u.name;
  document.getElementById('editClassId').value = u.class_id || '';
  document.getElementById('editActive').checked= u.is_active == 1;
  openModal('modalEdit');
}
</script>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/footer.php'; ?>
