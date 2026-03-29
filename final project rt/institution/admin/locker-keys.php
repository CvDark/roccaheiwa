<?php
require_once 'config.php';

// Ensure only admins can access
if (!isAdminLoggedIn()) {
    redirect('login.php');
}

$admin_name = $_SESSION['admin_name'] ?? $_SESSION['user_name'] ?? 'Admin';
$message    = '';
$msg_type   = '';

// ── UPDATE LOCKER KEY ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_key') {
    $locker_id  = (int)($_POST['locker_id'] ?? 0);
    $locker_key = trim($_POST['locker_key'] ?? '');

    if (!$locker_id) {
        $message = 'Invalid Locker ID.'; $msg_type = 'error';
    } elseif (strlen($locker_key) < 4) {
        $message = 'Locker key must be at least 4 characters.'; $msg_type = 'error';
    } elseif (strlen($locker_key) > 50) {
        $message = 'Locker key is too long (maximum 50 characters).'; $msg_type = 'error';
    } else {
        try {
            $pdo->prepare("UPDATE lockers SET locker_key = ? WHERE id = ?")
                ->execute([$locker_key, $locker_id]);
            $message = 'Locker key updated successfully.'; $msg_type = 'success';
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage(); $msg_type = 'error';
        }
    }
}

// ── CLEAR LOCKER KEY ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_key') {
    $locker_id = (int)($_POST['locker_id'] ?? 0);
    if ($locker_id) {
        try {
            $pdo->prepare("UPDATE lockers SET locker_key = NULL WHERE id = ?")
                ->execute([$locker_id]);
            $message = 'Locker key deleted successfully.'; $msg_type = 'success';
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage(); $msg_type = 'error';
        }
    }
}

// ── BULK SET KEYS ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_set') {
    $keys = $_POST['bulk_keys'] ?? [];
    $updated = 0;
    try {
        foreach ($keys as $lid => $key) {
            $key = trim($key);
            if ($key !== '') {
                $pdo->prepare("UPDATE lockers SET locker_key = ? WHERE id = ?")
                    ->execute([$key, (int)$lid]);
                $updated++;
            }
        }
        $message = "$updated locker key(s) updated successfully."; $msg_type = 'success';
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage(); $msg_type = 'error';
    }
}

