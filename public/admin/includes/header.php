<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? 'dashboard.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'NChanger Admin' ?></title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="brand">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect>
                <line x1="12" y1="18" x2="12.01" y2="18"></line>
            </svg>
            <div>NChanger <span>Admin</span></div>
        </div>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                    <span>📊</span> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="licenses.php" class="nav-link <?= $currentPage === 'licenses.php' ? 'active' : '' ?>">
                    <span>🔑</span> Licenses
                </a>
            </li>
            <li class="nav-item">
                <a href="models.php" class="nav-link <?= $currentPage === 'models.php' ? 'active' : '' ?>">
                    <span>📱</span> Models
                </a>
            </li>
            <li class="nav-item">
                <a href="roms.php" class="nav-link <?= $currentPage === 'roms.php' ? 'active' : '' ?>">
                    <span>💿</span> ROM Profiles
                </a>
            </li>
            <li class="nav-item">
                <a href="change_profiles.php" class="nav-link <?= $currentPage === 'change_profiles.php' ? 'active' : '' ?>">
                    <span>🛠️</span> Change Profiles
                </a>
            </li>
            <li class="nav-item">
                <a href="devices.php" class="nav-link <?= $currentPage === 'devices.php' ? 'active' : '' ?>">
                    <span>🔌</span> Devices
                </a>
            </li>
            <li class="nav-item">
                <a href="backups.php" class="nav-link <?= $currentPage === 'backups.php' ? 'active' : '' ?>">
                    <span>💾</span> Backups & Restore
                </a>
            </li>
            <li class="nav-item">
                <a href="logs.php" class="nav-link <?= $currentPage === 'logs.php' ? 'active' : '' ?>">
                    <span>📜</span> Audit Logs
                </a>
            </li>
            <li class="nav-item">
                <a href="settings.php" class="nav-link <?= $currentPage === 'settings.php' ? 'active' : '' ?>">
                    <span>⚙️</span> Settings
                </a>
            </li>
            <li class="nav-item" style="margin-top: 20px; border-top: 1px solid var(--border-color); padding-top: 10px;">
                <a href="/api/v1/docs" target="_blank" class="nav-link">
                    <span>📖</span> API Docs (Swagger) ↗
                </a>
            </li>
        </ul>
        <div class="user-panel">
            <div>
                <div style="font-weight: 600; color: #fff;"><?= htmlspecialchars($currentUser['username'] ?? 'Admin') ?></div>
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;"><?= htmlspecialchars($currentUser['role'] ?? 'super_admin') ?></div>
            </div>
            <a href="logout.php" style="color: var(--danger); text-decoration: none; font-size: 13px;" title="Logout">Đăng xuất</a>
        </div>
    </aside>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <header class="topbar">
            <div style="font-weight: 600; font-size: 16px;"><?= $pageTitle ?? 'Dashboard' ?></div>
            <div style="color: var(--text-muted); font-size: 12px;">Server Time: <?= date('Y-m-d H:i:s') ?></div>
        </header>
        <main class="content-body">
