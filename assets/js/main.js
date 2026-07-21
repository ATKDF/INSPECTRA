/**
 * INSPECTRA — Global JavaScript
 */
'use strict';

// ── Sidebar Toggle ────────────────────────────────────────
const sidebar       = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebarToggle');

if (sidebarToggle && sidebar) {
  sidebarToggle.addEventListener('click', () => {
    sidebar.classList.toggle('mobile-open');
    sidebarToggle.setAttribute('aria-expanded', sidebar.classList.contains('mobile-open'));
  });
  // Close on outside click (mobile)
  document.addEventListener('click', e => {
    if (window.innerWidth <= 768 &&
        !sidebar.contains(e.target) &&
        !sidebarToggle.contains(e.target)) {
      sidebar.classList.remove('mobile-open');
    }
  });
}

// ── Notification Dropdown ────────────────────────────────
const notifBtn      = document.getElementById('notifBtn');
const notifDropdown = document.getElementById('notifDropdown');

if (notifBtn && notifDropdown) {
  notifBtn.addEventListener('click', e => {
    e.stopPropagation();
    const open = notifDropdown.classList.toggle('open');
    notifBtn.setAttribute('aria-expanded', open);
  });
  document.addEventListener('click', e => {
    if (!notifDropdown.contains(e.target) && e.target !== notifBtn) {
      notifDropdown.classList.remove('open');
      notifBtn.setAttribute('aria-expanded', false);
    }
  });

  // Click notification item
  notifDropdown.querySelectorAll('.notif-item').forEach(item => {
    item.addEventListener('click', () => {
      const id  = item.dataset.id;
      const url = item.dataset.url;
      if (id) {
        fetch(`${BASE_URL}/api/notifications.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
          body: JSON.stringify({ action: 'mark_read', id })
        });
        item.classList.remove('unread');
        item.querySelector('.notif-dot-icon')?.remove();
      }
      if (url && url !== '#') window.location.href = url;
    });
  });

  // Mark all read
  document.getElementById('markAllRead')?.addEventListener('click', e => {
    e.preventDefault();
    fetch(`${BASE_URL}/api/notifications.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
      body: JSON.stringify({ action: 'mark_all_read' })
    }).then(() => {
      notifDropdown.querySelectorAll('.notif-item').forEach(i => {
        i.classList.remove('unread');
        i.querySelector('.notif-dot-icon')?.remove();
      });
      document.querySelectorAll('.notif-dot, .nav-badge').forEach(el => el.remove());
    });
  });
}

// ── Alert Dismiss ────────────────────────────────────────
document.addEventListener('click', e => {
  if (e.target.matches('.alert-close')) {
    e.target.closest('.alert')?.remove();
  }
});

// Auto-dismiss success alerts
document.querySelectorAll('.alert-success').forEach(alert => {
  setTimeout(() => alert.remove(), 5000);
});

// ── Tabs ─────────────────────────────────────────────────
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const group = btn.closest('[data-tabs]') || btn.parentElement.parentElement;
    const target = btn.dataset.tab;
    group.querySelectorAll('.tab-btn').forEach(b => {
      b.classList.remove('active');
      b.setAttribute('aria-selected', false);
    });
    btn.classList.add('active');
    btn.setAttribute('aria-selected', true);
    document.querySelectorAll(`.tab-content`).forEach(c => c.classList.remove('active'));
    document.getElementById(target)?.classList.add('active');
  });
});

// ── Modals ────────────────────────────────────────────────
function openModal(id) {
  const overlay = document.getElementById(id);
  if (overlay) {
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
    overlay.querySelector('[autofocus]')?.focus();
  }
}
function closeModal(id) {
  const overlay = document.getElementById(id);
  if (overlay) {
    overlay.classList.remove('open');
    document.body.style.overflow = '';
  }
}

document.addEventListener('click', e => {
  if (e.target.matches('[data-modal-open]')) openModal(e.target.dataset.modalOpen);
  if (e.target.matches('[data-modal-close]')) closeModal(e.target.dataset.modalClose);
  if (e.target.matches('.modal-overlay')) closeModal(e.target.id);
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.open').forEach(m => closeModal(m.id));
  }
});

// ── AJAX Form Helper ──────────────────────────────────────
async function postJson(url, data) {
  const res = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': CSRF_TOKEN,
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: JSON.stringify(data)
  });
  return res.json();
}

async function postForm(url, formData) {
  formData.append('{CSRF_TOKEN_NAME}', CSRF_TOKEN);
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: formData
  });
  return res.json();
}

// ── Toast Notifications ───────────────────────────────────
function showToast(message, type = 'success', duration = 4000) {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.style.cssText = 'position:fixed;top:80px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:10px;max-width:380px;';
    document.body.appendChild(container);
  }
  const toast = document.createElement('div');
  toast.className = `alert alert-${type}`;
  toast.style.cssText = 'margin:0;box-shadow:0 10px 25px rgba(0,0,0,0.15);animation:slideIn 0.3s ease;';
  toast.innerHTML = message + '<button type="button" class="alert-close">&times;</button>';
  container.appendChild(toast);
  setTimeout(() => toast.remove(), duration);
}

// ── Confirm Dialog ────────────────────────────────────────
function confirmAction(message, callback) {
  if (confirm(message)) callback();
}

// ── Currency Format ───────────────────────────────────────
function formatCurrency(amount, symbol = '₵') {
  return symbol + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

// ── Date helpers ──────────────────────────────────────────
function formatDate(dateStr) {
  return new Date(dateStr).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });
}

// ── Geolocation capture ───────────────────────────────────
function getGeolocation() {
  return new Promise((resolve) => {
    if (!navigator.geolocation) return resolve({ lat: null, lng: null });
    navigator.geolocation.getCurrentPosition(
      pos => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude }),
      ()  => resolve({ lat: null, lng: null }),
      { timeout: 8000 }
    );
  });
}

// ── Image Preview ─────────────────────────────────────────
document.querySelectorAll('[data-preview-target]').forEach(input => {
  input.addEventListener('change', function() {
    const targetId = this.dataset.previewTarget;
    const img      = document.getElementById(targetId);
    if (img && this.files[0]) {
      img.src = URL.createObjectURL(this.files[0]);
      img.style.display = 'block';
    }
  });
});

// ── Animate on scroll ─────────────────────────────────────
if ('IntersectionObserver' in window) {
  const io = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.classList.add('visible');
        io.unobserve(e.target);
      }
    });
  }, { threshold: 0.1 });
  document.querySelectorAll('.animate-in').forEach(el => io.observe(el));
}

// ── Slide-in animation ────────────────────────────────────
const style = document.createElement('style');
style.textContent = `
  @keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to   { transform: translateX(0);   opacity: 1; }
  }
  .animate-in { opacity: 0; transform: translateY(16px); transition: opacity 0.4s ease, transform 0.4s ease; }
  .animate-in.visible { opacity: 1; transform: translateY(0); }
`;
document.head.appendChild(style);
