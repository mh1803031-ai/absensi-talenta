<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/functions.php';
requireRole('admin','guru','instruktur');

$pageTitle  = 'Generate Token Absensi';
$activePage = 'token';
$base = '/TUGASPAKDANIL/ABSENSITALENTA';
$role = currentRole();
$rolePath = $role === 'admin' ? 'admin' : ($role === 'guru' ? 'guru' : 'instruktur');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    if ($action === 'generate') {
        $classId  = $_POST['class_id'] ?: null;
        $duration = max(1, min(24, (int)($_POST['duration'] ?? 8)));
        $token    = generateUniqueToken();
        $expiredAt = date('Y-m-d H:i:s', strtotime("+{$duration} hours"));
        $userId   = currentUser()['id'];

        db()->prepare(
            "INSERT INTO attendance_tokens (token, generated_by, class_id, valid_date, expired_at)
             VALUES (?,?,?,CURDATE(),?)"
        )->execute([$token, $userId, $classId, $expiredAt]);

        setFlash('success', "Token <strong>{$token}</strong> berhasil dibuat. Berlaku hingga " . date('H:i', strtotime($expiredAt)));
    }
    if ($action === 'deactivate') {
        db()->prepare("UPDATE attendance_tokens SET is_active = 0 WHERE id = ?")->execute([(int)$_POST['token_id']]);
        setFlash('success', 'Token berhasil dinonaktifkan.');
    }
    if ($action === 'regenerate') {
        $newToken  = generateUniqueToken();
        $duration  = 8;
        $expiredAt = date('Y-m-d H:i:s', strtotime("+{$duration} hours"));
        db()->prepare("UPDATE attendance_tokens SET token=?,expired_at=?,is_active=1 WHERE id=?")
           ->execute([$newToken, $expiredAt, (int)$_POST['token_id']]);
        setFlash('success', "Token berhasil di-regenerate menjadi <strong>{$newToken}</strong>.");
    }
    header("Location: $base/$rolePath/generate_token.php"); exit;
}

$classes = db()->query("SELECT * FROM classes ORDER BY name")->fetchAll();
$tokens  = db()->query(
    "SELECT t.*, u.name AS gen_name, c.name AS class_name,
            (SELECT COUNT(*) FROM attendance_records r WHERE r.token_id = t.id) AS used_count
     FROM attendance_tokens t
     JOIN users u ON u.id = t.generated_by
     LEFT JOIN classes c ON c.id = t.class_id
     ORDER BY t.created_at DESC LIMIT 30"
)->fetchAll();

include $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/header.php';
?>

<div style="display:grid;grid-template-columns:360px 1fr;gap:1.5rem;align-items:start">
  <!-- Generate Card -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fas fa-key text-accent"></i> Buat Token Baru</h3>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="generate">
      <div class="form-group">
        <label class="form-label">Kelas</label>
        <select name="class_id" class="form-control">
          <option value="">Semua Kelas</option>
          <?php foreach ($classes as $c): ?>
          <option value="<?= $c['id'] ?>"><?= clean($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Durasi Berlaku (jam)</label>
        <input type="number" name="duration" class="form-control" value="8" min="1" max="24">
        <div class="form-hint">Token otomatis kedaluwarsa setelah durasi ini.</div>
      </div>
      <button type="submit" class="btn btn-accent btn-block" style="margin-top:1rem">
        <i class="fas fa-key"></i> Generate Token
      </button>
    </form>

    <div class="divider"></div>
    <div style="background:var(--card2-bg);border-radius:var(--radius);padding:1rem;text-align:center">
      <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:.5rem">Token berlaku hari ini</div>
      <?php
        $active = array_values(array_filter($tokens, fn($t) => $t['is_active'] && date('Y-m-d', strtotime($t['created_at'])) === date('Y-m-d')));
        if ($active):
      ?>
      <div style="font-size:2.5rem;font-weight:900;color:var(--accent);letter-spacing:8px"><?= $active[0]['token'] ?></div>
      <div style="font-size:.78rem;color:var(--text-muted);margin-top:.3rem">Exp: <?= date('H:i', strtotime($active[0]['expired_at'])) ?> • <?= $active[0]['used_count'] ?> siswa absen</div>
      <?php else: ?>
      <div style="color:var(--text-muted);font-size:.88rem">—</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tokens Table -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fas fa-list text-accent"></i> Riwayat Token</h3>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Token</th><th>Kelas</th><th>Dibuat Oleh</th><th>Tgl</th><th>Exp</th><th>Digunakan</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php if ($tokens): foreach ($tokens as $t):
            $expired = strtotime($t['expired_at']) < time();
          ?>
          <tr>
            <td><code style="font-size:1.05rem;font-weight:800;color:var(--accent);letter-spacing:3px"><?= $t['token'] ?></code></td>
            <td><?= clean($t['class_name'] ?? 'Semua') ?></td>
            <td><?= clean($t['gen_name']) ?></td>
            <td style="font-size:.8rem"><?= formatDate($t['valid_date']) ?></td>
            <td style="font-size:.8rem"><?= date('H:i', strtotime($t['expired_at'])) ?></td>
            <td><span class="badge badge-info"><?= $t['used_count'] ?> siswa</span></td>
            <td>
              <?php if (!$t['is_active']): ?>
                <span class="badge badge-danger">Nonaktif</span>
              <?php elseif ($expired): ?>
                <span class="badge badge-warning">Kedaluwarsa</span>
              <?php else: ?>
                <span class="badge badge-success">Aktif</span>
              <?php endif; ?>
            </td>
            <td>
              <div style="display:flex;gap:.4rem">
                <form method="POST" style="display:inline">
                  <input type="hidden" name="action" value="regenerate">
                  <input type="hidden" name="token_id" value="<?= $t['id'] ?>">
                  <button type="submit" class="btn btn-warning btn-sm" title="Regenerate"><i class="fas fa-sync"></i></button>
                </form>
                <?php if ($t['is_active']): ?>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="action" value="deactivate">
                  <input type="hidden" name="token_id" value="<?= $t['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm" title="Nonaktifkan"><i class="fas fa-ban"></i></button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="8" class="text-center text-muted" style="padding:2rem">Belum ada token dibuat.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/footer.php'; ?>
