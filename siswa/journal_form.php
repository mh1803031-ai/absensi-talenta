<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/functions.php';
requireRole('siswa');

$base      = '/TUGASPAKDANIL/ABSENSITALENTA';
$studentId = currentUser()['id'];

if (!hasAttendedToday($studentId)) {
    header("Location: $base/siswa/dashboard.php"); exit;
}
if (hasSubmittedJournalToday($studentId)) {
    setFlash('warning','Anda sudah mengisi jurnal hari ini.');
    header("Location: $base/siswa/dashboard.php"); exit;
}

$attendanceId = getTodayAttendanceId($studentId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = clean($_POST['title']);
    $content = clean($_POST['content']);

    // ── Tugas file (doc/pdf/zip) ─────────────────────────────
    $taskSubmitted = 0;
    $taskFilename  = null;
    if (!empty($_FILES['task_file']['name'])) {
        $ext     = strtolower(pathinfo($_FILES['task_file']['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf','doc','docx','zip','txt','xls','xlsx','ppt','pptx'];
        if (in_array($ext, $allowed) && $_FILES['task_file']['size'] < 20*1024*1024) {
            $taskFilename = uniqid('task_') . '.' . $ext;
            $uploadDir    = $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/uploads/tasks/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            move_uploaded_file($_FILES['task_file']['tmp_name'], $uploadDir . $taskFilename);
            $taskSubmitted = 1;
        } else {
            setFlash('danger','File tugas tidak valid (maks 20MB, format: PDF, DOC, DOCX, ZIP, XLS, PPT).');
            header("Location: $base/siswa/journal_form.php"); exit;
        }
    }

    // ── Insert journal ────────────────────────────────────────
    db()->prepare(
        "INSERT INTO journals (student_id, attendance_id, title, content, task_file, task_submitted)
         VALUES (?,?,?,?,?,?)"
    )->execute([$studentId, $attendanceId, $title, $content, $taskFilename, $taskSubmitted]);
    $journalId = db()->lastInsertId();

    // ── Notifikasi ke Admin & Guru ────────────────────────────────────────
    try {
        $studentName = currentUser()['name'];
        $notifMsg = "$studentName mengirimkan jurnal harian baru.";
        $link = "/TUGASPAKDANIL/ABSENSITALENTA/admin/journals.php";
        
        $admins = db()->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
        foreach ($admins as $ad) {
            db()->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)")
                ->execute([$ad['id'], 'Jurnal Baru MASUK', $notifMsg, $link]);
        }
        
        $classId = currentUser()['class_id'];
        $teachers = db()->prepare("SELECT id FROM users WHERE role IN ('guru','instruktur') AND class_id = ?");
        $teachers->execute([$classId]);
        foreach ($teachers->fetchAll() as $tc) {
            db()->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)")
                ->execute([$tc['id'], 'Jurnal Baru MASUK', $notifMsg, $link]);
        }
    } catch (Exception $e) {}

    // ── Media files (foto & video) ────────────────────────────
    $mediaDir = $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/uploads/media/';
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

    setFlash('success','Jurnal beserta bukti foto/video berhasil dikirim! Tunggu tinjauan dari Guru atau Instruktur.');
    header("Location: $base/siswa/dashboard.php"); exit;
}

