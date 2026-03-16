<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!in_array(currentRole(), ['admin', 'guru'])) {
    header('Location: /TUGASPAKDANIL/ABSENSITALENTA/login.php');
    exit;
}

$action = $_GET['action'] ?? '';
$error = '';
$success = getFlash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    if ($postAction === 'create') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        
        if (!$title || !$content) {
            $error = "Judul dan Isi pengumuman wajib diisi.";
        } else {
            $stmt = db()->prepare("INSERT INTO announcements (title, content, author_id) VALUES (?, ?, ?)");
            $stmt->execute([$title, $content, currentUser()['id']]);
            setFlash('success', 'Pengumuman berhasil ditambahkan.');
            header('Location: manage_announcements.php');
            exit;
        }
    } elseif ($postAction === 'delete') {
        $id = (int)$_POST['id'];
        db()->prepare("DELETE FROM announcements WHERE id = ?")->execute([$id]);
        setFlash('success', 'Pengumuman dihapus.');
        header('Location: manage_announcements.php');
        exit;
    }
}

$stmt = db()->query("
    SELECT a.*, u.name as author_name 
    FROM announcements a 
    JOIN users u ON a.author_id = u.id 
    ORDER BY a.created_at DESC
");
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Kelola Pengumuman';
$activePage = 'announcements';
include __DIR__ . '/../includes/header.php';
?>

<?php if ($error): ?>
  <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= clean($error) ?></div>
<?php endif; ?>

<div class="d-flex" style="justify-content:space-between;align-items:center;margin-bottom:1.5rem">
  <h2 style="font-size:1.4rem;font-weight:700">Daftar Pengumuman</h2>
  <button class="btn btn-primary" onclick="openModal('modalCreate')"><i class="fas fa-plus"></i> Buat Pengumuman</button>
</div>

<div class="card p-0">
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>Judul</th>
          <th>Isi Pengumuman</th>
          <th>Dibuat Oleh</th>
          <th width="100">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if(count($announcements)): foreach($announcements as $a): ?>
        <tr>
          <td style="font-size:.85rem;color:var(--text-muted)"><?= formatDateTime($a['created_at']) ?></td>
          <td style="font-weight:600"><?= clean($a['title']) ?></td>
          <td><div style="max-height:3em;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;font-size:.85rem;color:var(--text-muted)"><?= clean($a['content']) ?></div></td>
          <td><div class="badge badge-info"><i class="fas fa-user-circle"></i> <?= clean($a['author_name']) ?></div></td>
          <td>
            <form method="POST" style="display:inline" onsubmit="return confirm('Hapus pengumuman ini?');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $a['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm text-danger" title="Hapus"><i class="fas fa-trash-alt"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="5" class="text-center text-muted" style="padding:2rem">Belum ada pengumuman.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Buat Pengumuman -->
<div class="modal-overlay" id="modalCreate" style="display:none">
  <div class="modal-box">
    <div class="modal-icon" style="background:rgba(42,157,143,.15);color:var(--success)"><i class="fas fa-bullhorn"></i></div>
    <div class="modal-title">Buat Pengumuman Baru</div>
    <div class="modal-subtitle">Pengumuman akan tampil di dashboard semua pengguna.</div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="form-group">
        <label class="form-label">Judul Pengumuman</label>
        <input type="text" name="title" class="form-control" required placeholder="Contoh: Info Libur Semester">
      </div>
      <div class="form-group">
        <label class="form-label">Isi Pengumuman</label>
        <textarea name="content" class="form-control" rows="5" required placeholder="Tuliskan pesan lengkap..."></textarea>
      </div>
      <div style="display:flex;gap:.8rem;justify-content:flex-end;margin-top:1.5rem">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modalCreate')">Batal</button>
        <button type="submit" class="btn btn-success"><i class="fas fa-paper-plane"></i> Publikasikan</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>