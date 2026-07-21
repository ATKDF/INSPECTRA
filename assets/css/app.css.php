  :root {
    --glass: rgba(255,255,255,0.72);
    --glass-border: rgba(255,255,255,0.45);
    --blur: blur(20px);
    --primary: #1a1f36;
    --accent: #3b6ef8;
    --accent2: #00c9a7;
    --accent3: #f97316;
    --danger: #ef4444;
    --warn: #f59e0b;
    --success: #10b981;
    --text: #0f1117;
    --text-secondary: #6b7280;
    --text-tertiary: #9ca3af;
    --surface: #f8f9fc;
    --surface2: #ffffff;
    --border: rgba(0,0,0,0.07);
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
    --shadow-md: 0 4px 16px rgba(0,0,0,0.08), 0 2px 6px rgba(0,0,0,0.05);
    --shadow-lg: 0 20px 60px rgba(0,0,0,0.1), 0 8px 24px rgba(0,0,0,0.06);
    --radius: 16px; --radius-sm: 10px; --radius-xs: 6px;
    --sidebar-w: 260px;
    --font: 'DM Sans', sans-serif;
    --mono: 'DM Mono', monospace;
  }
  * { margin:0;padding:0;box-sizing:border-box; }
  body { font-family:var(--font);background:var(--surface);color:var(--text);min-height:100vh;overflow-x:hidden;-webkit-font-smoothing:antialiased; }
  .bg-mesh { position:fixed;inset:0;z-index:0;pointer-events:none;background:radial-gradient(ellipse 80% 60% at 10% -10%,rgba(59,110,248,0.10) 0%,transparent 70%),radial-gradient(ellipse 60% 60% at 90% 110%,rgba(0,201,167,0.08) 0%,transparent 70%),linear-gradient(160deg,#f0f4ff 0%,#f8f9fc 50%,#f0fdf9 100%); }
  .app { display:flex;min-height:100vh;position:relative;z-index:1; }
  .sidebar { width:var(--sidebar-w);min-height:100vh;background:var(--glass);backdrop-filter:var(--blur);-webkit-backdrop-filter:var(--blur);border-right:1px solid var(--glass-border);display:flex;flex-direction:column;position:fixed;left:0;top:0;bottom:0;z-index:100;padding:0 0 24px;transition:transform 0.3s cubic-bezier(0.4,0,0.2,1);overflow-y:auto; }
  .sidebar-logo { padding:28px 24px 20px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--border); }
  .logo-icon { width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--accent),#6366f1);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(59,110,248,0.35); }
  .logo-icon svg { width:20px;height:20px; }
  .logo-text { font-size:17px;font-weight:600;letter-spacing:-0.4px;color:var(--text); }
  .logo-sub { font-size:10px;color:var(--text-secondary);font-weight:400;letter-spacing:0.5px;text-transform:uppercase; }
  .sidebar-section-label { font-size:10px;font-weight:600;letter-spacing:0.8px;text-transform:uppercase;color:var(--text-tertiary);padding:20px 24px 8px; }
  .nav-item { display:flex;align-items:center;gap:12px;padding:10px 16px 10px 20px;margin:1px 10px;border-radius:var(--radius-sm);cursor:pointer;transition:all 0.18s ease;font-size:13.5px;font-weight:400;color:var(--text-secondary);position:relative;text-decoration:none; }
  .nav-item:hover { background:rgba(59,110,248,0.06);color:var(--accent); }
  .nav-item.active { background:rgba(59,110,248,0.1);color:var(--accent);font-weight:500; }
  .nav-item.active::before { content:'';position:absolute;left:-10px;top:50%;transform:translateY(-50%);width:3px;height:22px;border-radius:0 2px 2px 0;background:var(--accent); }
  .nav-icon { width:18px;height:18px;flex-shrink:0;opacity:0.7; }
  .nav-item.active .nav-icon,.nav-item:hover .nav-icon { opacity:1; }
  .nav-badge { margin-left:auto;background:var(--accent);color:white;font-size:10px;font-weight:600;padding:2px 6px;border-radius:20px;min-width:18px;text-align:center; }
  .nav-badge.warn { background:var(--warn); }
  .nav-badge.success { background:var(--success); }
  .sidebar-bottom { margin-top:auto;padding:16px 14px 0;border-top:1px solid var(--border); }
  .user-card { display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:var(--radius-sm);cursor:pointer;transition:background 0.15s; }
  .user-card:hover { background:rgba(0,0,0,0.04); }
  .avatar { width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#3b6ef8,#6366f1);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:600;color:white;flex-shrink:0; }
  .user-info { flex:1;min-width:0; }
  .user-name { font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
  .user-role { font-size:11px;color:var(--text-tertiary); }
  .user-chevron { width:14px;height:14px;color:var(--text-tertiary); }
  .main { margin-left:var(--sidebar-w);flex:1;min-height:100vh;display:flex;flex-direction:column; }
  .topbar { height:68px;background:var(--glass);backdrop-filter:var(--blur);-webkit-backdrop-filter:var(--blur);border-bottom:1px solid var(--glass-border);display:flex;align-items:center;gap:16px;padding:0 32px;position:sticky;top:0;z-index:50; }
  .topbar-title { font-size:17px;font-weight:600;letter-spacing:-0.3px;flex:1; }
  .topbar-actions { display:flex;align-items:center;gap:10px; }
  .page-content { padding:32px; }
  .btn { display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:var(--radius-sm);font-size:13px;font-weight:500;cursor:pointer;border:none;transition:all 0.18s ease;font-family:var(--font);white-space:nowrap; }
  .btn-primary { background:var(--accent);color:white;box-shadow:0 2px 8px rgba(59,110,248,0.35); }
  .btn-primary:hover { background:#2d5de8;box-shadow:0 4px 16px rgba(59,110,248,0.45);transform:translateY(-1px); }
  .btn-ghost { background:rgba(0,0,0,0.04);color:var(--text-secondary);border:1px solid var(--border); }
  .btn-ghost:hover { background:rgba(0,0,0,0.07);color:var(--text); }
  .btn-danger { background:var(--danger);color:white; }
  .btn-danger:hover { background:#dc2626; }
  .btn svg { width:16px;height:16px; }
  .notif-btn { width:38px;height:38px;border-radius:var(--radius-sm);background:rgba(0,0,0,0.04);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;position:relative;transition:background 0.15s;flex-shrink:0; }
  .notif-btn:hover { background:rgba(0,0,0,0.07); }
  .notif-dot { position:absolute;top:8px;right:8px;width:7px;height:7px;border-radius:50%;background:var(--danger);border:1.5px solid white; }
  .stats-grid { display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px; }
  .stat-card { background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);padding:22px 24px;position:relative;overflow:hidden;transition:all 0.2s ease; }
  .stat-card:hover { box-shadow:var(--shadow-md);transform:translateY(-2px); }
  .stat-card-accent { position:absolute;top:0;left:0;right:0;height:3px;border-radius:var(--radius) var(--radius) 0 0; }
  .stat-icon { width:38px;height:38px;border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;margin-bottom:14px; }
  .stat-icon svg { width:18px;height:18px; }
  .stat-value { font-size:28px;font-weight:600;letter-spacing:-0.8px;line-height:1;margin-bottom:5px; }
  .stat-label { font-size:12.5px;color:var(--text-secondary); }
  .stat-change { font-size:11.5px;margin-top:8px;display:flex;align-items:center;gap:4px; }
  .stat-change.up { color:var(--success); }
  .stat-change.down { color:var(--danger); }
  .section-header { display:flex;align-items:center;justify-content:space-between;margin-bottom:16px; }
  .section-title { font-size:15px;font-weight:600;letter-spacing:-0.2px; }
  .section-sub { font-size:12px;color:var(--text-secondary);margin-top:1px; }
  .card { background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden; }
  .table-wrap { overflow-x:auto; }
  table { width:100%;border-collapse:collapse;font-size:13.5px; }
  thead th { text-align:left;padding:12px 20px;font-size:11px;font-weight:600;letter-spacing:0.5px;text-transform:uppercase;color:var(--text-tertiary);background:rgba(0,0,0,0.018);border-bottom:1px solid var(--border); }
  tbody td { padding:14px 20px;border-bottom:1px solid var(--border);vertical-align:middle; }
  tbody tr:last-child td { border-bottom:none; }
  tbody tr:hover { background:rgba(59,110,248,0.025); }
  .badge { display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:500; }
  .badge::before { content:'';width:6px;height:6px;border-radius:50%; }
  .badge-active { background:rgba(16,185,129,0.1);color:#059669; }
  .badge-active::before { background:#10b981; }
  .badge-booked { background:rgba(59,110,248,0.1);color:var(--accent); }
  .badge-booked::before { background:var(--accent); }
  .badge-returned { background:rgba(107,114,128,0.1);color:#6b7280; }
  .badge-returned::before { background:#9ca3af; }
  .badge-paid { background:rgba(245,158,11,0.1);color:#d97706; }
  .badge-paid::before { background:var(--warn); }
  .badge-completed { background:rgba(99,102,241,0.1);color:#6366f1; }
  .badge-completed::before { background:#6366f1; }
  .vehicle-thumb { width:40px;height:30px;border-radius:6px;background:linear-gradient(135deg,#e0e7ff,#c7d2fe);display:flex;align-items:center;justify-content:center;font-size:18px; }
  .action-btn { display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:var(--radius-xs);font-size:12px;font-weight:500;cursor:pointer;border:1px solid var(--border);background:transparent;color:var(--text-secondary);transition:all 0.15s;font-family:var(--font); }
  .action-btn:hover { background:rgba(0,0,0,0.04);color:var(--text); }
  .action-btn.primary { background:var(--accent);color:white;border-color:transparent; }
  .action-btn.primary:hover { background:#2d5de8; }
  .action-btn.danger { background:rgba(239,68,68,0.08);color:var(--danger);border-color:rgba(239,68,68,0.2); }
  .action-btn.danger:hover { background:var(--danger);color:white; }
  .grid-2 { display:grid;grid-template-columns:1fr 1fr;gap:20px; }
  .grid-3 { display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px; }
  .grid-cols-60-40 { display:grid;grid-template-columns:60fr 40fr;gap:20px; }
  .modal-overlay { display:none;position:fixed;inset:0;z-index:200;background:rgba(15,17,23,0.55);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);align-items:center;justify-content:center; }
  .modal-overlay.open { display:flex; }
  .modal { background:var(--surface2);border-radius:20px;box-shadow:var(--shadow-lg);width:90%;max-width:520px;max-height:88vh;overflow:hidden;display:flex;flex-direction:column;animation:modal-in 0.25s cubic-bezier(0.34,1.56,0.64,1); }
  .modal-lg { max-width:720px; }
  @keyframes modal-in { from{opacity:0;transform:scale(0.95) translateY(10px);} }
  .modal-header { padding:24px 28px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0; }
  .modal-title { font-size:16px;font-weight:600; }
  .modal-close { width:30px;height:30px;border-radius:var(--radius-xs);border:none;background:rgba(0,0,0,0.06);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;color:var(--text-secondary);transition:background 0.15s; }
  .modal-close:hover { background:rgba(0,0,0,0.1); }
  .modal-body { padding:24px 28px;overflow-y:auto;flex:1;min-height:0; }
  .modal-footer { padding:20px 28px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end;flex-shrink:0; }
  .modal > form { display:flex;flex-direction:column;overflow:hidden;flex:1;min-height:0; }
  .form-group { margin-bottom:18px; }
  .form-label { font-size:12.5px;font-weight:500;color:var(--text);margin-bottom:6px;display:block; }
  .form-input { width:100%;padding:10px 14px;border-radius:var(--radius-xs);border:1.5px solid var(--border);background:var(--surface);font-size:13.5px;font-family:var(--font);color:var(--text);transition:border-color 0.15s,box-shadow 0.15s;outline:none; }
  .form-input:focus { border-color:var(--accent);box-shadow:0 0 0 3px rgba(59,110,248,0.1); }
  .form-row { display:grid;grid-template-columns:1fr 1fr;gap:14px; }
  .form-hint { font-size:11.5px;color:var(--text-tertiary);margin-top:4px; }
  .notif-panel { position:fixed;top:76px;right:24px;width:340px;max-height:calc(100vh - 100px);background:var(--glass);backdrop-filter:var(--blur);-webkit-backdrop-filter:var(--blur);border:1px solid var(--glass-border);border-radius:var(--radius);box-shadow:var(--shadow-lg);z-index:150;display:none;flex-direction:column;animation:slide-down 0.2s ease; }
  @keyframes slide-down { from{opacity:0;transform:translateY(-10px);} }
  .notif-panel.open { display:flex; }
  .notif-panel-header { padding:16px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0; }
  .notif-panel-title { font-size:14px;font-weight:600; }
  .notif-panel-list { overflow-y:auto; }
  .notif-item { padding:14px 18px;border-bottom:1px solid var(--border);display:flex;gap:12px;cursor:pointer;transition:background 0.15s; }
  .notif-item:last-child { border-bottom:none; }
  .notif-item:hover { background:rgba(0,0,0,0.02); }
  .notif-item-dot { width:8px;height:8px;border-radius:50%;background:var(--accent);margin-top:4px;flex-shrink:0; }
  .notif-item-title { font-size:13px;font-weight:500;margin-bottom:2px; }
  .notif-item-desc { font-size:12px;color:var(--text-secondary);white-space:pre-line;line-height:1.5; }
  .notif-item-time { font-size:11px;color:var(--text-tertiary);margin-top:3px; }
  .tabs { display:flex;gap:0;background:rgba(0,0,0,0.04);border-radius:var(--radius-sm);padding:4px;margin-bottom:20px; }
  .tab { padding:8px 18px;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;color:var(--text-secondary);transition:all 0.18s;flex:1;text-align:center; }
  .tab.active { background:var(--surface2);color:var(--text);box-shadow:var(--shadow-sm); }
  .vehicle-grid { display:grid;grid-template-columns:repeat(3,1fr);gap:16px; }
  .vehicle-card { background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;transition:all 0.2s;cursor:pointer; }
  .vehicle-card:hover { box-shadow:var(--shadow-md);transform:translateY(-2px); }
  .vehicle-card-img { height:130px;display:flex;align-items:center;justify-content:center;font-size:56px;position:relative; }
  .vehicle-card-status { position:absolute;top:10px;right:10px; }
  .vehicle-card-body { padding:16px; }
  .vehicle-card-title { font-size:14px;font-weight:600;margin-bottom:3px; }
  .vehicle-card-reg { font-size:11.5px;color:var(--text-secondary);font-family:var(--mono);letter-spacing:0.5px; }
  .vehicle-card-meta { display:flex;gap:12px;margin-top:12px; }
  .vehicle-meta-item { display:flex;flex-direction:column;gap:2px; }
  .vehicle-meta-val { font-size:13px;font-weight:500; }
  .vehicle-meta-label { font-size:10.5px;color:var(--text-tertiary); }
  .vehicle-card-actions { display:flex;gap:8px;margin-top:14px; }
  .search-bar { display:flex;align-items:center;gap:10px;background:rgba(0,0,0,0.04);border:1px solid var(--border);border-radius:var(--radius-sm);padding:8px 14px;font-size:13.5px;color:var(--text-secondary);cursor:text; }
  .search-bar svg { width:15px;height:15px;flex-shrink:0; }
  .timeline { position:relative;padding-left:24px; }
  .timeline::before { content:'';position:absolute;left:7px;top:8px;bottom:8px;width:1px;background:var(--border); }
  .timeline-item { position:relative;padding-bottom:20px; }
  .timeline-item:last-child { padding-bottom:0; }
  .timeline-dot { position:absolute;left:-20px;top:4px;width:14px;height:14px;border-radius:50%;border:2px solid white;box-shadow:0 0 0 1px var(--border); }
  .timeline-title { font-size:13px;font-weight:500;margin-bottom:2px; }
  .timeline-desc { font-size:12px;color:var(--text-secondary); }
  .timeline-time { font-size:11px;color:var(--text-tertiary);margin-top:2px; }
  .toast-container { position:fixed;bottom:24px;right:24px;z-index:500;display:flex;flex-direction:column;gap:10px; }
  .toast { background:var(--primary);color:white;padding:12px 18px;border-radius:var(--radius-sm);font-size:13px;font-weight:500;box-shadow:0 8px 24px rgba(0,0,0,0.2);display:flex;align-items:center;gap:10px;animation:toast-in 0.25s cubic-bezier(0.34,1.56,0.64,1); }
  @keyframes toast-in { from{opacity:0;transform:translateX(20px) scale(0.95);} }
  .toast.success { background:var(--success); }
  .toast.error { background:var(--danger); }
  .spacer { flex:1; }
  .chip { display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:20px;font-size:11.5px;font-weight:500;border:1px solid var(--border);background:rgba(0,0,0,0.03);color:var(--text-secondary);cursor:pointer;transition:all 0.15s; }
  .chip.active { background:rgba(59,110,248,0.1);border-color:rgba(59,110,248,0.3);color:var(--accent); }
  .empty-state { text-align:center;padding:60px 24px;color:var(--text-secondary); }
  .empty-state-icon { font-size:48px;margin-bottom:16px;opacity:0.5; }
  .empty-state-title { font-size:16px;font-weight:600;color:var(--text);margin-bottom:6px; }
  .empty-state-desc { font-size:13px; }
  .progress-bar { height:6px;background:rgba(0,0,0,0.07);border-radius:3px;overflow:hidden; }
  .progress-fill { height:100%;border-radius:3px;background:var(--accent);transition:width 0.4s ease; }
  .sig-pad { position:relative;background:rgba(0,0,0,0.02);border:1.5px dashed var(--border);border-radius:var(--radius-sm);height:120px;display:flex;align-items:center;justify-content:center;overflow:hidden;cursor:crosshair; }
  .sig-pad canvas { position:absolute;inset:0;width:100%;height:100%; }
  .sig-placeholder { font-size:13px;color:var(--text-tertiary);pointer-events:none; }
  .rev-chart-wrap { padding:20px 20px 8px; }
  .rev-bars { display:flex;align-items:flex-end;gap:8px;height:100px; }
  .rev-bar { flex:1;border-radius:4px 4px 0 0;min-height:4px;background:var(--accent);opacity:0.7;transition:opacity 0.15s;cursor:pointer; }
  .rev-bar:hover { opacity:1; }
  .rev-labels { display:flex;gap:8px;margin-top:8px; }
  .rev-label { flex:1;font-size:10px;text-align:center;color:var(--text-tertiary); }
  @media(max-width:1100px){.stats-grid{grid-template-columns:repeat(2,1fr)}.grid-2,.grid-cols-60-40{grid-template-columns:1fr}.vehicle-grid{grid-template-columns:repeat(2,1fr)}}
  @media(max-width:768px){.sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}.main{margin-left:0}.page-content{padding:20px 16px}.vehicle-grid,.grid-3{grid-template-columns:1fr}}
