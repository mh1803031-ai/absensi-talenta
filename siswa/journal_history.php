<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('siswa');

$base      = '/TUGASPAKDANIL/ABSENSITALENTA';
$studentId = currentUser()['id'];

$page    = max(1,(int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page-1)*$perPage;

$total = countTable('journals','student_id=?',[$studentId]);
$pages = max(1,(int)ceil($total/$perPage));

$stmt = db()->prepare(
    "SELECT j.*, r.attended_at, u.name AS reviewer_name
     FROM journals j
     JOIN attendance_records r ON r.id = j.attendance_id
     LEFT JOIN users u ON u.id = j.reviewed_by
     WHERE j.student_id = ?
     ORDER BY j.submitted_at DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute([$studentId]);
$journals = $stmt->fetchAll();

$journalIds = array_column($journals, 'id');
$mediaMap   = [];
if ($journalIds) {
    $inClause = implode(',', array_map('intval', $journalIds));
    $mediaRows = db()->query(
        "SELECT * FROM journal_media WHERE journal_id IN ($inClause) ORDER BY uploaded_at ASC"
    )->fetchAll();
    foreach ($mediaRows as $m) {
        $mediaMap[$m['journal_id']][] = $m;
    }
}

$pageTitle  = 'Riwayat Jurnal';
$activePage = 'journals';
include __DIR__ . '/../includes/header.php';
?>

<style>
.media-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px,1fr));
    gap: .6rem;
    margin-top: .8rem;
}
.media-thumb {
    position: relative;
    border-radius: var(--radius);
    overflow: hidden;
    aspect-ratio: 1;
    background: var(--card2-bg);
    border: 1px solid var(--border);
    cursor: pointer;
    transition: transform .2s;
}
.media-thumb:hover { transform: scale(1.04); }
.media-thumb img {
    width: 100%; height: 100%; object-fit: cover; display: block;
}
.media-thumb .play-icon {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    background: rgba(0,0,0,.4);
    font-size: 1.6rem; color: #fff;
}
.media-thumb .type-badge {
    position: absolute; top: 4px; right: 4px;
    background: rgba(0,0,0,.6); color: #fff;
    font-size: .65rem; padding: .1rem .35rem; border-radius: 4px;
}

