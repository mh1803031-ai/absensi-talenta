<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('siswa');

$base      = '/TUGASPAKDANIL/ABSENSITALENTA';
$studentId = currentUser()['id'];

$journalId = (int)($_GET['id'] ?? 0);
if (!$journalId) { header("Location: $base/siswa/journal_history.php"); exit; }

// Verify journal belongs to student AND is in revision status
$stmt = db()->prepare("SELECT * FROM journals WHERE id = ? AND student_id = ?");
$stmt->execute([$journalId, $studentId]);
$journal = $stmt->fetch();

if (!$journal) {
    setFlash('danger', 'Jurnal tidak ditemukan.');
    header("Location: $base/siswa/journal_history.php"); exit;
}
if ($journal['status'] !== 'revision') {
    setFlash('warning', 'Hanya jurnal dengan status Revisi yang dapat diedit.');
    header("Location: $base/siswa/journal_history.php"); exit;
}

// Fetch existing media
$mediaStmt = db()->prepare("SELECT * FROM journal_media WHERE journal_id = ?");
$mediaStmt->execute([$journalId]);
$existingMedia = $mediaStmt->fetchAll();
$photos = array_filter($existingMedia, fn($m) => $m['file_type'] === 'photo');
$videos = array_filter($existingMedia, fn($m) => $m['file_type'] === 'video');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = clean($_POST['title']);
    $content = clean($_POST['content']);

    // ── Update Tugas file (jika di-upload ulang) ───────────
    $taskFilename  = $journal['task_file']; // default keep existing
    $taskSubmitted = $journal['task_submitted'];
    
    if (!empty($_FILES['task_file']['name'])) {
        $ext     = strtolower(pathinfo($_FILES['task_file']['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf','doc','docx','zip','txt','xls','xlsx','ppt','pptx'];
        if (in_array($ext, $allowed) && $_FILES['task_file']['size'] < 20*1024*1024) {
            $taskFilename = uniqid('task_') . '.' . $ext;
            $uploadDir    = __DIR__ . '/../uploads/tasks/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            move_uploaded_file($_FILES['task_file']['tmp_name'], $uploadDir . $taskFilename);
            $taskSubmitted = 1;
        } else {
            setFlash('danger','File tugas tidak valid (maks 20MB, format: PDF, DOC, DOCX, ZIP, dll).');
            header("Location: $base/siswa/journal_edit.php?id=$journalId"); exit;
        }
    }

    // ── Remove old media if requested ────────────────────────
    $removeMedia = $_POST['remove_media'] ?? [];
    if (!empty($removeMedia)) {
        foreach ($removeMedia as $mId) {
            $mStmt = db()->prepare("SELECT file_name FROM journal_media WHERE id = ? AND journal_id = ?");
            $mStmt->execute([(int)$mId, $journalId]);
            $mFile = $mStmt->fetchColumn();
            if ($mFile) {
                $path = __DIR__ . '/../uploads/media/' . $mFile;
                if (file_exists($path)) @unlink($path);
                db()->prepare("DELETE FROM journal_media WHERE id = ?")->execute([(int)$mId]);
            }
        }
    }

    // ── Add new Media files (foto & video) ────────────────────
    $mediaDir = __DIR__ . '/../uploads/media/';
    if (!is_dir($mediaDir)) mkdir($mediaDir, 0755, true);

    $allowedPhotos = ['jpg','jpeg','png','gif','webp','heic'];
    $allowedVideos = ['mp4','mov','avi','mkv','webm','3gp'];
    $maxMediaSize  = 100 * 1024 * 1024; // 100MB

    // Multiple photos
    if (!empty($_FILES['photos']['name'][0])) {
        $files = $_FILES['photos'];
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] !== 0 || empty($files['name'][$i])) continue;
            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedPhotos)) continue;
            if ($files['size'][$i] > $maxMediaSize) continue;
            $fname = uniqid('photo_') . '.' . $ext;
            if (move_uploaded_file($files['tmp_name'][$i], $mediaDir . $fname)) {
                db()->prepare(
                    "INSERT INTO journal_media (journal_id, file_name, file_type, original_name) VALUES (?,?,'photo',?)"
                )->execute([$journalId, $fname, clean($files['name'][$i])]);
            }
        }
    }

    // Multiple videos
    if (!empty($_FILES['videos']['name'][0])) {
        $files = $_FILES['videos'];
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] !== 0 || empty($files['name'][$i])) continue;
            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedVideos)) continue;
            if ($files['size'][$i] > $maxMediaSize) continue;
            $fname = uniqid('video_') . '.' . $ext;
            if (move_uploaded_file($files['tmp_name'][$i], $mediaDir . $fname)) {
                db()->prepare(
                    "INSERT INTO journal_media (journal_id, file_name, file_type, original_name) VALUES (?,?,'video',?)"
                )->execute([$journalId, $fname, clean($files['name'][$i])]);
            }
        }
    }

    // ── Update journal & reset status to pending ──────────────
    db()->prepare(
        "UPDATE journals SET title=?, content=?, task_file=?, task_submitted=?, status='pending', submitted_at=NOW() WHERE id=?"
    )->execute([$title, $content, $taskFilename, $taskSubmitted, $journalId]);

    setFlash('success','Jurnal berhasil direvisi dan dikirim ulang untuk ditinjau.');
    header("Location: $base/siswa/journal_history.php"); exit;
}

