<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$pageTitle  = 'Backup Database';
$activePage = 'backup';
$base = defined('APP_BASE_PATH') ? APP_BASE_PATH : '/TUGASPAKDANIL/ABSENSITALENTA';

// Only accessible from localhost for security
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    die('<div style="text-align:center;padding:4rem;font-family:sans-serif;"><h2>⛔ Akses Ditolak</h2><p>Halaman ini hanya bisa diakses dari komputer server.</p></div>');
}

$message = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'backup') {
    // Use already-defined constants from config/database.php (loaded via require_once at top)
    $dbhost = DB_HOST;
    $dbname = DB_NAME;
    $dbuser = DB_USER;
    $dbpass = DB_PASS;

    $backupDir = __DIR__ . '/../backups/';
    if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);

    $filename  = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath  = $backupDir . $filename;

    // Try mysqldump first
    $passArg = $dbpass ? '--password=' . escapeshellarg($dbpass) : '--password=';
    $cmd = sprintf(
        'mysqldump --host=%s --user=%s %s %s > %s 2>&1',
        escapeshellarg($dbhost),
        escapeshellarg($dbuser),
        $passArg,
        escapeshellarg($dbname),
        escapeshellarg($filepath)
    );
    exec($cmd, $output, $returnCode);

    if ($returnCode === 0 && file_exists($filepath) && filesize($filepath) > 0) {
        $message = "✅ Backup berhasil dibuat: <strong>$filename</strong> (" . number_format(filesize($filepath) / 1024, 1) . " KB)";
        $msgType = 'success';
    } else {
        // Fallback: PHP-based dump using the already-connected db()
        try {
            $pdo = db();
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $sql = "-- Talenta Database Backup\n-- Date: " . date('Y-m-d H:i:s') . "\n-- Database: $dbname\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";
            foreach ($tables as $table) {
                $sql .= "-- ---\n-- Table: $table\n-- ---\n";
                $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
                $sql .= "DROP TABLE IF EXISTS `$table`;\n" . $create[1] . ";\n\n";
                $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                if ($rows) {
                    $cols = '`' . implode('`,`', array_keys($rows[0])) . '`';
                    $sql .= "INSERT INTO `$table` ($cols) VALUES\n";
                    $valParts = [];
                    foreach ($rows as $row) {
                        $vals = array_map(fn($v) => is_null($v) ? 'NULL' : $pdo->quote($v), $row);
                        $valParts[] = '(' . implode(',', $vals) . ')';
                    }
                    $sql .= implode(",\n", $valParts) . ";\n\n";
                }
            }
            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
            file_put_contents($filepath, $sql);
            $message = "✅ Backup berhasil (PHP export): <strong>$filename</strong> (" . number_format(filesize($filepath) / 1024, 1) . " KB)";
            $msgType = 'success';
        } catch (Exception $e) {
            $message = "❌ Backup gagal: " . htmlspecialchars($e->getMessage());
            $msgType = 'danger';
        }
    }
}

if (isset($_GET['download'])) {
    $reqFile = basename($_GET['download']);
    $filePath = __DIR__ . '/../backups/' . $reqFile;
    if (file_exists($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) === 'sql') {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $reqFile . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath); exit;
    }
}

if (isset($_GET['delete_backup'])) {
    $reqFile = basename($_GET['delete_backup']);
    $filePath = __DIR__ . '/../backups/' . $reqFile;
    if (file_exists($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) === 'sql') {
        unlink($filePath);
        setFlash('success', "File backup '$reqFile' berhasil dihapus.");
    }
    header("Location: $base/admin/backup.php"); exit;
}

// List existing backups
$backupDir = __DIR__ . '/../backups/';
$backupFiles = [];
if (is_dir($backupDir)) {
    $files = glob($backupDir . '*.sql');
    foreach ($files as $f) {
        $backupFiles[] = ['name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f)];
    }
    usort($backupFiles, fn($a, $b) => $b['time'] - $a['time']);
}

include __DIR__ . '/../includes/header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
  <h2><i class="fas fa-database text-primary"></i> Backup Database</h2>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $msgType ?>" style="margin-bottom:1.5rem;"><?= $message ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">
  <!-- Create Backup -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fas fa-plus-circle text-success"></i> Buat Backup Baru</h3>
    </div>
    <div style="padding:.5rem 0;">
      <p class="text-muted" style="font-size:.9rem;margin-bottom:1.5rem;">
        Backup akan menyimpan seluruh tabel dan data dalam format SQL yang kompatibel dengan phpMyAdmin dan MySQL CLI.
      </p>
      <form method="POST">
        <input type="hidden" name="action" value="backup">
        <button type="submit" class="btn btn-success btn-block">
          <i class="fas fa-cloud-download-alt"></i> Buat Backup Sekarang
        </button>
      </form>
    </div>
  </div>

  <!-- Info Card -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fas fa-info-circle text-accent"></i> Informasi</h3>
    </div>
    <div style="font-size:.88rem;color:var(--text-muted);line-height:1.8;">
      <p><i class="fas fa-check-circle text-success"></i> File backup disimpan di folder <code>/backups/</code></p>
      <p><i class="fas fa-check-circle text-success"></i> Format: SQL (Excel-ready)</p>
      <p><i class="fas fa-exclamation-triangle text-warning"></i> Disarankan backup secara rutin setiap minggu</p>
      <p><i class="fas fa-shield-alt text-primary"></i> Halaman ini hanya bisa diakses dari server lokal</p>
    </div>
  </div>
</div>

<!-- Backup Files List -->
<div class="card">
  <div class="card-header">
    <h3><i class="fas fa-archive text-accent"></i> Daftar File Backup</h3>
    <span class="badge badge-primary"><?= count($backupFiles) ?> file</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Nama File</th>
          <th>Ukuran</th>
          <th>Waktu Backup</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($backupFiles): foreach ($backupFiles as $i => $f): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><i class="fas fa-file-code text-muted"></i> <strong><?= htmlspecialchars($f['name']) ?></strong></td>
          <td><?= number_format($f['size'] / 1024, 1) ?> KB</td>
          <td style="font-size:.85rem;color:var(--text-muted)"><?= date('d M Y H:i:s', $f['time']) ?></td>
          <td>
            <div style="display:flex;gap:.4rem;flex-wrap:wrap">
              <a href="?download=<?= urlencode($f['name']) ?>" class="btn btn-success btn-sm" title="Unduh">
                <i class="fas fa-download"></i>
              </a>
              <a href="?delete_backup=<?= urlencode($f['name']) ?>" class="btn btn-danger btn-sm"
                 data-confirm="Hapus file backup '<?= htmlspecialchars($f['name']) ?>'?"
                 title="Hapus">
                <i class="fas fa-trash"></i>
              </a>
            </div>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="5" class="text-center text-muted" style="padding:2rem;">Belum ada file backup. Buat backup pertama Anda di atas.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
