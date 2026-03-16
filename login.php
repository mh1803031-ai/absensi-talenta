<?php
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) { redirectByRole(); }
$base = defined('APP_BASE_PATH') ? APP_BASE_PATH : '/TUGASPAKDANIL/ABSENSITALENTA';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — Talenta</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --primary:#1E3A5F;--primary-light:#2d5491;--accent:#F4A261;
  --success:#2A9D8F;--danger:#E63946;--warning:#E9C46A;
  --bg:#0f1e35;--card:#1a2f4a;--card2:#213652;
  --text:#e8edf2;--text-muted:#8fa3b8;--border:#2d4a68;
  --shadow:0 20px 60px rgba(0,0,0,.4);
}
body{font-family:'Inter',sans-serif;background:var(--bg);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem 1rem;}
.bg-anim{position:fixed;inset:0;z-index:0;pointer-events:none}
.bg-anim::before{content:'';position:absolute;width:600px;height:600px;border-radius:50%;background:radial-gradient(circle,rgba(30,58,95,.6) 0%,transparent 70%);top:-200px;left:-200px;animation:float1 8s ease-in-out infinite}
.bg-anim::after{content:'';position:absolute;width:500px;height:500px;border-radius:50%;background:radial-gradient(circle,rgba(244,162,97,.15) 0%,transparent 70%);bottom:-150px;right:-150px;animation:float2 10s ease-in-out infinite}
@keyframes float1{0%,100%{transform:translate(0,0)}50%{transform:translate(40px,30px)}}
@keyframes float2{0%,100%{transform:translate(0,0)}50%{transform:translate(-30px,-40px)}}

.login-wrap{position:relative;z-index:1;width:100%;max-width:460px;padding:1rem}
.logo-area{text-align:center;margin-bottom:2rem}
.logo-icon{width:72px;height:72px;background:linear-gradient(135deg,var(--accent),#e76f51);border-radius:20px;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;box-shadow:0 8px 30px rgba(244,162,97,.3)}
.logo-icon i{font-size:32px;color:#fff}
.logo-title{font-size:2.2rem;font-weight:800;color:var(--text);letter-spacing:-1px}
.logo-sub{color:var(--text-muted);font-size:.9rem;margin-top:.3rem}

.card{background:var(--card);border:1px solid var(--border);border-radius:24px;padding:2.5rem;box-shadow:var(--shadow);backdrop-filter:blur(12px)}
.card h2{color:var(--text);font-size:1.4rem;font-weight:700;margin-bottom:.4rem}
.card p{color:var(--text-muted);font-size:.88rem;margin-bottom:1.8rem}

.form-group{margin-bottom:1.3rem;position:relative}
.form-group label{display:block;color:var(--text-muted);font-size:.82rem;font-weight:500;margin-bottom:.5rem;letter-spacing:.5px;text-transform:uppercase}
.input-wrap{position:relative}
.input-wrap i.icon-left{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:.95rem}
.form-control{width:100%;padding:.85rem 1rem .85rem 2.8rem;background:var(--card2);border:1.5px solid var(--border);border-radius:12px;color:var(--text);font-size:.95rem;font-family:'Inter',sans-serif;transition:all .25s;outline:none}
.form-control:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(244,162,97,.15)}
.form-control::placeholder{color:#4d6580}
.toggle-pw{position:absolute;right:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);cursor:pointer;background:none;border:none;padding:0;font-size:.95rem;transition:color .2s}
.toggle-pw:hover{color:var(--accent)}

.btn-login{width:100%;padding:1rem;background:linear-gradient(135deg,var(--accent),#e76f51);border:none;border-radius:12px;color:#fff;font-size:1rem;font-weight:700;font-family:'Inter',sans-serif;cursor:pointer;letter-spacing:.5px;transition:all .3s;box-shadow:0 6px 20px rgba(244,162,97,.35);position:relative;overflow:hidden;margin-top:.5rem}
.btn-login::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,.15),transparent);opacity:0;transition:opacity .3s}
.btn-login:hover::before{opacity:1}
.btn-login:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(244,162,97,.45)}
.btn-login:active{transform:translateY(0)}

