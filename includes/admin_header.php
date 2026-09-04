<?php
// includes/admin_header.php
// Usage: include at top of each admin page
// Requires $page_title and $active_nav to be set before including
requireAdminLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BiMAP Admin — <?= htmlspecialchars($page_title ?? 'Dashboard') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Nunito', sans-serif; background: #f0f4f8; color: #1a1a2e; display: flex; flex-direction: column; min-height: 100vh; }
  
  /* TOP BAR */
  .topbar {
    background: #1a1a2e;
    color: white;
    padding: 6px 24px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.5px;
  }

  /* MAIN LAYOUT */
  .layout { display: flex; flex: 1; }

  /* SIDEBAR */
  .sidebar {
    width: 170px;
    background: linear-gradient(180deg, #1a73e8 0%, #0d47a1 100%);
    min-height: calc(100vh - 30px);
    display: flex;
    flex-direction: column;
    position: sticky;
    top: 0;
    height: calc(100vh - 30px);
  }
  .sidebar-logo {
    padding: 18px 16px 12px;
    border-bottom: 1px solid rgba(255,255,255,0.15);
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .sidebar-logo .logo-icon {
    width: 42px; height: 42px;
    background: white;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
  }
  .sidebar-logo .brand-text {
    font-size: 22px;
    font-weight: 900;
    color: white;
    letter-spacing: -0.5px;
  }
  .sidebar-nav { flex: 1; padding: 8px 0; }
  .nav-item {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 16px;
    color: rgba(255,255,255,0.8);
    text-decoration: none;
    font-size: 14px;
    font-weight: 700;
    transition: background 0.15s, color 0.15s;
    border-radius: 0;
  }
  .nav-item:hover { background: rgba(255,255,255,0.15); color: white; }
  .nav-item.active { background: rgba(255,255,255,0.22); color: white; }
  .nav-item svg { flex-shrink: 0; opacity: 0.9; }
  .nav-logout { margin-top: auto; border-top: 1px solid rgba(255,255,255,0.15); }

  /* MAIN CONTENT */
  .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }

  /* HEADER */
  .page-header {
    background: white;
    border-bottom: 1px solid #e0e7ef;
    padding: 14px 28px;
    display: flex; align-items: center; justify-content: space-between;
  }
  .page-header h1 { font-size: 22px; font-weight: 900; color: #1a1a2e; }
  .admin-info { display: flex; align-items: center; gap: 10px; }
  .admin-info .bell { position: relative; }
  .admin-info .bell svg { cursor: pointer; }
  .admin-name { font-weight: 800; font-size: 15px; }
  .admin-avatar {
    width: 38px; height: 38px;
    background: #e0e7ef;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    overflow: hidden;
    text-decoration: none;
    flex-shrink: 0;
    transition: box-shadow 0.15s, transform 0.1s;
  }
  .admin-avatar:hover { box-shadow: 0 0 0 3px rgba(26,115,232,0.25); transform: translateY(-1px); }
  .admin-avatar img { width: 100%; height: 100%; object-fit: cover; }

  /* PAGE BODY */
  .page-body { padding: 24px 28px; flex: 1; overflow-y: auto; }

  /* STAT CARDS */
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
  }
  .stat-card {
    background: white;
    border-radius: 14px;
    padding: 20px 20px;
    display: flex; align-items: center; gap: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    transition: transform 0.15s, box-shadow 0.15s;
  }
  .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.1); }
  .stat-icon {
    width: 52px; height: 52px;
    background: #f0f4ff;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
  }
  .stat-info .num { font-size: 26px; font-weight: 900; color: #1a1a2e; line-height: 1; }
  .stat-info .lbl { font-size: 13px; color: #888; font-weight: 700; margin-top: 2px; }

  /* CARD */
  .card {
    background: white;
    border-radius: 14px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    overflow: hidden;
  }
  .card-header {
    padding: 18px 22px 14px;
    display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid #f0f4f8;
  }
  .card-header h2 { font-size: 17px; font-weight: 800; }
  .card-body { padding: 0; }

  /* TABLE */
  table { width: 100%; border-collapse: collapse; }
  thead th {
    padding: 12px 16px;
    text-align: left;
    font-size: 13px;
    font-weight: 800;
    color: #888;
    border-bottom: 1px solid #f0f4f8;
    background: #fafbfd;
  }
  tbody td {
    padding: 13px 16px;
    font-size: 14px;
    border-bottom: 1px solid #f5f7fa;
    vertical-align: middle;
  }
  tbody tr:last-child td { border-bottom: none; }
  tbody tr:hover { background: #fafcff; }

  /* BADGES */
  .badge {
    display: inline-block;
    padding: 5px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.3px;
  }
  .badge-pending { background: #fff3cd; color: #856404; }
  .badge-resolved { background: #d4edda; color: #155724; }
  .badge-yes { background: #d4edda; color: #155724; }
  .badge-no { background: #fde8e8; color: #9b2226; }
  .badge-residents { background: #e3f2fd; color: #1565c0; }
  .badge-drivers { background: #f3e5f5; color: #6a1b9a; }
  .badge-all { background: #e8f5e9; color: #2e7d32; }

  /* BUTTONS */
  .btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 800;
    font-family: 'Nunito', sans-serif;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: opacity 0.15s, transform 0.1s;
  }
  .btn:hover { opacity: 0.88; transform: translateY(-1px); }
  .btn-primary { background: #1a73e8; color: white; }
  .btn-success { background: #28a745; color: white; }
  .btn-danger { background: #dc3545; color: white; }
  .btn-sm { padding: 5px 12px; font-size: 12px; }
  .btn-view { background: #1a73e8; color: white; padding: 6px 14px; font-size: 12px; font-weight: 700; border-radius: 6px; text-decoration: none; display: inline-block; transition: opacity 0.15s; }
  .btn-view:hover { opacity: 0.85; }

  /* ACTIVITY LIST */
  .activity-item {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 12px 18px;
    border-bottom: 1px solid #f5f7fa;
    transition: background 0.1s;
  }
  .activity-item:last-child { border-bottom: none; }
  .activity-item:hover { background: #fafcff; }
  .activity-bell {
    width: 34px; height: 34px;
    background: #f0f4ff;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    margin-top: 2px;
  }
  .activity-text { font-size: 13px; font-weight: 700; line-height: 1.4; }
  .activity-time { font-size: 11px; color: #aaa; font-weight: 600; margin-top: 2px; }
  .activity-action { margin-left: auto; flex-shrink: 0; }

  /* MODAL */
  .modal-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.45);
    z-index: 1000;
    align-items: center; justify-content: center;
  }
  .modal-overlay.open { display: flex; }
  .modal {
    background: white;
    border-radius: 18px;
    width: 100%;
    max-width: 480px;
    overflow: hidden;
    box-shadow: 0 25px 60px rgba(0,0,0,0.3);
    animation: modalIn 0.2s ease;
  }
  @keyframes modalIn { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
  .modal-header {
    background: linear-gradient(135deg, #1a73e8, #0d47a1);
    padding: 20px 24px;
    color: white;
    font-size: 18px;
    font-weight: 900;
  }
  .modal-body { padding: 24px; }
  .modal-footer { padding: 0 24px 24px; display: flex; gap: 10px; justify-content: flex-end; }
  .form-group { margin-bottom: 18px; }
  .form-group label { display: block; font-weight: 800; font-size: 13px; margin-bottom: 6px; color: #333; }
  .form-group label .req { color: #e53935; margin-left: 2px; }
  .form-control {
    width: 100%;
    padding: 11px 14px;
    border: 2px solid #e0e7ef;
    border-radius: 10px;
    font-size: 14px;
    font-family: 'Nunito', sans-serif;
    outline: none;
    transition: border-color 0.2s;
  }
  .form-control:focus { border-color: #1a73e8; }
  textarea.form-control { min-height: 100px; resize: vertical; }
  .radio-group { display: flex; gap: 20px; margin-top: 8px; }
  .radio-label {
    display: flex; align-items: center; gap: 8px;
    font-weight: 700; font-size: 14px; cursor: pointer;
  }
  .radio-label input[type=radio] { accent-color: #1a73e8; width: 18px; height: 18px; }
  
  /* ALERT */
  .alert { padding: 12px 16px; border-radius: 10px; font-size: 14px; font-weight: 700; margin-bottom: 16px; }
  .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
  .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

  /* PAGINATION */
  .pagination { display: flex; gap: 6px; margin-top: 18px; }
  .page-link {
    padding: 7px 13px; border-radius: 7px; font-size: 13px; font-weight: 700;
    background: white; border: 1px solid #e0e7ef; color: #333; text-decoration: none;
    transition: background 0.15s;
  }
  .page-link:hover, .page-link.active { background: #1a73e8; color: white; border-color: #1a73e8; }

  /* RESPONSIVE tweaks */
  @media (max-width: 900px) {
    .stats-grid { grid-template-columns: repeat(2,1fr); }
  }
</style>
</head>
<body>
<div class="topbar">For Admin</div>
<div class="layout">
  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-logo" style="justify-content:center;">
      <img src="../assets/logo.png" alt="BiMAP Logo" style="width:110px;object-fit:contain;filter:drop-shadow(0 2px 6px rgba(0,0,0,0.18));">
    </div>
    <nav class="sidebar-nav">
      <a href="dashboard.php" class="nav-item <?= ($active_nav??'') === 'dashboard' ? 'active' : '' ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
        Dashboard
      </a>
      <a href="driver_approvals.php" class="nav-item <?= ($active_nav??'') === 'driver_approvals' ? 'active' : '' ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        Driver Approvals
      </a>
      <a href="messages.php" class="nav-item <?= ($active_nav??'') === 'messages' ? 'active' : '' ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
        Messages
      </a>
      <a href="complaints.php" class="nav-item <?= ($active_nav??'') === 'complaints' ? 'active' : '' ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
        Complaints
      </a>
      <a href="announcements.php" class="nav-item <?= ($active_nav??'') === 'announcements' ? 'active' : '' ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M18 11v2H6v-2H4v2c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-2h-2zM12 2L6.5 8H10v7h4V8h3.5L12 2z"/></svg>
        Announcements
      </a>
      <a href="reports.php" class="nav-item <?= ($active_nav??'') === 'reports' ? 'active' : '' ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
        Reports
      </a>
      <a href="schedule.php" class="nav-item <?= ($active_nav??'') === 'schedule' ? 'active' : '' ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/></svg>
        Schedule
      </a>
      <a href="settings.php" class="nav-item <?= ($active_nav??'') === 'settings' ? 'active' : '' ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19.14 12.94c.04-.3.06-.61.06-.94s-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
        Settings
      </a>
    </nav>
    <div class="nav-logout">
      <a href="logout.php" class="nav-item">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
        Logout
      </a>
    </div>
  </aside>

  <!-- MAIN -->
  <div class="main-content">
    <header class="page-header">
      <h1><?= htmlspecialchars($page_title ?? 'Admin Dashboard') ?></h1>
      <div class="admin-info">
        <div class="bell">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="#555"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6V11c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
        </div>
        <span class="admin-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></span>
        <a href="settings.php" class="admin-avatar" title="Account Settings">
          <?php if (!empty($_SESSION['admin_pic'])): ?>
            <img src="../<?= htmlspecialchars($_SESSION['admin_pic']) ?>" alt="Profile picture">
          <?php else: ?>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="#888"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
          <?php endif; ?>
        </a>
      </div>
    </header>
    <div class="page-body">