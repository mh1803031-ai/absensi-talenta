// Update clock
function updateClock() {
  const now = new Date();
  const timeStr = now.toLocaleTimeString('id-ID');
  document.querySelectorAll('.live-time').forEach(el => el.textContent = timeStr);
  document.querySelectorAll('.live-date').forEach(el => {
    el.textContent = now.toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  });
}
updateClock();
setInterval(updateClock, 1000);

// ── Sidebar / Hamburger with backdrop ───────────────────────
const ham = document.getElementById('hamburger');
const sidebar = document.querySelector('.sidebar');

// Inject backdrop element once
let backdrop = document.querySelector('.sidebar-backdrop');
if (!backdrop) {
  backdrop = document.createElement('div');
  backdrop.className = 'sidebar-backdrop';
  document.body.appendChild(backdrop);
}

function openSidebar() {
  sidebar.classList.add('open');
  backdrop.classList.add('show');
  document.body.style.overflow = 'hidden'; // prevent scroll behind overlay
}

function closeSidebar() {
  sidebar.classList.remove('open');
  backdrop.classList.remove('show');
  document.body.style.overflow = '';
}

if (ham && sidebar) {
  ham.addEventListener('click', e => {
    e.stopPropagation();
    sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
  });
  backdrop.addEventListener('click', closeSidebar);
}

// Close sidebar on resize to desktop
window.addEventListener('resize', () => {
  if (window.innerWidth > 768) { closeSidebar(); }
});

// Close sidebar when a nav link is clicked (mobile UX)
sidebar?.querySelectorAll('.nav-item').forEach(link => {
  link.addEventListener('click', () => {
    if (window.innerWidth <= 768) closeSidebar();
  });
});

// ── Flash auto-dismiss ────────────────────────────────────────
document.querySelectorAll('.flash-alert').forEach(el => {
  setTimeout(() => {
    el.style.transition = 'opacity .4s, transform .4s';
    el.style.opacity = '0';
    el.style.transform = 'translateY(-10px)';
    setTimeout(() => el.remove(), 400);
  }, 4500);
});

// ── Confirm delete (Custom Modal — replaces native window.confirm) ────────────────
let _pendingDeleteForm = null;

function _buildConfirmModal() {
  if (document.getElementById('_confirmModal')) return;
  const overlay = document.createElement('div');
  overlay.id = '_confirmModal';
  overlay.style.cssText = 'display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.55);backdrop-filter:blur(4px);align-items:center;justify-content:center;';
  overlay.innerHTML = `
    <div style="background:var(--card-bg,#1a2f4a);border:1px solid var(--border,#2d4a68);border-radius:16px;padding:2rem 2rem 1.5rem;max-width:380px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.5);text-align:center;">
      <div style="width:52px;height:52px;background:rgba(230,57,70,.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;font-size:1.4rem;color:#ef4444;">
        <i class="fas fa-exclamation-triangle"></i>
      </div>
      <div id="_confirmMsg" style="color:var(--text,#e8edf2);font-size:.95rem;margin-bottom:1.5rem;line-height:1.5;"></div>
      <div style="display:flex;gap:.8rem;justify-content:center;">
        <button id="_confirmCancel" style="padding:.6rem 1.4rem;background:transparent;border:1px solid var(--border,#2d4a68);border-radius:8px;color:var(--text-muted,#8fa3b8);cursor:pointer;font-size:.9rem;">Batal</button>
        <button id="_confirmOk" style="padding:.6rem 1.4rem;background:#ef4444;border:none;border-radius:8px;color:#fff;cursor:pointer;font-size:.9rem;font-weight:600;">Ya, Hapus</button>
      </div>
    </div>`;
  document.body.appendChild(overlay);
  overlay.style.display = 'none';
  document.getElementById('_confirmCancel').addEventListener('click', () => { overlay.style.display = 'none'; _pendingDeleteForm = null; });
  document.getElementById('_confirmOk').addEventListener('click', () => { overlay.style.display = 'none'; if (_pendingDeleteForm) _pendingDeleteForm.submit(); });
  overlay.addEventListener('click', e => { if (e.target === overlay) { overlay.style.display = 'none'; _pendingDeleteForm = null; } });
}

_buildConfirmModal();

document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', e => {
    e.preventDefault();
    const msg = el.dataset.confirm || 'Yakin ingin menghapus data ini?';
    document.getElementById('_confirmMsg').textContent = msg;
    // Find the closest form to submit
    _pendingDeleteForm = el.closest('form') || null;
    // If it's a direct link with data-confirm, set redirect
    if (!_pendingDeleteForm && el.href) { _pendingDeleteForm = { submit: () => { window.location.href = el.href; } }; }
    document.getElementById('_confirmModal').style.display = 'flex';
  });
});

// ── Modal helpers ─────────────────────────────────────────────
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

// Close modals on overlay click and ESC
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) {
    // Only close if it's NOT the token modal (that one is non-dismissible)
    if (e.target.id !== 'tokenModal') {
      e.target.style.display = 'none';
    }
  }
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay').forEach(m => {
      if (m.id !== 'tokenModal') m.style.display = 'none';
    });
  }
});

// ── Theme Switcher ────────────────────────────────────────────
// Apply theme immediately to prevent flash (this works if script is loaded, 
// but since main.js is at bottom, we also need it in head or just let it run fast here)
const savedTheme = localStorage.getItem('talenta_theme') || 'dark';
if (savedTheme === 'light') document.documentElement.setAttribute('data-theme', 'light');

document.addEventListener('DOMContentLoaded', () => {
  const themeToggleBtn = document.getElementById('themeToggle');
  const themeIcon = document.getElementById('themeIcon');

  if (themeToggleBtn && themeIcon) {
    // Init icon
    if (document.documentElement.getAttribute('data-theme') === 'light') {
      themeIcon.className = 'fas fa-sun';
    }

    themeToggleBtn.addEventListener('click', () => {
      const currentTheme = document.documentElement.getAttribute('data-theme');
      if (currentTheme === 'light') {
        document.documentElement.removeAttribute('data-theme');
        localStorage.setItem('talenta_theme', 'dark');
        themeIcon.className = 'fas fa-moon';
      } else {
        document.documentElement.setAttribute('data-theme', 'light');
        localStorage.setItem('talenta_theme', 'light');
        themeIcon.className = 'fas fa-sun';
      }
    });
  }
});
