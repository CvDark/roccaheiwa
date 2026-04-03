<?php
require_once 'config.php';
requireAdmin();

$me       = (int)$_SESSION['admin_id'];
$my_role  = $_SESSION['admin_role'] ?? 'admin';
$message  = '';
$msg_type = '';

// ── HANDLE ACTIONS ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ADD ADMIN
    if ($action === 'add_admin') {
        $name        = sanitize($_POST['name'] ?? '');
        $username    = sanitize($_POST['username'] ?? '');
        $password    = $_POST['password'] ?? '';
        $role        = in_array($_POST['role'] ?? '', ['staff','superadmin']) ? $_POST['role'] : 'staff';
        $institution = sanitize($_POST['institution'] ?? '');

        if (empty($name) || empty($username) || empty($password)) {
            $message = 'Nama, username dan password diperlukan.';
            $msg_type = 'error';
        } elseif (strlen($password) < 6) {
            $message = 'Password mestilah sekurang-kurangnya 6 aksara.';
            $msg_type = 'error';
        } else {
            try {
                $chk = $pdo->prepare("SELECT id FROM admins WHERE username = ?");
                $chk->execute([$username]);
                if ($chk->fetch()) {
                    $message = 'Username sudah digunakan.';
                    $msg_type = 'error';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $pdo->prepare("INSERT INTO admins (name, username, password, role, institution, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())")
                        ->execute([$name, $username, $hash, $role, $institution]);
                    $message = "Akaun admin '$username' berjaya dicipta.";
                    $msg_type = 'success';
                }
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $msg_type = 'error';
            }
        }
    }

    // EDIT ADMIN
    elseif ($action === 'edit_admin') {
        $aid         = (int)($_POST['admin_id'] ?? 0);
        $name        = sanitize($_POST['name'] ?? '');
        $role        = in_array($_POST['role'] ?? '', ['staff','superadmin']) ? $_POST['role'] : 'staff';
        $institution = sanitize($_POST['institution'] ?? '');
        $new_pass    = $_POST['new_password'] ?? '';

        try {
            if (!empty($new_pass)) {
                if (strlen($new_pass) < 6) {
                    $message = 'Password baru mestilah sekurang-kurangnya 6 aksara.'; $msg_type = 'error';
                    goto done;
                }
                $hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE admins SET name=?, role=?, institution=?, password=? WHERE id=?")
                    ->execute([$name, $role, $institution, $hash, $aid]);
            } else {
                $pdo->prepare("UPDATE admins SET name=?, role=?, institution=? WHERE id=?")
                    ->execute([$name, $role, $institution, $aid]);
            }
            $message = 'Maklumat admin berjaya dikemaskini.';
            $msg_type = 'success';
            // Update session if editing self
            if ($aid === $me) {
                $_SESSION['admin_name'] = $name;
                $_SESSION['admin_role'] = $role;
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage(); $msg_type = 'error';
        }
    }

    // TOGGLE STATUS
    elseif ($action === 'toggle_status') {
        $aid  = (int)($_POST['admin_id'] ?? 0);
        $curr = (int)($_POST['current_status'] ?? 1);
        if ($aid === $me) {
            $message = 'Anda tidak boleh nyahaktifkan akaun anda sendiri.'; $msg_type = 'error';
        } else {
            $pdo->prepare("UPDATE admins SET is_active=? WHERE id=?")->execute([$curr ? 0 : 1, $aid]);
            $message = 'Status admin dikemaskini.'; $msg_type = 'success';
        }
    }

    // RESET PASSWORD
    elseif ($action === 'reset_password') {
        $aid      = (int)($_POST['admin_id'] ?? 0);
        $new_pass = $_POST['reset_pass'] ?? '';
        if (strlen($new_pass) < 6) {
            $message = 'Password mestilah sekurang-kurangnya 6 aksara.'; $msg_type = 'error';
        } else {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE admins SET password=? WHERE id=?")->execute([$hash, $aid]);
            $message = 'Password berjaya ditukar.'; $msg_type = 'success';
        }
    }

    // DELETE ADMIN
    elseif ($action === 'delete_admin') {
        $aid = (int)($_POST['admin_id'] ?? 0);
        if ($aid === $me) {
            $message = 'Anda tidak boleh padam akaun anda sendiri.'; $msg_type = 'error';
        } else {
            $pdo->prepare("DELETE FROM admins WHERE id=?")->execute([$aid]);
            $message = 'Akaun admin dipadam.'; $msg_type = 'success';
        }
    }

    done:
}

