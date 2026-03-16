<?php
// Shared head + topbar for all dashboard pages
// Usage: include with $pageTitle and $activePage set
if (session_status() === PHP_SESSION_NONE) session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/functions.php';
$user = currentUser();
$role = currentRole();
$base = defined('APP_BASE_PATH') ? APP_BASE_PATH : '/TUGASPAKDANIL/ABSENSITALENTA';
$initials = avatarInitials($user['name'] ?? 'U');

$unreadNotifs = 0;
$recentNotifs = [];
if (isLoggedIn() && function_exists('db')) {
    try {
        $nStmt = db()->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
        $nStmt->execute([$user['id']]);
        $recentNotifs = $nStmt->fetchAll();
        
        $cStmt = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $cStmt->execute([$user['id']]);
        $unreadNotifs = (int)$cStmt->fetchColumn();
    } catch (Exception $e) {}
}

$canLogout = true;
if (isLoggedIn() && $role === 'siswa' && function_exists('hasAttendedToday') && function_exists('hasSubmittedJournalToday')) {
    $sid = $user['id'];
    if (hasAttendedToday($sid) && !hasSubmittedJournalToday($sid)) {
        $canLogout = false;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'Talenta') ?> — Talenta</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= $base ?>/assets/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/assets/css/style.css') ?>">
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon"><i class="fas fa-graduation-cap"></i></div>
    <div>
      <div class="logo-text">Talenta</div>
      <div class="logo-sub">Sistem Absensi Digital</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <?php if ($role === 'admin'): ?>
      <div class="nav-section">
        <div class="nav-section-title">Utama</div>
        <a href="<?= $base ?>/admin/dashboard.php"        class="nav-item <?= ($activePage==='dashboard')?'active':'' ?>"><i class="fas fa-home"></i> Dashboard</a>
        <a href="<?= $base ?>/admin/manage_users.php"     class="nav-item <?= ($activePage==='users')?'active':'' ?>"><i class="fas fa-users"></i> Kelola Pengguna</a>
        <a href="<?= $base ?>/admin/manage_classes.php"   class="nav-item <?= ($activePage==='classes')?'active':'' ?>"><i class="fas fa-chalkboard"></i> Kelola Kelas</a>
        <a href="<?= $base ?>/admin/manage_announcements.php" class="nav-item <?= ($activePage==='announcements')?'active':'' ?>"><i class="fas fa-bullhorn"></i> Pengumuman</a>
      </div>
      <div class="nav-section">
        <div class="nav-section-title">Absensi & Jurnal</div>
        <a href="<?= $base ?>/admin/generate_token.php"   class="nav-item <?= ($activePage==='token')?'active':'' ?>"><i class="fas fa-qrcode"></i> Token Absensi</a>
        <a href="<?= $base ?>/admin/journals.php"         class="nav-item <?= ($activePage==='journals')?'active':'' ?>"><i class="fas fa-book-open"></i> Semua Jurnal</a>
      </div>
      <div class="nav-section">
        <div class="nav-section-title">Akademik</div>
        <a href="<?= $base ?>/admin/schedules.php"        class="nav-item <?= ($activePage==='schedules')?'active':'' ?>"><i class="fas fa-calendar-alt"></i> Jadwal Pelajaran</a>
        <a href="<?= $base ?>/admin/quizzes.php"          class="nav-item <?= ($activePage==='quizzes')?'active':'' ?>"><i class="fas fa-clipboard-list"></i> Ulangan</a>
        <a href="<?= $base ?>/admin/reports.php"          class="nav-item <?= ($activePage==='reports')?'active':'' ?>"><i class="fas fa-chart-bar"></i> Laporan</a>
        <a href="<?= $base ?>/admin/manage_materials.php" class="nav-item <?= ($activePage==='materials')?'active':'' ?>"><i class="fas fa-book"></i> Materi Belajar</a>
      </div>
      <div class="nav-section">
        <div class="nav-section-title">Sistem</div>
        <a href="<?= $base ?>/admin/backup.php" class="nav-item <?= ($activePage==='backup')?'active':'' ?>"><i class="fas fa-database"></i> Backup Database</a>
      </div>

    <?php elseif ($role === 'guru'): ?>
      <div class="nav-section">
        <div class="nav-section-title">Utama</div>
        <a href="<?= $base ?>/guru/dashboard.php"        class="nav-item <?= ($activePage==='dashboard')?'active':'' ?>"><i class="fas fa-home"></i> Dashboard</a>
        <a href="<?= $base ?>/guru/manage_students.php"  class="nav-item <?= ($activePage==='students')?'active':'' ?>"><i class="fas fa-user-graduate"></i> Kelola Siswa</a>
        <a href="<?= $base ?>/guru/manage_announcements.php" class="nav-item <?= ($activePage==='announcements')?'active':'' ?>"><i class="fas fa-bullhorn"></i> Pengumuman</a>
      </div>
      <div class="nav-section">
        <div class="nav-section-title">Absensi & Jurnal</div>
        <a href="<?= $base ?>/guru/generate_token.php"   class="nav-item <?= ($activePage==='token')?'active':'' ?>"><i class="fas fa-qrcode"></i> Token Kelas Saya</a>
        <a href="<?= $base ?>/guru/journals.php"         class="nav-item <?= ($activePage==='journals')?'active':'' ?>"><i class="fas fa-book-open"></i> Review Jurnal</a>
      </div>
      <div class="nav-section">
        <div class="nav-section-title">Akademik</div>
        <a href="<?= $base ?>/guru/quizzes.php"          class="nav-item <?= ($activePage==='quizzes')?'active':'' ?>"><i class="fas fa-clipboard-list"></i> Ulangan</a>
        <a href="<?= $base ?>/guru/manage_materials.php" class="nav-item <?= ($activePage==='materials')?'active':'' ?>"><i class="fas fa-book"></i> Materi Belajar</a>
      </div>

    <?php elseif ($role === 'instruktur'): ?>
      <div class="nav-section">
        <div class="nav-section-title">Utama</div>
        <a href="<?= $base ?>/instruktur/dashboard.php"        class="nav-item <?= ($activePage==='dashboard')?'active':'' ?>"><i class="fas fa-home"></i> Dashboard</a>
      </div>
      <div class="nav-section">
        <div class="nav-section-title">Kehadiran & Jurnal</div>
        <a href="<?= $base ?>/instruktur/generate_token.php"   class="nav-item <?= ($activePage==='token')?'active':'' ?>"><i class="fas fa-key"></i> Generate Token</a>
        <a href="<?= $base ?>/instruktur/journals.php"         class="nav-item <?= ($activePage==='journals')?'active':'' ?>"><i class="fas fa-book-open"></i> Jurnal Siswa</a>
        <a href="<?= $base ?>/instruktur/leave_permission.php" class="nav-item <?= ($activePage==='leave')?'active':'' ?>"><i class="fas fa-door-open"></i> Izin Pulang</a>
      </div>
      <div class="nav-section">
        <div class="nav-section-title">Akademik</div>
        <a href="<?= $base ?>/instruktur/quizzes.php"          class="nav-item <?= ($activePage==='quizzes')?'active':'' ?>"><i class="fas fa-clipboard-list"></i> Ulangan</a>
        <a href="<?= $base ?>/instruktur/manage_materials.php" class="nav-item <?= ($activePage==='materials')?'active':'' ?>"><i class="fas fa-book"></i> Materi Belajar</a>
      </div>

    <?php elseif ($role === 'siswa'): ?>
      <div class="nav-section">
        <div class="nav-section-title">Menu Saya</div>
        <a href="<?= $base ?>/siswa/dashboard.php"       class="nav-item <?= ($activePage==='dashboard')?'active':'' ?>"><i class="fas fa-home"></i> Beranda</a>
        <a href="<?= $base ?>/siswa/analytics.php"       class="nav-item <?= ($activePage==='analytics')?'active':'' ?>"><i class="fas fa-chart-line"></i> Statistik Belajar</a>
        <a href="<?= $base ?>/siswa/journal_history.php" class="nav-item <?= ($activePage==='journals')?'active':'' ?>"><i class="fas fa-book-open"></i> Jurnal Saya</a>
        <a href="<?= $base ?>/siswa/quiz.php"            class="nav-item <?= ($activePage==='quiz')?'active':'' ?>"><i class="fas fa-clipboard-list"></i> Ulangan</a>
        <a href="<?= $base ?>/siswa/materials.php"       class="nav-item <?= ($activePage==='materials')?'active':'' ?>"><i class="fas fa-book"></i> Materi Belajar</a>
        <a href="<?= $base ?>/profile.php"               class="nav-item <?= ($activePage==='profile')?'active':'' ?>"><i class="fas fa-user"></i> Profil Saya</a>
      </div>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="avatar"><?= $user['photo'] ? "<img src='{$base}/uploads/photos/{$user['photo']}'>" : $initials ?></div>
      <div class="sidebar-user-info">
        <div class="name"><?= clean($user['name']) ?></div>
        <div class="role"><?= ucfirst($role) ?></div>
      </div>
    </div>
    <div style="display:flex;gap:.5rem;margin-top:.5rem">
      <a href="<?= $base ?>/profile.php" class="btn-ghost" style="flex:1;text-align:center;font-size:.9rem;padding:.5rem;border-radius:var(--radius);border:1px solid var(--border)"><i class="fas fa-user-cog"></i> Profil</a>
      <?php if ($canLogout): ?>
      <a href="<?= $base ?>/logout.php" class="btn-logout" style="flex:1;text-align:center;padding:.5rem;margin:0"><i class="fas fa-sign-out-alt"></i> Keluar</a>
      <?php else: ?>
      <a href="#" onclick="alert('Anda harus mengirimkan jurnal/tugas harian terlebih dahulu sebelum dapat keluar.'); return false;" class="btn-logout" style="flex:1;text-align:center;padding:.5rem;margin:0;background:var(--text-muted);cursor:not-allowed;" title="Keluar (Terkunci)"><i class="fas fa-lock"></i></a>
      <?php endif; ?>
    </div>
  </div>
</aside>
<!-- END SIDEBAR -->

  <div class="main-content">
    <!-- TOPBAR -->
    <div class="topbar">
      <button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
      <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></div>
      
      <div style="display:flex;align-items:center;gap:1.2rem;margin-left:auto;position:relative;">
        
        <!-- Notifikasi -->
        <div class="dropdown" id="notifDropdownToggle" style="position:relative;cursor:pointer;">
          <button class="btn-ghost" style="padding:.4rem .6rem;border-radius:var(--radius);font-size:1.1rem;color:var(--text);position:relative;">
            <i class="fas fa-bell"></i>
            <?php if ($unreadNotifs > 0): ?>
            <span style="position:absolute;top:0;right:0;background:var(--danger);color:#fff;font-size:.65rem;font-weight:bold;padding:.1rem .3rem;border-radius:10px;line-height:1;transform:translate(20%,-20%)"><?= $unreadNotifs > 99 ? '99+' : $unreadNotifs ?></span>
            <?php endif; ?>
          </button>
          
          <div id="notifMenu" style="display:none;position:absolute;top:120%;right:0;width:320px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow-lg);z-index:100;overflow:hidden;">
            <div style="padding:1rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
              <h4 style="margin:0;font-size:1rem;">Notifikasi</h4>
              <?php if ($unreadNotifs > 0): ?>
              <a href="<?= $base ?>/ajax/read_notifs.php" style="font-size:.8rem;color:var(--primary);text-decoration:none;">Tandai semua dibaca</a>
              <?php endif; ?>
            </div>
            <div style="max-height:350px;overflow-y:auto;">
              <?php if ($recentNotifs): foreach ($recentNotifs as $n): ?>
              <a href="<?= clean($n['link']) ?>" style="display:block;padding:1rem;border-bottom:1px solid var(--border);text-decoration:none;color:var(--text);background:<?= $n['is_read'] ? 'transparent' : 'rgba(42,157,143,.05)' ?>;transition:background .2s">
                <div style="font-size:.9rem;font-weight:<?= $n['is_read'] ? '500' : '700' ?>;margin-bottom:.3rem;"><?= clean($n['title']) ?></div>
                <div style="font-size:.8rem;color:var(--text-muted);line-height:1.4;margin-bottom:.4rem;"><?= clean($n['message']) ?></div>
                <div style="font-size:.7rem;color:var(--text-muted);"><i class="fas fa-clock"></i> <?= formatDateTime($n['created_at']) ?></div>
              </a>
              <?php endforeach; else: ?>
              <div style="padding:2rem 1rem;text-align:center;color:var(--text-muted);font-size:.9rem;">Belum ada notifikasi.</div>
              <?php endif; ?>
            </div>
            <div style="padding:.8rem;text-align:center;border-top:1px solid var(--border);background:rgba(0,0,0,.02);">
              <a href="#" style="font-size:.85rem;color:var(--text-muted);text-decoration:none;">Lihat Semua</a>
            </div>
          </div>
        </div>

        <!-- Tema -->
        <button id="themeToggle" class="btn-ghost" style="padding:.4rem .6rem;border-radius:var(--radius);font-size:1.1rem;color:var(--text)" title="Ganti Tema">
          <i class="fas fa-moon" id="themeIcon"></i>
        </button>
        <div class="datetime"><i class="fas fa-clock"></i> <span class="live-time"></span></div>
      </div>
    </div>
    <!-- END TOPBAR -->

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const notifBtn = document.getElementById('notifDropdownToggle');
        const notifMenu = document.getElementById('notifMenu');
        if (notifBtn && notifMenu) {
            notifBtn.addEventListener('click', (e) => {
                if(e.target.closest('#notifMenu')) return; // Don't close if clicking inside menu
                notifMenu.style.display = notifMenu.style.display === 'none' ? 'block' : 'none';
            });
            document.addEventListener('click', (e) => {
                if (!notifBtn.contains(e.target)) {
                    notifMenu.style.display = 'none';
                }
            });
        }
    });
    </script>

  <div class="page-content">
  <?php
  $flash = getFlash();
  if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> flash-alert" style="transition:opacity .4s,transform .4s">
      <i class="fas fa-<?= $flash['type']==='success'?'check-circle':($flash['type']==='danger'?'exclamation-circle':'info-circle') ?>"></i>
      <?= clean($flash['message']) ?>
    </div>
  <?php endif; ?>
