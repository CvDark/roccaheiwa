<?php
// sidebar.php — included in every admin page
$current = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">🛡️</div>
        <div class="brand-text">
            <strong>Admin Panel</strong>
            <span>Institution</span>
        </div>
    </div>

    <div class="sidebar-user">
        <div class="av"><?php echo strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)); ?></div>
        <div>
            <strong><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></strong>
            <span class="role-badge"><?php echo ucfirst($_SESSION['admin_role'] ?? 'admin'); ?></span>
        </div>
    </div>

    <nav class="sidenav">
        <div class="nav-section">Main</div>
        <a href="dashboard.php" class="nav-item <?php echo $current==='dashboard.php'?'active':''; ?>">
            <i class="fas fa-th-large"></i> Dashboard
        </a>
        <a href="users.php" class="nav-item <?php echo $current==='users.php'?'active':''; ?>">
            <i class="fas fa-users"></i> Manage Users
        </a>
        <a href="lockers.php" class="nav-item <?php echo $current==='lockers.php'?'active':''; ?>">
            <i class="fas fa-box"></i> Manage Lockers
        </a>
        <a href="logs.php" class="nav-item <?php echo $current==='logs.php'?'active':''; ?>">
            <i class="fas fa-history"></i> Activity Logs
        </a>
        <a href="locker-keys.php" class="nav-item <?php echo $current==='locker-keys.php'?'active':''; ?>">
            <i class="fas fa-key"></i> Manage Keys
        </a>
        <a href="matrics.php" class="nav-item <?php echo $current==='matrics.php'?'active':''; ?>">
            <i class="fas fa-id-card"></i> Manage Matrics
        </a>
        <a href="nfc_management.php" class="nav-item <?php echo $current==='nfc_management.php'?'active':''; ?>">
            <i class="fas fa-wifi"></i> NFC Cards
        </a>
        <div class="nav-section">System</div>
        <a href="accounts.php" class="nav-item <?php echo $current==='accounts.php'?'active':''; ?>">
            <i class="fas fa-user-shield"></i> Admin Accounts
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="../login.php" class="nav-item" style="color:rgba(255,255,255,0.35);">
            <i class="fas fa-external-link-alt"></i> Institution Portal
        </a>
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</aside>