$pageTitle  = 'Revisi Jurnal';
$activePage = 'journals';
include __DIR__ . '/../includes/header.php';
?>

<style>
.upload-zone {
    border: 2px dashed var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    text-align: center;
    cursor: pointer;
    transition: all .25s;
    position: relative;
    background: var(--input-bg);
}
.upload-zone:hover, .upload-zone.drag-over {
    border-color: var(--accent);
    background: rgba(244,162,97,.06);
}
.upload-zone input[type="file"] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
    width: 100%;
    height: 100%;
}
.upload-zone .zone-icon { font-size: 2rem; margin-bottom: .5rem; display: block; }
.upload-zone .zone-title { font-weight: 700; color: var(--text); font-size: .93rem; }
.upload-zone .zone-hint  { font-size: .78rem; color: var(--text-muted); margin-top: .3rem; }

.preview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: .8rem;
    margin-top: 1rem;
}
.preview-item {
    position: relative;
    border-radius: var(--radius);
    overflow: hidden;
    background: var(--card2-bg);
    border: 1px solid var(--border);
    aspect-ratio: 1;
}
.preview-item img,
.preview-item video {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.preview-item .preview-remove {
    position: absolute;
    top: 4px; right: 4px;
    background: rgba(230,57,70,.85);
    color: #fff;
    border: none;
    border-radius: 50%;
    width: 24px; height: 24px;
    cursor: pointer;
    font-size: .7rem;
    display: flex; align-items: center; justify-content: center;
    z-index: 10;
}
.preview-item .preview-label {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    background: rgba(0,0,0,.6);
    color: #fff;
    font-size: .7rem;
    padding: .3rem .4rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.count-badge {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .2rem .6rem;
    background: rgba(244,162,97,.15);
    color: var(--accent);
    border-radius: 50px;
    font-size: .78rem;
    font-weight: 700;
    margin-left: .5rem;
}
.deleted-overlay {
    position: absolute; inset: 0;
    background: rgba(230,57,70,.7);
    color: white; font-weight: bold;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    font-size: .8rem;
    opacity: 0; transition: opacity .2s; z-index: 5;
}
.preview-item.marked-delete .deleted-overlay { opacity: 1; }
</style>

<div style="max-width:760px;margin:0 auto">
  <div class="card">
    <div class="card-header">
      <h3><i class="fas fa-edit text-accent"></i> Revisi Jurnal</h3>
      <span class="badge badge-danger">Status: Revisi</span>
    </div>

    <!-- Review Note from Admin/Guru -->
    <div style="background:rgba(230,57,70,.08);border:1px solid rgba(230,57,70,.2);border-radius:var(--radius);padding:1rem;margin-bottom:1.5rem">
      <div style="font-size:.8rem;color:var(--danger);font-weight:700;margin-bottom:.4rem;text-transform:uppercase;letter-spacing:.5px">
        <i class="fas fa-exclamation-circle"></i> Catatan Revisi dari Reviewer
      </div>
      <div style="font-size:.9rem;color:var(--text);line-height:1.6">
        <?= nl2br(clean($journal['review_note'] ?: '(Tidak ada catatan spesifik)')) ?>
      </div>
    </div>

    <form method="POST" enctype="multipart/form-data" id="journalForm">
      
      <!-- Checkbox hidden inputs untuk menampung ID media yang dihapus -->
      <div id="removedMediaContainer"></div>

      <!-- Judul -->
      <div class="form-group">
        <label class="form-label">Judul Jurnal</label>
        <input type="text" name="title" class="form-control" required value="<?= clean($journal['title']) ?>">
      </div>

      <!-- Isi -->
      <div class="form-group">
        <label class="form-label">Isi Jurnal</label>
        <textarea name="content" id="content" class="form-control" rows="9" required><?= clean($journal['content']) ?></textarea>
        <div class="form-hint" id="charCount"><?= strlen($journal['content']) ?> karakter</div>
      </div>

      <hr class="divider">

      <!-- EXISTING FOTO -->
      <div class="form-group">
        <label class="form-label">
          <i class="fas fa-camera" style="color:var(--accent)"></i> Foto Saat Ini (<?= count($photos) ?>)
        </label>
        <?php if ($photos): ?>
        <div class="preview-grid" style="margin-bottom: 1rem;">
          <?php foreach ($photos as $p): ?>
          <div class="preview-item existing-media" id="media-<?= $p['id'] ?>">
            <img src="<?= $base ?>/uploads/media/<?= $p['file_name'] ?>" alt="Foto">
            <button type="button" class="preview-remove" onclick="toggleRemoveMedia(<?= $p['id'] ?>)" title="Hapus foto ini"><i class="fas fa-trash"></i></button>
            <div class="preview-label"><?= clean($p['original_name'] ?? 'Foto') ?></div>
            <div class="deleted-overlay"><i class="fas fa-trash-alt mb-1"></i>Akan Dihapus <div style="font-size:.65rem;margin-top:4px;cursor:pointer;text-decoration:underline" onclick="toggleRemoveMedia(<?= $p['id'] ?>)">Batal</div></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-muted" style="font-size:.85rem;margin-bottom:1rem">Tidak ada foto.</p>
        <?php endif; ?>

        <!-- TAMBAH FOTO BARU -->
        <label class="form-label mt-2">Tambah Foto Baru <span class="count-badge" id="photoCount"><i class="fas fa-image"></i> 0 foto</span></label>
        <div class="upload-zone" id="photoZone">
          <input type="file" name="photos[]" id="photoInput" multiple accept="image/jpeg,image/png,image/gif,image/webp,.heic">
          <span class="zone-icon">📷</span>
          <div class="zone-title">Sertakan foto tambahan (Bila perlu)</div>
          <div class="zone-hint">JPG, PNG, GIF, WebP, HEIC • Maks 100MB/file</div>
        </div>
        <div class="preview-grid" id="photoPreview"></div>
      </div>

      <hr class="divider">

      <!-- EXISTING VIDEO -->
      <div class="form-group">
        <label class="form-label">
          <i class="fas fa-video" style="color:var(--info)"></i> Video Saat Ini (<?= count($videos) ?>)
        </label>
        <?php if ($videos): ?>
        <div class="preview-grid" style="margin-bottom: 1rem;">
          <?php foreach ($videos as $v): ?>
          <div class="preview-item existing-media" id="media-<?= $v['id'] ?>">
            <video src="<?= $base ?>/uploads/media/<?= $v['file_name'] ?>"></video>
            <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.3);font-size:1.5rem;color:#fff"><i class="fas fa-play"></i></div>
            <button type="button" class="preview-remove" onclick="toggleRemoveMedia(<?= $v['id'] ?>)" title="Hapus video ini"><i class="fas fa-trash"></i></button>
            <div class="preview-label"><?= clean($v['original_name'] ?? 'Video') ?></div>
            <div class="deleted-overlay"><i class="fas fa-trash-alt mb-1"></i>Akan Dihapus <div style="font-size:.65rem;margin-top:4px;cursor:pointer;text-decoration:underline" onclick="toggleRemoveMedia(<?= $v['id'] ?>)">Batal</div></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-muted" style="font-size:.85rem;margin-bottom:1rem">Tidak ada video.</p>
        <?php endif; ?>

        <!-- TAMBAH VIDEO BARU -->
        <label class="form-label mt-2">Tambah Video Baru <span class="count-badge" id="videoCount" style="background:rgba(69,123,157,.15);color:#7ec8e3"><i class="fas fa-film"></i> 0 video</span></label>
        <div class="upload-zone" id="videoZone">
          <input type="file" name="videos[]" id="videoInput" multiple accept="video/mp4,video/quicktime,video/x-msvideo,video/webm,video/3gpp,.mkv,.mov">
          <span class="zone-icon">🎥</span>
          <div class="zone-title">Sertakan video tambahan (Bila perlu)</div>
          <div class="zone-hint">MP4, MOV, AVI, WebM, MKV • Maks 100MB/file</div>
        </div>
        <div class="preview-grid" id="videoPreview"></div>
      </div>

      <hr class="divider">

      <!-- TUGAS -->
      <div class="form-group">
        <label class="form-label">
          <i class="fas fa-paperclip" style="color:var(--success)"></i>
          File Tugas
        </label>
        <?php if ($journal['task_file']): ?>
        <div style="margin-bottom: .8rem; font-size: .85rem;">
            File saat ini: <a href="<?= $base ?>/uploads/tasks/<?= $journal['task_file'] ?>" target="_blank" class="text-accent underline"><i class="fas fa-file-download"></i> <?= $journal['task_file'] ?></a>
        </div>
        <?php endif; ?>
        <input type="file" name="task_file" class="form-control" accept=".pdf,.doc,.docx,.zip,.txt,.xls,.xlsx,.ppt,.pptx">
        <div class="form-hint">Biarkan kosong jika tidak ingin mengubah file tugas. Format: PDF, DOC, DOCX, ZIP, XLS, PPT. Maks: 20MB</div>
      </div>

      <!-- Warning jumlah file -->
      <div id="fileWarning" class="alert alert-warning" style="display:none">
        <i class="fas fa-exclamation-triangle"></i>
        <span id="fileWarningMsg"></span>
      </div>

      <div style="display:flex;gap:.8rem;justify-content:flex-end;margin-top:1.5rem">
        <a href="<?= $base ?>/siswa/journal_history.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Batal</a>
        <button type="submit" class="btn btn-primary" id="submitBtn">
          <i class="fas fa-paper-plane"></i> Kirim Revisi
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// ── Char counter ─────────────────────────────────────────────
const content = document.getElementById('content');
const cc      = document.getElementById('charCount');
content.addEventListener('input', () => { cc.textContent = content.value.length + ' karakter'; });

// ── Existing Media Delete Toggle ──────────────────────────────
function toggleRemoveMedia(id) {
    const el = document.getElementById('media-' + id);
    const container = document.getElementById('removedMediaContainer');
    let input = document.getElementById('remove-input-' + id);

    if (el.classList.contains('marked-delete')) {
        // Undelete
        el.classList.remove('marked-delete');
        if (input) input.remove();
    } else {
        // Mark as delete
        el.classList.add('marked-delete');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'remove_media[]';
            input.value = id;
            input.id = 'remove-input-' + id;
            container.appendChild(input);
        }
    }
}