/* Lightbox */
.lightbox-overlay {
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,.92);
    display: flex; align-items: center; justify-content: center;
}
.lightbox-overlay img,
.lightbox-overlay video {
    max-width: 95vw; max-height: 92vh;
    border-radius: var(--radius);
    box-shadow: 0 0 60px rgba(0,0,0,.8);
}
.lightbox-close {
    position: absolute; top: 1rem; right: 1.2rem;
    background: rgba(255,255,255,.12); border: none; border-radius: 50%;
    width: 40px; height: 40px; color: #fff; font-size: 1.1rem;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.lightbox-nav {
    position: absolute; top: 50%; transform: translateY(-50%);
    background: rgba(255,255,255,.1); border: none; border-radius: 50%;
    width: 44px; height: 44px; color: #fff; font-size: 1.1rem;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.lightbox-nav.prev { left: 1rem; }
.lightbox-nav.next { right: 1rem; }
.lightbox-counter {
    position: absolute; bottom: 1rem; left: 50%; transform: translateX(-50%);
    background: rgba(0,0,0,.5); color: #fff; padding: .3rem .8rem;
    border-radius: 50px; font-size: .82rem;
}
</style>

<div class="card">
  <div class="card-header">
    <h3><i class="fas fa-book-open text-accent"></i> Riwayat Jurnal Saya</h3>
    <span class="badge badge-info"><?= $total ?> total</span>
  </div>

  <?php if ($journals): ?>
  <?php foreach ($journals as $j):
    $media  = $mediaMap[$j['id']] ?? [];
    $photos = array_values(array_filter($media, fn($m) => $m['file_type'] === 'photo'));
    $videos = array_values(array_filter($media, fn($m) => $m['file_type'] === 'video'));
  ?>
  <div class="journal-card" style="background:var(--card2-bg);border-color:var(--border);margin-bottom:.8rem">
    <!-- Header -->
    <div class="journal-card-header" style="border-bottom:1px solid var(--border)">
      <div style="flex:1">
        <strong><?= clean($j['title']) ?></strong>
        <div style="font-size:.78rem;color:var(--text-muted);margin-top:.2rem">
          <i class="fas fa-calendar-alt"></i> <?= formatDate($j['submitted_at']) ?>
          <?php if ($j['reviewer_name']): ?>
          &nbsp;|&nbsp; <i class="fas fa-user-check"></i> <?= clean($j['reviewer_name']) ?>
          <?php endif; ?>
          <?php if ($photos): ?>
          &nbsp;|&nbsp; <i class="fas fa-image" style="color:var(--accent)"></i> <?= count($photos) ?> foto
          <?php endif; ?>
          <?php if ($videos): ?>
          &nbsp;|&nbsp; <i class="fas fa-video" style="color:#7ec8e3"></i> <?= count($videos) ?> video
          <?php endif; ?>
        </div>
      </div>
      <div style="display:flex;gap:.5rem;align-items:center">
        <?php if ($j['task_file']): ?>
        <a href="<?= $base ?>/uploads/tasks/<?= $j['task_file'] ?>" class="badge badge-success" target="_blank"><i class="fas fa-paperclip"></i> Tugas</a>
        <?php endif; ?>
        <?php if ($j['status'] === 'pending'): ?>
        <button class="btn btn-ghost btn-sm text-danger" onclick="deleteJournal(<?= $j['id'] ?>)" title="Hapus Jurnal">
          <i class="fas fa-trash-alt"></i>
        </button>
        <?php endif; ?>
        <?php if ($j['status'] === 'revision'): ?>
        <a href="<?= $base ?>/siswa/journal_edit.php?id=<?= $j['id'] ?>" class="btn btn-ghost btn-sm text-accent" title="Revisi Jurnal">
          <i class="fas fa-edit"></i> Revisi
        </a>
        <?php endif; ?>
        <?= statusBadge($j['status']) ?>
        <button class="btn btn-ghost btn-sm" onclick="toggleContent(<?= $j['id'] ?>)">
          <i class="fas fa-chevron-down" id="icon-<?= $j['id'] ?>"></i>
        </button>
      </div>
    </div>

    <!-- Body -->
    <div id="content-<?= $j['id'] ?>" class="journal-card-body" style="display:none">
      <!-- Journal text -->
      <div style="white-space:pre-wrap;font-size:.88rem;line-height:1.7;color:var(--text);margin-bottom:.8rem"><?= clean($j['content']) ?></div>

      <!-- Review note -->
      <?php if ($j['review_note']): ?>
      <div style="background:rgba(244,162,97,.08);border:1px solid rgba(244,162,97,.2);border-radius:var(--radius);padding:.8rem;font-size:.84rem;margin-bottom:.8rem">
        <strong style="color:var(--accent)"><i class="fas fa-comment-alt"></i> Catatan Reviewer:</strong>
        <div style="margin-top:.4rem;color:var(--text)"><?= clean($j['review_note']) ?></div>
      </div>
      <?php endif; ?>

      <!-- Photos -->
      <?php if ($photos): ?>
      <div style="margin-bottom:.8rem">
        <div style="font-size:.78rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:.5rem">
          <i class="fas fa-camera" style="color:var(--accent)"></i> Foto Bukti (<?= count($photos) ?>)
        </div>
        <div class="media-grid" id="photoGrid-<?= $j['id'] ?>">
          <?php foreach ($photos as $pi => $p): ?>
          <div class="media-thumb" onclick="openLightbox('photo', <?= $j['id'] ?>, <?= $pi ?>)"
               data-src="<?= $base ?>/uploads/media/<?= $p['file_name'] ?>"
               data-name="<?= clean($p['original_name'] ?? $p['file_name']) ?>">
            <img src="<?= $base ?>/uploads/media/<?= $p['file_name'] ?>" alt="<?= clean($p['original_name'] ?? '') ?>" loading="lazy">
            <div class="type-badge">FOTO</div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Videos -->
      <?php if ($videos): ?>
      <div>
        <div style="font-size:.78rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:.5rem">
          <i class="fas fa-video" style="color:#7ec8e3"></i> Video Bukti (<?= count($videos) ?>)
        </div>
        <div class="media-grid">
          <?php foreach ($videos as $vi => $v): ?>
          <div class="media-thumb" onclick="openVideoLightbox('<?= $base ?>/uploads/media/<?= $v['file_name'] ?>', '<?= clean($v['original_name'] ?? $v['file_name']) ?>')">
            <video src="<?= $base ?>/uploads/media/<?= $v['file_name'] ?>" preload="metadata" muted style="pointer-events:none"></video>
            <div class="play-icon"><i class="fas fa-play-circle"></i></div>
            <div class="type-badge">VIDEO</div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!$photos && !$videos): ?>
      <div style="font-size:.8rem;color:var(--text-muted)"><i class="fas fa-info-circle"></i> Tidak ada bukti foto/video pada jurnal ini.</div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- Pagination -->
  <?php if ($pages > 1): ?>
  <div style="display:flex;justify-content:center;gap:.5rem;margin-top:1rem">
    <?php for ($p=1;$p<=$pages;$p++): ?>
    <a href="?page=<?= $p ?>" class="btn btn-sm <?= $p==$page?'btn-accent':'btn-ghost' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

  <?php else: ?>
  <div class="empty-state"><i class="fas fa-book"></i>Belum ada jurnal yang dikirim.</div>
  <?php endif; ?>