// ── FETCH ALL ADMINS ──
$admins = $pdo->query("SELECT id, name, username, role, institution, is_active, last_login, created_at FROM admins ORDER BY id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admin Accounts — Institution</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="admin-style.css">
    <style>
        .admin-card {
            background: white;
            border: 1.5px solid var(--border);
            border-radius: 14px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: box-shadow 0.2s, border-color 0.2s;
            margin-bottom: 12px;
        }
        .admin-card:hover { border-color: var(--teal); box-shadow: 0 4px 16px rgba(13,115,119,0.08); }
        .admin-card.me { border-color: var(--gold); background: linear-gradient(135deg, #fffdf4, white); }
        .admin-av {
            width: 48px; height: 48px; border-radius: 50%;
            background: linear-gradient(135deg, var(--teal), var(--teal-d));
            display: grid; place-items: center;
            color: white; font-size: 18px; font-weight: 800; flex-shrink: 0;
        }
        .admin-av.superadmin { background: linear-gradient(135deg, var(--gold), #c87f00); }
        .admin-av.inactive { background: #ccc; }
        .admin-info { flex: 1; min-width: 0; }
        .admin-name { font-size: 15px; font-weight: 800; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .admin-meta { font-size: 12px; color: var(--mid); margin-top: 3px; display: flex; gap: 14px; flex-wrap: wrap; }
        .admin-meta span { display: flex; align-items: center; gap: 5px; }
        .role-pill {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 10px; font-weight: 800; letter-spacing: 0.07em; text-transform: uppercase;
            padding: 3px 10px; border-radius: 20px;
        }
        .role-pill.superadmin { background: rgba(240,165,0,0.12); color: var(--gold); border: 1px solid rgba(240,165,0,0.3); }
        .role-pill.staff { background: var(--teal-l); color: var(--teal); border: 1px solid var(--border); }
        .me-tag { font-size: 10px; font-weight: 700; background: rgba(240,165,0,0.12); color: var(--gold); padding: 2px 8px; border-radius: 20px; border: 1px solid rgba(240,165,0,0.3); }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
        .status-dot.on { background: #0a7c63; } .status-dot.off { background: #e74c3c; }
        .action-btns { display: flex; gap: 6px; flex-shrink: 0; }
        .abtn { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 8px; border: 1.5px solid var(--border); background: white; cursor: pointer; font-size: 13px; color: var(--mid); transition: all 0.2s; }
        .abtn:hover.edit { border-color: var(--teal); color: var(--teal); background: var(--teal-l); }
        .abtn:hover.reset { border-color: #f0a500; color: #f0a500; background: #fffdf4; }
        .abtn:hover.tog { border-color: #0a7c63; color: #0a7c63; background: #e8f7f3; }
        .abtn:hover.del { border-color: #e74c3c; color: #e74c3c; background: #fdf0ef; }
        .abtn.disabled { opacity: 0.3; cursor: not-allowed; pointer-events: none; }

        /* MODAL */
        .modal-bg { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 200; align-items: center; justify-content: center; padding: 16px; }
        .modal-bg.show { display: flex; }
        .modal { background: white; border-radius: 16px; padding: 28px; width: 100%; max-width: 480px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); animation: mIn 0.25s ease; }
        @keyframes mIn { from{opacity:0;transform:scale(0.95)} to{opacity:1;transform:scale(1)} }
        .modal-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .modal-head h3 { font-size: 16px; font-weight: 800; }
        .modal-close { background: none; border: none; font-size: 18px; cursor: pointer; color: var(--mid); width: 30px; height: 30px; border-radius: 8px; display: grid; place-items: center; }
        .modal-close:hover { background: var(--light); }
        .form-group { margin-bottom: 14px; }
        .form-group label { font-size: 11px; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase; color: var(--mid); display: block; margin-bottom: 7px; }
        .form-group input, .form-group select {
            width: 100%; padding: 10px 14px; border: 2px solid var(--border); border-radius: 9px;
            font-family: inherit; font-size: 13px; color: var(--dark); background: var(--light); outline: none; transition: border-color 0.2s;
        }
        .form-group input:focus, .form-group select:focus { border-color: var(--teal); background: white; box-shadow: 0 0 0 3px rgba(13,115,119,0.08); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .pw-wrap { position: relative; }
        .pw-wrap input { padding-right: 40px; }
        .pw-eye { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #aac5c6; font-size: 14px; }
        .pw-eye:hover { color: var(--teal); }
        .btn-row { display: flex; gap: 10px; margin-top: 20px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; padding: 10px 20px; border-radius: 9px; font-family: inherit; font-size: 13px; font-weight: 700; border: none; cursor: pointer; transition: all 0.2s; }
        .btn-p { background: var(--teal); color: white; flex: 1; } .btn-p:hover { background: var(--teal-d); }
        .btn-o { background: white; color: var(--mid); border: 2px solid var(--border); flex: 1; } .btn-o:hover { border-color: var(--teal); color: var(--teal); }
        .btn-d { background: #e74c3c; color: white; flex: 1; } .btn-d:hover { background: #c0392b; }
        .btn-g { background: var(--gold); color: white; flex: 1; } .btn-g:hover { background: #c87f00; }
        .msg { padding: 12px 16px; border-radius: 10px; font-size: 13px; display: flex; align-items: center; gap: 9px; margin-bottom: 20px; }
        .msg.success { background: #e8f7f3; border: 1.5px solid #b6e8da; color: #0a7c63; }
        .msg.error   { background: #fdf0ef; border: 1.5px solid #f5c6c2; color: #c0392b; }
        .empty-state { text-align: center; padding: 48px 20px; color: var(--mid); }
        .hint { font-size: 11px; color: #aaa; margin-top: 5px; }
        footer { padding: 16px 28px; border-top: 1px solid var(--border); font-size: 11px; color: #aaa; display: flex; justify-content: space-between; }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="main">
    <div class="topbar">
        <div>
            <h1><i class="fas fa-user-shield" style="color:var(--teal);margin-right:8px"></i>Admin Accounts</h1>
            <p>Urus akaun admin panel — tambah, edit & padam</p>
        </div>
        <button class="btn btn-p" style="width:auto;padding:9px 18px" onclick="openAdd()">
            <i class="fas fa-plus"></i> Tambah Admin
        </button>
    </div>

    <div class="content">
        <?php if ($message): ?>
        <div class="msg <?php echo $msg_type; ?>">
            <i class="fas fa-<?php echo $msg_type==='success'?'check-circle':'times-circle'; ?>"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <!-- STATS -->
        <div class="stats-grid" style="margin-bottom:20px">
            <div class="stat-card">
                <div class="stat-icon" style="background:var(--teal-l);color:var(--teal)"><i class="fas fa-user-shield"></i></div>
                <div><div class="stat-num"><?php echo count($admins); ?></div><div class="stat-label">Jumlah Admin</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(240,165,0,0.1);color:var(--gold)"><i class="fas fa-crown"></i></div>
                <div><div class="stat-num"><?php echo count(array_filter($admins, fn($a)=>$a['role']==='superadmin')); ?></div><div class="stat-label">Superadmin</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#e8f7f3;color:#0a7c63"><i class="fas fa-check-circle"></i></div>
                <div><div class="stat-num"><?php echo count(array_filter($admins, fn($a)=>$a['is_active'])); ?></div><div class="stat-label">Aktif</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fdf0ef;color:#e74c3c"><i class="fas fa-ban"></i></div>
                <div><div class="stat-num"><?php echo count(array_filter($admins, fn($a)=>!$a['is_active'])); ?></div><div class="stat-label">Tidak Aktif</div></div>
            </div>
        </div>

        <!-- ADMIN LIST -->
        <?php if (empty($admins)): ?>
        <div class="empty-state">
            <i class="fas fa-user-slash" style="font-size:40px;opacity:0.12;display:block;margin-bottom:12px"></i>
            <p>Tiada admin. Tambah admin pertama anda.</p>
        </div>
        <?php else: ?>
        <?php foreach ($admins as $adm): $isMe = ($adm['id'] == $me); ?>
        <div class="admin-card <?php echo $isMe?'me':''; ?>">
            <div class="admin-av <?php echo $adm['role']==='superadmin'?'superadmin':''; ?> <?php echo !$adm['is_active']?'inactive':''; ?>">
                <?php echo strtoupper(substr($adm['name'],0,1)); ?>
            </div>
            <div class="admin-info">
                <div class="admin-name">
                    <?php echo htmlspecialchars($adm['name']); ?>
                    <span class="role-pill <?php echo $adm['role']; ?>">
                        <?php echo $adm['role']==='superadmin'?'👑 Superadmin':'🛡️ Staff'; ?>
                    </span>
                    <?php if($isMe): ?><span class="me-tag">⭐ Saya</span><?php endif; ?>
                    <span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;color:<?php echo $adm['is_active']?'#0a7c63':'#e74c3c'; ?>;font-weight:600">
                        <span class="status-dot <?php echo $adm['is_active']?'on':'off'; ?>"></span>
                        <?php echo $adm['is_active']?'Aktif':'Tidak Aktif'; ?>
                    </span>
                </div>
                <div class="admin-meta">
                    <span><i class="fas fa-at"></i><?php echo htmlspecialchars($adm['username']); ?></span>
                    <?php if($adm['institution']): ?><span><i class="fas fa-university"></i><?php echo htmlspecialchars($adm['institution']); ?></span><?php endif; ?>
                    <span><i class="fas fa-clock"></i>Login: <?php echo $adm['last_login'] ? date('d M Y, H:i', strtotime($adm['last_login'])) : 'Belum pernah'; ?></span>
                </div>
            </div>
            <div class="action-btns">
                <button class="abtn edit" title="Edit" onclick="openEdit(<?php echo htmlspecialchars(json_encode($adm)); ?>)">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="abtn reset" title="Reset Password" onclick="openReset(<?php echo (int)$adm['id']; ?>, '<?php echo htmlspecialchars($adm['name']); ?>')">
                    <i class="fas fa-key"></i>
                </button>
                <button class="abtn tog <?php echo $isMe?'disabled':''; ?>" title="<?php echo $adm['is_active']?'Nyahaktif':'Aktifkan'; ?>"
                        onclick="<?php echo $isMe?'':'toggleStatus('.$adm['id'].','.(int)$adm['is_active'].')'; ?>">
                    <i class="fas fa-<?php echo $adm['is_active']?'toggle-on':'toggle-off'; ?>"></i>
                </button>
                <button class="abtn del <?php echo $isMe?'disabled':''; ?>" title="Padam"
                        onclick="<?php echo $isMe?'':'openDel('.$adm['id'].', \''.htmlspecialchars($adm['name']).'\')'; ?>">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <footer>
        <span>© 2026 Smart Locker System — Institution Admin</span>
        <span><?php echo htmlspecialchars($_SESSION['admin_name']??'Admin'); ?> · <?php echo date('d M Y, H:i'); ?></span>
    </footer>
</div>

<!-- ── MODAL: ADD ADMIN ── -->
<div class="modal-bg" id="mAdd">
    <div class="modal">
        <div class="modal-head">
            <h3><i class="fas fa-user-plus" style="color:var(--teal);margin-right:8px"></i>Tambah Admin Baru</h3>
            <button class="modal-close" onclick="closeModal('mAdd')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_admin">
            <div class="form-row">
                <div class="form-group">
                    <label>Nama Penuh *</label>
                    <input type="text" name="name" placeholder="Contoh: Ahmad Zulkifli" required>
                </div>
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" placeholder="Contoh: ahmad.admin" required autocomplete="off">
                </div>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="ahmad@institution.edu.my">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Role *</label>
                    <select name="role">
                        <option value="staff">🛡️ Staff</option>
                        <option value="superadmin">👑 Superadmin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Institusi</label>
                    <input type="text" name="institution" placeholder="Contoh: KMJ">
                </div>
            </div>
            <div class="form-group">
                <label>Password *</label>
                <div class="pw-wrap">
                    <input type="password" name="password" id="addPw" placeholder="Min 6 aksara" required autocomplete="new-password">
                    <button type="button" class="pw-eye" onclick="togglePw('addPw',this)"><i class="fas fa-eye"></i></button>
                </div>
                <p class="hint">Sekurang-kurangnya 6 aksara</p>
            </div>
            <div class="btn-row">
                <button type="button" class="btn btn-o" onclick="closeModal('mAdd')">Batal</button>
                <button type="submit" class="btn btn-p"><i class="fas fa-plus"></i> Tambah Admin</button>
            </div>
        </form>
    </div>
</div>

<!-- ── MODAL: EDIT ADMIN ── -->
<div class="modal-bg" id="mEdit">
    <div class="modal">
        <div class="modal-head">
            <h3><i class="fas fa-edit" style="color:var(--teal);margin-right:8px"></i>Edit Admin</h3>
            <button class="modal-close" onclick="closeModal('mEdit')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_admin">
            <input type="hidden" name="admin_id" id="editId">
            <div class="form-row">
                <div class="form-group">
                    <label>Nama Penuh *</label>
                    <input type="text" name="name" id="editName" required>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" id="editUsername" disabled style="opacity:0.5">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Role *</label>
                    <select name="role" id="editRole">
                        <option value="staff">🛡️ Staff</option>
                        <option value="superadmin">👑 Superadmin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Institusi</label>
                    <input type="text" name="institution" id="editInst">
                </div>
            </div>
            <div class="form-group">
                <label>Password Baru <span style="color:#aaa;font-weight:400;text-transform:none">(kosongkan jika tidak tukar)</span></label>
                <div class="pw-wrap">
                    <input type="password" name="new_password" id="editPw" placeholder="Kosongkan jika tidak tukar" autocomplete="new-password">
                    <button type="button" class="pw-eye" onclick="togglePw('editPw',this)"><i class="fas fa-eye"></i></button>
                </div>
            </div>
            <div class="btn-row">
                <button type="button" class="btn btn-o" onclick="closeModal('mEdit')">Batal</button>
                <button type="submit" class="btn btn-p"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- ── MODAL: RESET PASSWORD ── -->
<div class="modal-bg" id="mReset">
    <div class="modal" style="max-width:400px">
        <div class="modal-head">
            <h3><i class="fas fa-key" style="color:var(--gold);margin-right:8px"></i>Reset Password</h3>
            <button class="modal-close" onclick="closeModal('mReset')">✕</button>
        </div>
        <p id="resetName" style="font-size:13px;color:var(--mid);margin-bottom:16px"></p>
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="admin_id" id="resetId">
            <div class="form-group">
                <label>Password Baru *</label>
                <div class="pw-wrap">
                    <input type="password" name="reset_pass" id="resetPw" placeholder="Min 6 aksara" required autocomplete="new-password">
                    <button type="button" class="pw-eye" onclick="togglePw('resetPw',this)"><i class="fas fa-eye"></i></button>
                </div>
                <p class="hint">Sekurang-kurangnya 6 aksara</p>
            </div>
            <div class="btn-row">
                <button type="button" class="btn btn-o" onclick="closeModal('mReset')">Batal</button>
                <button type="submit" class="btn btn-g"><i class="fas fa-key"></i> Reset Password</button>
            </div>
        </form>
    </div>
</div>

<!-- ── MODAL: DELETE CONFIRM ── -->
<div class="modal-bg" id="mDel">
    <div class="modal" style="max-width:400px">
        <div class="modal-head">
            <h3 style="color:#e74c3c"><i class="fas fa-exclamation-triangle" style="margin-right:8px"></i>Padam Admin</h3>
            <button class="modal-close" onclick="closeModal('mDel')">✕</button>
        </div>
        <p id="delMsg" style="font-size:13px;color:var(--mid);margin-bottom:20px;line-height:1.6"></p>
        <form method="POST">
            <input type="hidden" name="action" value="delete_admin">
            <input type="hidden" name="admin_id" id="delId">
            <div class="btn-row">
                <button type="button" class="btn btn-o" onclick="closeModal('mDel')">Batal</button>
                <button type="submit" class="btn btn-d"><i class="fas fa-trash"></i> Ya, Padam</button>
            </div>
        </form>
    </div>
</div>

<!-- ── TOGGLE STATUS FORM (hidden) ── -->
<form id="togForm" method="POST" style="display:none">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="admin_id" id="togId">
    <input type="hidden" name="current_status" id="togStatus">
</form>

<script>
function openAdd() { document.getElementById('mAdd').classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }

// Close modal when click outside
document.querySelectorAll('.modal-bg').forEach(bg => {
    bg.addEventListener('click', e => { if (e.target === bg) bg.classList.remove('show'); });
});

function openEdit(data) {
    document.getElementById('editId').value       = data.id;
    document.getElementById('editName').value     = data.name;
    document.getElementById('editUsername').value = data.username;
    document.getElementById('editRole').value     = data.role;
    document.getElementById('editInst').value     = data.institution || '';
    document.getElementById('editPw').value       = '';
    document.getElementById('mEdit').classList.add('show');
}

function openReset(id, name) {
    document.getElementById('resetId').value   = id;
    document.getElementById('resetName').textContent = 'Reset password untuk: ' + name;
    document.getElementById('resetPw').value   = '';
    document.getElementById('mReset').classList.add('show');
}

function openDel(id, name) {
    document.getElementById('delId').value  = id;
    document.getElementById('delMsg').textContent = 'Adakah anda pasti mahu memadam akaun admin "' + name + '"? Tindakan ini tidak boleh dibatalkan.';
    document.getElementById('mDel').classList.add('show');
}

function toggleStatus(id, curr) {
    if (!confirm(curr ? 'Nyahaktifkan admin ini?' : 'Aktifkan semula admin ini?')) return;
    document.getElementById('togId').value     = id;
    document.getElementById('togStatus').value = curr;
    document.getElementById('togForm').submit();
}

function togglePw(id, btn) {
    const inp = document.getElementById(id);
    const show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    btn.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
}
</script>
</body>
</html>