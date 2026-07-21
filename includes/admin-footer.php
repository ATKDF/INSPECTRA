    </div><!-- end page-content -->
  </main><!-- end main -->
</div><!-- end app -->

<script>
// Toggle notification panel
function toggleNotif() {
  const panel = document.getElementById('notifPanel');
  panel.classList.toggle('open');
}
document.addEventListener('click', function(e) {
  const panel = document.getElementById('notifPanel');
  const btn = document.getElementById('notifToggle');
  if (panel && !panel.contains(e.target) && btn && !btn.contains(e.target)) {
    panel.classList.remove('open');
  }
});

// Toast notifications
function showToast(message, type = 'default') {
  const container = document.getElementById('toastContainer');
  const toast = document.createElement('div');
  toast.className = 'toast ' + (type === 'success' ? 'success' : type === 'error' ? 'error' : '');
  toast.innerHTML = (type==='success'?'✅ ':type==='error'?'❌ ':'ℹ️ ') + message;
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 4000);
}

// Mark notification as read
function readNotif(id) {
  fetch('<?= APP_URL ?>/api/notifications/mark-read.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({id})
  });
}

// Mark all notifications as read
function markAllRead() {
  fetch('<?= APP_URL ?>/api/notifications/mark-all-read.php', {method: 'POST'})
    .then(() => { document.getElementById('notifPanel').innerHTML = '<div style="padding:24px;text-align:center;color:var(--text-secondary);font-size:13px;">All caught up!</div>'; });
}

// Sidebar mobile toggle
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

// Modal helpers
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
});

// Show flash messages from URL params
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('success')) showToast(decodeURIComponent(urlParams.get('success')), 'success');
if (urlParams.get('error')) showToast(decodeURIComponent(urlParams.get('error')), 'error');

// Responsive
function checkResponsive() {
  const btn = document.getElementById('menuBtn');
  if (btn) btn.style.display = window.innerWidth < 768 ? 'flex' : 'none';
}
checkResponsive();
window.addEventListener('resize', checkResponsive);
</script>
</body>
</html>
