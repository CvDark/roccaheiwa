<?php
require_once 'config.php';
if (!isAdminLoggedIn()) redirect('login.php');

$message = ''; $msg_type = '';

// ── TAMBAH SATU MATRIK ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $id_number   = strtoupper(trim($_POST['id_number'] ?? ''));
    $full_name   = trim($_POST['full_name'] ?? '');
    $user_type   = $_POST['user_type'] ?? 'student';
    $institution = trim($_POST['institution'] ?? '');

    if (!$id_number) {
        $message = 'ID/Matrik tidak boleh kosong.'; $msg_type = 'error';
    } elseif (!in_array($user_type, ['student','staff'])) {
        $message = 'Jenis pengguna tidak sah.'; $msg_type = 'error';
    } else {
        try {
            $chk = $pdo->prepare("SELECT id FROM registered_matrics WHERE id_number = ?");
            $chk->execute([$id_number]);
            if ($chk->fetch()) {
                $message = "ID '$id_number' sudah wujud dalam senarai."; $msg_type = 'error';
            } else {
                $pdo->prepare("INSERT INTO registered_matrics (id_number, full_name, user_type, institution, added_by) VALUES (?,?,?,?,?)")
                    ->execute([$id_number, $full_name ?: null, $user_type, $institution ?: null, $_SESSION['admin_id']]);
                $message = "ID '$id_number' berjaya ditambah."; $msg_type = 'success';
            }
        } catch (Exception $e) { $message = 'Error: '.$e->getMessage(); $msg_type = 'error'; }
    }
}

// ── IMPORT BULK (CSV) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import') {
    $csv_text  = trim($_POST['csv_data'] ?? '');
    $def_type  = $_POST['default_type'] ?? 'student';
    $def_inst  = trim($_POST['default_institution'] ?? '');
    $lines     = array_filter(array_map('trim', explode("\n", $csv_text)));
    $added = $skipped = $errors = 0;

    foreach ($lines as $line) {
        if (empty($line)) continue;
        $parts = array_map('trim', explode(',', $line));
        $id_number = strtoupper($parts[0] ?? '');
        $full_name = $parts[1] ?? '';
        $u_type    = in_array(strtolower($parts[2] ?? ''), ['student','staff']) ? strtolower($parts[2]) : $def_type;
        $inst      = $parts[3] ?? $def_inst;

        if (!$id_number) { $errors++; continue; }
        try {
            $chk = $pdo->prepare("SELECT id FROM registered_matrics WHERE id_number = ?");
            $chk->execute([$id_number]);
            if ($chk->fetch()) { $skipped++; continue; }
            $pdo->prepare("INSERT INTO registered_matrics (id_number, full_name, user_type, institution, added_by) VALUES (?,?,?,?,?)")
                ->execute([$id_number, $full_name ?: null, $u_type, $inst ?: null, $_SESSION['admin_id']]);
            $added++;
        } catch (Exception $e) { $errors++; }
    }
    $message = "Import selesai: <strong>$added</strong> ditambah, <strong>$skipped</strong> dah ada (skip), <strong>$errors</strong> ralat.";
    $msg_type = $errors > 0 ? 'warning' : 'success';
}

// ── DELETE ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['matric_id'] ?? 0);
    try {
        $pdo->prepare("DELETE FROM registered_matrics WHERE id = ? AND is_used = 0")->execute([$id]);
        $message = 'Rekod berjaya dipadam.'; $msg_type = 'success';
    } catch (Exception $e) { $message = 'Error: '.$e->getMessage(); $msg_type = 'error'; }
}

