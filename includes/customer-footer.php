    </div>
  </main>
</div>

<script>
function toggleNotif() {
  document.getElementById('notifPanel').classList.toggle('open');
}
document.addEventListener('click', function(e) {
  const panel = document.getElementById('notifPanel');
  const btn = document.getElementById('notifToggle');
  if (panel && !panel.contains(e.target) && btn && !btn.contains(e.target)) panel.classList.remove('open');
});
function showToast(message, type = 'default') {
  const c = document.getElementById('toastContainer');
  const t = document.createElement('div');
  t.className = 'toast ' + (type==='success'?'success':type==='error'?'error':'');
  t.innerHTML = (type==='success'?'✅ ':type==='error'?'❌ ':'ℹ️ ') + message;
  c.appendChild(t); setTimeout(()=>t.remove(), 4000);
}
function markAllRead() {
  fetch('<?= APP_URL ?>/api/notifications/mark-all-read.php', {method:'POST'})
    .then(()=>{ document.getElementById('notifPanel').innerHTML='<div style="padding:24px;text-align:center;font-size:13px;color:var(--text-secondary)">All caught up!</div>'; });
}
function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.addEventListener('keydown', e => {
  if (e.key==='Escape') document.querySelectorAll('.modal-overlay.open').forEach(m=>m.classList.remove('open'));
});
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('success')) showToast(decodeURIComponent(urlParams.get('success')), 'success');
if (urlParams.get('error')) showToast(decodeURIComponent(urlParams.get('error')), 'error');
function checkResponsive() {
  const btn = document.getElementById('menuBtn');
  if (btn) btn.style.display = window.innerWidth < 768 ? 'flex' : 'none';
}
checkResponsive();
window.addEventListener('resize', checkResponsive);
</script>
</body>
</html>
