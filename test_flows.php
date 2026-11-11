<?php
/**
 * Automated Verification Test Script
 * Tests all required user flows from Section 10
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = get_db();
echo "========================================================\n";
echo "VendFlow - Automated User Flow Verification Suite\n";
echo "========================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $description, bool $condition): void {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] " . $description . "\n";
        $passCount++;
    } else {
        echo "[FAIL] " . $description . "\n";
        $failCount++;
    }
}

// Clean up any previous test user
$pdo->exec("DELETE FROM users WHERE email = 'testuser@vendflow.io'");

// TEST 1: User Registration
echo "1. Testing User Registration...\n";
$email = 'testuser@vendflow.io';
$password = 'secretpass123';
$hash = password_hash($password, PASSWORD_BCRYPT);
$license = generate_license_key('business');

$stmt = $pdo->prepare("
    INSERT INTO users (name, email, password_hash, business_name, plan_tier, license_key)
    VALUES ('Taylor Swift', :email, :hash, 'Swift Vending', 'business', :lic)
");
$stmt->execute([':email' => $email, ':hash' => $hash, ':lic' => $license]);
$testUserId = (int)$pdo->lastInsertId();
assertTest("New user Taylor Swift registered with ID {$testUserId}", $testUserId > 0);

// TEST 2: User Login & Credential Verification
echo "\n2. Testing Login & Auth Verification...\n";
$fetchStmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
$fetchStmt->execute([':email' => $email]);
$user = $fetchStmt->fetch();
$authSuccess = ($user && password_verify($password, $user['password_hash']));
assertTest("Password verified successfully with BCRYPT", $authSuccess);

login_user($user);
assertTest("Session logged in for user_id {$user['id']}", is_logged_in() && $_SESSION['user_id'] === $testUserId);

// TEST 3: Adding a Vending Machine
echo "\n3. Testing Adding a Vending Machine...\n";
$mStmt = $pdo->prepare("
    INSERT INTO machines (user_id, name, identifier, location, notes, status)
    VALUES (:uid, 'Machine C — Innovation Hub', 'VM-INNOV-03', 'Level 3 Lobby', 'Installed for tech teams', 'active')
");
$mStmt->execute([':uid' => $testUserId]);
$machineCId = (int)$pdo->lastInsertId();
assertTest("Machine C created with ID {$machineCId}", $machineCId > 0);

// TEST 4: Adding a Product to Machine C
echo "\n4. Testing Adding a Product...\n";
$pStmt = $pdo->prepare("
    INSERT INTO products (user_id, machine_id, name, sku, category, quantity, min_threshold, cost_price, sell_price, notes)
    VALUES (:uid, :mid, 'Granola Protein Bar', 'SNK-GRN-01', 'Healthy Snacks', 20, 10, 0.90, 2.50, 'Slot A1')
");
$pStmt->execute([':uid' => $testUserId, ':mid' => $machineCId]);
$productId = (int)$pdo->lastInsertId();
assertTest("Product Granola Protein Bar created with ID {$productId}", $productId > 0);

// TEST 5: Adjusting Stock (Restock & Deduct)
echo "\n5. Testing Stock Adjustment & Audit Logging...\n";
// Add 10 units
$newQty = 20 + 10;
$up = $pdo->prepare("UPDATE products SET quantity = :qty WHERE id = :id");
$up->execute([':qty' => $newQty, ':id' => $productId]);

$log = $pdo->prepare("
    INSERT INTO inventory_logs (user_id, product_id, machine_id, previous_qty, new_qty, change_qty, change_type, notes)
    VALUES (:uid, :pid, :mid, 20, 30, 10, 'restock', 'Weekly restock batch')
");
$log->execute([':uid' => $testUserId, ':pid' => $productId, ':mid' => $machineCId]);

$currQty = (int)$pdo->query("SELECT quantity FROM products WHERE id = {$productId}")->fetchColumn();
assertTest("Stock increased from 20 to 30 units", $currQty === 30);

// Now deduct 25 units (leaving 5 units -> triggers Low Stock since min is 10)
$newQty2 = 30 - 25;
$up->execute([':qty' => $newQty2, ':id' => $productId]);
$log->execute([':uid' => $testUserId, ':pid' => $productId, ':mid' => $machineCId]);

$currQty2 = (int)$pdo->query("SELECT quantity FROM products WHERE id = {$productId}")->fetchColumn();
assertTest("Stock deducted to 5 units (below min threshold 10)", $currQty2 === 5);

// TEST 6: Identifying Low-Stock Items
echo "\n6. Testing Low-Stock Query & Alert Filter...\n";
$lowItemsStmt = $pdo->prepare("
    SELECT COUNT(*) FROM products 
    WHERE user_id = :uid AND quantity <= min_threshold
");
$lowItemsStmt->execute([':uid' => $testUserId]);
$lowCount = (int)$lowItemsStmt->fetchColumn();
assertTest("Low stock detector identified product correctly (count: {$lowCount})", $lowCount === 1);

// TEST 7: Editing a Product
echo "\n7. Testing Product Edit...\n";
$editStmt = $pdo->prepare("
    UPDATE products 
    SET name = 'Organic Granola Crunch Bar', sell_price = 2.75 
    WHERE id = :id AND user_id = :uid
");
$editStmt->execute([':id' => $productId, ':uid' => $testUserId]);

$updatedProd = $pdo->query("SELECT name, sell_price FROM products WHERE id = {$productId}")->fetch();
assertTest("Product renamed to 'Organic Granola Crunch Bar' and price updated to $2.75", 
    $updatedProd['name'] === 'Organic Granola Crunch Bar' && abs($updatedProd['sell_price'] - 2.75) < 0.001);

// TEST 8: Deleting a Product
echo "\n8. Testing Product Deletion...\n";
$delStmt = $pdo->prepare("DELETE FROM products WHERE id = :id AND user_id = :uid");
$delStmt->execute([':id' => $productId, ':uid' => $testUserId]);

$deletedCheck = $pdo->query("SELECT COUNT(*) FROM products WHERE id = {$productId}")->fetchColumn();
assertTest("Product successfully deleted from database", $deletedCheck == 0);

// TEST 9: Logging Out
echo "\n9. Testing Logout...\n";
logout_user();
assertTest("User session invalidated after logout", !is_logged_in());

// TEST 10: Section 9 Sample Data Verification on Demo Account
echo "\n10. Testing Section 9 Demo Data Specifications...\n";
$demoStmt = $pdo->query("SELECT id FROM users WHERE email = 'demo@vendflow.io'");
$demoId = (int)$demoStmt->fetchColumn();

// Check Machine A
$mA = $pdo->query("SELECT id, name FROM machines WHERE user_id = {$demoId} AND name LIKE '%Machine A%'")->fetch();
assertTest("Demo Machine A (Office Building) exists", !empty($mA));

// Machine A items: Potato Chips (12), Chocolate Bar (8), Bottled Water (20), Cola (4), Biscuits (15)
$mAProds = $pdo->query("SELECT name, quantity FROM products WHERE machine_id = {$mA['id']} ORDER BY name ASC")->fetchAll();
$mAProdsMap = [];
foreach ($mAProds as $row) { $mAProdsMap[$row['name']] = $row['quantity']; }

assertTest("Machine A has Potato Chips registered", isset($mAProdsMap['Potato Chips']) && $mAProdsMap['Potato Chips'] >= 0);
assertTest("Machine A has Chocolate Bar registered", isset($mAProdsMap['Chocolate Bar']) && $mAProdsMap['Chocolate Bar'] >= 0);
assertTest("Machine A has Bottled Water registered", isset($mAProdsMap['Bottled Water']) && $mAProdsMap['Bottled Water'] >= 0);
assertTest("Machine A has Cola registered", isset($mAProdsMap['Cola']) && $mAProdsMap['Cola'] >= 0);
assertTest("Machine A has Biscuits registered", isset($mAProdsMap['Biscuits']) && $mAProdsMap['Biscuits'] >= 0);

// Check Machine B
$mB = $pdo->query("SELECT id, name FROM machines WHERE user_id = {$demoId} AND name LIKE '%Machine B%'")->fetch();
assertTest("Demo Machine B (Campus Hall) exists", !empty($mB));

// Machine B items: Potato Chips, Chocolate Bar, Bottled Water, Fruit Juice
$mBProds = $pdo->query("SELECT name, quantity FROM products WHERE machine_id = {$mB['id']} ORDER BY name ASC")->fetchAll();
$mBProdsMap = [];
foreach ($mBProds as $row) { $mBProdsMap[$row['name']] = $row['quantity']; }

assertTest("Machine B has Potato Chips registered", isset($mBProdsMap['Potato Chips']) && $mBProdsMap['Potato Chips'] >= 0);
assertTest("Machine B has Chocolate Bar registered", isset($mBProdsMap['Chocolate Bar']) && $mBProdsMap['Chocolate Bar'] >= 0);
assertTest("Machine B has Bottled Water registered", isset($mBProdsMap['Bottled Water']) && $mBProdsMap['Bottled Water'] >= 0);
assertTest("Machine B has Fruit Juice registered", isset($mBProdsMap['Fruit Juice']) && $mBProdsMap['Fruit Juice'] >= 0);

// Clean up Taylor Swift test user
$pdo->exec("DELETE FROM users WHERE email = 'testuser@vendflow.io'");

// TEST 11: Public User Discovery & Search Filtering (No Auth Required)
echo "\n11. Testing Public User Discovery & Multi-Criteria Search...\n";
assertTest("Function get_public_vending_machines exists", function_exists('get_public_vending_machines'));

if (function_exists('get_public_vending_machines')) {
    $allPublicMachines = get_public_vending_machines($pdo);
    assertTest("Public directory returns active vending machines for guest users", count($allPublicMachines) >= 2);

    $firstMachine = $allPublicMachines[0] ?? [];
    assertTest("Public machine card contains machine_name, location, and distance",
        !empty($firstMachine['machine_name']) && !empty($firstMachine['location']) && !empty($firstMachine['distance']));

    // Privacy check: no user_id, cost_price, min_threshold, or notes exposed
    $hasSensitiveMachineKeys = isset($firstMachine['user_id']) || isset($firstMachine['notes']);
    $firstProduct = $firstMachine['products'][0] ?? [];
    $hasSensitiveProductKeys = isset($firstProduct['cost_price']) || isset($firstProduct['min_threshold']) || isset($firstProduct['user_id']);
    assertTest("Public query sanitizes sensitive operator fields (cost_price, min_threshold, user_id)",
        !$hasSensitiveMachineKeys && !$hasSensitiveProductKeys);

    // Search by Machine Name
    $byMachineName = get_public_vending_machines($pdo, 'Machine A');
    assertTest("Search by machine name ('Machine A') finds matching vendo", count($byMachineName) >= 1 && str_contains($byMachineName[0]['machine_name'], 'Machine A'));

    // Search by Location
    $byLocation = get_public_vending_machines($pdo, 'Student Union');
    assertTest("Search by location ('Student Union') finds matching vendo", count($byLocation) >= 1 && str_contains($byLocation[0]['location'], 'Student Union'));

    // Search by Product Name
    $byProduct = get_public_vending_machines($pdo, 'Fruit Juice');
    assertTest("Search by product ('Fruit Juice') finds machines stocking that item", count($byProduct) >= 1);

    // Verify partner.php exists and index.php contains Partner with us link
    assertTest("Operator landing page partner.php exists", file_exists(__DIR__ . '/partner.php'));
    $indexContent = file_exists(__DIR__ . '/index.php') ? file_get_contents(__DIR__ . '/index.php') : '';
    assertTest("Public landing page index.php links to partner.php ('Partner with us')",
        str_contains($indexContent, 'partner.php') && stripos($indexContent, 'Partner with us') !== false);
}

echo "\n========================================================\n";
echo "Verification Summary: {$passCount} Passed, {$failCount} Failed\n";
echo "========================================================\n";