// ── FETCH ALL LOCKERS + assignment info ──
try {
    $lockers = $pdo->query("
        SELECT l.*,
               COUNT(ula.id) AS total_assignments,
               u.full_name AS assigned_user,
               u.email AS assigned_email
        FROM lockers l
        LEFT JOIN user_locker_assignments ula ON ula.locker_id = l.id AND ula.is_active = 1
        LEFT JOIN users u ON u.id = ula.user_id
        GROUP BY l.id
        ORDER BY l.unique_code ASC
    ")->fetchAll();
} catch (Exception $e) { $lockers = []; }

$total    = count($lockers);
$hasKey   = count(array_filter($lockers, fn($l) => !empty($l['locker_key'])));
$noKey    = $total - $hasKey;
$occupied = count(array_filter($lockers, fn($l) => $l['status'] === 'occupied'));
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Locker Keys — Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="admin-style.css">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root{
  --teal:#0d7377;--teal-d:#085c60;--teal-l:#e8f6f7;
  --dark:#0f1f20;--mid:#3d5c5e;--light:#f4f9f9;--border:#d0e8e9;
  --sw:240px;--gold:#f0a500;--red:#e74c3c;--green:#0a7c63;
}
body{font-family:"Plus Jakarta Sans",sans-serif;background:var(--light);color:var(--dark);display:flex;min-height:100vh;}

/* MAIN */
.main{margin-left:var(--sw);flex:1;display:flex;flex-direction:column;}
.topbar{background:#fff;border-bottom:1px solid var(--border);padding:16px 32px;position:sticky;top:0;z-index:40;display:flex;align-items:center;justify-content:space-between;}
.topbar-left h1{font-size:18px;font-weight:800;}
.topbar-left p{font-size:12px;color:var(--mid);margin-top:2px;}
.content{padding:28px 32px;flex:1;}

/* STATS */
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px;}
.stat{background:#fff;border:1.5px solid var(--border);border-radius:14px;padding:16px 18px;display:flex;align-items:center;gap:14px;}
.stat-ic{width:42px;height:42px;border-radius:11px;display:grid;place-items:center;font-size:18px;flex-shrink:0;}
.stat-val{font-size:22px;font-weight:800;line-height:1;}
.stat-lbl{font-size:11px;color:var(--mid);margin-top:3px;}

/* ALERT */
.msg{padding:12px 16px;border-radius:10px;font-size:13px;display:flex;align-items:flex-start;gap:9px;margin-bottom:20px;line-height:1.5;}
.msg.success{background:#e8f7f3;border:1.5px solid #b6e8da;color:#0a7c63;}
.msg.error{background:#fdf0ef;border:1.5px solid #f5c6c2;color:#c0392b;}

/* FILTER BAR */
.filterbar{background:#fff;border:1.5px solid var(--border);border-radius:12px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
.search-wrap{position:relative;flex:1;min-width:200px;}
.search-wrap input{width:100%;padding:9px 12px 9px 36px;border:2px solid var(--border);border-radius:9px;font-family:inherit;font-size:13px;outline:none;transition:border-color .2s;background:var(--light);}
.search-wrap input:focus{border-color:var(--teal);background:#fff;}
.search-wrap i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#aac5c6;font-size:13px;}
.filter-btn{padding:8px 14px;border-radius:8px;font-family:inherit;font-size:12px;font-weight:700;border:2px solid var(--border);background:var(--light);color:var(--mid);cursor:pointer;transition:all .2s;}
.filter-btn:hover,.filter-btn.on{border-color:var(--teal);background:var(--teal-l);color:var(--teal);}
.btn-gen{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:9px;font-family:inherit;font-size:12px;font-weight:700;border:none;cursor:pointer;transition:all .2s;}
.btn-teal{background:var(--teal);color:#fff;}.btn-teal:hover{background:var(--teal-d);}
.btn-gold{background:var(--gold);color:#fff;}.btn-gold:hover{background:#c87f00;}

/* TABLE */
.card{background:#fff;border:1.5px solid var(--border);border-radius:16px;overflow:hidden;}
.ch{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;}
.ch h2{font-size:15px;font-weight:800;flex:1;}
.ch-ic{width:32px;height:32px;border-radius:8px;background:var(--teal-l);display:grid;place-items:center;color:var(--teal);font-size:13px;}
table{width:100%;border-collapse:collapse;}
thead th{padding:11px 16px;text-align:left;font-size:11px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--mid);background:var(--light);border-bottom:1.5px solid var(--border);}
tbody tr{border-bottom:1px solid var(--border);transition:background .15s;}
tbody tr:last-child{border-bottom:none;}
tbody tr:hover{background:#f8fffe;}
tbody td{padding:13px 16px;font-size:13px;vertical-align:middle;}
.badge-status{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;}
.bs-av{background:#e8f7f3;color:#0a7c63;}
.bs-oc{background:#fdf0ef;color:#c0392b;}
.bs-ma{background:#fff8e1;color:#c87f00;}
.badge-key{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;}
.bk-set{background:#e8f7f3;color:#0a7c63;}
.bk-none{background:#fdf0ef;color:#c0392b;}

/* KEY DISPLAY */
.key-display{display:flex;align-items:center;gap:8px;}
.key-masked{font-family:monospace;font-size:13px;font-weight:700;color:var(--teal);background:var(--teal-l);padding:3px 10px;border-radius:6px;letter-spacing:.1em;cursor:pointer;transition:all .2s;user-select:none;}
.key-masked:hover{background:var(--teal);color:#fff;}
.key-visible{font-family:monospace;font-size:13px;font-weight:700;color:#0a7c63;background:#e8f7f3;padding:3px 10px;border-radius:6px;letter-spacing:.08em;}

/* INLINE EDIT */
.key-edit-row{display:flex;align-items:center;gap:7px;}
.key-edit-row input{padding:7px 10px;border:2px solid var(--border);border-radius:8px;font-family:monospace;font-size:13px;font-weight:700;letter-spacing:.08em;width:140px;outline:none;transition:all .2s;}
.key-edit-row input:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(13,115,119,.08);}
.ic-btn{width:30px;height:30px;border-radius:7px;border:none;cursor:pointer;display:grid;place-items:center;font-size:12px;transition:all .2s;flex-shrink:0;}
.ic-save{background:#0a7c63;color:#fff;}.ic-save:hover{background:#086b54;}
.ic-cancel{background:var(--light);color:var(--mid);border:1.5px solid var(--border);}.ic-cancel:hover{border-color:var(--red);color:var(--red);}
.ic-edit{background:var(--teal-l);color:var(--teal);}.ic-edit:hover{background:var(--teal);color:#fff;}
.ic-del{background:#fdf0ef;color:#e74c3c;}.ic-del:hover{background:#e74c3c;color:#fff;}
.ic-eye{background:var(--teal-l);color:var(--teal);}.ic-eye:hover{background:var(--teal);color:#fff;}
.action-row{display:flex;align-items:center;gap:5px;}

/* WARNING for no key */
.no-key-warn{display:inline-flex;align-items:center;gap:5px;font-size:11px;color:#c0392b;font-weight:600;}

/* MODAL */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:200;align-items:center;justify-content:center;padding:16px;}
.modal-bg.show{display:flex;}
.modal{background:#fff;border-radius:20px;width:100%;max-width:440px;box-shadow:0 24px 80px rgba(0,0,0,.25);animation:mIn .25s ease;}
@keyframes mIn{from{opacity:0;transform:scale(.95) translateY(16px)}to{opacity:1;transform:scale(1) translateY(0)}}
.modal-head{background:linear-gradient(135deg,var(--teal),var(--teal-d));padding:20px 24px;color:#fff;border-radius:20px 20px 0 0;position:relative;}
.modal-head h3{font-size:16px;font-weight:800;margin-bottom:3px;}
.modal-head p{font-size:12px;opacity:.8;}
.modal-close{position:absolute;top:14px;right:16px;background:rgba(255,255,255,.15);border:none;color:#fff;width:30px;height:30px;border-radius:8px;cursor:pointer;font-size:16px;display:grid;place-items:center;transition:background .2s;}
.modal-close:hover{background:rgba(255,255,255,.3);}
.modal-body{padding:24px;}
.flbl{font-size:11px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--mid);display:block;margin-bottom:7px;}
.finp{width:100%;padding:11px 14px;border:2px solid var(--border);border-radius:9px;font-family:monospace;font-size:15px;font-weight:700;background:var(--light);outline:none;transition:border-color .2s;letter-spacing:.08em;}
.finp:focus{border-color:var(--teal);background:#fff;}
.brow{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:18px;}
.btn-full{display:flex;align-items:center;justify-content:center;gap:8px;padding:11px;border-radius:9px;font-family:inherit;font-size:13px;font-weight:700;border:none;cursor:pointer;transition:all .2s;}
.btn-teal-full{background:var(--teal);color:#fff;}.btn-teal-full:hover{background:var(--teal-d);}
.btn-grey-full{background:var(--light);color:var(--mid);border:2px solid var(--border);}.btn-grey-full:hover{border-color:var(--teal);color:var(--teal);}
.key-strength{height:4px;border-radius:2px;margin-top:8px;transition:all .3s;background:var(--border);}
.hint{font-size:11px;color:#aaa;margin-top:6px;}

/* SECURITY NOTICE */
.sec-notice{background:linear-gradient(135deg,#fff8e1,#fffde7);border:2px solid #ffe082;border-radius:12px;padding:14px 16px;margin-bottom:20px;display:flex;gap:12px;}
.sec-notice i{font-size:20px;color:var(--gold);flex-shrink:0;margin-top:1px;}
.sec-notice strong{font-size:13px;color:#7a5c00;display:block;margin-bottom:3px;}
.sec-notice p{font-size:12px;color:#9a7a00;line-height:1.5;}

footer{padding:16px 32px;border-top:1px solid var(--border);font-size:11px;color:#aaa;display:flex;justify-content:space-between;}
@media(max-width:1100px){.stats{grid-template-columns:repeat(2,1fr);}.main{margin-left:0;}.content{padding:20px 16px;}}
</style>
</head>
<body>
<?php require_once 'sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div class="topbar-left">
      <h1><i class="fas fa-key" style="color:var(--teal);margin-right:8px"></i>Manage Locker Keys</h1>
      <p>Set and manage physical keys for each locker — only admins can view these keys</p>
    </div>
    <button class="btn-gen btn-gold" onclick="openBulk()">
      <i class="fas fa-layer-group"></i> Bulk Update Keys
    </button>
  </div>

  <div class="content">

    <?php if($message): ?>
    <div class="msg <?php echo $msg_type; ?>">
      <i class="fas fa-<?php echo $msg_type==='success'?'check-circle':'exclamation-circle'; ?>" style="flex-shrink:0;margin-top:1px"></i>
      <span><?php echo htmlspecialchars($message); ?></span>
    </div>
    <?php endif; ?>

    <!-- SECURITY NOTICE -->
    <div class="sec-notice">
      <i class="fas fa-shield-alt"></i>
      <div>
        <strong>Security Information — Locker Keys</strong>
        <p>The locker key here is the <strong>physical key</strong> attached/written on the locker. Users must enter this key when assigning a locker. The key is <strong>never displayed</strong> to users anywhere in the system — only on this page.</p>
      </div>
    </div>

    <!-- STATS -->
    <div class="stats">
      <div class="stat">
        <div class="stat-ic" style="background:var(--teal-l);color:var(--teal)"><i class="fas fa-box"></i></div>
        <div><div class="stat-val"><?php echo $total; ?></div><div class="stat-lbl">Total Lockers</div></div>
      </div>
      <div class="stat">
        <div class="stat-ic" style="background:#e8f7f3;color:#0a7c63"><i class="fas fa-key"></i></div>
        <div><div class="stat-val" style="color:#0a7c63"><?php echo $hasKey; ?></div><div class="stat-lbl">Has Key</div></div>
      </div>
      <div class="stat">
        <div class="stat-ic" style="background:#fdf0ef;color:#e74c3c"><i class="fas fa-exclamation-triangle"></i></div>
        <div><div class="stat-val" style="color:#e74c3c"><?php echo $noKey; ?></div><div class="stat-lbl">No Key Yet</div></div>
      </div>
      <div class="stat">
        <div class="stat-ic" style="background:#fff8e1;color:var(--gold)"><i class="fas fa-lock"></i></div>
        <div><div class="stat-val" style="color:var(--gold)"><?php echo $occupied; ?></div><div class="stat-lbl">Currently In Use</div></div>
      </div>
    </div>

    <!-- FILTER BAR -->
    <div class="filterbar">
      <div class="search-wrap">
        <i class="fas fa-search"></i>
        <input type="text" id="searchInp" placeholder="Search locker (code, device ID)..." oninput="filterTable()">
      </div>
      <button class="filter-btn on" id="fb-all" onclick="setFilter('all',this)">All</button>
      <button class="filter-btn" id="fb-key" onclick="setFilter('has_key',this)">Has Key</button>
      <button class="filter-btn" id="fb-nokey" onclick="setFilter('no_key',this)">No Key</button>
      <button class="filter-btn" id="fb-occ" onclick="setFilter('occupied',this)">In Use</button>
    </div>

    <!-- TABLE -->
    <div class="card">
      <div class="ch">
        <div class="ch-ic"><i class="fas fa-list"></i></div>
        <h2>Locker & Keys List</h2>
        <span style="font-size:12px;color:var(--mid)" id="countLabel"><?php echo $total; ?> locker</span>
      </div>
      <div style="overflow-x:auto">
        <table id="lockersTable">
          <thead>
            <tr>
              <th>#</th>
              <th>Locker Code</th>
              <th>Device ID</th>
              <th>Status</th>
              <th>Locker Key</th>
              <th>User</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($lockers as $i => $lk): ?>
            <tr data-code="<?php echo strtolower($lk['unique_code']); ?>"
                data-dev="<?php echo strtolower($lk['device_id']??''); ?>"
                data-status="<?php echo $lk['status']; ?>"
                data-haskey="<?php echo !empty($lk['locker_key'])?'1':'0'; ?>">
              <td style="color:#aac5c6;font-size:12px"><?php echo $i+1; ?></td>
              <td>
                <div style="font-weight:800;font-size:14px"><?php echo htmlspecialchars($lk['unique_code']); ?></div>
              </td>
              <td style="font-family:monospace;font-size:12px;color:var(--mid)"><?php echo htmlspecialchars($lk['device_id']??'—'); ?></td>
              <td>
                <?php
                $sc = ['available'=>'bs-av','occupied'=>'bs-oc','maintenance'=>'bs-ma','active'=>'bs-av'];
                $sl = ['available'=>'Available','occupied'=>'Occupied','maintenance'=>'Maintenance','active'=>'Active'];
                $ic = ['available'=>'circle','occupied'=>'lock','maintenance'=>'tools','active'=>'check-circle'];
                $st = $lk['status']??'available';
                ?>
                <span class="badge-status <?php echo $sc[$st]??'bs-av'; ?>">
                  <i class="fas fa-<?php echo $ic[$st]??'circle'; ?>" style="font-size:8px"></i>
                  <?php echo $sl[$st]??ucfirst($st); ?>
                </span>
              </td>
              <td>
                <?php if(!empty($lk['locker_key'])): ?>
                <!-- Key ada — tunjuk masked, boleh toggle reveal -->
                <div class="key-display" id="kd_<?php echo $lk['id']; ?>">
                  <span class="key-masked" id="km_<?php echo $lk['id']; ?>"
                        title="Click to reveal"
                        onclick="toggleKey(<?php echo $lk['id']; ?>, '<?php echo addslashes(htmlspecialchars($lk['locker_key'])); ?>')">
                    ••••••••
                  </span>
                  <span class="badge-key bk-set"><i class="fas fa-check" style="font-size:8px"></i> Set</span>
                </div>
                <?php else: ?>
                <span class="no-key-warn"><i class="fas fa-exclamation-triangle"></i> Not set</span>
                <?php endif; ?>
              </td>
              <td style="font-size:12px">
                <?php if($lk['assigned_user']): ?>
                <div style="font-weight:700"><?php echo htmlspecialchars($lk['assigned_user']); ?></div>
                <div style="color:var(--mid)"><?php echo htmlspecialchars($lk['assigned_email']); ?></div>
                <?php else: ?>
                <span style="color:#aac5c6">—</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="action-row">
                  <!-- Edit Key -->
                  <button class="ic-btn ic-edit" title="Edit Key"
                          onclick="openEdit(<?php echo $lk['id']; ?>, '<?php echo htmlspecialchars($lk['unique_code']); ?>', '<?php echo addslashes($lk['locker_key']??''); ?>')">
                    <i class="fas fa-edit"></i>
                  </button>
                  <?php if(!empty($lk['locker_key'])): ?>
                  <!-- Reveal Key -->
                  <button class="ic-btn ic-eye" title="Show/Hide Key"
                          onclick="toggleKey(<?php echo $lk['id']; ?>, '<?php echo addslashes(htmlspecialchars($lk['locker_key'])); ?>')">
                    <i class="fas fa-eye" id="eyeIc_<?php echo $lk['id']; ?>"></i>
                  </button>
                  <!-- Clear Key -->
                  <button class="ic-btn ic-del" title="Delete Key"
                          onclick="confirmClear(<?php echo $lk['id']; ?>, '<?php echo htmlspecialchars($lk['unique_code']); ?>')">
                    <i class="fas fa-trash"></i>
                  </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
  <footer>
    <span>© 2026 Smart Locker System — Admin Panel</span>
    <span><?php echo htmlspecialchars($admin_name)." · ".date("d M Y, H:i"); ?></span>
  </footer>
</div>

<!-- ═══════════════════════════════════
     MODAL: EDIT KEY
═══════════════════════════════════ -->
<div class="modal-bg" id="mEdit">
  <div class="modal">
    <div class="modal-head">
      <h3><i class="fas fa-key" style="margin-right:8px"></i>Set Locker Key</h3>
      <p id="mEditSub">—</p>
      <button class="modal-close" onclick="closeEdit()">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" id="editForm">
        <input type="hidden" name="action" value="update_key">
        <input type="hidden" name="locker_id" id="editLid">

        <label class="flbl"><i class="fas fa-key" style="margin-right:5px;color:var(--teal)"></i>New Locker Key *</label>
        <div style="position:relative">
          <input type="text" name="locker_key" id="editKeyInp" class="finp"
                 placeholder="Example: ABC123" maxlength="50" autocomplete="off"
                 oninput="checkStrength(this.value)" required>
        </div>
        <div class="key-strength" id="strengthBar"></div>
        <p class="hint" id="strengthHint">Enter the physical locker key (at least 4 characters)</p>

        <div style="background:#fff8e1;border:1.5px solid #ffe082;border-radius:9px;padding:11px 13px;margin-top:14px;font-size:12px;color:#7a5c00;display:flex;gap:8px;align-items:flex-start">
          <i class="fas fa-exclamation-triangle" style="flex-shrink:0;margin-top:1px;color:var(--gold)"></i>
          <span>Make sure this key <strong>exactly matches</strong> the one written on the physical locker. Users must enter this key to assign the locker.</span>
        </div>

        <div class="brow">
          <button type="button" class="btn-full btn-grey-full" onclick="closeEdit()"><i class="fas fa-times"></i> Cancel</button>
          <button type="submit" class="btn-full btn-teal-full"><i class="fas fa-save"></i> Save Key</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════
     MODAL: BULK UPDATE
═══════════════════════════════════ -->
<div class="modal-bg" id="mBulk">
  <div class="modal" style="max-width:600px">
    <div class="modal-head" style="background:linear-gradient(135deg,#c87f00,var(--gold))">
      <h3><i class="fas fa-layer-group" style="margin-right:8px"></i>Bulk Update Locker Keys</h3>
      <p>Set keys for all lockers at once</p>
      <button class="modal-close" onclick="closeBulk()">✕</button>
    </div>
    <div class="modal-body">
      <p style="font-size:12px;color:var(--mid);margin-bottom:16px">
        <i class="fas fa-info-circle" style="color:var(--teal);margin-right:5px"></i>
        Leave the field empty for lockers you don't want to update.
      </p>
      <form method="POST" id="bulkForm" style="max-height:55vh;overflow-y:auto;padding-right:4px">
        <input type="hidden" name="action" value="bulk_set">
        <table style="width:100%;border-collapse:collapse">
          <thead>
            <tr>
              <th style="padding:8px 10px;text-align:left;font-size:11px;font-weight:700;color:var(--mid);border-bottom:1.5px solid var(--border)">Locker</th>
              <th style="padding:8px 10px;text-align:left;font-size:11px;font-weight:700;color:var(--mid);border-bottom:1.5px solid var(--border)">Current Key</th>
              <th style="padding:8px 10px;text-align:left;font-size:11px;font-weight:700;color:var(--mid);border-bottom:1.5px solid var(--border)">New Key</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($lockers as $lk): ?>
            <tr style="border-bottom:1px solid var(--border)">
              <td style="padding:10px">
                <div style="font-weight:800;font-size:13px"><?php echo htmlspecialchars($lk['unique_code']); ?></div>
                <div style="font-size:10px;color:var(--mid)"><?php echo htmlspecialchars($lk['device_id']??'—'); ?></div>
              </td>
              <td style="padding:10px;font-family:monospace;font-size:12px">
                <?php if(!empty($lk['locker_key'])): ?>
                <span style="background:var(--teal-l);color:var(--teal);padding:2px 8px;border-radius:5px">••••••</span>
                <?php else: ?>
                <span style="color:#e74c3c;font-size:11px">Not set</span>
                <?php endif; ?>
              </td>
              <td style="padding:10px">
                <input type="text" name="bulk_keys[<?php echo $lk['id']; ?>]"
                       placeholder="<?php echo !empty($lk['locker_key'])?'(keep unchanged if empty)':'Enter key...'; ?>"
                       maxlength="50" autocomplete="off"
                       style="width:100%;padding:7px 10px;border:2px solid var(--border);border-radius:7px;font-family:monospace;font-size:12px;font-weight:700;outline:none;letter-spacing:.05em;transition:border-color .2s;"
                       onfocus="this.style.borderColor='var(--teal)'"
                       onblur="this.style.borderColor='var(--border)'">
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div class="brow" style="margin-top:16px">
          <button type="button" class="btn-full btn-grey-full" onclick="closeBulk()"><i class="fas fa-times"></i> Cancel</button>
          <button type="submit" class="btn-full" style="background:var(--gold);color:#fff;display:flex;align-items:center;justify-content:center;gap:8px;padding:11px;border-radius:9px;font-family:inherit;font-size:13px;font-weight:700;border:none;cursor:pointer;" onclick="return confirm('Update all filled keys?')">
            <i class="fas fa-save"></i> Save All
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Hidden clear form -->
<form id="clearForm" method="POST" style="display:none">
  <input type="hidden" name="action" value="clear_key">
  <input type="hidden" name="locker_id" id="clearLid">
</form>

<script>
let currentFilter = 'all';
let keyVisible = {}; // track which keys are currently revealed

/* ── TOGGLE REVEAL KEY ── */
function toggleKey(id, key) {
  const span = document.getElementById("km_" + id);
  const eye  = document.getElementById("eyeIc_" + id);
  if (!span) return;
  if (keyVisible[id]) {
    span.textContent = "••••••••";
    span.title = "Click to reveal";
    if (eye) eye.className = "fas fa-eye";
    keyVisible[id] = false;
  } else {
    span.textContent = key;
    span.title = "Click to hide";
    if (eye) eye.className = "fas fa-eye-slash";
    keyVisible[id] = true;
    // Auto hide after 5 seconds
    setTimeout(() => {
      if (keyVisible[id]) toggleKey(id, key);
    }, 5000);
  }
}

/* ── EDIT MODAL ── */
function openEdit(lid, code, currentKey) {
  document.getElementById("editLid").value = lid;
  document.getElementById("mEditSub").textContent = "Locker: " + code;
  document.getElementById("editKeyInp").value = currentKey || '';
  checkStrength(currentKey || '');
  document.getElementById("mEdit").classList.add("show");
  setTimeout(() => document.getElementById("editKeyInp").focus(), 200);
}
function closeEdit() {
  document.getElementById("mEdit").classList.remove("show");
}

/* ── KEY STRENGTH INDICATOR ── */
function checkStrength(val) {
  const bar  = document.getElementById("strengthBar");
  const hint = document.getElementById("strengthHint");
  const len  = val.trim().length;
  if (len === 0) { bar.style.background = "var(--border)"; bar.style.width = "0"; hint.textContent = "Enter the physical locker key (at least 4 characters)"; hint.style.color = "#aaa"; return; }
  if (len < 4)   { bar.style.background = "#e74c3c"; bar.style.width = "25%"; hint.textContent = "Too short (minimum 4 characters)"; hint.style.color = "#e74c3c"; return; }
  if (len < 6)   { bar.style.background = "#f0a500"; bar.style.width = "55%"; hint.textContent = "Acceptable key"; hint.style.color = "#c87f00"; return; }
  if (len < 10)  { bar.style.background = "#0a7c63"; bar.style.width = "80%"; hint.textContent = "Strong key ✓"; hint.style.color = "#0a7c63"; return; }
  bar.style.background = "#0a7c63"; bar.style.width = "100%"; hint.textContent = "Very strong key ✓✓"; hint.style.color = "#0a7c63";
}

/* ── BULK MODAL ── */
function openBulk() { document.getElementById("mBulk").classList.add("show"); }
function closeBulk() { document.getElementById("mBulk").classList.remove("show"); }

/* ── CONFIRM CLEAR ── */
function confirmClear(lid, code) {
  if (confirm("Delete locker key for locker " + code + "?\n\nUsers will not be able to assign this locker until a new key is set.")) {
    document.getElementById("clearLid").value = lid;
    document.getElementById("clearForm").submit();
  }
}

/* ── CLOSE MODAL ON BACKDROP ── */
["mEdit","mBulk"].forEach(id => {
  document.getElementById(id).addEventListener("click", e => {
    if (e.target === document.getElementById(id)) document.getElementById(id).classList.remove("show");
  });
});

/* ── SEARCH & FILTER ── */
function setFilter(f, btn) {
  currentFilter = f;
  document.querySelectorAll(".filter-btn").forEach(b => b.classList.remove("on"));
  btn.classList.add("on");
  filterTable();
}

function filterTable() {
  const q   = document.getElementById("searchInp").value.toLowerCase();
  const rows = document.querySelectorAll("#lockersTable tbody tr");
  let visible = 0;
  rows.forEach(row => {
    const code   = row.dataset.code   || '';
    const dev    = row.dataset.dev    || '';
    const status = row.dataset.status || '';
    const hasKey = row.dataset.haskey || '0';
    const matchQ = !q || code.includes(q) || dev.includes(q);
    let matchF = true;
    if (currentFilter === 'has_key')  matchF = hasKey === '1';
    if (currentFilter === 'no_key')   matchF = hasKey === '0';
    if (currentFilter === 'occupied') matchF = status === 'occupied';
    row.style.display = (matchQ && matchF) ? '' : 'none';
    if (matchQ && matchF) visible++;
  });
  document.getElementById("countLabel").textContent = visible + " locker";
}
</script>
</body>
</html>