// ── Drag & Drop highlight ─────────────────────────────────────
['photoZone','videoZone'].forEach(id => {
  const zone = document.getElementById(id);
  zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
  zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
  zone.addEventListener('drop', () => zone.classList.remove('drag-over'));
});

// ── Preview builder ───────────────────────────────────────────
function buildPreviews(input, previewGrid, countBadge, type, max) {
  input.addEventListener('change', function() {
    const files = Array.from(this.files);
    
    previewGrid.innerHTML = '';
    const validFiles = files.slice(0, max);
    countBadge.innerHTML = `<i class="fas fa-${type==='foto'?'image':'film'}"></i> ${validFiles.length} ${type} baru`;

    validFiles.forEach((file, i) => {
      const item = document.createElement('div');
      item.className = 'preview-item';

      if (type === 'foto') {
        const img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        img.onload = () => URL.revokeObjectURL(img.src);
        item.appendChild(img);
      } else {
        const vid = document.createElement('video');
        vid.src = URL.createObjectURL(file);
        vid.controls = false;
        vid.muted    = true;
        vid.style.pointerEvents = 'none';
        item.appendChild(vid);
      }

      const label = document.createElement('div');
      label.className = 'preview-label';
      label.textContent = file.name;
      item.appendChild(label);

      // Size badge
      const size = document.createElement('div');
      size.style.cssText = 'position:absolute;top:4px;left:4px;background:rgba(0,0,0,.6);color:#fff;font-size:.65rem;padding:.15rem .35rem;border-radius:4px';
      size.textContent = (file.size/1024/1024).toFixed(1) + 'MB';
      item.appendChild(size);

      previewGrid.appendChild(item);
    });
  });
}

buildPreviews(
  document.getElementById('photoInput'),
  document.getElementById('photoPreview'),
  document.getElementById('photoCount'),
  'foto', 5
);
buildPreviews(
  document.getElementById('videoInput'),
  document.getElementById('videoPreview'),
  document.getElementById('videoCount'),
  'video', 3
);

// ── Submit guard ──────────────────────────────────────────────
document.getElementById('journalForm').addEventListener('submit', function(e) {
  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim Revisi...';
  // Re-enable after 30s fallback
  setTimeout(() => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Kirim Revisi';
  }, 30000);
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
