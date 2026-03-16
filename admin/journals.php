<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/functions.php';
requireRole('admin','guru','instruktur');

$pageTitle  = 'Jurnal Siswa';
$activePage = 'journals';
$base = defined('APP_BASE_PATH') ? APP_BASE_PATH : '/TUGASPAKDANIL/ABSENSITALENTA';
$role = currentRole();
$rolePath = $role === 'admin' ? 'admin' : ($role === 'guru' ? 'guru' : 'instruktur');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $journalId = (int)$_POST['journal_id'];
    if ($action === 'review') {
        $status     = $_POST['status'];
        $reviewNote = clean($_POST['review_note']);
        db()->prepare(
            "UPDATE journals SET status=?,review_note=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?"
        )->execute([$status,$reviewNote,currentUser()['id'],$journalId]);
        
        try {
            $jStmt = db()->prepare("SELECT student_id, title FROM journals WHERE id = ?");
            $jStmt->execute([$journalId]);
            if ($jRow = $jStmt->fetch()) {
                $statusStr = $status === 'reviewed' ? 'Disetujui' : 'Revisi';
                $notifMsg = "Jurnal anda '{$jRow['title']}' telah direview ($statusStr).";
                db()->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)")
                    ->execute([$jRow['student_id'], 'Jurnal Direview', $notifMsg, '/TUGASPAKDANIL/ABSENSITALENTA/siswa/journal_history.php']);
            }
        } catch (Exception $e) {}
        
        setFlash('success','Jurnal berhasil diperbarui.');
    }
    header("Location: $base/$rolePath/journals.php"); exit;
}

$filterStatus = $_GET['status'] ?? '';
$filterClass  = $_GET['class_id'] ?? '';
$search       = $_GET['q'] ?? '';
$perPage      = 15;
$page         = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($page - 1) * $perPage;

$sqlBase = "FROM journals j
        JOIN users u ON u.id = j.student_id
        LEFT JOIN classes c ON c.id = u.class_id
        LEFT JOIN users ru ON ru.id = j.reviewed_by
        WHERE 1=1";
$params = [];
if ($filterStatus) { $sqlBase .= " AND j.status = ?"; $params[] = $filterStatus; }
if ($filterClass)  { $sqlBase .= " AND u.class_id = ?"; $params[] = $filterClass; }
if ($search)       { $sqlBase .= " AND (u.name LIKE ? OR j.title LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

// Total count for pagination
$totalStmt = db()->prepare("SELECT COUNT(*) " . $sqlBase);
$totalStmt->execute($params);
$totalCount = (int)$totalStmt->fetchColumn();
$totalPages = max(1, ceil($totalCount / $perPage));

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportSql = "SELECT j.title, j.content, u.name AS student_name, c.name AS class_name, 
                         j.status, ru.name AS reviewer_name, j.submitted_at 
                  " . $sqlBase . " ORDER BY j.submitted_at DESC";
    $exportStmt = db()->prepare($exportSql);
    $exportStmt->execute($params);
    $exportData = $exportStmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="laporan_jurnal_' . date('Ymd_Hi') . '.csv"');
    header('Pragma: no-cache');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Siswa', 'Kelas', 'Judul', 'Isi Jurnal', 'Status', 'Direview Oleh', 'Waktu Submit'], ';');
    foreach ($exportData as $row) {
        $statusStr = $row['status'] === 'pending' ? 'Pending' : ($row['status'] === 'reviewed' ? 'Disetujui' : 'Revisi');
        fputcsv($out, [
            $row['student_name'], 
            $row['class_name'] ?? '-', 
            $row['title'], 
            $row['content'], 
            $statusStr, 
            $row['reviewer_name'] ?? '-', 
            $row['submitted_at']
        ], ';');
    }
    fclose($out); exit;
}

$sql = "SELECT j.*, u.name AS student_name, u.username, c.name AS class_name, ru.name AS reviewer_name " . $sqlBase . " ORDER BY j.submitted_at DESC LIMIT $perPage OFFSET $offset";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$journals = $stmt->fetchAll();

$classes = db()->query("SELECT * FROM classes ORDER BY name")->fetchAll();

