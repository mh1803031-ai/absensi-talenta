<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('siswa');

// Fetch all materials
$stmt = db()->query("
    SELECT m.*, u.name as author_name 
    FROM materials m 
    JOIN users u ON m.author_id = u.id 
    ORDER BY m.created_at DESC
");
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Materi Pembelajaran';
$activePage = 'materials';
include __DIR__ . '/../includes/header.php';
?>

<div class="card p-0" style="background:transparent;border:none;box-shadow:none">
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.5rem">
    <?php if(count($materials)): foreach($materials as $m): ?>
    <div class="card" style="display:flex;flex-direction:column;transition:transform .2s;border-top:4px solid var(--primary-lt)" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform=''">
      <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:.5rem"><i class="fas fa-clock"></i> <?= formatDateTime($m['created_at']) ?></div>
      <h3 style="font-size:1.15rem;margin-bottom:.5rem;color:var(--text)"><?= clean($m['title']) ?></h3>
      
      <div style="font-size:.85rem;color:var(--text-muted);margin-bottom:1rem;flex:1">
        <?= $m['description'] ? clean($m['description']) : '<em>Tidak ada deksripsi khusus.</em>' ?>
      </div>
      
      <div style="font-size:.8rem;color:var(--info);margin-bottom:1.2rem;font-weight:600">
        <i class="fas fa-user-tie"></i> <?= clean($m['author_name']) ?>
      </div>

      <div style="display:flex;gap:.8rem;flex-wrap:wrap;margin-top:auto border-top:1px solid var(--border);padding-top:1rem">
         <?php if($m['file_name']): ?>
           <a href="/TUGASPAKDANIL/ABSENSITALENTA/uploads/materials/<?= $m['file_name'] ?>" target="_blank" class="btn btn-success btn-sm" style="flex:1;text-align:center"><i class="fas fa-download"></i> Unduh File</a>
         <?php endif; ?>
         <?php if($m['file_url']): ?>
           <a href="<?= htmlspecialchars($m['file_url']) ?>" target="_blank" class="btn btn-info btn-sm" style="flex:1;text-align:center;color:#fff"><i class="fas fa-external-link-alt"></i> Buka Tautan</a>
         <?php endif; ?>
      </div>
    </div>
    <?php endforeach; else: ?>
    <div class="empty-state" style="grid-column:1/-1;background:var(--card-bg);border-radius:var(--radius-lg);padding:4rem 2rem"><i class="fas fa-box-open" style="font-size:3rem;color:var(--text-muted);margin-bottom:1rem"></i><br>Belum ada materi pembelajaran yang tersedia.</div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
