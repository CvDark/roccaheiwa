<?php
// One-time setup: create user_nfc_cards table + migrate old cards + reset stuck modes
// Run once: http://localhost/final%20project%20rt/commercial/setup_nfc_table.php
// DELETE this file after running!

require_once 'config.php';
$results = [];

// 1. Create table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_nfc_cards (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            user_id       INT NOT NULL,
            nfc_uid       VARCHAR(50) NOT NULL,
            label         VARCHAR(100) DEFAULT NULL,
            registered_at DATETIME NOT NULL DEFAULT NOW(),
            UNIQUE KEY uk_nfc_uid (nfc_uid),
            KEY idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $results[] = ['ok', 'Table user_nfc_cards created (or already exists).'];
} catch (Exception $e) {
    $results[] = ['err', 'Create table failed: ' . $e->getMessage()];
}

// 2. Migrate existing nfc_uid from users table
try {
    $pdo->exec("
        INSERT IGNORE INTO user_nfc_cards (user_id, nfc_uid, label, registered_at)
        SELECT id, nfc_uid, 'Card 1 (Migrated)', COALESCE(nfc_registered_at, NOW())
        FROM users WHERE nfc_uid IS NOT NULL AND nfc_uid != ''
    ");
    $results[] = ['ok', 'Migrated existing NFC UIDs from users.nfc_uid into user_nfc_cards.'];
} catch (Exception $e) {
    $results[] = ['warn', 'Migration: ' . $e->getMessage()];
}

// 3. Fix label numbering
try {
    $userIds = $pdo->query("SELECT DISTINCT user_id FROM user_nfc_cards")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($userIds as $uid) {
        $cards = $pdo->prepare("SELECT id FROM user_nfc_cards WHERE user_id = ? ORDER BY registered_at ASC");
        $cards->execute([$uid]);
        foreach ($cards->fetchAll() as $i => $row) {
            $pdo->prepare("UPDATE user_nfc_cards SET label = ? WHERE id = ?")
                ->execute(['Card ' . ($i + 1), $row['id']]);
        }
    }
    $results[] = ['ok', 'Card labels renumbered.'];
} catch (Exception $e) {
    $results[] = ['warn', 'Label fix: ' . $e->getMessage()];
}

// 4. Reset any stuck register mode (fixes ESP32 being stuck without pressing EN)
try {
    $pdo->exec("UPDATE lockers SET nfc_register_mode = 0 WHERE nfc_register_mode = 1");
    $results[] = ['ok', 'Reset stuck nfc_register_mode → ESP32 will return to ACCESS mode automatically.'];
} catch (Exception $e) {
    $results[] = ['warn', 'Reset mode: ' . $e->getMessage()];
}

// 5. Show all registered cards
try {
    $count = $pdo->query("SELECT COUNT(*) FROM user_nfc_cards")->fetchColumn();
    $cards = $pdo->query("
        SELECT u.full_name, c.nfc_uid, c.label, c.registered_at
        FROM user_nfc_cards c
        JOIN users u ON u.id = c.user_id
        ORDER BY c.user_id, c.registered_at
    ")->fetchAll();
    $results[] = ['ok', "Total registered cards: $count"];
    foreach ($cards as $c) {
        $results[] = ['info', "→ {$c['full_name']} | [{$c['label']}] {$c['nfc_uid']} | {$c['registered_at']}"];
    }
} catch (Exception $e) {
    $results[] = ['err', 'Verify failed: ' . $e->getMessage()];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>NFC Setup</title>
    <style>
        body { font-family: monospace; background: #1e2535; color: #e2e8f0; padding: 40px; }
        .ok   { color: #22c55e; } .err { color: #ef4444; } .warn { color: #f59e0b; } .info { color: #94a3b8; }
        li { margin: 8px 0; font-size: .95rem; list-style: none; }
        h2 { color: #667eea; margin-bottom: 20px; }
        .done { background: #16a34a22; border: 1px solid #22c55e; padding: 16px 20px; border-radius: 10px; margin-top: 24px; line-height: 2; }
        a { color: #667eea; }
    </style>
</head>
<body>
<h2>🔧 NFC Multi-Card Setup</h2>
<ul>
<?php foreach ($results as [$type, $msg]): ?>
    <li class="<?= $type ?>">
        <?= $type === 'ok' ? '✅' : ($type === 'err' ? '❌' : ($type === 'info' ? '   📋' : '⚠️')) ?>
        <?= htmlspecialchars($msg) ?>
    </li>
<?php endforeach; ?>
</ul>
<div class="done">
    ✅ <strong>Done!</strong> Delete this file after running:<br>
    <code>/opt/lampp/htdocs/final project rt/commercial/setup_nfc_table.php</code><br>
    Then go to → <a href="profile.php">Profile page</a>
</div>
</body>
</html>