.alert{padding:.85rem 1rem;border-radius:12px;font-size:.88rem;margin-bottom:1.2rem;display:flex;align-items:center;gap:.6rem}
.alert-danger{background:rgba(230,57,70,.15);border:1px solid rgba(230,57,70,.3);color:#ff6b78}
.alert-success{background:rgba(42,157,143,.15);border:1px solid rgba(42,157,143,.3);color:#4ecdc4}

.footer-note{text-align:center;color:var(--text-muted);font-size:.78rem;margin-top:1.5rem}
.footer-note span{color:var(--accent);font-weight:600}

/* Mobile Responsiveness */
@media (max-width: 480px) {
  body { padding: 1rem; }
  .logo-area { margin-bottom: 1.5rem; }
  .logo-icon { width: 56px; height: 56px; margin-bottom: .8rem; border-radius: 16px; }
  .logo-icon i { font-size: 24px; }
  .logo-title { font-size: 1.8rem; }
  .card { padding: 1.5rem; border-radius: 20px; }
  .card h2 { font-size: 1.2rem; }
  .card p { font-size: .82rem; margin-bottom: 1.4rem; }
  .form-group { margin-bottom: 1.1rem; }
  .form-group label { font-size: .78rem; }
  .form-control { padding: .75rem 1rem .75rem 2.5rem; font-size: .9rem; }
  .btn-login { padding: .85rem; font-size: .95rem; margin-top: .3rem; }
  .alert { font-size: .82rem; padding: .75rem; }
}
</style>
</head>
<body>
<div class="bg-anim"></div>
<div class="login-wrap">
  <div class="logo-area">
    <div class="logo-icon"><i class="fas fa-graduation-cap"></i></div>
    <div class="logo-title">Talenta</div>
    <div class="logo-sub">Sistem Absensi &amp; Jurnal Digital</div>
  </div>
  <div class="card">
    <h2>Selamat Datang</h2>
    <p>Masuk ke akun Anda untuk melanjutkan</p>

    <?php if (isset($_GET['error'])): ?>
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?php
          $err = $_GET['error'];
          if ($err === 'invalid') echo 'Username atau password salah.';
          elseif ($err === 'inactive') echo 'Akun Anda tidak aktif. Hubungi Admin.';
          elseif ($err === 'unauthorized') echo 'Anda tidak memiliki akses ke halaman tersebut.';
          else echo 'Terjadi kesalahan. Coba lagi.';
        ?>
      </div>
    <?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'logout'): ?>
      <div class="alert alert-success"><i class="fas fa-check-circle"></i>Anda berhasil keluar.</div>
    <?php endif; ?>

    <form method="POST" action="<?= $base ?>/process/login_process.php">
      <div class="form-group">
        <label>Username</label>
        <div class="input-wrap">
          <i class="fas fa-user icon-left"></i>
          <input type="text" name="username" class="form-control" placeholder="Masukkan username" required autocomplete="username">
        </div>
      </div>
      <div class="form-group">
        <label>Password</label>
        <div class="input-wrap">
          <i class="fas fa-lock icon-left"></i>
          <input type="password" name="password" id="pw" class="form-control" placeholder="Masukkan password" required autocomplete="current-password">
          <button type="button" class="toggle-pw" onclick="togglePw()"><i class="fas fa-eye" id="pwIcon"></i></button>
        </div>
      </div>
      <button type="submit" class="btn-login"><i class="fas fa-sign-in-alt"></i> &nbsp;Masuk</button>
    </form>
  </div>
  <div class="footer-note">&copy; 2025 <span>Talenta</span> &mdash; Sistem Manajemen Kehadiran</div>
</div>
<script>
function togglePw(){
  const p=document.getElementById('pw'),i=document.getElementById('pwIcon');
  if(p.type==='password'){p.type='text';i.className='fas fa-eye-slash';}
  else{p.type='password';i.className='fas fa-eye';}
}
</script>
</body>
</html>