$pageTitle  = 'Isi Jurnal Harian';
$activePage = 'journals';
include $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/header.php';
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
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
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
    width: 22px; height: 22px;
    cursor: pointer;
    font-size: .7rem;
    display: flex; align-items: center; justify-content: center;
}
.preview-item .preview-label {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    background: rgba(0,0,0,.6);
    color: #fff;
    font-size: .7rem;
    padding: .2rem .4rem;
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
</style>

<div style="max-width:760px;margin:0 auto">
  <div class="card">
    <div class="card-header">
      <h3><i class="fas fa-pen text-accent"></i> Jurnal Harian</h3>
      <span class="badge badge-info"><i class="fas fa-calendar-alt"></i> <?= date('d M Y') ?></span>
    </div>

    <div style="background:rgba(244,162,97,.08);border:1px solid rgba(244,162,97,.2);border-radius:var(--radius);padding:.85rem 1rem;margin-bottom:1.2rem;font-size:.87rem;color:var(--accent)">
      <i class="fas fa-info-circle"></i>
      Isi jurnal dengan jujur dan lengkap. Tambahkan foto/video sebagai bukti kegiatan. Jurnal akan ditinjau oleh Guru atau Instruktur.
    </div>

    <form method="POST" enctype="multipart/form-data" id="journalForm">

      <!-- Judul -->
      <div class="form-group">
        <label class="form-label">Judul Jurnal</label>
        <input type="text" name="title" class="form-control" required
          placeholder="Contoh: Kegiatan Belajar — Pemrograman Web">
      </div>

      <!-- Isi -->
      <div class="form-group">
        <label class="form-label">Isi Jurnal</label>
        <textarea name="content" id="content" class="form-control" rows="9" required
          placeholder="Ceritakan apa yang Anda pelajari atau kerjakan hari ini:
• Kegiatan yang dilakukan
• Materi yang dipelajari
• Kendala yang dihadapi
• Solusi yang diterapkan"></textarea>
        <div class="form-hint" id="charCount">0 karakter</div>
      </div>

      <hr class="divider">

      <!-- FOTO -->
      <div class="form-group">
        <label class="form-label">
          <i class="fas fa-camera" style="color:var(--accent)"></i>
          Bukti Foto
          <span class="count-badge" id="photoCount"><i class="fas fa-image"></i> 0 foto</span>
        </label>
        <div class="upload-zone" id="photoZone">
          <input type="file" name="photos[]" id="photoInput" multiple
            accept="image/jpeg,image/png,image/gif,image/webp,.heic">
          <span class="zone-icon">📷</span>
          <div class="zone-title">Klik atau seret foto ke sini</div>
          <div class="zone-hint">Maks 5 foto • JPG, PNG, GIF, WebP, HEIC • Maks 100MB/file</div>
        </div>
        <div class="preview-grid" id="photoPreview"></div>
      </div>

      <!-- VIDEO -->
      <div class="form-group">
        <label class="form-label">
          <i class="fas fa-video" style="color:var(--info,#457b9d)"></i>
          Bukti Video
          <span class="count-badge" id="videoCount" style="background:rgba(69,123,157,.15);color:#7ec8e3"><i class="fas fa-film"></i> 0 video</span>
        </label>
        <div class="upload-zone" id="videoZone">
          <input type="file" name="videos[]" id="videoInput" multiple
            accept="video/mp4,video/quicktime,video/x-msvideo,video/webm,video/3gpp,.mkv,.mov">
          <span class="zone-icon">🎥</span>
          <div class="zone-title">Klik atau seret video ke sini</div>
          <div class="zone-hint">Maks 3 video • MP4, MOV, AVI, WebM, MKV • Maks 100MB/file</div>
        </div>
        <div class="preview-grid" id="videoPreview"></div>
      </div>

      <hr class="divider">

      <!-- TUGAS -->
      <div class="form-group">
        <label class="form-label">
          <i class="fas fa-paperclip" style="color:var(--success)"></i>
          Upload Tugas <span class="text-muted">(Opsional)</span>
        </label>
        <input type="file" name="task_file" class="form-control"
          accept=".pdf,.doc,.docx,.zip,.txt,.xls,.xlsx,.ppt,.pptx">
        <div class="form-hint">Format: PDF, DOC, DOCX, ZIP, XLS, PPT. Maks: 20MB</div>
      </div>

      <!-- Warning jumlah file -->
      <div id="fileWarning" class="alert alert-warning" style="display:none">
        <i class="fas fa-exclamation-triangle"></i>
        <span id="fileWarningMsg"></span>
      </div>

      <div style="display:flex;gap:.8rem;justify-content:flex-end;margin-top:1.5rem">
        <a href="<?= $base ?>/siswa/dashboard.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Kembali</a>
        <button type="submit" class="btn btn-accent" id="submitBtn">
          <i class="fas fa-paper-plane"></i> Kirim Jurnal
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
    const warning = document.getElementById('fileWarning');
    const warnMsg = document.getElementById('fileWarningMsg');

    if (files.length > max) {
      warnMsg.textContent = `Maksimal ${max} ${type} yang dapat diunggah. ${files.length - max} file diabaikan.`;
      warning.style.display = 'flex';
    }

    previewGrid.innerHTML = '';
    const validFiles = files.slice(0, max);
    countBadge.innerHTML = `<i class="fas fa-${type==='foto'?'image':'film'}"></i> ${validFiles.length} ${type}`;

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
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengupload...';
  // Re-enable after 30s fallback
  setTimeout(() => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Kirim Jurnal';
  }, 30000);
});
</script>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/footer.php'; ?>
