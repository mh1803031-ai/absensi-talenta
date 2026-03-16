<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pageTitle  = 'Profil Saya';
$activePage = 'profile';
$base = defined('APP_BASE_PATH') ? APP_BASE_PATH : '/TUGASPAKDANIL/ABSENSITALENTA';

$pdo = db();
$user_id = currentUser()['id'];
$currentUserData = $pdo->query("SELECT u.*, c.name as class_name FROM users u LEFT JOIN classes c ON c.id = u.class_id WHERE u.id = $user_id")->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'change_password') {
        $oldPass = $_POST['old_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        if (password_verify($oldPass, $currentUserData['password_hash'])) {
            if (strlen($newPass) >= 6) {
                if ($newPass === $confirm) {
                    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    if ($stmt->execute([$newHash, $user_id])) {
                        setFlash('success', 'Password berhasil diubah!');
                        $currentUserData = $pdo->query("SELECT u.*, c.name as class_name FROM users u LEFT JOIN classes c ON c.id = u.class_id WHERE u.id = $user_id")->fetch();
                    } else {
                        setFlash('danger', 'Gagal menyimpan password baru.');
                    }
                } else {
                    setFlash('warning', 'Konfirmasi password tidak cocok.');
                }
            } else {
                setFlash('warning', 'Password baru minimal 6 karakter.');
            }
        } else {
            setFlash('danger', 'Password lama salah.');
        }
        header('Location: ' . $base . '/profile.php');
        exit;
    }
}

include __DIR__ . '/includes/header.php';
?>

<div style="max-width:800px;margin: 0 auto;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
    <h2><i class="fas fa-user-circle text-primary"></i> Profil Saya</h2>
  </div>

  <div style="display:grid;grid-template-columns:1fr;gap:1.5rem;">
    <!-- Biodata / Profile Info -->
    <div class="card">
      <div class="card-header">
        <h3><i class="fas fa-id-badge text-accent"></i> Biodata Akun</h3>
      </div>
      <div style="padding:.5rem 0;">
        <table class="table-details" style="width:100%;text-align:left;border-collapse:collapse;">
          <tbody>
            <tr>
              <th style="padding:1rem .5rem;border-bottom:1px solid var(--border);color:var(--text-muted);width:30%">Nama Lengkap</th>
              <td style="padding:1rem .5rem;border-bottom:1px solid var(--border);font-weight:600"><?= clean($currentUserData['name']) ?></td>
            </tr>
            <tr>
              <th style="padding:1rem .5rem;border-bottom:1px solid var(--border);color:var(--text-muted)">Username</th>
              <td style="padding:1rem .5rem;border-bottom:1px solid var(--border)"><code><?= clean($currentUserData['username']) ?></code></td>
            </tr>
            <tr>
              <th style="padding:1rem .5rem;border-bottom:1px solid var(--border);color:var(--text-muted)">Peran (Role)</th>
              <td style="padding:1rem .5rem;border-bottom:1px solid var(--border)">
                <span class="badge badge-primary" style="text-transform:uppercase"><?= clean($currentUserData['role']) ?></span>
              </td>
            </tr>
            <?php if ($currentUserData['role'] === 'siswa'): ?>
            <tr>
              <th style="padding:1rem .5rem;border-bottom:1px solid var(--border);color:var(--text-muted)">Kelas</th>
              <td style="padding:1rem .5rem;border-bottom:1px solid var(--border)"><?= clean($currentUserData['class_name'] ?? '—') ?></td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Change Password Form -->
    <div class="card">
      <div class="card-header">
        <h3><i class="fas fa-key text-accent"></i> Ganti Password</h3>
      </div>
      <form method="POST" action="">
        <input type="hidden" name="action" value="change_password">
        
        <div class="form-group mb-3">
          <label class="form-label">Password Lama</label>
          <input type="password" name="old_password" class="form-control" required placeholder="Masukkan password saat ini">
        </div>
        
        <div class="form-group mb-3">
          <label class="form-label">Password Baru</label>
          <input type="password" name="new_password" class="form-control" required minlength="6" placeholder="Minimal 6 karakter">
        </div>
        
        <div class="form-group mb-3">
          <label class="form-label">Konfirmasi Password Baru</label>
          <input type="password" name="confirm_password" class="form-control" required minlength="6" placeholder="Ulangi password baru">
        </div>
        
        <div style="display:flex;justify-content:flex-end;">
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Password Baru</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>