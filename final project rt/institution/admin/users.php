<?php
require_once 'config.php';
requireAdmin();

$message = '';
$msg_type = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_status') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $curr = (int)($_POST['current_status'] ?? 1);
        $new  = $curr ? 0 : 1;
        $pdo->prepare("UPDATE users SET is_active=? WHERE id=?")->execute([$new, $uid]);
        $message  = 'User status updated.';
        $msg_type = 'success';

    } elseif ($action === 'delete_user') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
        $message  = 'User deleted successfully.';
        $msg_type = 'success';

    } elseif ($action === 'add_user') {
        $full_name      = sanitize($_POST['full_name'] ?? '');
        $user_id_number = strtoupper(sanitize($_POST['user_id_number'] ?? ''));
        $email          = sanitize($_POST['email'] ?? '');
        $user_type      = sanitize($_POST['user_type'] ?? 'student');
        $institution    = sanitize($_POST['institution'] ?? '');
        $password       = $_POST['password'] ?? 'locker123';

        if ($full_name && $user_id_number && $email) {
            $chk = $pdo->prepare("SELECT id FROM users WHERE email=? OR user_id_number=?");
            $chk->execute([$email, $user_id_number]);
            if ($chk->fetch()) {
                $message = 'Email or ID number already exists.'; $msg_type = 'danger';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (email,password_hash,full_name,user_id_number,user_type,institution,role,created_at,is_active) VALUES (?,?,?,?,?,?,'user',NOW(),1)")
                    ->execute([$email, $hash, $full_name, $user_id_number, $user_type, $institution]);
                $message = 'User added successfully!'; $msg_type = 'success';
            }
        } else {
            $message = 'Please fill in all required fields.'; $msg_type = 'danger';
        }
    }
}

// Filters
$filter_type   = sanitize($_GET['type'] ?? '');
$filter_search = sanitize($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

$where = "WHERE 1=1";
$params = [];
if ($filter_type)   { $where .= " AND user_type=?"; $params[] = $filter_type; }
if ($filter_search) { $where .= " AND (full_name LIKE ? OR user_id_number LIKE ? OR email LIKE ?)"; $params = array_merge($params, ["%$filter_search%","%$filter_search%","%$filter_search%"]); }

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users $where");
$count_stmt->execute($params);
$total_rows = $count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $per_page);

$stmt = $pdo->prepare("SELECT * FROM users $where ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Manage Users — Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="admin-style.css">
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main">
    <div class="topbar">
        <div><h1>Manage Users</h1><p><?php echo $total_rows; ?> users registered</p></div>
        <button class="btn btn-primary" onclick="openModal('addModal')">
            <i class="fas fa-user-plus"></i> Add User
        </button>
    </div>
    <div class="content">

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $msg_type; ?>">
            <i class="fas fa-<?php echo $msg_type==='success'?'check-circle':'exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <form method="GET" class="search-bar">
            <input type="text" name="search" placeholder="Search name, ID, email..."
                   value="<?php echo htmlspecialchars($filter_search); ?>">
            <select name="type" style="padding:9px 12px;border:2px solid var(--border);border-radius:9px;font-family:inherit;font-size:13px;outline:none;cursor:pointer;">
                <option value="">All Types</option>
                <option value="student" <?php echo $filter_type==='student'?'selected':''; ?>>Student</option>
                <option value="staff"   <?php echo $filter_type==='staff'?'selected':''; ?>>Staff</option>
            </select>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
            <?php if ($filter_search || $filter_type): ?>
            <a href="users.php" class="btn btn-outline">Clear</a>
            <?php endif; ?>
        </form>

        <div class="panel">
            <div class="tbl-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th><th>Name</th><th>ID Number</th><th>Type</th>
                            <th>Institution</th><th>Joined</th><th>Status</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($users)): ?>
                    <tr><td colspan="8"><div class="empty-state"><i class="fas fa-users"></i>No users found.</div></td></tr>
                    <?php else: ?>
                    <?php foreach ($users as $i => $u): ?>
                    <tr>
                        <td style="color:#aaa;font-size:11px"><?php echo $offset+$i+1; ?></td>
                        <td>
                            <div style="font-weight:600"><?php echo htmlspecialchars($u['full_name']); ?></div>
                            <div style="font-size:11px;color:#aaa"><?php echo htmlspecialchars($u['email']); ?></div>
                        </td>
                        <td style="font-family:monospace;font-size:12px"><?php echo htmlspecialchars($u['user_id_number']); ?></td>
                        <td><span class="badge badge-<?php echo $u['user_type']; ?>"><?php echo ucfirst($u['user_type']); ?></span></td>
                        <td style="font-size:12px"><?php echo htmlspecialchars($u['institution'] ?: '—'); ?></td>
                        <td style="font-size:11px;color:#aaa"><?php echo date('d M Y', strtotime($u['created_at'])); ?></td>
                        <td>
                            <span class="badge <?php echo $u['is_active']?'badge-active':'badge-inactive'; ?>">
                                <?php echo $u['is_active']?'Active':'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $u['is_active']; ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" title="<?php echo $u['is_active']?'Deactivate':'Activate'; ?>">
                                        <i class="fas fa-<?php echo $u['is_active']?'ban':'check'; ?>"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this user?')">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div style="padding:12px 20px;">
                <div class="pagination">
                    <?php if ($page > 1): ?><a href="?page=<?php echo $page-1; ?>&type=<?php echo $filter_type; ?>&search=<?php echo urlencode($filter_search); ?>">←</a><?php endif; ?>
                    <?php for ($p=1; $p<=$total_pages; $p++): ?>
                    <a href="?page=<?php echo $p; ?>&type=<?php echo $filter_type; ?>&search=<?php echo urlencode($filter_search); ?>"
                       class="<?php echo $p==$page?'active':''; ?>"><?php echo $p; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?><a href="?page=<?php echo $page+1; ?>&type=<?php echo $filter_type; ?>&search=<?php echo urlencode($filter_search); ?>">→</a><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <footer>
        <span>© 2026 Smart Locker System — Institution Admin</span>
        <span><?php echo date('d M Y, H:i'); ?></span>
    </footer>
</div>

<!-- ADD USER MODAL -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <h3><i class="fas fa-user-plus" style="color:var(--teal);margin-right:8px"></i>Add New User</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_user">
            <div class="form-grid cols2">
                <div class="field"><label>Full Name *</label><input type="text" name="full_name" required placeholder="Full name"></div>
                <div class="field"><label>ID / Matric No */Student Card *</label><input type="text" name="user_id_number" required placeholder="e.g. MC2516203265"></div>
                <div class="field"><label>Email *</label><input type="email" name="email" required placeholder="email@example.com"></div>
                <div class="field"><label>User Type</label>
                    <select name="user_type">
                        <option value="student">Student</option>
                        <option value="staff">Staff</option>
                    </select>
                </div>
                <div class="field"><label>Institution</label><input type="text" name="institution" placeholder="e.g. Matrikulasi Kedah"></div>
                <div class="field"><label>Password</label><input type="text" name="password" value="locker123" placeholder="Default: locker123"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save User</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});
</script>
</body>
</html>