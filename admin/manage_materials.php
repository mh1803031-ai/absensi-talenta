<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/functions.php';

// Allow admin, guru, instruktur
if (!in_array(currentRole(), ['admin', 'guru', 'instruktur'])) {
    header('Location: /TUGASPAKDANIL/ABSENSITALENTA/login.php');
    exit;
}

$error = '';
$success = getFlash('success');

// Handle directory creation
$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/uploads/materials/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $fileUrl = trim($_POST['file_url'] ?? '');
        
        $fileName = null;
        
        if (!$title) {
            $error = "Judul materi wajib diisi.";
        } else {
            // Check file upload
            if (isset($_FILES['material_file']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK) {
                // Max 50MB
                if ($_FILES['material_file']['size'] > 50 * 1024 * 1024) {
                    $error = "Ukuran file maksimal 50MB.";
                } else {
                    $ext = strtolower(pathinfo($_FILES['material_file']['name'], PATHINFO_EXTENSION));
                    $allowedExts = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip', 'rar'];
                    
                    if (!in_array($ext, $allowedExts)) {
                        $error = "Ekstensi file tidak diizinkan. Gunakan dokumen atau arsip.";
                    } else {
                        $fileName = uniqid('mat_') . '_' . time() . '.' . $ext;
                        if (!move_uploaded_file($_FILES['material_file']['tmp_name'], $uploadDir . $fileName)) {
                            $error = "Gagal mengunggah file.";
                            $fileName = null;
                        }
                    }
                }
            }
            
            if (!$error) {
                if (!$fileName && !$fileUrl) {
                    $error = "Anda harus melampirkan file materi atau tautan/URL materi.";
                } else {
                    $stmt = db()->prepare("INSERT INTO materials (title, description, file_name, file_url, author_id) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $description, $fileName, $fileUrl, currentUser()['id']]);
                    setFlash('success', 'Materi berhasil diunggah.');
                    header('Location: manage_materials.php');
                    exit;
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        
        // Find existing file to delete it locally
        $stmt = db()->prepare("SELECT file_name FROM materials WHERE id = ?");
        $stmt->execute([$id]);
        $mat = $stmt->fetch();
        
        if ($mat) {
            if ($mat['file_name'] && file_exists($uploadDir . $mat['file_name'])) {
                unlink($uploadDir . $mat['file_name']);
            }
            db()->prepare("DELETE FROM materials WHERE id = ?")->execute([$id]);
            setFlash('success', 'Materi dihapus.');
        }
        header('Location: manage_materials.php');
        exit;
    }
}

// Fetch all materials
$stmt = db()->query("
    SELECT m.*, u.name as author_name 
    FROM materials m 
    JOIN users u ON m.author_id = u.id 
    ORDER BY m.created_at DESC
");
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Kelola Materi';
$activePage = 'materials';
include $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/header.php';
?>

<?php if ($error): ?>
  <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= clean($error) ?></div>
<?php endif; ?>

<div class="d-flex" style="justify-content:space-between;align-items:center;margin-bottom:1.5rem">
  <h2 style="font-size:1.4rem;font-weight:700">Daftar Modul Materi</h2>
  <button class="btn btn-primary" onclick="openModal('modalCreate')"><i class="fas fa-upload"></i> Unggah Materi</button>
</div>

<div class="card p-0">
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>Judul Materi</th>
          <th>File/Tautan</th>
          <th>Dibuat Oleh</th>
          <th width="100">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if(count($materials)): foreach($materials as $m): ?>
        <tr>
          <td style="font-size:.85rem;color:var(--text-muted)"><?= formatDateTime($m['created_at']) ?></td>
          <td style="font-weight:600">
             <?= clean($m['title']) ?>
             <?php if($m['description']): ?>
               <div style="font-size:.8rem;color:var(--text-muted);font-weight:400;margin-top:.2rem"><?= clean(substr($m['description'], 0, 70)) . (strlen($m['description'])>70?'...':'') ?></div>
             <?php endif; ?>
          </td>
          <td>
            <div style="display:flex;flex-direction:column;gap:.4rem">
               <?php if($m['file_name']): ?>
                 <a href="/TUGASPAKDANIL/ABSENSITALENTA/uploads/materials/<?= $m['file_name'] ?>" target="_blank" class="badge badge-success" style="width:fit-content;text-decoration:none"><i class="fas fa-file-download"></i> Unduh File</a>
               <?php endif; ?>
               <?php if($m['file_url']): ?>
                 <a href="<?= htmlspecialchars($m['file_url']) ?>" target="_blank" class="badge badge-info" style="width:fit-content;text-decoration:none"><i class="fas fa-link"></i> Buka Tautan</a>
               <?php endif; ?>
            </div>
          </td>
          <td><div class="badge badge-warning text-dark"><i class="fas fa-chalkboard-teacher"></i> <?= clean($m['author_name']) ?></div></td>
          <td>
            <form method="POST" style="display:inline" onsubmit="return confirm('Hapus materi ini? Semua file terkait akan hilang.');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $m['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm text-danger" title="Hapus"><i class="fas fa-trash-alt"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="5" class="text-center text-muted" style="padding:2rem">Belum ada materi pembelajaran yang diunggah.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Unggah Materi -->
<div class="modal-overlay" id="modalCreate" style="display:none">
  <div class="modal-box">
    <div class="modal-icon" style="background:rgba(42,157,143,.15);color:var(--success)"><i class="fas fa-book"></i></div>
    <div class="modal-title">Unggah Materi Pembelajaran</div>
    <div class="modal-subtitle">Bahan ajar bagikan ke semua kelas secara publik. Lampirkan file atau cukup tautan luar (misal YouTube).</div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="create">
      <div class="form-group">
        <label class="form-label">Judul Materi Pembelajaran <span class="text-danger">*</span></label>
        <input type="text" name="title" class="form-control" required placeholder="Contoh: Modul Dasar PHP & MySQL">
      </div>
      <div class="form-group">
        <label class="form-label">Deskripsi Singkat (Opsional)</label>
        <textarea name="description" class="form-control" rows="3" placeholder="Tulis instruksi atau keterangan singkat mengenai materi ini..."></textarea>
      </div>
      
      <div style="background:var(--card2-bg);border:1px dashed var(--border);padding:1rem;border-radius:var(--radius);margin-bottom:1rem">
        <div class="form-group" style="margin-bottom:.8rem">
          <label class="form-label"><i class="fas fa-upload"></i> Unggah File Materi</label>
          <input type="file" name="material_file" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip,.rar,.txt">
          <div style="font-size:.75rem;color:var(--text-muted);margin-top:.4rem">Mendukung dokumen atau arsip. Maks 50MB.</div>
        </div>
        <div style="text-align:center;font-weight:700;color:var(--text-muted);margin:1rem 0;font-size:.85rem">ATAU / DAN</div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label"><i class="fas fa-link"></i> Tautan / Link Eksternal</label>
          <input type="url" name="file_url" class="form-control" placeholder="https://youtube.com/...">
        </div>
      </div>
      
      <div style="display:flex;gap:.8rem;justify-content:flex-end;margin-top:1.5rem">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modalCreate')">Batal</button>
        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Simpan Materi</button>
      </div>
    </form>
  </div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/footer.php'; ?>