include $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <h3><i class="fas fa-book-open text-accent"></i> Jurnal Siswa</h3>
    <div class="flex gap-1">
      <span class="badge badge-warning"><?= countTable('journals','status=?',['pending']) ?> Pending</span>
      <span class="badge badge-success"><?= countTable('journals','status=?',['approved']) ?> Disetujui</span>
    </div>
  </div>

  <!-- Filter -->
  <form method="GET" style="display:flex;gap:.8rem;flex-wrap:wrap;margin-bottom:1.2rem">
    <input type="text" name="q" class="form-control" placeholder="Cari siswa atau judul..." value="<?= clean($search) ?>" style="flex:1;min-width:180px">
    <select name="status" class="form-control" style="width:155px">
      <option value="">Semua Status</option>
      <option value="pending"  <?= $filterStatus==='pending'?'selected':'' ?>>Pending</option>
      <option value="reviewed" <?= $filterStatus==='reviewed'?'selected':'' ?>>Ditinjau</option>
      <option value="approved" <?= $filterStatus==='approved'?'selected':'' ?>>Disetujui</option>
      <option value="revision" <?= $filterStatus==='revision'?'selected':'' ?>>Revisi</option>
    </select>
    <select name="class_id" class="form-control" style="width:170px">
      <option value="">Semua Kelas</option>
      <?php foreach ($classes as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $filterClass==$c['id']?'selected':'' ?>><?= clean($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <div style="display:flex;gap:.8rem;">
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Cari</button>
        <button type="submit" name="export" value="csv" class="btn btn-success"><i class="fas fa-file-csv"></i> Export CSV</button>
    </div>
    <a href="?." class="btn btn-ghost">Reset</a>
  </form>

  <div class="table-wrap">
    <table>
      <thead><tr><th>Siswa</th><th>Kelas</th><th>Judul Jurnal</th><th>Bukti</th><th>Tugas</th><th>Status</th><th>Direview Oleh</th><th>Waktu</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php if ($journals): foreach ($journals as $j):
          $jMedia = db()->prepare("SELECT file_type, COUNT(*) as cnt FROM journal_media WHERE journal_id=? GROUP BY file_type");
          $jMedia->execute([$j['id']]); $mediaCounts = [];
          foreach ($jMedia->fetchAll() as $mc) $mediaCounts[$mc['file_type']] = $mc['cnt'];
        ?>
        <tr>
          <td><strong><?= clean($j['student_name']) ?></strong><br><small class="text-muted"><?= clean($j['username']) ?></small></td>
          <td><?= $j['class_name'] ? clean($j['class_name']) : '—' ?></td>
          <td><?= clean(substr($j['title'],0,28)) . (strlen($j['title'])>28?'...':'') ?></td>
          <td>
            <?php if (isset($mediaCounts['photo'])): ?>
              <span class="badge badge-info"><i class="fas fa-image"></i> <?= $mediaCounts['photo'] ?></span>
            <?php endif; ?>
            <?php if (isset($mediaCounts['video'])): ?>
              <span class="badge badge-primary"><i class="fas fa-video"></i> <?= $mediaCounts['video'] ?></span>
            <?php endif; ?>
            <?php if (!$mediaCounts): ?><span class="text-muted">—</span><?php endif; ?>
          </td>
          <td>
            <?php if ($j['task_file']): ?>
              <a href="<?= $base ?>/uploads/tasks/<?= $j['task_file'] ?>" class="badge badge-success" target="_blank"><i class="fas fa-download"></i> Ada</a>
            <?php else: ?>
              <span class="badge badge-warning">Belum</span>
            <?php endif; ?>
          </td>
          <td><?= statusBadge($j['status']) ?></td>
          <td><?= $j['reviewer_name'] ? clean($j['reviewer_name']) : '<span class="text-muted">—</span>' ?></td>
          <td style="font-size:.8rem;color:var(--text-muted)"><?= formatDateTime($j['submitted_at']) ?></td>
          <td><button class="btn btn-primary btn-sm" onclick="openReviewModal(<?= htmlspecialchars(json_encode($j)) ?>)"><i class="fas fa-eye"></i> Review</button></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="8" class="text-center text-muted" style="padding:2rem">Tidak ada jurnal ditemukan.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<style>
.media-mini-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(90px,1fr));gap:.5rem;margin-top:.6rem}
.media-mini-item{border-radius:8px;overflow:hidden;aspect-ratio:1;background:var(--card2-bg);border:1px solid var(--border);cursor:pointer;position:relative}
.media-mini-item img{width:100%;height:100%;object-fit:cover;display:block}
.media-mini-item .play-ov{position:absolute;inset:0;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.2rem}
.media-mini-item .type-lbl{position:absolute;bottom:2px;right:3px;background:rgba(0,0,0,.6);color:#fff;font-size:.6rem;padding:.1rem .3rem;border-radius:3px}
</style>

<!-- Modal Review -->
<div class="modal-overlay" id="modalReview" style="display:none">
  <div class="modal-box" style="max-width:680px;max-height:92vh;overflow-y:auto">
    <div class="modal-icon" style="background:rgba(42,157,143,.15);color:var(--success)"><i class="fas fa-book-open"></i></div>
    <div class="modal-title" id="reviewTitle">Review Jurnal</div>
    <div class="modal-subtitle" id="reviewStudent"></div>

    <!-- Isi Jurnal -->
    <div style="background:var(--card2-bg);border-radius:var(--radius);padding:1rem;margin-bottom:1rem;max-height:160px;overflow-y:auto">
      <div style="font-size:.75rem;color:var(--text-muted);margin-bottom:.4rem;text-transform:uppercase;letter-spacing:.5px">Isi Jurnal</div>
      <div id="reviewContent" style="font-size:.87rem;line-height:1.7;white-space:pre-wrap"></div>
    </div>

    <!-- Media Section -->
    <div id="reviewMediaSection" style="display:none;margin-bottom:1rem">
      <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:.5rem">
        <i class="fas fa-photo-video" style="color:var(--accent)"></i> Bukti Foto & Video
      </div>
      <div id="reviewMediaGrid" class="media-mini-grid"></div>
    </div>

    <!-- Task file -->
    <div id="reviewTaskFile" style="display:none;margin-bottom:1rem">
      <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:.5rem"><i class="fas fa-paperclip"></i> File Tugas</div>
      <div id="reviewTaskLink"></div>
    </div>

    <form method="POST">
      <input type="hidden" name="action" value="review">
      <input type="hidden" name="journal_id" id="reviewJournalId">
      <div class="form-group">
        <label class="form-label">Status</label>
        <select name="status" id="reviewStatus" class="form-control">
          <option value="reviewed">Ditinjau</option>
          <option value="approved">Disetujui ✓</option>
          <option value="revision">Perlu Revisi ✗</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Catatan Review</label>
        <textarea name="review_note" id="reviewNote" class="form-control" rows="3" placeholder="Tambahkan catatan atau masukan untuk siswa..."></textarea>
      </div>
      <div style="display:flex;gap:.8rem;justify-content:flex-end">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modalReview')">Batal</button>
        <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Simpan Review</button>
      </div>
    </form>
  </div>
</div>

<?php if ($totalPages > 1): ?>
<?php
$queryStr = http_build_query(array_filter(['q' => $search, 'status' => $filterStatus, 'class_id' => $filterClass]));
?>
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.8rem;margin-bottom:1.5rem;">
  <div class="text-muted" style="font-size:.85rem;">
    Menampilkan <?= min($offset + 1, $totalCount) ?>–<?= min($offset + $perPage, $totalCount) ?> dari <?= $totalCount ?> jurnal
  </div>
  <div style="display:flex;gap:.4rem;flex-wrap:wrap">
    <?php if ($page > 1): ?>
    <a href="?<?= $queryStr ?>&page=<?= $page - 1 ?>" class="btn btn-ghost btn-sm"><i class="fas fa-chevron-left"></i> Sebelumnya</a>
    <?php endif; ?>
    <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
    <a href="?<?= $queryStr ?>&page=<?= $p ?>" class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-ghost' ?>"><?= $p ?></a>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
    <a href="?<?= $queryStr ?>&page=<?= $page + 1 ?>" class="btn btn-ghost btn-sm">Berikutnya <i class="fas fa-chevron-right"></i></a>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Lightbox for review -->
<div id="reviewLightbox" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.92);align-items:center;justify-content:center" onclick="if(event.target===this)document.getElementById('reviewLightbox').style.display='none'">
  <button onclick="document.getElementById('reviewLightbox').style.display='none'" style="position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.1);border:none;border-radius:50%;width:38px;height:38px;color:#fff;font-size:1rem;cursor:pointer"><i class="fas fa-times"></i></button>
  <img id="rlbImg" src="" style="max-width:92vw;max-height:88vh;border-radius:10px;display:none">
  <video id="rlbVid" controls style="max-width:92vw;max-height:85vh;border-radius:10px;display:none"></video>