</div>

<!-- LIGHTBOX for photos -->
<div class="lightbox-overlay" id="photoLightbox" style="display:none" onclick="if(event.target===this)closeLightbox()">
  <button class="lightbox-close" onclick="closeLightbox()"><i class="fas fa-times"></i></button>
  <button class="lightbox-nav prev" onclick="navPhoto(-1)"><i class="fas fa-chevron-left"></i></button>
  <img id="lightboxImg" src="" alt="">
  <button class="lightbox-nav next" onclick="navPhoto(1)"><i class="fas fa-chevron-right"></i></button>
  <div class="lightbox-counter" id="lightboxCounter"></div>
</div>

<!-- LIGHTBOX for video -->
<div class="lightbox-overlay" id="videoLightbox" style="display:none" onclick="if(event.target===this)closeVideoLightbox()">
  <button class="lightbox-close" onclick="closeVideoLightbox()"><i class="fas fa-times"></i></button>
  <video id="lightboxVideo" controls style="max-width:95vw;max-height:85vh;border-radius:12px"></video>
</div>

<script>
function toggleContent(id){
  const el=document.getElementById('content-'+id);
  const ic=document.getElementById('icon-'+id);
  if(el.style.display==='none'){el.style.display='block';ic.className='fas fa-chevron-up';}
  else{el.style.display='none';ic.className='fas fa-chevron-down';}
}

function deleteJournal(id) {
    if (confirm('Yakin ingin menghapus jurnal ini? Tindakan ini tidak dapat dibatalkan.')) {
        fetch('<?= $base ?>/ajax/delete_journal.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'journal_id=' + id
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) location.reload();
            else alert(res.message);
        })
        .catch(() => alert('Terjadi kesalahan koneksi.'));
    }
}

let currentPhotos = [];
let currentIdx    = 0;

function openLightbox(type, jid, idx) {
  const grid    = document.getElementById('photoGrid-' + jid);
  const thumbs  = grid ? Array.from(grid.querySelectorAll('.media-thumb')) : [];
  currentPhotos = thumbs.map(t => ({ src: t.dataset.src, name: t.dataset.name }));
  currentIdx    = idx;
  showPhoto();
  document.getElementById('photoLightbox').style.display = 'flex';
  document.addEventListener('keydown', lbKeyHandler);
}

function showPhoto() {
  const p = currentPhotos[currentIdx];
  document.getElementById('lightboxImg').src = p.src;
  document.getElementById('lightboxCounter').textContent = (currentIdx+1) + ' / ' + currentPhotos.length + (p.name ? ' — ' + p.name : '');
}

function navPhoto(dir) {
  currentIdx = (currentIdx + dir + currentPhotos.length) % currentPhotos.length;
  showPhoto();
}

function closeLightbox() {
  document.getElementById('photoLightbox').style.display = 'none';
  document.removeEventListener('keydown', lbKeyHandler);
}

function lbKeyHandler(e) {
  if (e.key === 'ArrowLeft')  navPhoto(-1);
  if (e.key === 'ArrowRight') navPhoto(1);
  if (e.key === 'Escape')     closeLightbox();
}

function openVideoLightbox(src, name) {
  const v = document.getElementById('lightboxVideo');
  v.src = src;
  v.play();
  document.getElementById('videoLightbox').style.display = 'flex';
}
function closeVideoLightbox() {
  const v = document.getElementById('lightboxVideo');
  v.pause(); v.src = '';
  document.getElementById('videoLightbox').style.display = 'none';
}
document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && document.getElementById('videoLightbox').style.display !== 'none') closeVideoLightbox();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>