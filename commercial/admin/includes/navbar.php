<!-- admin/includes/navbar.php -->
<nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg,#1a1a2e,#0f3460); position:sticky; top:0; z-index:1000;">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="admin-dashboard.php">
            <i class="fas fa-shield-alt me-2"></i>Admin Panel
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNav">
            <ul class="navbar-nav me-auto gap-1">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF'])=='admin-dashboard.php'?'active':''; ?>" href="admin-dashboard.php">
                        <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF'])=='admin-requests.php'?'active':''; ?>" href="admin-requests.php">
                        <i class="fas fa-inbox me-1"></i>Requests
                        <?php
                        // Show pending count badge
                        try {
                            $b = $pdo->query("SELECT COUNT(*) FROM locker_requests WHERE status='pending'")->fetchColumn();
                            if ($b > 0) echo "<span class='badge bg-danger ms-1'>$b</span>";
                        } catch(Exception $e) {}
                        ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF'])=='admin-lockers.php'?'active':''; ?>" href="admin-lockers.php">
                        <i class="fas fa-boxes me-1"></i>Lockers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF'])=='admin-users.php'?'active':''; ?>" href="admin-users.php">
                        <i class="fas fa-users me-1"></i>Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF'])=='admin-logs.php'?'active':''; ?>" href="admin-logs.php">
                        <i class="fas fa-history me-1"></i>Activity Logs
                    </a>
                </li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <span class="text-white-50 small">
                    <i class="fas fa-user-shield me-1"></i><?php echo htmlspecialchars($admin_name); ?>
                    <span class="badge bg-info ms-1"><?php echo ucfirst($admin_role); ?></span>
                </span>
                <a href="admin-logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </div>
</nav>