<?php
/**
 * VendFlow - Sales Velocity Engine & Smart Par Advisor Test Suite
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = get_db();
echo "========================================================\n";
echo "VendFlow - Sales Velocity & Par Advisor Test Suite\n";
echo "========================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertVelTest(string $description, bool $condition): void {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] " . $description . "\n";
        $passCount++;
    } else {
        echo "[FAIL] " . $description . "\n";
        $failCount++;
    }
}

// 1. Setup Test Operator & Products
echo "1. Setting up Test Workspace & Fixtures...\n";
$pdo->exec("DELETE FROM users WHERE email = 'velocity_test@vendflow.io'");
$opHash = password_hash('secret123', PASSWORD_BCRYPT);
$opStmt = $pdo->prepare("
    INSERT INTO users (name, email, password_hash, business_name, plan_tier, license_key)
    VALUES ('Velocity Tester', 'velocity_test@vendflow.io', :hash, 'Fast Snack Ops', 'pro', 'TEST-LIC-VEL-01')
");
$opStmt->execute([':hash' => $opHash]);
$testUid = (int)$pdo->lastInsertId();
assertVelTest("Created test operator ID {$testUid}", $testUid > 0);

$mStmt = $pdo->prepare("INSERT INTO machines (user_id, name, location, status) VALUES (:uid, 'Vel Machine 1', 'Lobby A', 'active')");
$mStmt->execute([':uid' => $testUid]);
$testMid = (int)$pdo->lastInsertId();
assertVelTest("Created test machine ID {$testMid}", $testMid > 0);

// Fast Mover (Selling 10 units/day, current par 8 -> under-buffered!)
$p1 = $pdo->prepare("INSERT INTO products (user_id, machine_id, name, sku, category, quantity, min_threshold) VALUES (:uid, :mid, 'Nitro Cold Brew', 'NCB-01', 'Cold Drinks', 4, 8)");
$p1->execute([':uid' => $testUid, ':mid' => $testMid]);
$prodFastId = (int)$pdo->lastInsertId();

// Steady Mover (Selling 2 units/day, par 15 -> adequately buffered)
$p2 = $pdo->prepare("INSERT INTO products (user_id, machine_id, name, sku, category, quantity, min_threshold) VALUES (:uid, :mid, 'Granola Bar', 'GB-02', 'Chips & Snacks', 12, 15)");
$p2->execute([':uid' => $testUid, ':mid' => $testMid]);
$prodSteadyId = (int)$pdo->lastInsertId();

// New SKU (0 sales)
$p3 = $pdo->prepare("INSERT INTO products (user_id, machine_id, name, sku, category, quantity, min_threshold) VALUES (:uid, :mid, 'New Matcha Can', 'MC-03', 'Cold Drinks', 20, 10)");
$p3->execute([':uid' => $testUid, ':mid' => $testMid]);
$prodNewId = (int)$pdo->lastInsertId();

// Insert simulated sales for Fast Mover: 70 units over last 7 days = 10 units/day
$logIns = $pdo->prepare("
    INSERT INTO inventory_logs (user_id, product_id, machine_id, previous_qty, new_qty, change_qty, change_type, notes, created_at)
    VALUES (:uid, :pid, :mid, 50, 40, :sold, 'manual_sale', 'Tally sale', DATE_SUB(NOW(), INTERVAL :days DAY))
");
for ($d = 1; $d <= 7; $d++) {
    $logIns->execute([':uid' => $testUid, ':pid' => $prodFastId, ':mid' => $testMid, ':sold' => 10, ':days' => $d]);
}

// Insert simulated sales for Steady Mover: 14 units over 7 days = 2 units/day
for ($d = 1; $d <= 7; $d++) {
    $logIns->execute([':uid' => $testUid, ':pid' => $prodSteadyId, ':mid' => $testMid, ':sold' => 2, ':days' => $d]);
}

echo "\n2. Testing Product Velocity Engine...\n";
$fastMetrics = get_product_velocity_metrics($pdo, $prodFastId, 14);
assertVelTest("Fast mover daily velocity is approx 5.0 - 10.0 units/day (got {$fastMetrics['daily_velocity']})", $fastMetrics['daily_velocity'] >= 5.0);
assertVelTest("Fast mover band is 'high'", $fastMetrics['band'] === 'high');
assertVelTest("Fast mover is detected as under-buffered", $fastMetrics['is_under_buffered'] === true);
assertVelTest("Fast mover recommended par is higher than current (8) (got {$fastMetrics['recommended_par']})", $fastMetrics['recommended_par'] > 8);

$steadyMetrics = get_product_velocity_metrics($pdo, $prodSteadyId, 14);
assertVelTest("Steady mover band is 'steady'", $steadyMetrics['band'] === 'steady');
assertVelTest("Steady mover is NOT under-buffered", $steadyMetrics['is_under_buffered'] === false);

$newMetrics = get_product_velocity_metrics($pdo, $prodNewId, 14);
assertVelTest("New product with 0 sales has band 'new'", $newMetrics['band'] === 'new');
assertVelTest("New product daily velocity is 0.0", $newMetrics['daily_velocity'] === 0.0);

echo "\n3. Testing Workspace Batch Recommendations...\n";
$allRecs = get_workspace_velocity_recommendations($pdo, $testUid, 14);
assertVelTest("Workspace recommendations contain all 3 products", count($allRecs) === 3);
assertVelTest("Workspace recommendation for Fast Mover matches single product calculation", isset($allRecs[$prodFastId]) && $allRecs[$prodFastId]['band'] === 'high');

echo "\n4. Testing Par Threshold Update Engine...\n";
$updated = update_product_par_threshold($pdo, $testUid, $prodFastId, (int)$fastMetrics['recommended_par']);
assertVelTest("Par threshold update succeeded", $updated === true);

$checkStmt = $pdo->prepare("SELECT min_threshold FROM products WHERE id = :id");
$checkStmt->execute([':id' => $prodFastId]);
$newPar = (int)$checkStmt->fetchColumn();
assertVelTest("Product min_threshold updated in DB to {$fastMetrics['recommended_par']} (found {$newPar})", $newPar === $fastMetrics['recommended_par']);

// Cross-tenant protection test
$unauthorized = update_product_par_threshold($pdo, 999999, $prodFastId, 50);
assertVelTest("Cross-tenant par update is rejected", $unauthorized === false);

// Check Audit Log for the Par Update
$auditStmt = $pdo->prepare("SELECT * FROM inventory_logs WHERE product_id = :pid AND change_type = 'adjustment' ORDER BY id DESC LIMIT 1");
$auditStmt->execute([':pid' => $prodFastId]);
$auditRow = $auditStmt->fetch();
assertVelTest("Audit log entry created for par threshold update", !empty($auditRow) && str_contains($auditRow['notes'], 'Par Advisor'));

echo "\n5. Testing Velocity Badge Formatter...\n";
$badgeInfo = get_velocity_badge_info($fastMetrics);
assertVelTest("High velocity badge class is 'badge-velocity-high'", str_contains($badgeInfo['class'], 'badge-velocity-high'));
assertVelTest("High velocity cadence specifies 1-2 days", str_contains($badgeInfo['cadence'], '1–2'));

echo "\n6. Testing Par Advisor POST Action Handlers...\n";
$lowStockSrc = file_get_contents(__DIR__ . '/low-stock.php');
$invSrc = file_get_contents(__DIR__ . '/inventory.php');

assertVelTest("low-stock.php handles 'apply_par_recommendation' POST action", str_contains($lowStockSrc, "apply_par_recommendation"));
assertVelTest("inventory.php handles 'apply_par_recommendation' POST action", str_contains($invSrc, "apply_par_recommendation"));

echo "\n========================================================\n";
echo "Velocity Test Suite Results: {$passCount} passed, {$failCount} failed\n";
echo "========================================================\n";
exit($failCount > 0 ? 1 : 0);