</div>

<script>
const BASE = '<?= $base ?>';

function openReviewModal(j){
  document.getElementById('reviewJournalId').value = j.id;
  document.getElementById('reviewTitle').textContent = j.title;
  document.getElementById('reviewStudent').textContent = 'Oleh: ' + j.student_name + (j.class_name ? ' | ' + j.class_name : '');
  document.getElementById('reviewContent').textContent = j.content;
  document.getElementById('reviewStatus').value = j.status !== 'pending' ? j.status : 'reviewed';
  document.getElementById('reviewNote').value = j.review_note || '';

  // Task file
  const tf = document.getElementById('reviewTaskFile');
  const tl = document.getElementById('reviewTaskLink');
  if (j.task_file) {
    tl.innerHTML = '<a href="' + BASE + '/uploads/tasks/' + j.task_file + '" target="_blank" class="btn btn-success btn-sm"><i class="fas fa-download"></i> Download Tugas</a>';
    tf.style.display = 'block';
  } else { tf.style.display = 'none'; }

  // Load media via AJAX
  const mediaSection = document.getElementById('reviewMediaSection');
  const mediaGrid    = document.getElementById('reviewMediaGrid');
  mediaSection.style.display = 'none';
  mediaGrid.innerHTML = '<span style="color:var(--text-muted);font-size:.82rem">Memuat...</span>';

  fetch(BASE + '/ajax/get_journal_media.php?journal_id=' + j.id)
    .then(r => r.json())
    .then(data => {
      mediaGrid.innerHTML = '';
      if (data && data.length) {
        mediaSection.style.display = 'block';
        data.forEach(m => {
          const item = document.createElement('div');
          item.className = 'media-mini-item';
          const safeName = m.original_name ? m.original_name.replace(/"/g, '&quot;') : '';
          
          if (m.file_type === 'photo') {
            item.innerHTML = `<img src="${BASE}/uploads/media/${m.file_name}" alt="${safeName}" loading="lazy"><div class="type-lbl">FOTO</div>`;
            item.onclick = () => openReviewLightbox('photo', BASE + '/uploads/media/' + m.file_name);
          } else {
            item.innerHTML = `<video src="${BASE}/uploads/media/${m.file_name}"></video><div class="play-ov"><i class="fas fa-play-circle"></i></div><div class="type-lbl">VIDEO</div>`;
            item.onclick = () => openReviewLightbox('video', BASE + '/uploads/media/' + m.file_name);
          }
          mediaGrid.appendChild(item);
        });
      }
    })
    .catch(() => mediaGrid.innerHTML = '<span class="text-danger">Gagal memuat media.</span>');

  openModal('modalReview');
}

function openReviewLightbox(type, src){
  const lb  = document.getElementById('reviewLightbox');
  const img = document.getElementById('rlbImg');
  const vid = document.getElementById('rlbVid');
  img.style.display = 'none'; vid.style.display = 'none';
  if (type === 'photo') { img.src = src; img.style.display = 'block'; }
  else { vid.src = src; vid.style.display = 'block'; vid.play(); }
  lb.style.display = 'flex';
}

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.getElementById('reviewLightbox').style.display = 'none';
    document.getElementById('rlbVid').pause();
  }
});
</script>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/footer.php'; ?>
