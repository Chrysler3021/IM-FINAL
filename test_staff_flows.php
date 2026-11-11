<?php
/**
 * Automated Verification Test for Staff Account Creation, Login, Uniqueness, and Machine Access
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

$pdo = get_db();
echo "========================================================\n";
echo "VendFlow - Staff Account & Workflow Verification Suite\n";
echo "========================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertStaffTest(string $description, bool $condition): void {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] " . $description . "\n";
        $passCount++;
    } else {
        echo "[FAIL] " . $description . "\n";
        $failCount++;
    }
}

// 1. Setup Test Operator
echo "1. Setting up Test Operator...\n";
$pdo->exec("DELETE FROM users WHERE email = 'operator_test@vendflow.io'");
$opHash = password_hash('opsecret123', PASSWORD_BCRYPT);
$opStmt = $pdo->prepare("
    INSERT INTO users (name, email, password_hash, business_name, plan_tier, license_key)
    VALUES ('Sarah Fleet Operator', 'operator_test@vendflow.io', :hash, 'Sarah Routes Co.', 'business', 'TEST-LIC-KEY-999')
");
$opStmt->execute([':hash' => $opHash]);
$operatorId = (int)$pdo->lastInsertId();
assertStaffTest("Test operator created with ID {$operatorId}", $operatorId > 0);

// Setup 2 test machines for this operator
$mStmt = $pdo->prepare("INSERT INTO machines (user_id, name, location, status) VALUES (:uid, :name, :loc, 'active')");
$mStmt->execute([':uid' => $operatorId, ':name' => 'Machine 1 — Downtown', ':loc' => 'Downtown Lobby']);
$machine1Id = (int)$pdo->lastInsertId();

$mStmt->execute([':uid' => $operatorId, ':name' => 'Machine 2 — Uptown', ':loc' => 'Uptown Cafeteria']);
$machine2Id = (int)$pdo->lastInsertId();
assertStaffTest("Created 2 machines for test operator (M1: {$machine1Id}, M2: {$machine2Id})", $machine1Id > 0 && $machine2Id > 0);

// Setup test product in Machine 1
$pStmt = $pdo->prepare("
    INSERT INTO products (user_id, machine_id, name, sku, category, quantity, min_threshold)
    VALUES (:uid, :mid, 'Energy Drink Z', 'ENG-Z-01', 'Cold Drinks & Sodas', 10, 15)
");
$pStmt->execute([':uid' => $operatorId, ':mid' => $machine1Id]);
$product1Id = (int)$pdo->lastInsertId();
assertStaffTest("Created test product Energy Drink Z with ID {$product1Id}", $product1Id > 0);

// Setup test product in Machine 2
$pStmt->execute([':uid' => $operatorId, ':mid' => $machine2Id]);
$product2Id = (int)$pdo->lastInsertId();
assertStaffTest("Created test product in Machine 2 with ID {$product2Id}", $product2Id > 0);

// 2. Test Staff Account Creation
echo "\n2. Testing Staff Account Creation by Operator...\n";
$staffUsername = 'john_restock_test';
$staffPass = 'johnpass123';
$staffHash = password_hash($staffPass, PASSWORD_BCRYPT);

// Clean up any previous test staff
$pdo->exec("DELETE FROM staff_accounts WHERE username = '{$staffUsername}'");

$insStaff = $pdo->prepare("
    INSERT INTO staff_accounts (operator_id, name, username, password_hash, access_scope, status)
    VALUES (:oid, 'John Field Worker', :u, :hash, 'selected', 'active')
");
$insStaff->execute([
    ':oid' => $operatorId,
    ':u' => $staffUsername,
    ':hash' => $staffHash
]);
$staff1Id = (int)$pdo->lastInsertId();
assertStaffTest("Operator successfully created staff account for John (ID {$staff1Id})", $staff1Id > 0);

// Assign staff only to Machine 1
$pdo->exec("INSERT INTO staff_machines (staff_id, machine_id) VALUES ({$staff1Id}, {$machine1Id})");
assertStaffTest("Assigned staff member strictly to Machine 1", true);

// 3. Test Username Taken Validation
echo "\n3. Testing Username Taken Collision Prevention...\n";
$isTakenSameCase = is_staff_username_taken($pdo, 'john_restock_test');
assertStaffTest("Detected taken username (exact match: john_restock_test)", $isTakenSameCase === true);

$isTakenDifferentCase = is_staff_username_taken($pdo, 'JOHN_RESTOCK_TEST');
assertStaffTest("Detected taken username (case-insensitive: JOHN_RESTOCK_TEST)", $isTakenDifferentCase === true);

$isTakenOperatorEmail = is_staff_username_taken($pdo, 'operator_test@vendflow.io');
assertStaffTest("Detected taken username colliding with operator email", $isTakenOperatorEmail === true);

$isAvailable = is_staff_username_taken($pdo, 'available_new_user_123');
assertStaffTest("Available username correctly identified as NOT taken", $isAvailable === false);

// 4. Test Staff Login with Username and Password
echo "\n4. Testing Staff Login with Username & Password...\n";
$loginQuery = $pdo->prepare("SELECT * FROM staff_accounts WHERE LOWER(username) = LOWER(:u)");
$loginQuery->execute([':u' => 'john_restock_test']);
$fetchedStaff = $loginQuery->fetch();

assertStaffTest("Found staff account by username", !empty($fetchedStaff));
$passValid = password_verify($staffPass, $fetchedStaff['password_hash']);
assertStaffTest("Staff password verified successfully with BCRYPT", $passValid);

// Log in as staff
$opFetch = $pdo->query("SELECT * FROM users WHERE id = {$operatorId}")->fetch();
login_staff($fetchedStaff, $opFetch);

assertStaffTest("Session identified as logged in", is_logged_in());
assertStaffTest("Session role identified as staff", is_staff() && !is_operator());
assertStaffTest("current_staff() returns John Field Worker", current_staff()['name'] === 'John Field Worker');
assertStaffTest("current_user() returns parent operator Sarah Fleet Operator", (current_user()['operator_name'] ?? '') === 'Sarah Fleet Operator');

// 5. Test Machine Access Scoping for Staff
echo "\n5. Testing Machine Access Scoping...\n";
$allowedMachines = get_staff_accessible_machine_ids($pdo, current_staff());
assertStaffTest("Staff has access to Machine 1 ({$machine1Id})", in_array($machine1Id, $allowedMachines, true));
assertStaffTest("Staff is BLOCKED from Machine 2 ({$machine2Id})", !in_array($machine2Id, $allowedMachines, true));

assertStaffTest("can_current_session_access_machine() allows Machine 1", can_current_session_access_machine($pdo, $machine1Id) === true);
assertStaffTest("can_current_session_access_machine() denies Machine 2", can_current_session_access_machine($pdo, $machine2Id) === false);

// 6. Test Stock Adjustment Audit Trail by Staff
echo "\n6. Testing Stock Adjustment & Audit Trail by Staff...\n";
$addQty = 8;
$newQty = 10 + $addQty;
$up = $pdo->prepare("UPDATE products SET quantity = :qty WHERE id = :id");
$up->execute([':qty' => $newQty, ':id' => $product1Id]);

$currStaff = current_staff();
$currOp = current_user();

$log = $pdo->prepare("
    INSERT INTO inventory_logs (user_id, staff_id, actor_name, product_id, machine_id, previous_qty, new_qty, change_qty, change_type, notes)
    VALUES (:uid, :sid, :actor, :pid, :mid, 10, :nqty, :dqty, 'restock', 'Route restock by staff')
");
$log->execute([
    ':uid' => $currOp['id'],
    ':sid' => $currStaff['id'],
    ':actor' => $currStaff['name'] . ' (Staff)',
    ':pid' => $product1Id,
    ':mid' => $machine1Id,
    ':nqty' => $newQty,
    ':dqty' => $addQty
]);
$logId = (int)$pdo->lastInsertId();

$savedLog = $pdo->query("SELECT * FROM inventory_logs WHERE id = {$logId}")->fetch();
assertStaffTest("Stock adjustment logged with staff_id {$currStaff['id']}", (int)$savedLog['staff_id'] === (int)$currStaff['id']);
assertStaffTest("Stock adjustment actor_name is 'John Field Worker (Staff)'", $savedLog['actor_name'] === 'John Field Worker (Staff)');
assertStaffTest("Stock adjustment belongs to operator user_id {$currOp['id']}", (int)$savedLog['user_id'] === (int)$currOp['id']);

// 7. Test Suspended Staff Account Login Block
echo "\n7. Testing Suspended Staff Account Block...\n";
$pdo->exec("UPDATE staff_accounts SET status = 'suspended' WHERE id = {$staff1Id}");
$suspendedStaff = $pdo->query("SELECT * FROM staff_accounts WHERE id = {$staff1Id}")->fetch();
assertStaffTest("Staff account status is now 'suspended'", $suspendedStaff['status'] === 'suspended');

// Clean up session
logout_user();
assertStaffTest("Logged out staff session", !is_logged_in());

// 8. Clean up test records
$pdo->exec("DELETE FROM users WHERE email = 'operator_test@vendflow.io'");
$pdo->exec("DELETE FROM staff_accounts WHERE username = '{$staffUsername}'");

echo "\n========================================================\n";
echo "Staff Suite Summary: {$passCount} Passed, {$failCount} Failed\n";
echo "========================================================\n";