// ── FETCH DATA ──
$filter  = $_GET['filter'] ?? 'all';
$search  = trim($_GET['q'] ?? '');
$where   = '1=1';
$params  = [];
if ($filter === 'unused')  { $where .= ' AND is_used=0'; }
if ($filter === 'used')    { $where .= ' AND is_used=1'; }
if ($filter === 'student') { $where .= ' AND user_type="student"'; }
if ($filter === 'staff')   { $where .= ' AND user_type="staff"'; }
if ($search) { $where .= ' AND (id_number LIKE ? OR full_name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }

$stmt = $pdo->prepare("SELECT * FROM registered_matrics WHERE $where ORDER BY created_at DESC");
$stmt->execute($params);
$matrics = $stmt->fetchAll();

$stats = $pdo->query("SELECT COUNT(*) total, SUM(is_used=0) unused, SUM(is_used=1) used, SUM(user_type='student') students, SUM(user_type='staff') staffs FROM registered_matrics")->fetch();
$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Manage Matrics — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="admin-style.css">
<style>
.stat-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:14px;margin-bottom:24px;}
.scard{background:white;border:1.5px solid var(--border);border-radius:12px;padding:14px 16px;display:flex;align-items:center;gap:12px;}
.scard-ic{width:38px;height:38px;border-radius:9px;display:grid;place-items:center;font-size:16px;flex-shrink:0;}
.si-teal{background:var(--teal-l);color:var(--teal);}
.si-green{background:#e8f7f3;color:#0a7c63;}
.si-amber{background:#fff8e1;color:#c87f00;}
.si-blue{background:#e8f4fd;color:#2980b9;}
.si-gold{background:#fff8e1;color:var(--gold);}
.scard-val{font-size:20px;font-weight:800;line-height:1;}
.scard-lbl{font-size:11px;color:var(--mid);margin-top:2px;}
.toolbar{display:flex;gap:10px;align-items:center;margin-bottom:18px;flex-wrap:wrap;}
.search-wrap{position:relative;flex:1;min-width:200px;}
.search-wrap i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#aac5c6;font-size:13px;}
.search-inp{width:100%;padding:9px 12px 9px 36px;border:2px solid var(--border);border-radius:9px;font-family:inherit;font-size:13px;background:white;outline:none;transition:border-color .2s;}
.search-inp:focus{border-color:var(--teal);}
.filter-tabs{display:flex;gap:6px;flex-wrap:wrap;}
.ftab{padding:7px 14px;border-radius:8px;font-size:12px;font-weight:700;border:2px solid var(--border);background:white;color:var(--mid);cursor:pointer;text-decoration:none;transition:all .2s;}
.ftab:hover,.ftab.act{background:var(--teal);color:white;border-color:var(--teal);}
/* TABLE */
.tbl-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
thead th{background:var(--light);padding:10px 14px;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--mid);text-align:left;border-bottom:2px solid var(--border);}
tbody td{padding:12px 14px;border-bottom:1px solid var(--border);font-size:13px;vertical-align:middle;}
tbody tr:last-child td{border-bottom:none;}
tbody tr:hover{background:var(--light);}
.badge-st{background:rgba(13,115,119,.12);color:var(--teal);padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;}
.badge-sf{background:rgba(20,169,138,.12);color:#0a7c63;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;}
.badge-used{background:#e8f7f3;color:#0a7c63;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;}
.badge-unused{background:var(--teal-l);color:var(--teal);padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;}
.badge-warn{background:#fff8e1;color:#c87f00;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;}
.del-btn{background:none;border:none;color:#e74c3c;cursor:pointer;padding:6px 8px;border-radius:6px;font-size:13px;transition:background .2s;}
.del-btn:hover{background:#fdf0ef;}
.del-btn:disabled{color:#aac5c6;cursor:not-allowed;}
/* PANELS */
.panels{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:24px;}
.panel{background:white;border:1.5px solid var(--border);border-radius:14px;overflow:hidden;}
.ph{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:9px;}
.ph h3{font-size:14px;font-weight:800;flex:1;}
.ph-ic{width:30px;height:30px;border-radius:8px;background:var(--teal-l);display:grid;place-items:center;color:var(--teal);font-size:12px;}
.pb{padding:18px;}
.flbl{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--mid);display:block;margin-bottom:6px;}
.finp{width:100%;padding:9px 12px;border:2px solid var(--border);border-radius:8px;font-family:inherit;font-size:13px;background:var(--light);outline:none;transition:border-color .2s;margin-bottom:12px;}
.finp:focus{border-color:var(--teal);background:white;}
select.finp{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8'%3E%3Cpath d='M1 1l5 5 5-5' fill='none' stroke='%23aaa' stroke-width='2'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;}
.frow{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.submit-btn{width:100%;padding:11px;background:var(--teal);border:none;border-radius:8px;color:white;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;transition:background .2s;}
.submit-btn:hover{background:var(--teal-d);}
textarea.finp{height:120px;resize:vertical;font-family:monospace;font-size:12px;}
.csv-hint{font-size:11px;color:#9ab5b6;margin:-8px 0 12px;line-height:1.6;}
.msg{padding:11px 14px;border-radius:9px;font-size:13px;display:flex;align-items:flex-start;gap:9px;margin-bottom:18px;}
.msg.success{background:#e8f7f3;border:1.5px solid #b6e8da;color:#0a7c63;}
.msg.error{background:#fdf0ef;border:1.5px solid #f5c6c2;color:#c0392b;}
.msg.warning{background:#fff8e1;border:1.5px solid #ffe082;color:#c87f00;}
@media(max-width:800px){.panels{grid-template-columns:1fr;}.frow{grid-template-columns:1fr;}}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main">
<div class="topbar">
    <div>
        <h1>Manage Matrics / ID</h1>
        <p>Senarai nombor matrik & ID staf yang dibenarkan mendaftar</p>
    </div>
    <div style="display:flex;gap:10px">
        <button class="btn btn-outline" onclick="document.getElementById('importPanel').scrollIntoView({behavior:'smooth'})">
            <i class="fas fa-file-csv"></i> Import CSV
        </button>
    </div>
</div>

<div class="content">

<?php if($message): ?>
<div class="msg <?php echo $msg_type; ?>"><i class="fas fa-<?php echo $msg_type==='success'?'check-circle':($msg_type==='warning'?'exclamation-triangle':'exclamation-circle'); ?>" style="flex-shrink:0"></i><span><?php echo $message; ?></span></div>
<?php endif; ?>

<!-- STATS -->
<div class="stat-row">
    <div class="scard"><div class="scard-ic si-teal"><i class="fas fa-list"></i></div><div><div class="scard-val"><?php echo $stats['total']??0; ?></div><div class="scard-lbl">Jumlah</div></div></div>
    <div class="scard"><div class="scard-ic si-green"><i class="fas fa-check"></i></div><div><div class="scard-val"><?php echo $stats['used']??0; ?></div><div class="scard-lbl">Dah Daftar</div></div></div>
    <div class="scard"><div class="scard-ic si-amber"><i class="fas fa-clock"></i></div><div><div class="scard-val"><?php echo $stats['unused']??0; ?></div><div class="scard-lbl">Belum Daftar</div></div></div>
    <div class="scard"><div class="scard-ic si-blue"><i class="fas fa-user-graduate"></i></div><div><div class="scard-val"><?php echo $stats['students']??0; ?></div><div class="scard-lbl">Pelajar</div></div></div>
    <div class="scard"><div class="scard-ic si-gold"><i class="fas fa-chalkboard-teacher"></i></div><div><div class="scard-val"><?php echo $stats['staffs']??0; ?></div><div class="scard-lbl">Staf</div></div></div>
</div>

<!-- ADD + IMPORT PANELS -->
<div class="panels">
    <!-- ADD ONE -->
    <div class="panel">
        <div class="ph"><div class="ph-ic"><i class="fas fa-plus"></i></div><h3>Tambah Satu</h3></div>
        <div class="pb">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="frow">
                    <div>
                        <label class="flbl">No. Matrik / Staff ID <span style="color:#c0392b">*</span></label>
                        <input type="text" name="id_number" class="finp" placeholder="MC2516203265" required style="text-transform:uppercase">
                    </div>
                    <div>
                        <label class="flbl">Nama Penuh</label>
                        <input type="text" name="full_name" class="finp" placeholder="Nama pelajar">
                    </div>
                </div>
                <div class="frow">
                    <div>
                        <label class="flbl">Jenis</label>
                        <select name="user_type" class="finp">
                            <option value="student">Pelajar</option>
                            <option value="staff">Staf</option>
                        </select>
                    </div>
                    <div>
                        <label class="flbl">Institusi</label>
                        <input type="text" name="institution" class="finp" placeholder="KMJ">
                    </div>
                </div>
                <button type="submit" class="submit-btn"><i class="fas fa-plus-circle"></i> Tambah</button>
            </form>
        </div>
    </div>

    <!-- IMPORT CSV -->
    <div class="panel" id="importPanel">
        <div class="ph"><div class="ph-ic"><i class="fas fa-file-csv"></i></div><h3>Import CSV / Bulk</h3></div>
        <div class="pb">
            <form method="POST">
                <input type="hidden" name="action" value="import">
                <label class="flbl">Data CSV (satu baris = satu ID)</label>
                <textarea name="csv_data" class="finp" placeholder="MC2516203265,Ahmad Ali,student,KMJ&#10;MC2516203266,Siti Hassan,student,KMJ&#10;STF001,Dr. Razak,staff,KMJ" required></textarea>
                <p class="csv-hint">Format: <code>id_number</code>, <code>nama</code>, <code>student/staff</code>, <code>institusi</code><br>Nama, jenis & institusi adalah pilihan.</p>
                <div class="frow">
                    <div>
                        <label class="flbl">Jenis Default</label>
                        <select name="default_type" class="finp"><option value="student">Pelajar</option><option value="staff">Staf</option></select>
                    </div>
                    <div>
                        <label class="flbl">Institusi Default</label>
                        <input type="text" name="default_institution" class="finp" placeholder="KMJ">
                    </div>
                </div>
                <button type="submit" class="submit-btn"><i class="fas fa-upload"></i> Import</button>
            </form>
        </div>
    </div>
</div>

<!-- TABLE -->
<div class="panel">
    <div class="ph">
        <div class="ph-ic"><i class="fas fa-list"></i></div>
        <h3>Senarai (<?php echo count($matrics); ?> rekod)</h3>
    </div>
    <div class="pb">
        <!-- TOOLBAR -->
        <div class="toolbar">
            <form method="GET" class="search-wrap">
                <i class="fas fa-search"></i>
                <input type="text" name="q" class="search-inp" placeholder="Cari ID atau nama..." value="<?php echo htmlspecialchars($search); ?>">
                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
            </form>
            <div class="filter-tabs">
                <?php
                $tabs = ['all'=>'Semua','unused'=>'Belum Daftar','used'=>'Dah Daftar','student'=>'Pelajar','staff'=>'Staf'];
                foreach($tabs as $k=>$v): ?>
                <a href="?filter=<?php echo $k; ?>&q=<?php echo urlencode($search); ?>" class="ftab <?php echo $filter===$k?'act':''; ?>"><?php echo $v; ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- TABLE -->
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>No. Matrik / ID</th>
                        <th>Nama</th>
                        <th>Jenis</th>
                        <th>Institusi</th>
                        <th>Status</th>
                        <th>Ditambah</th>
                        <th>Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(empty($matrics)): ?>
                <tr><td colspan="8" style="text-align:center;color:#aaa;padding:32px">Tiada rekod ditemui.</td></tr>
                <?php else: ?>
                <?php foreach($matrics as $i => $m): ?>
                <tr>
                    <td style="color:#aaa;font-size:12px"><?php echo $i+1; ?></td>
                    <td>
                        <code style="background:var(--teal-l);color:var(--teal);padding:3px 8px;border-radius:6px;font-size:12px;font-weight:800;letter-spacing:.04em"><?php echo htmlspecialchars($m['id_number']); ?></code>
                    </td>
                    <td style="font-size:13px"><?php echo htmlspecialchars($m['full_name'] ?? '—'); ?></td>
                    <td><span class="badge-<?php echo $m['user_type']==='staff'?'sf':'st'; ?>"><?php echo $m['user_type']==='staff'?'Staf':'Pelajar'; ?></span></td>
                    <td style="font-size:12px;color:var(--mid)"><?php echo htmlspecialchars($m['institution'] ?? '—'); ?></td>
                    <td>
                        <?php if($m['is_used']): ?>
                        <span class="badge-used"><i class="fas fa-check" style="font-size:9px;margin-right:3px"></i>Dah Daftar</span>
                        <?php else: ?>
                        <span class="badge-unused"><i class="fas fa-clock" style="font-size:9px;margin-right:3px"></i>Belum Daftar</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:11px;color:#aaa"><?php echo date('d M Y', strtotime($m['created_at'])); ?></td>
                    <td>
                        <?php if(!$m['is_used']): ?>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Padam ID <?php echo htmlspecialchars($m['id_number']); ?>?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="matric_id" value="<?php echo (int)$m['id']; ?>">
                            <button type="submit" class="del-btn" title="Padam"><i class="fas fa-trash-alt"></i></button>
                        </form>
                        <?php else: ?>
                        <button class="del-btn" disabled title="Tidak boleh padam — sudah digunakan"><i class="fas fa-lock" style="font-size:11px"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>
<footer>
    <span>© 2026 Smart Locker System — Institution Admin</span>
    <span>Logged in: <?php echo htmlspecialchars($_SESSION['admin_name']); ?> · <?php echo date('H:i'); ?></span>
</footer>
</div>
</body>
</html>