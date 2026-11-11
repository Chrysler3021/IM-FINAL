<?php
/**
 * VendFlow - Platform Superadmin Console
 * Multi-Tenant Operator Authority, License Ledger, Billing Receipts & Audit Telemetry
 * 100% Pure Vanilla PHP + MySQL PDO (9 Relational Tables) — PostHog Dashboard UI
 */
require_once __DIR__ . '/includes/functions.php';

// 1. Handle exiting client impersonation back to Superadmin Console
if (isset($_GET['exit_impersonate']) && $_GET['exit_impersonate'] === '1') {
    if (isset($_SESSION['impersonator_id'])) {
        $realAdminId = (int)$_SESSION['impersonator_id'];
        unset($_SESSION['impersonator_id']);
        $db = get_db();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $realAdminId]);
        $realAdmin = $stmt->fetch();
        if ($realAdmin) {
            login_user($realAdmin);
        }
        set_flash('success', 'Exited client workspace and returned to the Superadmin console.');
    }
    header("Location: superadmin.php");
    exit;
}

// 2. Require Superadmin Authentication (supports ?demo_admin=1 for 1-click entry)
$superadmin = require_superadmin();
$db = get_db();
$adminId = (int)$superadmin['id'];

// Active Sidebar Tab Routing
$allowedTabs = ['overview', 'operators', 'licenses', 'audit', 'messages', 'system'];
$activeTab = $_GET['tab'] ?? 'overview';
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'overview';
}

// =============================================================================
// Superadmin Actions (Impersonation & Form Mutations)
// =============================================================================

// Impersonate Client Operator (accepts numeric user ID or 'demo')
if (isset($_GET['impersonate'])) {
    $impRaw = $_GET['impersonate'];
    $targetUser = null;

    if ($impRaw === 'demo') {
        $targetUser = ensure_demo_user($db);
    } else {
        $targetId = (int)$impRaw;
        if ($targetId > 0 && $targetId !== $adminId) {
            $stmt = $db->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $targetId]);
            $targetUser = $stmt->fetch() ?: null;
        }
    }

    if ($targetUser && (int)$targetUser['id'] !== $adminId) {
        log_platform_event(
            $db,
            $adminId,
            $superadmin['name'],
            'superadmin',
            'impersonation_started',
            "Started support impersonation session for {$targetUser['name']} ({$targetUser['email']}).",
            (int)$targetUser['id']
        );

        $_SESSION['impersonator_id'] = $adminId;
        login_user($targetUser);
        $bizLabel = !empty($targetUser['business_name']) ? $targetUser['business_name'] : 'Operator Workspace';
        set_flash('info', "Impersonating {$targetUser['name']} ({$bizLabel}). Use the top banner anytime to exit back to Superadmin.");
        header("Location: dashboard.php");
        exit;
    }
}

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $returnTab = $_POST['return_tab'] ?? $activeTab;
    if (!in_array($returnTab, $allowedTabs, true)) {
        $returnTab = 'overview';
    }

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Your session security token expired. Please submit again.');
        header("Location: superadmin.php?tab=" . urlencode($returnTab));
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Action 1: Rotate / Reissue Operator Tiered License Key
    if ($action === 'rotate_license') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        if ($targetUserId > 0 && $targetUserId !== $adminId) {
            $uStmt = $db->prepare("SELECT id, name, email, business_name, plan_tier, custom_machine_limit, custom_staff_limit, custom_price FROM users WHERE id = :id LIMIT 1");
            $uStmt->execute([':id' => $targetUserId]);
            $uRow = $uStmt->fetch();

            if ($uRow) {
                $planInfo = get_user_plan_info($uRow);
                $tierKey  = $planInfo['tier'];
                $newKey   = generate_license_key($tierKey);
                $db->beginTransaction();
                try {
                    $db->prepare("UPDATE license_keys SET status = 'rotated' WHERE user_id = :uid AND status = 'active'")
                       ->execute([':uid' => $targetUserId]);

                    $db->prepare("UPDATE users SET license_key = :lic WHERE id = :id")
                       ->execute([':lic' => $newKey, ':id' => $targetUserId]);

                    $db->prepare("
                        INSERT INTO license_keys (license_key, user_id, plan_name, status, issued_by, activated_at)
                        VALUES (:lic, :uid, :pname, 'active', 'superadmin', NOW())
                    ")->execute([
                        ':lic'   => $newKey,
                        ':uid'   => $targetUserId,
                        ':pname' => $planInfo['plan_name'],
                    ]);

                    log_platform_event(
                        $db,
                        $adminId,
                        $superadmin['name'],
                        'superadmin',
                        'license_rotated',
                        "Rotated {$planInfo['label']} license key for {$uRow['name']} ({$uRow['email']}) to {$newKey}.",
                        $targetUserId
                    );

                    $db->commit();
                    set_flash('success', "Rotated {$planInfo['label']} license key for {$uRow['name']}. New active key: {$newKey}");
                } catch (Throwable $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    set_flash('error', 'Could not rotate operator license key.');
                }
            }
        }
        header("Location: superadmin.php?tab=" . urlencode($returnTab));
        exit;
    }

    // Action 2: Manage Operator Access Status, Plan Tier & Optional Key Rotation
    if ($action === 'manage_client') {
        $userId    = (int)($_POST['user_id'] ?? 0);
        $newStatus = strtolower(trim($_POST['status'] ?? 'active'));
        if (!in_array($newStatus, ['active', 'suspended'], true)) {
            $newStatus = 'active';
        }
        $newPlan    = strtolower(trim($_POST['plan_tier'] ?? ''));
        $validPlans = ['solo', 'business', 'max', 'enterprise'];

        if ($userId > 0 && $userId !== $adminId) {
            $curStmt = $db->prepare("SELECT id, name, email, license_key, plan_tier, custom_machine_limit, custom_staff_limit, custom_price FROM users WHERE id = :id LIMIT 1");
            $curStmt->execute([':id' => $userId]);
            $curUser = $curStmt->fetch();

            if ($curUser) {
                if ($curUser['email'] === 'demo@vendflow.io') {
                    $newStatus = 'active';
                }

                $targetPlan  = in_array($newPlan, $validPlans, true) ? $newPlan : ($curUser['plan_tier'] ?: 'solo');
                $planChanged = ($targetPlan !== ($curUser['plan_tier'] ?: 'solo'));
                $rotateKey   = !empty($_POST['regenerate_key']) || empty($curUser['license_key']) || $planChanged;
                $licKey      = $rotateKey ? generate_license_key($targetPlan) : $curUser['license_key'];

                // 1. Determine baseline minimum limits for the target plan tier
                $plansRegistry = get_license_plans();
                if (isset($plansRegistry[$targetPlan])) {
                    $tierMinMachines = (int)$plansRegistry[$targetPlan]['machine_limit'];
                    $tierMinStaff    = (int)$plansRegistry[$targetPlan]['staff_limit'];
                } else {
                    // Enterprise baseline floor
                    $tierMinMachines = 1;
                    $tierMinStaff    = 1;
                }

                // 2. Fetch current active machine and staff usage for this operator
                $cntStmt = $db->prepare("
                    SELECT
                        (SELECT COUNT(*) FROM machines WHERE user_id = :uid1) AS mach_cnt,
                        (SELECT COUNT(*) FROM staff_accounts WHERE operator_id = :uid2) AS staff_cnt
                ");
                $cntStmt->execute([':uid1' => $userId, ':uid2' => $userId]);
                $counts = $cntStmt->fetch();
                $currentActiveMachines = (int)($counts['mach_cnt'] ?? 0);
                $currentActiveStaff    = (int)($counts['staff_cnt'] ?? 0);

                // Absolute floors: never lower than the plan's minimum limits AND never lower than current active usage
                $floorMachines = max($tierMinMachines, $currentActiveMachines);
                $floorStaff    = max($tierMinStaff, $currentActiveStaff);

                // Parse custom allocation inputs
                $rawMach = trim($_POST['custom_machine_limit'] ?? '');
                $customMachineLimit = ($rawMach !== '' && is_numeric($rawMach)) ? (int)$rawMach : null;

                $rawStaff = trim($_POST['custom_staff_limit'] ?? '');
                $customStaffLimit = ($rawStaff !== '' && is_numeric($rawStaff)) ? (int)$rawStaff : null;

                $rawPrice = trim($_POST['custom_price'] ?? '');
                $customPrice = ($rawPrice !== '' && is_numeric($rawPrice)) ? max(0.0, (float)$rawPrice) : null;

                // Enforce plan limits baseline floor (e.g. for Solo, staff can never be below 1; machines never below 4)
                if ($customMachineLimit === null) {
                    $customMachineLimit = ($targetPlan === 'enterprise') ? 250 : $tierMinMachines;
                }
                if ($customMachineLimit < $floorMachines) {
                    $customMachineLimit = $floorMachines;
                }

                if ($customStaffLimit === null) {
                    $customStaffLimit = ($targetPlan === 'enterprise') ? 100 : $tierMinStaff;
                }
                if ($customStaffLimit < $floorStaff) {
                    $customStaffLimit = $floorStaff;
                }

                if ($customPrice === null || $customPrice <= 0) {
                    $autoCalc = calculate_tiered_capacity_price($targetPlan, $customMachineLimit, $customStaffLimit);
                    $customPrice = $autoCalc['total_price'];
                }

                $planMeta = get_user_plan_info(array_merge($curUser, [
                    'plan_tier'            => $targetPlan,
                    'custom_machine_limit' => $customMachineLimit,
                    'custom_staff_limit'   => $customStaffLimit,
                    'custom_price'         => $customPrice,
                ]));

                $db->beginTransaction();
                try {
                    if ($rotateKey) {
                        $db->prepare("UPDATE license_keys SET status = 'rotated' WHERE user_id = :uid AND status = 'active'")
                           ->execute([':uid' => $userId]);

                        $db->prepare("
                            INSERT INTO license_keys (license_key, user_id, plan_name, status, issued_by, activated_at)
                            VALUES (:lic, :uid, :pname, :st, 'superadmin', NOW())
                        ")->execute([
                            ':lic'   => $licKey,
                            ':uid'   => $userId,
                            ':pname' => $planMeta['plan_name'],
                            ':st'    => $newStatus === 'suspended' ? 'suspended' : 'active',
                        ]);
                    } else {
                        $db->prepare("UPDATE license_keys SET status = :st WHERE license_key = :lic")
                           ->execute([
                               ':st'  => $newStatus === 'suspended' ? 'suspended' : 'active',
                               ':lic' => $licKey,
                           ]);
                    }

                    $db->prepare("
                        UPDATE users 
                        SET plan_tier = :plan, 
                            status = :status, 
                            license_key = :lic,
                            custom_machine_limit = :cmach,
                            custom_staff_limit = :cstaff,
                            custom_price = :cprice
                        WHERE id = :id
                    ")->execute([
                        ':plan'   => $targetPlan,
                        ':status' => $newStatus,
                        ':lic'    => $licKey,
                        ':cmach'  => $customMachineLimit,
                        ':cstaff' => $customStaffLimit,
                        ':cprice' => $customPrice,
                        ':id'     => $userId,
                    ]);

                    log_platform_event(
                        $db,
                        $adminId,
                        $superadmin['name'],
                        'superadmin',
                        'client_updated',
                        "Updated workspace for {$curUser['name']} ({$curUser['email']}): Status = " . ucfirst($newStatus) . ", Plan = {$planMeta['label']} (Max {$planMeta['machine_limit']} machines, {$planMeta['staff_limit']} staff" . ($planMeta['price'] ? ", \${$planMeta['price']}/mo" : "") . ")" . ($rotateKey ? ", New key = {$licKey}" : ""),
                        $userId
                    );

                    $db->commit();
                    set_flash('success', "Updated {$curUser['name']} successfully ({$planMeta['label']} plan: {$planMeta['machine_limit']} machines, {$planMeta['staff_limit']} staff, {$newStatus})." . ($rotateKey ? " Issued key: {$licKey}" : ''));
                } catch (Throwable $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    set_flash('error', 'Could not update operator workspace: ' . $e->getMessage());
                }
            }
        }
        header("Location: superadmin.php?tab=" . urlencode($returnTab));
        exit;
    }

    // Action 3: Delete Operator Account
    if ($action === 'delete_user') {
        $delId = (int)($_POST['user_id'] ?? 0);
        if ($delId > 0 && $delId !== $adminId) {
            $chk = $db->prepare("SELECT name, email, business_name FROM users WHERE id = :id LIMIT 1");
            $chk->execute([':id' => $delId]);
            $delUser = $chk->fetch();
            if ($delUser && $delUser['email'] !== 'demo@vendflow.io') {
                $db->prepare("DELETE FROM users WHERE id = :id")->execute([':id' => $delId]);
                log_platform_event(
                    $db,
                    $adminId,
                    $superadmin['name'],
                    'superadmin',
                    'account_deleted',
                    "Deleted operator {$delUser['name']} ({$delUser['email']}) and cascaded associated licenses, receipts, machines, SKUs, and logs."
                );
                set_flash('success', "Deleted operator {$delUser['name']} and cascaded associated records.");
            }
        }
        header("Location: superadmin.php?tab=operators");
        exit;
    }

    // Action 4: Seed Sample Inventory into Demo Sandbox
    if ($action === 'seed_demo_sandbox') {
        $demoRow = ensure_demo_user($db);
        $dId = (int)$demoRow['id'];
        $db->prepare("DELETE FROM inventory_logs WHERE user_id = :uid")->execute([':uid' => $dId]);
        $db->prepare("DELETE FROM products WHERE user_id = :uid")->execute([':uid' => $dId]);
        $db->prepare("DELETE FROM machines WHERE user_id = :uid")->execute([':uid' => $dId]);
        seed_sample_inventory_data($db, $dId);
        $db->prepare("UPDATE users SET plan_tier = 'solo', status = 'active' WHERE id = :uid")
           ->execute([':uid' => $dId]);

        log_platform_event(
            $db,
            $adminId,
            $superadmin['name'],
            'superadmin',
            'demo_sandbox_seeded',
            'Seeded 2 machines and 9 sample SKUs into demo@vendflow.io sandbox.',
            $dId
        );
        set_flash('success', 'Demo sandbox (demo@vendflow.io) populated with 2 sample machines and 9 snack/beverage SKUs.');
        header("Location: superadmin.php?tab=" . urlencode($returnTab));
        exit;
    }

    // Action 5: Reset Demo Sandbox to Clean Empty State
    if ($action === 'reset_demo_sandbox') {
        $demoRow = ensure_demo_user($db);
        $dId = (int)$demoRow['id'];
        $db->prepare("DELETE FROM inventory_logs WHERE user_id = :uid")->execute([':uid' => $dId]);
        $db->prepare("DELETE FROM products WHERE user_id = :uid")->execute([':uid' => $dId]);
        $db->prepare("DELETE FROM machines WHERE user_id = :uid")->execute([':uid' => $dId]);

        $mIns = $db->prepare("
            INSERT INTO machines (user_id, name, identifier, location, notes, status)
            VALUES (:uid, :name, :identifier, :location, :notes, 'active')
        ");
        $mIns->execute([
            ':uid'        => $dId,
            ':name'       => 'Machine A — Office Building',
            ':identifier' => 'VM-OFFICE-01',
            ':location'   => '2nd Floor Breakroom, Tech Towers',
            ':notes'      => 'High foot traffic during morning coffee breaks and afternoon rush.',
        ]);
        $mIns->execute([
            ':uid'        => $dId,
            ':name'       => 'Machine B — Campus Hall',
            ':identifier' => 'VM-CAMPUS-02',
            ':location'   => 'Student Union Center, Building C',
            ':notes'      => 'Popular snack spot for students between lectures.',
        ]);
        $db->prepare("UPDATE users SET plan_tier = 'solo', status = 'active' WHERE id = :uid")
           ->execute([':uid' => $dId]);

        log_platform_event(
            $db,
            $adminId,
            $superadmin['name'],
            'superadmin',
            'demo_sandbox_reset',
            'Reset demo@vendflow.io sandbox to 2 empty cabinets and 0 SKUs.',
            $dId
        );
        set_flash('success', 'Demo sandbox (demo@vendflow.io) reset to clean initial state.');
        header("Location: superadmin.php?tab=" . urlencode($returnTab));
        exit;
    }

    // Action 6: Resolve, Reopen, or Permanently Delete Support Inquiry Message
    if ($action === 'resolve_message') {
        $msgId = (int)($_POST['message_id'] ?? 0);
        if ($msgId > 0) {
            $db->prepare("UPDATE messages SET status = 'resolved' WHERE id = :id")->execute([':id' => $msgId]);
            set_flash('success', "Support inquiry #{$msgId} marked as resolved and moved to archive.");
        }
        header("Location: superadmin.php?tab=messages");
        exit;
    }

    if ($action === 'reopen_message') {
        $msgId = (int)($_POST['message_id'] ?? 0);
        if ($msgId > 0) {
            $db->prepare("UPDATE messages SET status = 'open' WHERE id = :id")->execute([':id' => $msgId]);
            set_flash('success', "Support inquiry #{$msgId} reopened and returned to the active inbox.");
        }
        header("Location: superadmin.php?tab=messages&msg_status=resolved");
        exit;
    }

    if ($action === 'delete_message') {
        $msgId = (int)($_POST['message_id'] ?? 0);
        if ($msgId > 0) {
            $db->prepare("DELETE FROM messages WHERE id = :id")->execute([':id' => $msgId]);
            set_flash('success', "Support inquiry #{$msgId} permanently deleted.");
        }
        $viewFilter = ($_POST['msg_status'] ?? '') === 'resolved' ? '&msg_status=resolved' : '';
        header("Location: superadmin.php?tab=messages" . $viewFilter);
        exit;
    }
}

// =============================================================================
// Query Live Multi-Tenant Relational Telemetry (All 9 Tables)
// =============================================================================

// 1. Operators & Clients List
$operatorsStmt = $db->prepare("
    SELECT
        u.*,
        (SELECT COUNT(*) FROM machines m WHERE m.user_id = u.id) AS machines_count,
        (SELECT COUNT(*) FROM staff_accounts s WHERE s.operator_id = u.id) AS staff_count,
        (SELECT COUNT(*) FROM products p WHERE p.user_id = u.id) AS products_count,
        (SELECT COALESCE(SUM(p.quantity), 0) FROM products p WHERE p.user_id = u.id) AS units_count,
        (SELECT COUNT(*) FROM inventory_logs l WHERE l.user_id = u.id) AS logs_count
    FROM users u
    WHERE u.id != :aid AND COALESCE(u.role, 'operator') != 'superadmin'
    ORDER BY u.id DESC
");
$operatorsStmt->execute([':aid' => $adminId]);
$operators = $operatorsStmt->fetchAll();

$totalClients          = count($operators);
$activeClientsCount    = 0;
$suspendedClientsCount = 0;

foreach ($operators as $op) {
    if (strtolower($op['status'] ?? 'active') === 'suspended') {
        $suspendedClientsCount++;
    } else {
        $activeClientsCount++;
    }
}

if (!defined('VENDFLOW_PLAN_PRICE')) define('VENDFLOW_PLAN_PRICE', 19.00);

// Calculate MRR based on each active operator's actual tier (Solo $19, Business $35, Max $99, etc.)
$estimatedMrr = 0.0;
foreach ($operators as $op) {
    if (strtolower($op['status'] ?? 'active') !== 'suspended') {
        $opInfo = get_user_plan_info($op);
        $estimatedMrr += (float)($opInfo['price'] ?? VENDFLOW_PLAN_PRICE);
    }
}

// 2. License Keys & Simulated Billing Transactions
$licenseKeysList = $db->query("
    SELECT
        lk.*,
        u.name AS operator_name,
        u.email AS operator_email,
        u.business_name,
        u.plan_tier
    FROM license_keys lk
    LEFT JOIN users u ON lk.user_id = u.id
    ORDER BY lk.id DESC
    LIMIT 60
")->fetchAll();

$activeLicenseCount = (int)$db->query("SELECT COUNT(*) FROM license_keys WHERE status = 'active'")->fetchColumn();

$billingTransactions = $db->query("
    SELECT
        bt.*,
        u.name AS operator_name,
        u.email AS operator_email,
        u.business_name
    FROM billing_transactions bt
    JOIN users u ON bt.user_id = u.id
    ORDER BY bt.id DESC
    LIMIT 60
")->fetchAll();

$grossSimulatedRevenue = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM billing_transactions WHERE status = 'paid'")->fetchColumn();
$totalBillingCount     = (int)$db->query("SELECT COUNT(*) FROM billing_transactions")->fetchColumn();

// 3. Global Machines, SKUs & Platform Audit Logs
$totalMachines = (int)$db->query("
    SELECT COUNT(*) FROM machines m
    JOIN users u ON m.user_id = u.id
    WHERE COALESCE(u.role, 'operator') != 'superadmin'
")->fetchColumn();

$totalSkus = (int)$db->query("
    SELECT COUNT(*) FROM products p
    JOIN users u ON p.user_id = u.id
    WHERE COALESCE(u.role, 'operator') != 'superadmin'
")->fetchColumn();

$totalUnits = (int)$db->query("
    SELECT COALESCE(SUM(p.quantity), 0) FROM products p
    JOIN users u ON p.user_id = u.id
    WHERE COALESCE(u.role, 'operator') != 'superadmin'
")->fetchColumn();

// Platform Audit Log with Pagination (Defaults to 15 rows to cleanly fill viewport height)
$allowedAuditLimits      = [10, 15, 20, 25, 50];
$rawLimit                = isset($_GET['limit']) ? (int)$_GET['limit'] : 15;
$auditPerPage            = in_array($rawLimit, $allowedAuditLimits, true) ? $rawLimit : 15;

$totalPlatformAuditCount = (int)$db->query("SELECT COUNT(*) FROM platform_audit_logs")->fetchColumn();
$auditTotalPages         = max(1, (int)ceil($totalPlatformAuditCount / $auditPerPage));
$auditPageNum            = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
if ($auditPageNum > $auditTotalPages) {
    $auditPageNum = $auditTotalPages;
}
$auditOffset             = ($auditPageNum - 1) * $auditPerPage;
$auditStartResult        = $totalPlatformAuditCount > 0 ? $auditOffset + 1 : 0;
$auditEndResult          = min($auditOffset + $auditPerPage, $totalPlatformAuditCount);

$auditStmt = $db->prepare("
    SELECT * FROM platform_audit_logs
    ORDER BY id DESC
    LIMIT :limit OFFSET :offset
");
$auditStmt->bindValue(':limit', $auditPerPage, PDO::PARAM_INT);
$auditStmt->bindValue(':offset', $auditOffset, PDO::PARAM_INT);
$auditStmt->execute();
$platformAuditLogs = $auditStmt->fetchAll();

// 6 most recent events for Overview card
$recentAuditLogs = $db->query("
    SELECT * FROM platform_audit_logs
    ORDER BY id DESC
    LIMIT 6
")->fetchAll();

// 4. Support Inquiries Inbox (Open vs. Resolved Archive)
$allMessages = [];
try {
    $allMessages = $db->query("SELECT * FROM messages ORDER BY id DESC LIMIT 100")->fetchAll();
} catch (Throwable $e) {}

$openMessages     = [];
$resolvedMessages = [];
foreach ($allMessages as $m) {
    if (strtolower($m['status'] ?? 'open') === 'resolved') {
        $resolvedMessages[] = $m;
    } else {
        $openMessages[] = $m;
    }
}
$unreadMessagesCount   = count($openMessages);
$resolvedMessagesCount = count($resolvedMessages);
$msgStatusFilter       = (($_GET['msg_status'] ?? 'open') === 'resolved') ? 'resolved' : 'open';
$messages              = ($msgStatusFilter === 'resolved') ? $resolvedMessages : $openMessages;

// 5. System Diagnostics & Live 9-Table Row Counts
$phpVersion     = PHP_VERSION;
$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Apache / XAMPP';
$dbVersion      = $db->getAttribute(PDO::ATTR_SERVER_VERSION);

$tableStats = [];
$schemaTables = [
    'users'                => 'Platform accounts (Operators & Superadmin)',
    'license_keys'         => 'Auto-provisioned & rotated tiered license keys',
    'billing_transactions' => 'Simulated license checkout purchase receipts',
    'platform_audit_logs'  => 'Account signups, logins & admin security events',
    'machines'             => 'Registered vending machine cabinets',
    'products'             => 'Cataloged snack & beverage SKUs',
    'inventory_logs'       => 'Immutable stock count delta records',
    'password_resets'      => 'Expiring password recovery tokens',
    'messages'             => 'Public support & contact form inquiries',
];
if ($activeTab === 'system') {
    foreach ($schemaTables as $tbl => $desc) {
        $cnt = (int)$db->query("SELECT COUNT(*) FROM `{$tbl}`")->fetchColumn();
        $tableStats[] = ['table' => $tbl, 'desc' => $desc, 'rows' => $cnt];
    }
}

$tabMeta = [
    'overview'  => [
        'title'    => 'Platform Overview',
        'header'   => 'Platform overview',
        'subtitle' => 'Multi-tenant operator workspaces, simulated license purchases, and global database telemetry.',
    ],
    'operators' => [
        'title'    => 'Operators & Clients',
        'header'   => 'Operators & clients',
        'subtitle' => 'Operators registered across Solo ($19), Business ($35), Max ($99) & Enterprise tiers.',
    ],
    'licenses'  => [
        'title'    => 'Licenses & Billing',
        'header'   => 'Licenses & billing',
        'subtitle' => 'Auto-bound tiered license keys (license_keys) and simulated checkout receipts (billing_transactions).',
    ],
    'audit'     => [
        'title'    => 'Platform Audit Log',
        'header'   => 'Platform audit log',
        'subtitle' => 'System-wide ledger of account creations, license activations, logins, and superadmin actions.',
    ],
    'messages'  => [
        'title'    => 'Support Inbox',
        'header'   => 'Support inbox',
        'subtitle' => 'Operator questions and inquiries submitted via the public contact form.',
    ],
    'system'    => [
        'title'    => 'System & Database',
        'header'   => 'System & database',
        'subtitle' => 'Live 9-table MySQL row telemetry and database server environment diagnostics.',
    ],
];

$pageTitle    = $tabMeta[$activeTab]['title'];
$pageHeader   = $tabMeta[$activeTab]['header'];
$pageSubtitle = $tabMeta[$activeTab]['subtitle'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> • VendFlow Superadmin</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body class="dashboard-body">

    <!-- Thin Top Purple Accent Stripe -->
    <div class="top-accent-bar"></div>

    <div class="dashboard-wrapper">

        <!-- ===============================================================
             Left Sidebar (Exact PostHog Dashboard Layout)
             =============================================================== -->
        <aside class="sidebar">

            <!-- Project Selector -->
            <a href="superadmin.php?tab=system" class="project-selector" title="Platform System & Database" style="text-decoration: none;">
                <div class="project-badge">
                    <span class="badge-avatar">V</span>
                    <span style="max-width: 135px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        VendFlow Platform
                    </span>
                    <span style="font-size: 0.7rem; color: var(--text-muted);">▼</span>
                </div>
                <span class="kbd-shortcut">Ctrl K</span>
            </a>

            <!-- Superadmin Meta Strip -->
            <div class="sidebar-operator-meta">
                <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 135px;">
                    <span style="width:13px;height:13px;display:inline-flex;vertical-align:-1px;margin-right:3px;"><?= posthog_icon('user') ?></span><?= e($superadmin['name']) ?>
                </span>
                <span class="tier-tag-mini">SUPERADMIN</span>
            </div>

            <!-- Subnav Quick Switcher -->
            <div class="sidebar-subnav">
                <a href="superadmin.php?tab=overview" class="subnav-btn <?= $activeTab === 'overview' ? 'active' : '' ?>" style="text-decoration: none;">
                    <span style="width:12px;height:12px;display:inline-flex;"><?= posthog_icon('box') ?></span> Apps
                </a>
                <a href="superadmin.php?tab=licenses" class="subnav-btn <?= $activeTab === 'licenses' ? 'active' : '' ?>" style="text-decoration: none;">
                    <span style="width:12px;height:12px;display:inline-flex;"><?= posthog_icon('lock') ?></span> Keys
                </a>
                <a href="superadmin.php?tab=audit" class="subnav-btn <?= $activeTab === 'audit' ? 'active' : '' ?>" style="text-decoration: none;">
                    <span style="width:12px;height:12px;display:inline-flex;"><?= posthog_icon('clock') ?></span> Logs
                </a>
            </div>

            <!-- Primary Superadmin Navigation Menu -->
            <ul class="sidebar-menu">
                <li>
                    <a href="superadmin.php?tab=overview" class="menu-item <?= $activeTab === 'overview' ? 'active' : '' ?>">
                        <div class="menu-left">
                            <span class="menu-icon"><?= posthog_icon('dashboard') ?></span>
                            <span>Platform overview</span>
                        </div>
                    </a>
                </li>

                <li>
                    <a href="superadmin.php?tab=operators" class="menu-item <?= $activeTab === 'operators' ? 'active' : '' ?>">
                        <div class="menu-left">
                            <span class="menu-icon"><?= posthog_icon('people') ?></span>
                            <span>Operators &amp; clients</span>
                        </div>
                        <span class="sidebar-pill-badge"><?= $totalClients ?></span>
                    </a>
                </li>

                <li>
                    <a href="superadmin.php?tab=licenses" class="menu-item <?= $activeTab === 'licenses' ? 'active' : '' ?>">
                        <div class="menu-left">
                            <span class="menu-icon"><?= posthog_icon('receipt') ?></span>
                            <span>Licenses &amp; billing</span>
                        </div>
                        <span class="sidebar-pill-badge"><?= $activeLicenseCount ?></span>
                    </a>
                </li>

                <li>
                    <a href="superadmin.php?tab=audit" class="menu-item <?= $activeTab === 'audit' ? 'active' : '' ?>">
                        <div class="menu-left">
                            <span class="menu-icon"><?= posthog_icon('activity') ?></span>
                            <span>Platform audit log</span>
                        </div>
                        <span class="sidebar-pill-badge"><?= $totalPlatformAuditCount ?></span>
                    </a>
                </li>

                <li>
                    <a href="superadmin.php?tab=messages" class="menu-item <?= $activeTab === 'messages' ? 'active' : '' ?>">
                        <div class="menu-left">
                            <span class="menu-icon"><?= posthog_icon('support') ?></span>
                            <span>Support inbox</span>
                        </div>
                        <?php if ($unreadMessagesCount > 0): ?>
                            <span class="sidebar-pill-badge alert"><?= $unreadMessagesCount ?></span>
                        <?php else: ?>
                            <span class="sidebar-pill-badge">0</span>
                        <?php endif; ?>
                    </a>
                </li>

                <!-- Section: Workspace & Schema -->
                <li class="menu-section-header">
                    <span>Workspace</span>
                    <span style="width:11px;height:11px;display:inline-flex;"><?= posthog_icon('chevron_down') ?></span>
                </li>

                <li>
                    <a href="superadmin.php?tab=system" class="menu-item <?= $activeTab === 'system' ? 'active' : '' ?>">
                        <div class="menu-left">
                            <span class="menu-icon"><?= posthog_icon('database') ?></span>
                            <span>System &amp; database</span>
                        </div>
                    </a>
                </li>
            </ul>

            <!-- Bottom Sidebar Links & Logout -->
            <div class="sidebar-bottom">
                <a href="superadmin.php?tab=system" class="menu-item" title="System & Database">
                    <div class="menu-left">
                        <span class="menu-icon"><?= posthog_icon('gear') ?></span>
                        <span>Settings</span>
                    </div>
                </a>
                <a href="logout.php" class="menu-item" title="Log out & return to Landing Page">
                    <div class="menu-left">
                        <span class="menu-icon"><?= posthog_icon('leave') ?></span>
                        <span>Log out</span>
                    </div>
                    <span style="font-size: 0.8rem;">&rarr;</span>
                </a>
            </div>

        </aside>

        <!-- ===============================================================
             Main Canvas
             =============================================================== -->
        <main class="main-canvas">

            <?php displayFlashMessage(); ?>

            <!-- Shared Page Title & Quick Actions -->
            <div class="dash-content-header">
                <div class="dash-title-group">
                    <h1><?= e($pageHeader) ?></h1>
                    <p class="dash-subtitle"><?= e($pageSubtitle) ?></p>
                </div>
            </div>

            <?php if ($activeTab === 'overview'): ?>
                <!-- =======================================================
                     TAB 1: PLATFORM OVERVIEW
                     ======================================================= -->
                <div class="overview-kpi-grid">

                    <div class="kpi-card">
                        <span class="kpi-label">Registered operators</span>
                        <div class="kpi-value"><?= $totalClients ?></div>
                        <span class="kpi-subtext">
                            <?= $activeClientsCount ?> active<?= $suspendedClientsCount > 0 ? " · {$suspendedClientsCount} suspended" : ' workspaces' ?>
                        </span>
                    </div>

                    <div class="kpi-card">
                        <span class="kpi-label">Monthly license MRR</span>
                        <div class="kpi-value" style="color: #15803d;">$<?= number_format($estimatedMrr, 2) ?></div>
                        <span class="kpi-subtext">
                            Gross receipts <strong>$<?= number_format($grossSimulatedRevenue, 2) ?></strong> ($49/mo flat)
                        </span>
                    </div>

                    <div class="kpi-card">
                        <span class="kpi-label">Monitored machines</span>
                        <div class="kpi-value"><?= $totalMachines ?></div>
                        <span class="kpi-subtext">Registered cabinets across all operators</span>
                    </div>

                    <div class="kpi-card">
                        <span class="kpi-label">Cataloged SKUs</span>
                        <div class="kpi-value"><?= $totalSkus ?></div>
                        <span class="kpi-subtext"><?= number_format($totalUnits) ?> stocked units tracked</span>
                    </div>

                </div>

                <!-- Middle Split: Client Workspaces (Left) & Recent Simulated License Purchases (Right) -->
                <div class="overview-split-grid">

                    <!-- Left: Client Operators -->
                    <div class="overview-card-box">
                        <div class="overview-card-header">
                            <div>
                                <div class="overview-card-title">Client workspaces</div>
                                <div class="overview-card-desc">Operators registered via public Register checkout</div>
                            </div>
                            <a href="superadmin.php?tab=operators" class="card-header-link">All operators (<?= $totalClients ?>) &rarr;</a>
                        </div>

                        <?php if (empty($operators)): ?>
                            <div style="padding: 2rem 1rem; text-align: center; border: 1px dashed var(--border-light); border-radius: 8px; background: #fafaf9;">
                                <div style="width:32px;height:32px;margin:0 auto 0.4rem;color:#94a3b8;"><?= posthog_icon('people') ?></div>
                                <div style="font-weight: 800; color: var(--text-main); font-size: 0.92rem; margin-bottom: 0.25rem;">No client operators registered yet</div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);">Accounts created via the public Register modal appear here automatically with active keys.</div>
                            </div>
                        <?php else: ?>
                            <div class="posthog-table-responsive">
                                <table class="posthog-data-table">
                                    <thead>
                                        <tr>
                                            <th>Operator &amp; company</th>
                                            <th>Bound license key</th>
                                            <th>Fleet scale</th>
                                            <th>Status</th>
                                            <th style="text-align: right;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($operators, 0, 6) as $op):
                                            $opStatus = strtolower($op['status'] ?? 'active');
                                            $isDemo   = ($op['email'] === 'demo@vendflow.io');
                                        ?>
                                            <tr>
                                                <td>
                                                    <div class="cell-product-name">
                                                        <?= e($op['name']) ?>
                                                        <?php if ($isDemo): ?>
                                                            <span class="badge-pill badge-below-par" style="margin-left: 0.3rem; font-size: 0.65rem;">DEMO</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="cell-product-sku"><?= e($op['business_name'] ?: $op['email']) ?></div>
                                                </td>
                                                <td>
                                                    <code style="font-size: 0.74rem; background: #f3f4f6; padding: 0.15rem 0.4rem; border-radius: 4px; border: 1px solid var(--border-light); font-weight: 700;">
                                                        <?= e($op['license_key'] ?: 'UNASSIGNED') ?>
                                                    </code>
                                                </td>
                                                <td>
                                                    <span style="font-weight: 700; color: var(--text-main);"><?= (int)$op['machines_count'] ?></span>
                                                    <span style="color: var(--text-muted);">machines · <?= (int)$op['products_count'] ?> SKUs</span>
                                                </td>
                                                <td>
                                                    <?php if ($opStatus === 'suspended'): ?>
                                                        <span class="badge-pill badge-loss-red">Suspended</span>
                                                    <?php else: ?>
                                                        <span class="badge-active-green">Active</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="text-align: right;">
                                                    <a href="superadmin.php?impersonate=<?= (int)$op['id'] ?>" class="btn-log-restock" style="text-decoration: none; display: inline-block;">
                                                        Impersonate
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Right: Recent Simulated License Purchases -->
                    <div class="overview-card-box">
                        <div class="overview-card-header">
                            <div>
                                <div class="overview-card-title">Recent license purchases</div>
                                <div class="overview-card-desc">Auto-provisioned receipts in <code>billing_transactions</code></div>
                            </div>
                            <a href="superadmin.php?tab=licenses" class="card-header-link">All receipts (<?= $totalBillingCount ?>) &rarr;</a>
                        </div>

                        <?php if (empty($billingTransactions)): ?>
                            <div style="padding: 2rem 1rem; text-align: center; border: 1px dashed var(--border-light); border-radius: 8px; background: #fafaf9; color: var(--text-muted); font-size: 0.82rem;">
                                No simulated license purchases recorded yet.
                            </div>
                        <?php else: ?>
                            <div class="machine-fleet-list">
                                <?php foreach (array_slice($billingTransactions, 0, 4) as $txn): ?>
                                    <div class="machine-fleet-card">
                                        <div class="machine-card-top">
                                            <div>
                                                <div class="cell-machine-name"><?= e($txn['business_name'] ?: $txn['operator_name']) ?></div>
                                                <div class="cell-machine-loc"><code><?= e($txn['transaction_ref']) ?></code> &bull; <?= e($txn['payment_method']) ?></div>
                                            </div>
                                            <span class="badge-active-green">+$<?= number_format((float)$txn['amount'], 2) ?></span>
                                        </div>
                                        <div class="machine-card-bottom">
                                            <span>Key: <code><?= e($txn['license_key']) ?></code></span>
                                            <span><?= e(date('M j, H:i', strtotime($txn['created_at']))) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- Bottom Card: Recent Platform Audit Events -->
                <div class="overview-card-box" style="margin-bottom: 1.5rem;">
                    <div class="overview-card-header">
                        <div>
                            <div class="overview-card-title">Recent platform audit events</div>
                            <div class="overview-card-desc">Live registrations, license activations, logins, and admin actions</div>
                        </div>
                        <a href="superadmin.php?tab=audit" class="card-header-link">Full audit log (<?= $totalPlatformAuditCount ?>) &rarr;</a>
                    </div>

                    <?php if (empty($recentAuditLogs)): ?>
                        <div style="padding: 1.75rem 1.25rem; text-align: center; border: 1px dashed var(--border-light); border-radius: 8px; background: #fafaf9; color: var(--text-muted); font-size: 0.84rem;">
                            New account signups, license activations, and logins will stream here automatically.
                        </div>
                    <?php else: ?>
                        <div class="posthog-table-responsive">
                            <table class="posthog-data-table">
                                <thead>
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>Actor</th>
                                        <th>Event</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentAuditLogs as $ev): ?>
                                        <tr>
                                            <td class="cell-timestamp"><?= e(date('M j, H:i:s', strtotime($ev['created_at']))) ?></td>
                                            <td>
                                                <div class="cell-product-name"><?= e($ev['actor_name']) ?></div>
                                                <div class="cell-product-sku"><?= e(strtoupper($ev['actor_role'])) ?></div>
                                            </td>
                                            <td>
                                                <span class="badge-pill <?= $ev['actor_role'] === 'superadmin' ? 'badge-audit-purple' : 'badge-sale-blue' ?>">
                                                    <?= e($ev['event_type']) ?>
                                                </span>
                                            </td>
                                            <td style="font-size: 0.82rem; color: var(--text-sub);"><?= e($ev['description']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($activeTab === 'operators'): ?>
                <!-- =======================================================
                     TAB 2: OPERATORS & CLIENTS DIRECTORY
                     ======================================================= -->
                <div class="overview-kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
                    <div class="kpi-card">
                        <span class="kpi-label">Total client workspaces</span>
                        <div class="kpi-value"><?= $totalClients ?></div>
                        <span class="kpi-subtext">Created via public Register modal</span>
                    </div>
                    <div class="kpi-card">
                        <span class="kpi-label">Active operator accounts</span>
                        <div class="kpi-value" style="color: #15803d;"><?= $activeClientsCount ?></div>
                        <span class="kpi-subtext">Across Solo, Business, Max &amp; Enterprise</span>
                    </div>
                    <div class="kpi-card" style="<?= $suspendedClientsCount > 0 ? 'background: #fef2f2; border-color: #fecaca;' : '' ?>">
                        <span class="kpi-label" style="<?= $suspendedClientsCount > 0 ? 'color: #b91c1c;' : '' ?>">Suspended accounts</span>
                        <div class="kpi-value" style="<?= $suspendedClientsCount > 0 ? 'color: #dc2626;' : '' ?>"><?= $suspendedClientsCount ?></div>
                        <span class="kpi-subtext">Paused sign-in access</span>
                    </div>
                </div>

                <!-- Search & Status Filter Toolbar -->
                <div class="catalog-toolbar" style="margin-bottom: 1rem;">
                    <div class="search-box-wrapper">
                        <span class="search-icon-inside" style="width:14px;height:14px;display:inline-flex;align-items:center;color:#94a3b8;"><?= posthog_icon('search') ?></span>
                        <input
                            type="text"
                            id="operator-search-input"
                            class="catalog-search-input"
                            placeholder="Search operator name, company, email, or license key..."
                            oninput="filterOperatorsTable()"
                        >
                    </div>
                    <div class="filter-pills-container" style="margin-bottom: 0;">
                        <button type="button" class="filter-chip active" onclick="setStatusFilter('all', this)">
                            All <span class="chip-count"><?= $totalClients ?></span>
                        </button>
                        <button type="button" class="filter-chip" onclick="setStatusFilter('active', this)">
                            Active <span class="chip-count"><?= $activeClientsCount ?></span>
                        </button>
                        <button type="button" class="filter-chip" onclick="setStatusFilter('suspended', this)">
                            Suspended <span class="chip-count"><?= $suspendedClientsCount ?></span>
                        </button>
                    </div>
                </div>

                <div class="posthog-table-card" style="margin-bottom: 2rem;">
                    <?php if (empty($operators)): ?>
                        <div class="empty-state-box" style="border: none; margin-bottom: 0;">
                            <div class="empty-icon-wrap" style="width:36px;height:36px;margin:0 auto;color:#94a3b8;"><?= posthog_icon('people') ?></div>
                            <div class="empty-title">No client operators yet</div>
                            <div class="empty-desc">Operators register on the public landing page Register modal, where simulated checkout binds their Solo, Business, or Max license automatically.</div>
                        </div>
                    <?php else: ?>
                        <div class="posthog-table-responsive">
                            <table class="posthog-data-table" id="operators-directory-table">
                                <thead>
                                    <tr>
                                        <th>Operator &amp; business</th>
                                        <th>Email &amp; verified</th>
                                        <th>License plan</th>
                                        <th>Bound license key</th>
                                        <th>Fleet scale</th>
                                        <th>Status</th>
                                        <th>Joined</th>
                                        <th style="text-align: right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($operators as $op):
                                        $opStatus = strtolower($op['status'] ?? 'active');
                                        $isDemo   = ($op['email'] === 'demo@vendflow.io');
                                        $opPlan   = get_user_plan_info($op);
                                        $jsClientPayload = htmlspecialchars(json_encode([
                                            'id'                   => (int)$op['id'],
                                            'name'                 => $op['name'],
                                            'business'             => $op['business_name'] ?: 'Independent Operator',
                                            'status'               => $opStatus,
                                            'plan_tier'            => $opPlan['tier'],
                                            'machine_limit'        => (int)$opPlan['machine_limit'],
                                            'staff_limit'          => (int)$opPlan['staff_limit'],
                                            'price'                => (float)$opPlan['price'],
                                            'custom_machine_limit' => $op['custom_machine_limit'] !== null ? (int)$op['custom_machine_limit'] : null,
                                            'custom_staff_limit'   => $op['custom_staff_limit'] !== null ? (int)$op['custom_staff_limit'] : null,
                                            'custom_price'         => $op['custom_price'] !== null ? (float)$op['custom_price'] : null,
                                            'current_machines'     => (int)$op['machines_count'],
                                            'current_staff'        => (int)($op['staff_count'] ?? 0),
                                            'is_demo'              => $isDemo,
                                        ]), ENT_QUOTES, 'UTF-8');
                                    ?>
                                        <tr data-status="<?= e($opStatus) ?>">
                                            <td>
                                                <div class="cell-product-name">
                                                    <?= e($op['name']) ?>
                                                    <?php if ($isDemo): ?>
                                                        <span class="badge-pill badge-below-par" style="margin-left: 0.3rem; font-size: 0.65rem;">PUBLIC DEMO</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="cell-product-sku"><?= e($op['business_name'] ?: 'Independent Operator') ?></div>
                                            </td>
                                            <td style="font-family: monospace; font-size: 0.8rem; color: var(--text-sub);">
                                                <?= e($op['email']) ?>
                                                <div style="margin-top: 0.2rem;">
                                                    <?php if (!empty($op['email_verified_at'])): ?>
                                                        <span class="badge-active-green" style="font-size: 0.65rem; display:inline-flex; align-items:center; gap:3px;"><span style="width:10px;height:10px;display:inline-flex;"><?= posthog_icon('check') ?></span> Verified</span>
                                                    <?php else: ?>
                                                        <span class="badge-pill badge-loss-red" style="font-size: 0.65rem;">Pending verify</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <span class="badge-pill <?= $opPlan['tier'] === 'solo' ? 'badge-initial-gray' : ($opPlan['tier'] === 'business' ? 'badge-sale-blue' : ($opPlan['tier'] === 'max' ? 'badge-audit-purple' : 'badge-restock-green')) ?>" style="font-weight: 700;">
                                                        <?= e(strtoupper($opPlan['label'])) ?> ($<?= number_format($opPlan['price'], 0) ?>/mo)
                                                    </span>
                                                </div>
                                                <div class="cell-product-sku" style="margin-top: 3px;">
                                                    <?= (int)$opPlan['machine_limit'] ?> mach · <?= (int)$opPlan['staff_limit'] ?> staff
                                                </div>
                                            </td>
                                            <td>
                                                <code style="font-size: 0.76rem; background: #f3f4f6; padding: 0.18rem 0.45rem; border-radius: 4px; border: 1px solid var(--border-light); font-weight: 700;">
                                                    <?= e($op['license_key'] ?: 'UNASSIGNED') ?>
                                                </code>
                                            </td>
                                            <td>
                                                <div style="font-weight: 700; color: var(--text-main); font-size: 0.8rem;">
                                                    <?= (int)$op['machines_count'] ?> machines · <?= (int)$op['products_count'] ?> SKUs
                                                </div>
                                                <div class="cell-product-sku">
                                                    <?= number_format((int)$op['units_count']) ?> units · <?= (int)$op['logs_count'] ?> logs
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($opStatus === 'suspended'): ?>
                                                    <span class="badge-pill badge-loss-red">Suspended</span>
                                                <?php else: ?>
                                                    <span class="badge-active-green">Active</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="cell-timestamp">
                                                <?= date('M j, Y', strtotime($op['created_at'])) ?>
                                            </td>
                                            <td style="text-align: right; white-space: nowrap;">
                                                <div style="display: inline-flex; align-items: center; gap: 0.4rem;">
                                                    <a href="superadmin.php?impersonate=<?= (int)$op['id'] ?>" class="btn-log-restock" style="text-decoration: none;">
                                                        Impersonate
                                                    </a>
                                                    <button type="button" class="btn-dash-outline" style="padding: 0.32rem 0.65rem; font-size: 0.75rem;" onclick="openEditClientModal(<?= $jsClientPayload ?>)">
                                                        Manage
                                                    </button>
                                                    <?php if (!$isDemo): ?>
                                                        <form method="POST" action="superadmin.php" style="display: inline;" onsubmit="return confirm('Delete operator <?= e(addslashes($op['name'])) ?> and cascade associated licenses, receipts, machines, SKUs, and logs?');">
                                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                            <input type="hidden" name="return_tab" value="operators">
                                                            <input type="hidden" name="action" value="delete_user">
                                                            <input type="hidden" name="user_id" value="<?= (int)$op['id'] ?>">
                                                            <button type="submit" class="btn-danger-outline" style="padding: 0.32rem 0.55rem; font-size: 0.75rem;">
                                                                Delete
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($activeTab === 'licenses'): ?>
                <!-- =======================================================
                     TAB 3: LICENSES & BILLING LEDGER
                     ======================================================= -->
                <div class="overview-kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
                    <div class="kpi-card">
                        <span class="kpi-label">Active license keys</span>
                        <div class="kpi-value" style="color: #1e40af;"><?= $activeLicenseCount ?></div>
                        <span class="kpi-subtext">Bound to active operator workspaces</span>
                    </div>
                    <div class="kpi-card">
                        <span class="kpi-label">Simulated checkout receipts</span>
                        <div class="kpi-value"><?= $totalBillingCount ?></div>
                        <span class="kpi-subtext">Recorded in <code>billing_transactions</code></span>
                    </div>
                    <div class="kpi-card">
                        <span class="kpi-label">Gross license volume</span>
                        <div class="kpi-value" style="color: #15803d;">$<?= number_format($grossSimulatedRevenue, 2) ?></div>
                        <span class="kpi-subtext">All-time simulated checkout total</span>
                    </div>
                    <div class="kpi-card">
                        <span class="kpi-label">Tiered pricing model</span>
                        <div class="kpi-value" style="font-size: 1.15rem; color: var(--text-main);">$19 / $35 / $99</div>
                        <span class="kpi-subtext">Solo, Business &amp; Max plans</span>
                    </div>
                </div>

                <div class="overview-split-grid">

                    <!-- Left: Issued Software License Keys Registry -->
                    <div class="overview-card-box">
                        <div class="overview-card-header">
                            <div>
                                <div class="overview-card-title">License keys registry (<code>license_keys</code>)</div>
                                <div class="overview-card-desc">Auto-generated on account signup or rotated by Superadmin</div>
                            </div>
                        </div>

                        <?php if (empty($licenseKeysList)): ?>
                            <div style="padding: 2rem 1rem; text-align: center; border: 1px dashed var(--border-light); border-radius: 8px; background: #fafaf9; color: var(--text-muted); font-size: 0.84rem;">
                                No license keys issued yet.
                            </div>
                        <?php else: ?>
                            <div class="posthog-table-responsive">
                                <table class="posthog-data-table">
                                    <thead>
                                        <tr>
                                            <th>License key</th>
                                            <th>Operator workspace</th>
                                            <th>Status</th>
                                            <th style="text-align: right;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($licenseKeysList as $lk):
                                            $lkStatus = strtolower($lk['status'] ?? 'active');
                                        ?>
                                            <tr>
                                                <td>
                                                    <code style="font-size: 0.76rem; background: #f3f4f6; padding: 0.18rem 0.45rem; border-radius: 4px; border: 1px solid var(--border-light); font-weight: 700;">
                                                        <?= e($lk['license_key']) ?>
                                                    </code>
                                                    <div class="cell-product-sku"><strong><?= e($lk['plan_name'] ?: 'VendFlow License') ?></strong> · Origin: <?= e($lk['issued_by']) ?></div>
                                                </td>
                                                <td>
                                                    <div class="cell-product-name"><?= e($lk['business_name'] ?: ($lk['operator_name'] ?: 'Unassigned')) ?></div>
                                                    <div class="cell-product-sku"><?= e($lk['operator_email'] ?: '—') ?></div>
                                                </td>
                                                <td>
                                                    <?php if ($lkStatus === 'active'): ?>
                                                        <span class="badge-active-green">Active</span>
                                                    <?php elseif ($lkStatus === 'rotated'): ?>
                                                        <span class="badge-pill badge-initial-gray">Rotated</span>
                                                    <?php else: ?>
                                                        <span class="badge-pill badge-loss-red"><?= e(ucfirst($lkStatus)) ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="text-align: right;">
                                                    <?php if ($lkStatus === 'active' && !empty($lk['user_id'])): ?>
                                                        <form method="POST" action="superadmin.php" style="display: inline;" onsubmit="return confirm('Rotate and issue a fresh license key for this operator?');">
                                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                            <input type="hidden" name="return_tab" value="licenses">
                                                            <input type="hidden" name="action" value="rotate_license">
                                                            <input type="hidden" name="target_user_id" value="<?= (int)$lk['user_id'] ?>">
                                                            <button type="submit" class="btn-dash-outline" style="padding: 0.28rem 0.6rem; font-size: 0.74rem;">
                                                                Rotate key
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span style="font-size: 0.74rem; color: var(--text-muted);">Archived</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Right: Billing Receipts Table -->
                    <div class="overview-card-box">
                        <div class="overview-card-header">
                            <div>
                                <div class="overview-card-title">Billing receipts (<code>billing_transactions</code>)</div>
                                <div class="overview-card-desc">Simulated $49.00 license purchases from public registration</div>
                            </div>
                        </div>

                        <?php if (empty($billingTransactions)): ?>
                            <div style="padding: 2rem 1rem; text-align: center; border: 1px dashed var(--border-light); border-radius: 8px; background: #fafaf9; color: var(--text-muted); font-size: 0.84rem;">
                                No billing receipts yet.
                            </div>
                        <?php else: ?>
                            <div class="posthog-table-responsive">
                                <table class="posthog-data-table">
                                    <thead>
                                        <tr>
                                            <th>Receipt #</th>
                                            <th>Workspace</th>
                                            <th>Method</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($billingTransactions as $bt): ?>
                                            <tr>
                                                <td>
                                                    <code style="font-size: 0.75rem; font-weight: 700;"><?= e($bt['transaction_ref']) ?></code>
                                                    <div class="cell-product-sku"><?= e(date('M j, Y H:i', strtotime($bt['created_at']))) ?></div>
                                                </td>
                                                <td>
                                                    <div class="cell-product-name"><?= e($bt['business_name'] ?: $bt['operator_name']) ?></div>
                                                    <div class="cell-product-sku"><code><?= e($bt['license_key']) ?></code></div>
                                                </td>
                                                <td style="font-size: 0.78rem; color: var(--text-sub);"><?= e($bt['payment_method']) ?></td>
                                                <td style="font-weight: 800; color: #15803d;">$<?= number_format((float)$bt['amount'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

            <?php elseif ($activeTab === 'audit'): ?>
                <!-- =======================================================
                     TAB 4: PLATFORM AUDIT LOG
                     ======================================================= -->
                <div class="posthog-table-card" style="margin-bottom: 1.5rem; flex: 1; display: flex; flex-direction: column; min-height: 0;">
                    <?php if (empty($platformAuditLogs)): ?>
                        <div class="empty-state-box" style="border: none; margin-bottom: 0;">
                            <div class="empty-icon-wrap">⏱️</div>
                            <div class="empty-title">No platform events recorded yet</div>
                            <div class="empty-desc">Registrations, simulated license purchases, logins, and admin actions are logged here automatically.</div>
                        </div>
                    <?php else: ?>
                        <div class="posthog-table-responsive" style="flex: 1; overflow-y: auto;">
                            <table class="posthog-data-table">
                                <thead>
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>Actor</th>
                                        <th>Role</th>
                                        <th>Event type</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($platformAuditLogs as $ev): ?>
                                        <tr>
                                            <td class="cell-timestamp"><?= e(date('M j, Y H:i:s', strtotime($ev['created_at']))) ?></td>
                                            <td class="cell-product-name"><?= e($ev['actor_name']) ?></td>
                                            <td>
                                                <span class="badge-pill <?= $ev['actor_role'] === 'superadmin' ? 'badge-audit-purple' : 'badge-initial-gray' ?>" style="text-transform: uppercase;">
                                                    <?= e($ev['actor_role']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge-pill badge-sale-blue"><?= e($ev['event_type']) ?></span>
                                            </td>
                                            <td style="font-size: 0.82rem; color: var(--text-sub); max-width: 620px; line-height: 1.45;">
                                                <?= e($ev['description']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- PostHog Pagination (Adaptive Limit to Fill Empty Space) -->
                        <div class="posthog-pagination" style="margin-top: auto;">
                            <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                                <div>
                                    <?php if ($totalPlatformAuditCount > 0): ?>
                                        Showing <strong><?= $auditStartResult ?></strong> to <strong><?= $auditEndResult ?></strong> of <strong><?= $totalPlatformAuditCount ?></strong> entries (<?= $auditPerPage ?> per page)
                                    <?php else: ?>
                                        Showing <strong>0</strong> entries (<?= $auditPerPage ?> per page)
                                    <?php endif; ?>
                                </div>
                                <div style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.78rem;">
                                    <span style="color: var(--text-muted);">Rows:</span>
                                    <?php foreach ($allowedAuditLimits as $lim): ?>
                                        <a href="superadmin.php?tab=audit&amp;page=1&amp;limit=<?= $lim ?>"
                                           class="page-link-btn <?= $lim === $auditPerPage ? 'active' : '' ?>"
                                           style="padding: 0.2rem 0.45rem; font-size: 0.74rem;">
                                            <?= $lim ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="pagination-pages">
                                <?php $limitParam = ($auditPerPage !== 15) ? '&amp;limit=' . $auditPerPage : ''; ?>
                                <?php if ($auditPageNum > 1): ?>
                                    <a href="superadmin.php?tab=audit&amp;page=<?= $auditPageNum - 1 ?><?= $limitParam ?>" class="page-link-btn">&larr; Previous</a>
                                <?php else: ?>
                                    <span class="page-link-btn disabled">&larr; Previous</span>
                                <?php endif; ?>

                                <?php
                                $rangeStart = max(1, $auditPageNum - 2);
                                $rangeEnd   = min($auditTotalPages, $auditPageNum + 2);
                                if ($rangeStart > 1) {
                                    echo '<a href="superadmin.php?tab=audit&amp;page=1' . $limitParam . '" class="page-link-btn">1</a>';
                                    if ($rangeStart > 2) {
                                        echo '<span class="page-link-btn disabled" style="border:none;background:transparent;">…</span>';
                                    }
                                }
                                for ($p = $rangeStart; $p <= $rangeEnd; $p++) {
                                    $activeClass = ($p === $auditPageNum) ? 'active' : '';
                                    echo '<a href="superadmin.php?tab=audit&amp;page=' . $p . $limitParam . '" class="page-link-btn ' . $activeClass . '">' . $p . '</a>';
                                }
                                if ($rangeEnd < $auditTotalPages) {
                                    if ($rangeEnd < $auditTotalPages - 1) {
                                        echo '<span class="page-link-btn disabled" style="border:none;background:transparent;">…</span>';
                                    }
                                    echo '<a href="superadmin.php?tab=audit&amp;page=' . $auditTotalPages . $limitParam . '" class="page-link-btn">' . $auditTotalPages . '</a>';
                                }
                                ?>

                                <?php if ($auditPageNum < $auditTotalPages): ?>
                                    <a href="superadmin.php?tab=audit&amp;page=<?= $auditPageNum + 1 ?><?= $limitParam ?>" class="page-link-btn">Next &rarr;</a>
                                <?php else: ?>
                                    <span class="page-link-btn disabled">Next &rarr;</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($activeTab === 'messages'): ?>
                <!-- =======================================================
                     TAB 5: SUPPORT INBOX
                     ======================================================= -->
                <div class="overview-card-box" style="margin-bottom: 2rem;">
                    <div class="overview-card-header" style="flex-wrap: wrap; gap: 0.85rem;">
                        <div>
                            <div class="overview-card-title">
                                Operator support &amp; licensing inquiries
                                (<?= $msgStatusFilter === 'resolved' ? $resolvedMessagesCount . ' resolved' : $unreadMessagesCount . ' open' ?>)
                            </div>
                            <div class="overview-card-desc">Direct messages sent via <code>contact.php</code></div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
                            <div class="filter-pills-container" style="margin-bottom: 0;">
                                <a href="superadmin.php?tab=messages&amp;msg_status=open"
                                   class="filter-chip <?= $msgStatusFilter === 'open' ? 'active' : '' ?>"
                                   style="text-decoration: none;">
                                    Open <span class="chip-count"><?= $unreadMessagesCount ?></span>
                                </a>
                                <a href="superadmin.php?tab=messages&amp;msg_status=resolved"
                                   class="filter-chip <?= $msgStatusFilter === 'resolved' ? 'active' : '' ?>"
                                   style="text-decoration: none;">
                                    Resolved / Archive <span class="chip-count"><?= $resolvedMessagesCount ?></span>
                                </a>
                            </div>
                            <a href="contact.php" class="card-header-link">Open public contact page &rarr;</a>
                        </div>
                    </div>

                    <?php if (empty($messages)): ?>
                        <div style="padding: 2.75rem 1.5rem; text-align: center; border: 1px dashed var(--border-light); border-radius: 8px; background: #fafaf9;">
                            <div style="width:32px;height:32px;margin:0 auto 0.5rem;color:#94a3b8;"><?= posthog_icon($msgStatusFilter === 'resolved' ? 'database' : 'support') ?></div>
                            <div style="font-weight: 800; color: var(--text-main); font-size: 1rem; margin-bottom: 0.3rem;">
                                <?= $msgStatusFilter === 'resolved' ? 'No resolved inquiries yet' : 'Support inbox is clear' ?>
                            </div>
                            <div style="font-size: 0.84rem; color: var(--text-muted);">
                                <?= $msgStatusFilter === 'resolved'
                                    ? 'Inquiries marked as resolved from the Open inbox are archived here.'
                                    : 'Questions submitted on the public Contact page will appear here automatically.' ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="posthog-table-responsive">
                            <table class="posthog-data-table">
                                <thead>
                                    <tr>
                                        <th>Received</th>
                                        <th>Sender</th>
                                        <th>Email</th>
                                        <th>Message</th>
                                        <th>Status</th>
                                        <th style="text-align: right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($messages as $msg):
                                        $isResolved = (strtolower($msg['status'] ?? 'open') === 'resolved');
                                    ?>
                                        <tr>
                                            <td class="cell-timestamp"><?= date('M j, Y H:i', strtotime($msg['created_at'])) ?></td>
                                            <td class="cell-product-name"><?= e($msg['name']) ?></td>
                                            <td>
                                                <a href="mailto:<?= e($msg['email']) ?>" style="color: #2563eb; text-decoration: none; font-weight: 600;">
                                                    <?= e($msg['email']) ?>
                                                </a>
                                            </td>
                                            <td style="max-width: 480px; line-height: 1.45; color: var(--text-sub);">
                                                <?= nl2br(e($msg['message'])) ?>
                                            </td>
                                            <td>
                                                <?php if ($isResolved): ?>
                                                    <span class="badge-active-green" style="display:inline-flex; align-items:center; gap:4px;"><span style="width:12px;height:12px;display:inline-flex;"><?= posthog_icon('check') ?></span> Resolved</span>
                                                <?php else: ?>
                                                    <span class="badge-pill badge-below-par">Open</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: right; white-space: nowrap;">
                                                <a href="mailto:<?= e($msg['email']) ?>?subject=Re:%20VendFlow%20Support%20Inquiry" class="btn-dash-outline" style="padding: 0.3rem 0.65rem; font-size: 0.75rem; margin-right: 0.35rem;">
                                                    Reply
                                                </a>
                                                <?php if (!$isResolved): ?>
                                                    <form method="POST" action="superadmin.php" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                        <input type="hidden" name="return_tab" value="messages">
                                                        <input type="hidden" name="action" value="resolve_message">
                                                        <input type="hidden" name="message_id" value="<?= (int)$msg['id'] ?>">
                                                        <button type="submit" class="btn-dash-outline" style="padding: 0.3rem 0.65rem; font-size: 0.75rem; color: #15803d; border-color: #86efac; background: #f0fdf4;">
                                                            <span style="width:12px;height:12px;display:inline-flex;vertical-align:-1px;margin-right:3px;"><?= posthog_icon('check') ?></span> Resolve
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" action="superadmin.php" style="display: inline; margin-right: 0.35rem;">
                                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                        <input type="hidden" name="return_tab" value="messages">
                                                        <input type="hidden" name="action" value="reopen_message">
                                                        <input type="hidden" name="message_id" value="<?= (int)$msg['id'] ?>">
                                                        <button type="submit" class="btn-dash-outline" style="padding: 0.3rem 0.65rem; font-size: 0.75rem;">
                                                            Reopen
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="superadmin.php" style="display: inline;" onsubmit="return confirm('Permanently delete this archived inquiry?');">
                                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                        <input type="hidden" name="return_tab" value="messages">
                                                        <input type="hidden" name="msg_status" value="resolved">
                                                        <input type="hidden" name="action" value="delete_message">
                                                        <input type="hidden" name="message_id" value="<?= (int)$msg['id'] ?>">
                                                        <button type="submit" class="btn-danger-outline" style="padding: 0.3rem 0.6rem; font-size: 0.75rem;">
                                                            Delete
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($activeTab === 'system'): ?>
                <!-- =======================================================
                     TAB 6: SYSTEM & 9-TABLE DATABASE DIAGNOSTICS
                     ======================================================= -->
                <div class="overview-kpi-grid">
                    <div class="kpi-card">
                        <span class="kpi-label">PHP runtime</span>
                        <div class="kpi-value" style="font-size: 1.5rem;">PHP <?= e($phpVersion) ?></div>
                        <span class="kpi-subtext"><?= e($serverSoftware) ?></span>
                    </div>
                    <div class="kpi-card">
                        <span class="kpi-label">Database engine</span>
                        <div class="kpi-value" style="font-size: 1.5rem;">InnoDB PDO</div>
                        <span class="kpi-subtext">MySQL / MariaDB <?= e($dbVersion) ?></span>
                    </div>
                    <div class="kpi-card">
                        <span class="kpi-label">Relational schema</span>
                        <div class="kpi-value" style="font-size: 1.5rem; color: #1e40af;">9 tables</div>
                        <span class="kpi-subtext">Foreign-key cascade integrity</span>
                    </div>
                    <div class="kpi-card">
                        <span class="kpi-label">Security layer</span>
                        <div class="kpi-value" style="font-size: 1.5rem; color: #15803d;">Active</div>
                        <span class="kpi-subtext">CSRF · Bcrypt · PDO Transactions</span>
                    </div>
                </div>

                <!-- Live 9-Table Row Count Ledger -->
                <div class="overview-card-box" style="margin-bottom: 1.5rem;">
                    <div class="overview-card-header">
                        <div>
                            <div class="overview-card-title">Live MySQL schema telemetry (<code><?= e(DB_NAME) ?></code>)</div>
                            <div class="overview-card-desc">Real-time row counts across all 9 normalized tables</div>
                        </div>
                    </div>

                    <div class="posthog-table-responsive">
                        <table class="posthog-data-table">
                            <thead>
                                <tr>
                                    <th>Table name</th>
                                    <th>Purpose &amp; foreign-key scope</th>
                                    <th style="text-align: right;">Live rows</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tableStats as $ts): ?>
                                    <tr>
                                        <td><code style="font-weight: 800; font-size: 0.8rem;"><?= e($ts['table']) ?></code></td>
                                        <td style="color: var(--text-sub);"><?= e($ts['desc']) ?></td>
                                        <td style="text-align: right; font-weight: 800;"><?= number_format($ts['rows']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <div>
                <a href="partner.php" class="back-landing-link">&larr; Switch back to Operator Portal WebOS</a>
                &nbsp;&middot;&nbsp;
                <a href="index.php" class="back-landing-link">Public Vending Directory &rarr;</a>
            </div>

        </main>

    </div>

    <!-- ===================================================================
         Manage Client Access Status & License Rotation Modal (.modal-overlay)
         =================================================================== -->
    <div class="modal-overlay" id="edit-client-modal" onclick="if(event.target === this) closeSuperadminModals()">
        <div class="modal-container" style="max-width: 500px;">
            <div class="modal-header">
                <span class="modal-title" id="edit-client-modal-title">Manage operator workspace</span>
                <button type="button" class="modal-close-btn" onclick="closeSuperadminModals()" style="display:inline-flex;align-items:center;justify-content:center;"><?= posthog_icon('x', '', 'width:12px;height:12px;') ?></button>
            </div>
            <form id="edit-client-form" method="POST" action="superadmin.php" onsubmit="return handleClientFormSubmit(event)">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="return_tab" value="<?= e($activeTab) ?>">
                <input type="hidden" name="action" value="manage_client">
                <input type="hidden" name="user_id" id="edit-client-id" value="">

                <div class="modal-body" style="display: flex; flex-direction: column; gap: 0.9rem;">

                    <!-- Workspace Usage Indicator Strip -->
                    <div id="edit-client-usage-strip" style="background: #f8fafc; border: 1px solid var(--border-light); border-radius: 8px; padding: 0.65rem 0.85rem; font-size: 0.8rem; color: var(--text-sub); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                        <div>
                            <span>Current usage: </span>
                            <strong id="edit-client-current-usage" style="color: var(--text-main);">0 machines · 0 staff</strong>
                        </div>
                        <span id="edit-client-tier-badge" class="badge-pill badge-sale-blue" style="font-size: 0.68rem; font-weight: 700;">SOLO</span>
                    </div>

                    <!-- Assigned Plan Selection -->
                    <div class="form-group-item">
                        <label class="form-label" for="edit-client-plan">Assigned license plan tier</label>
                        <select id="edit-client-plan" name="plan_tier" class="form-input-text" onchange="onPlanSelectionChanged(this.value)">
                            <option value="solo">Solo ($19.00/mo — standard 4 machines, 1 staff)</option>
                            <option value="business">Business ($35.00/mo — standard 12 machines, 4 staff)</option>
                            <option value="max">Max ($99.00/mo — standard 50 machines, 18 staff)</option>
                            <option value="enterprise">Enterprise (Custom limits &amp; tailored fleet allocations)</option>
                        </select>
                    </div>

                    <!-- Custom Machine & Staff Allocations (Enforcing Plan Baseline Floors) -->
                    <div style="background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 0.85rem; display: flex; flex-direction: column; gap: 0.75rem;">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); text-transform: uppercase; letter-spacing: 0.03em;">
                                Machine &amp; Staff Allocation Limits
                            </label>
                            <span id="enterprise-tag-pill" class="badge-pill badge-audit-purple" style="font-size: 0.66rem; display: none;">ENTERPRISE CUSTOM</span>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                            <div>
                                <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 2px;">
                                    <label class="form-label" for="edit-client-machines" style="font-size: 0.76rem; margin-bottom: 0;">Machine Limit *</label>
                                    <span id="edit-client-machines-floor" style="font-size: 0.7rem; color: #b45309; font-weight: 700;">Min 4</span>
                                </div>
                                <div style="position: relative;">
                                    <input type="number" min="4" max="10000" id="edit-client-machines" name="custom_machine_limit" class="form-input-text" placeholder="e.g. 4" style="padding-right: 2.3rem;" oninput="enforceLiveFloor('machines')" onblur="snapToFloor('machines')">
                                    <span style="position: absolute; right: 0.65rem; top: 50%; transform: translateY(-50%); font-size: 0.72rem; color: var(--text-muted); pointer-events: none;">mach</span>
                                </div>
                                <span id="edit-client-machines-help" style="font-size: 0.7rem; color: var(--text-muted); margin-top: 2px; display: block;">Solo plan baseline: at least 4 machines</span>
                            </div>

                            <div>
                                <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 2px;">
                                    <label class="form-label" for="edit-client-staff" style="font-size: 0.76rem; margin-bottom: 0;">Staff Limit *</label>
                                    <span id="edit-client-staff-floor" style="font-size: 0.7rem; color: #b45309; font-weight: 700;">Min 1</span>
                                </div>
                                <div style="position: relative;">
                                    <input type="number" min="1" max="10000" id="edit-client-staff" name="custom_staff_limit" class="form-input-text" placeholder="e.g. 1" style="padding-right: 2.3rem;" oninput="enforceLiveFloor('staff')" onblur="snapToFloor('staff')">
                                    <span style="position: absolute; right: 0.65rem; top: 50%; transform: translateY(-50%); font-size: 0.72rem; color: var(--text-muted); pointer-events: none;">staff</span>
                                </div>
                                <span id="edit-client-staff-help" style="font-size: 0.7rem; color: var(--text-muted); margin-top: 2px; display: block;">Solo plan baseline: at least 1 staff account</span>
                            </div>
                        </div>

                        <div>
                            <label class="form-label" for="edit-client-price" style="font-size: 0.76rem;">Custom Monthly Fee ($/mo)</label>
                            <div style="position: relative;">
                                <span style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); font-size: 0.8rem; color: var(--text-muted); pointer-events: none;">$</span>
                                <input type="number" step="0.01" min="0" max="99999" id="edit-client-price" name="custom_price" class="form-input-text" placeholder="199.00" style="padding-left: 1.6rem;">
                            </div>
                            <span style="font-size: 0.7rem; color: var(--text-muted); margin-top: 2px; display: block;">Billed contract price (used for platform MRR tally)</span>
                        </div>
                    </div>

                    <!-- Workspace Access Status -->
                    <div class="form-group-item">
                        <label class="form-label" for="edit-client-status">Workspace access status</label>
                        <select id="edit-client-status" name="status" class="form-input-text">
                            <option value="active">Active (Sign-in enabled)</option>
                            <option value="suspended" id="edit-client-status-suspended">Suspended (Pause operator login)</option>
                        </select>
                    </div>

                    <!-- Key Rotation Checkbox -->
                    <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.82rem; color: var(--text-sub); cursor: pointer; background: #fafaf9; padding: 0.5rem 0.65rem; border-radius: 6px; border: 1px solid var(--border-light);">
                        <input type="checkbox" name="regenerate_key" value="1">
                        <span>Reissue fresh license key matching plan &amp; limits</span>
                    </label>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" onclick="closeSuperadminModals()">Cancel</button>
                    <button type="submit" class="btn-modal-save">Save changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===================================================================
         Confirm Workspace Changes Modal (.modal-overlay)
         =================================================================== -->
    <div class="modal-overlay" id="confirm-save-modal" style="z-index: 10050;" onclick="if(event.target === this) closeConfirmModal()">
        <div class="modal-container" style="max-width: 480px; border: 1.5px solid var(--border-light); box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.35);">
            <div class="modal-header" style="background: #fafaf9; border-bottom: 1px solid var(--border-light);">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 6px; background: #eff6ff; color: #2563eb;">
                        <?= posthog_icon('activity', '', 'width:14px;height:14px;') ?>
                    </span>
                    <span class="modal-title" style="font-size: 0.98rem; font-weight: 700;">Confirm changes</span>
                </div>
                <button type="button" class="modal-close-btn" onclick="closeConfirmModal()" style="display:inline-flex;align-items:center;justify-content:center;">
                    <?= posthog_icon('x', '', 'width:12px;height:12px;') ?>
                </button>
            </div>

            <div class="modal-body" style="padding: 1.15rem; display: flex; flex-direction: column; gap: 0.85rem;">
                <div>
                    <h4 style="margin: 0 0 0.25rem 0; font-size: 1.05rem; font-weight: 800; color: var(--text-main);">
                        Are you sure you want to save these changes?
                    </h4>
                    <p style="margin: 0; font-size: 0.8rem; color: var(--text-sub); line-height: 1.4;">
                        Review the modified limits and settings for <strong id="confirm-client-name" style="color: var(--text-main);">operator</strong>:
                    </p>
                </div>

                <!-- List of Detected Changes -->
                <div id="confirm-changes-list" style="display: flex; flex-direction: column; gap: 0.45rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.75rem;">
                    <!-- Dynamically populated with diff items -->
                </div>

                <div style="font-size: 0.72rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.4rem;">
                    <span style="display: inline-flex; width: 12px; height: 12px; color: #64748b;"><?= posthog_icon('check') ?></span>
                    <span>Modifications will take effect immediately upon confirmation.</span>
                </div>
            </div>

            <div class="modal-footer" style="padding: 0.75rem 1.15rem; background: #fafaf9; border-top: 1px solid var(--border-light); display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="btn-modal-cancel" onclick="closeConfirmModal()">Keep editing</button>
                <button type="button" class="btn-modal-save" onclick="executeConfirmedSubmit()" style="background: #2563eb; border-color: #2563eb; color: #ffffff;">
                    Yes, save changes
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentStatusFilter = 'all';

        const PLAN_DEFAULTS = {
            solo:       { label: 'Solo',       minMachines: 4,   minStaff: 1,   defaultMachines: 4,   defaultStaff: 1,   price: 19.00 },
            business:   { label: 'Business',   minMachines: 12,  minStaff: 4,   defaultMachines: 12,  defaultStaff: 4,   price: 35.00 },
            max:        { label: 'Max',        minMachines: 50,  minStaff: 18,  defaultMachines: 50,  defaultStaff: 18,  price: 99.00 },
            enterprise: { label: 'Enterprise', minMachines: 1,   minStaff: 1,   defaultMachines: 100, defaultStaff: 30,  price: 199.00 }
        };

        let currentActiveClient = null;
        let isSubmitConfirmed = false;

        function updatePlanMinimumFloors(tier) {
            const defs = PLAN_DEFAULTS[tier] || PLAN_DEFAULTS.solo;
            const machInput  = document.getElementById('edit-client-machines');
            const staffInput = document.getElementById('edit-client-staff');
            const machFloor  = document.getElementById('edit-client-machines-floor');
            const staffFloor = document.getElementById('edit-client-staff-floor');
            const machHelp   = document.getElementById('edit-client-machines-help');
            const staffHelp  = document.getElementById('edit-client-staff-help');

            const activeMach  = (currentActiveClient && currentActiveClient.current_machines) ? parseInt(currentActiveClient.current_machines, 10) : 0;
            const activeStaff = (currentActiveClient && currentActiveClient.current_staff) ? parseInt(currentActiveClient.current_staff, 10) : 0;

            const effectiveMinMach  = Math.max(defs.minMachines, activeMach);
            const effectiveMinStaff = Math.max(defs.minStaff, activeStaff);

            if (machInput) {
                machInput.min = effectiveMinMach;
            }
            if (staffInput) {
                staffInput.min = effectiveMinStaff;
            }

            if (machFloor) {
                machFloor.textContent = 'Min ' + effectiveMinMach;
            }
            if (staffFloor) {
                staffFloor.textContent = 'Min ' + effectiveMinStaff;
            }

            if (machHelp) {
                machHelp.textContent = defs.label + ' plan limit: at least ' + effectiveMinMach + ' machines' + (activeMach > defs.minMachines ? ' (' + activeMach + ' currently used)' : '');
            }
            if (staffHelp) {
                staffHelp.textContent = defs.label + ' plan limit: at least ' + effectiveMinStaff + ' staff account' + (effectiveMinStaff > 1 ? 's' : '') + (activeStaff > defs.minStaff ? ' (' + activeStaff + ' currently used)' : '');
            }
        }

        function enforceLiveFloor(field) {
            const planEl = document.getElementById('edit-client-plan');
            const tier   = planEl ? planEl.value : 'solo';
            const defs   = PLAN_DEFAULTS[tier] || PLAN_DEFAULTS.solo;
            const activeMach  = (currentActiveClient && currentActiveClient.current_machines) ? parseInt(currentActiveClient.current_machines, 10) : 0;
            const activeStaff = (currentActiveClient && currentActiveClient.current_staff) ? parseInt(currentActiveClient.current_staff, 10) : 0;

            const minMach  = Math.max(defs.minMachines, activeMach);
            const minStaff = Math.max(defs.minStaff, activeStaff);

            if (field === 'machines') {
                const el = document.getElementById('edit-client-machines');
                const val = parseInt(el.value, 10);
                if (!isNaN(val) && val < minMach) {
                    el.style.borderColor = '#ef4444';
                    el.style.backgroundColor = '#fef2f2';
                } else {
                    el.style.borderColor = '';
                    el.style.backgroundColor = '';
                }
            } else if (field === 'staff') {
                const el = document.getElementById('edit-client-staff');
                const val = parseInt(el.value, 10);
                if (!isNaN(val) && val < minStaff) {
                    el.style.borderColor = '#ef4444';
                    el.style.backgroundColor = '#fef2f2';
                } else {
                    el.style.borderColor = '';
                    el.style.backgroundColor = '';
                }
            }
        }

        function snapToFloor(field) {
            const planEl = document.getElementById('edit-client-plan');
            const tier   = planEl ? planEl.value : 'solo';
            const defs   = PLAN_DEFAULTS[tier] || PLAN_DEFAULTS.solo;
            const activeMach  = (currentActiveClient && currentActiveClient.current_machines) ? parseInt(currentActiveClient.current_machines, 10) : 0;
            const activeStaff = (currentActiveClient && currentActiveClient.current_staff) ? parseInt(currentActiveClient.current_staff, 10) : 0;

            const minMach  = Math.max(defs.minMachines, activeMach);
            const minStaff = Math.max(defs.minStaff, activeStaff);

            if (field === 'machines') {
                const el = document.getElementById('edit-client-machines');
                const val = parseInt(el.value, 10);
                if (isNaN(val) || val < minMach) {
                    el.value = minMach;
                }
                el.style.borderColor = '';
                el.style.backgroundColor = '';
            } else if (field === 'staff') {
                const el = document.getElementById('edit-client-staff');
                const val = parseInt(el.value, 10);
                if (isNaN(val) || val < minStaff) {
                    el.value = minStaff;
                }
                el.style.borderColor = '';
                el.style.backgroundColor = '';
            }
        }

        function validateClientLimitsForm() {
            const planEl = document.getElementById('edit-client-plan');
            const tier   = planEl ? planEl.value : 'solo';
            const defs   = PLAN_DEFAULTS[tier] || PLAN_DEFAULTS.solo;

            const machInput  = document.getElementById('edit-client-machines');
            const staffInput = document.getElementById('edit-client-staff');

            const activeMach  = (currentActiveClient && currentActiveClient.current_machines) ? parseInt(currentActiveClient.current_machines, 10) : 0;
            const activeStaff = (currentActiveClient && currentActiveClient.current_staff) ? parseInt(currentActiveClient.current_staff, 10) : 0;

            const effectiveMinMach  = Math.max(defs.minMachines, activeMach);
            const effectiveMinStaff = Math.max(defs.minStaff, activeStaff);

            const enteredMach  = parseInt(machInput.value, 10);
            const enteredStaff = parseInt(staffInput.value, 10);

            if (isNaN(enteredMach) || enteredMach < effectiveMinMach) {
                alert('For the ' + defs.label + ' plan, machines cannot be adjusted below the plan limit of ' + effectiveMinMach + '.');
                machInput.value = effectiveMinMach;
                machInput.focus();
                return false;
            }

            if (isNaN(enteredStaff) || enteredStaff < effectiveMinStaff) {
                alert('For the ' + defs.label + ' plan, staff accounts cannot be adjusted below the plan limit of ' + effectiveMinStaff + '.');
                staffInput.value = effectiveMinStaff;
                staffInput.focus();
                return false;
            }

            return true;
        }

        function onPlanSelectionChanged(tier) {
            const defs = PLAN_DEFAULTS[tier] || PLAN_DEFAULTS.solo;
            const entTag = document.getElementById('enterprise-tag-pill');
            const badge  = document.getElementById('edit-client-tier-badge');

            if (tier === 'enterprise') {
                if (entTag) entTag.style.display = 'inline-block';
                if (badge) {
                    badge.textContent = 'ENTERPRISE';
                    badge.className = 'badge-pill badge-audit-purple';
                }
            } else {
                if (entTag) entTag.style.display = 'none';
                if (badge) {
                    badge.textContent = tier.toUpperCase();
                    badge.className = tier === 'max' ? 'badge-pill badge-audit-purple' : (tier === 'business' ? 'badge-pill badge-sale-blue' : 'badge-pill badge-initial-gray');
                }
            }

            updatePlanMinimumFloors(tier);

            const machInput  = document.getElementById('edit-client-machines');
            const staffInput = document.getElementById('edit-client-staff');
            const priceInput = document.getElementById('edit-client-price');

            const activeMach  = (currentActiveClient && currentActiveClient.current_machines) ? parseInt(currentActiveClient.current_machines, 10) : 0;
            const activeStaff = (currentActiveClient && currentActiveClient.current_staff) ? parseInt(currentActiveClient.current_staff, 10) : 0;

            if (machInput)  machInput.value = Math.max(defs.defaultMachines, activeMach);
            if (staffInput) staffInput.value = Math.max(defs.defaultStaff, activeStaff);
            if (priceInput) priceInput.value = defs.price.toFixed(2);
        }

        function openEditClientModal(client) {
            currentActiveClient = client;
            isSubmitConfirmed = false;

            document.getElementById('edit-client-id').value = client.id;
            document.getElementById('edit-client-modal-title').textContent = 'Manage ' + client.name + ' (' + client.business + ')';
            document.getElementById('edit-client-status').value = client.status || 'active';

            const tier = client.plan_tier || 'solo';
            const planEl = document.getElementById('edit-client-plan');
            if (planEl) {
                planEl.value = tier;
            }

            // Usage indicator
            const usageEl = document.getElementById('edit-client-current-usage');
            if (usageEl) {
                usageEl.textContent = (client.current_machines || 0) + ' machines · ' + (client.current_staff || 0) + ' staff accounts';
            }

            // Set floor limits for this plan and active usage
            updatePlanMinimumFloors(tier);

            // Populate machine & staff limits
            const defs = PLAN_DEFAULTS[tier] || PLAN_DEFAULTS.solo;
            const machInput  = document.getElementById('edit-client-machines');
            const staffInput = document.getElementById('edit-client-staff');
            const priceInput = document.getElementById('edit-client-price');

            const activeMach  = client.current_machines ? parseInt(client.current_machines, 10) : 0;
            const activeStaff = client.current_staff ? parseInt(client.current_staff, 10) : 0;

            const minMach  = Math.max(defs.minMachines, activeMach);
            const minStaff = Math.max(defs.minStaff, activeStaff);

            let initialMach = minMach;
            if (machInput) {
                const existingMach = (client.custom_machine_limit !== null && client.custom_machine_limit !== undefined)
                    ? parseInt(client.custom_machine_limit, 10)
                    : (client.machine_limit ? parseInt(client.machine_limit, 10) : defs.defaultMachines);
                initialMach = Math.max(existingMach, minMach);
                machInput.value = initialMach;
            }

            let initialStaff = minStaff;
            if (staffInput) {
                const existingStaff = (client.custom_staff_limit !== null && client.custom_staff_limit !== undefined)
                    ? parseInt(client.custom_staff_limit, 10)
                    : (client.staff_limit ? parseInt(client.staff_limit, 10) : defs.defaultStaff);
                initialStaff = Math.max(existingStaff, minStaff);
                staffInput.value = initialStaff;
            }

            let initialPrice = defs.price;
            if (priceInput) {
                const effectivePrice = (client.custom_price !== null && client.custom_price !== undefined)
                    ? parseFloat(client.custom_price)
                    : (client.plan_price !== undefined ? parseFloat(client.plan_price) : defs.price);
                initialPrice = effectivePrice;
                priceInput.value = Number(effectivePrice).toFixed(2);
            }

            const entTag = document.getElementById('enterprise-tag-pill');
            if (entTag) {
                entTag.style.display = (tier === 'enterprise') ? 'inline-block' : 'none';
            }

            const badge = document.getElementById('edit-client-tier-badge');
            if (badge) {
                badge.textContent = tier.toUpperCase();
                badge.className = (tier === 'enterprise' || tier === 'max') ? 'badge-pill badge-audit-purple' : (tier === 'business' ? 'badge-pill badge-sale-blue' : 'badge-pill badge-initial-gray');
            }

            const suspOpt = document.getElementById('edit-client-status-suspended');
            if (suspOpt) {
                suspOpt.disabled = !!client.is_demo;
            }

            const regenCheckbox = document.querySelector('#edit-client-modal input[name="regenerate_key"]');
            if (regenCheckbox) {
                regenCheckbox.checked = false;
            }

            // Record initial baseline to compute what changed
            currentActiveClient._initial = {
                plan: tier,
                status: client.status || 'active',
                machines: initialMach,
                staff: initialStaff,
                price: initialPrice
            };

            document.getElementById('edit-client-modal').classList.add('open');
        }

        function closeSuperadminModals() {
            document.querySelectorAll('.modal-overlay.open').forEach(function (el) {
                el.classList.remove('open');
            });
            isSubmitConfirmed = false;
        }

        function closeConfirmModal() {
            const modal = document.getElementById('confirm-save-modal');
            if (modal) modal.classList.remove('open');
            isSubmitConfirmed = false;
        }

        function buildChangesDiff() {
            if (!currentActiveClient || !currentActiveClient._initial) {
                return [];
            }

            const initial = currentActiveClient._initial;
            const diffs = [];

            // 1. Plan Tier Change
            const planEl = document.getElementById('edit-client-plan');
            const newPlan = planEl ? planEl.value : initial.plan;
            if (newPlan !== initial.plan) {
                const oldLabel = (PLAN_DEFAULTS[initial.plan] ? PLAN_DEFAULTS[initial.plan].label : initial.plan.toUpperCase());
                const newLabel = (PLAN_DEFAULTS[newPlan] ? PLAN_DEFAULTS[newPlan].label : newPlan.toUpperCase());
                diffs.push({
                    label: 'License Plan',
                    oldVal: oldLabel,
                    newVal: newLabel,
                    color: newPlan === 'enterprise' || newPlan === 'max' ? '#7c3aed' : (newPlan === 'business' ? '#2563eb' : '#475569'),
                    bg: newPlan === 'enterprise' || newPlan === 'max' ? '#f3e8ff' : (newPlan === 'business' ? '#eff6ff' : '#f1f5f9')
                });
            }

            // 2. Monthly Fee Change
            const priceEl = document.getElementById('edit-client-price');
            const newPrice = priceEl ? parseFloat(priceEl.value) : initial.price;
            if (!isNaN(newPrice) && Math.abs(newPrice - initial.price) >= 0.005) {
                diffs.push({
                    label: 'Monthly Fee',
                    oldVal: '$' + initial.price.toFixed(2),
                    newVal: '$' + newPrice.toFixed(2) + '/mo',
                    color: '#0284c7',
                    bg: '#e0f2fe'
                });
            }

            // 3. Machine Limit Change
            const machEl = document.getElementById('edit-client-machines');
            const newMach = machEl ? parseInt(machEl.value, 10) : initial.machines;
            if (!isNaN(newMach) && newMach !== initial.machines) {
                diffs.push({
                    label: 'Machine Limit',
                    oldVal: initial.machines + ' machines',
                    newVal: newMach + ' machines',
                    color: '#059669',
                    bg: '#ecfdf5'
                });
            }

            // 4. Staff Limit Change
            const staffEl = document.getElementById('edit-client-staff');
            const newStaff = staffEl ? parseInt(staffEl.value, 10) : initial.staff;
            if (!isNaN(newStaff) && newStaff !== initial.staff) {
                diffs.push({
                    label: 'Staff Limit',
                    oldVal: initial.staff + ' staff',
                    newVal: newStaff + ' staff',
                    color: '#059669',
                    bg: '#ecfdf5'
                });
            }

            // 5. Workspace Access Status Change
            const statusEl = document.getElementById('edit-client-status');
            const newStatus = statusEl ? statusEl.value : initial.status;
            if (newStatus !== initial.status) {
                const isSuspended = (newStatus === 'suspended');
                diffs.push({
                    label: 'Access Status',
                    oldVal: initial.status.toUpperCase(),
                    newVal: newStatus.toUpperCase(),
                    color: isSuspended ? '#dc2626' : '#15803d',
                    bg: isSuspended ? '#fef2f2' : '#f0fdf4'
                });
            }

            // 6. License Key Rotation
            const regenCheckbox = document.querySelector('#edit-client-modal input[name="regenerate_key"]');
            if (regenCheckbox && regenCheckbox.checked) {
                diffs.push({
                    label: 'License Key',
                    oldVal: 'Retain current key',
                    newVal: 'Reissue & rotate key',
                    color: '#7c3aed',
                    bg: '#f3e8ff'
                });
            } else if (newPlan !== initial.plan) {
                diffs.push({
                    label: 'License Key',
                    oldVal: 'Current key',
                    newVal: 'Auto-rotate for ' + newPlan.toUpperCase(),
                    color: '#7c3aed',
                    bg: '#f3e8ff'
                });
            }

            return diffs;
        }

        function showConfirmSaveModal() {
            const nameEl = document.getElementById('confirm-client-name');
            if (nameEl && currentActiveClient) {
                nameEl.textContent = currentActiveClient.name + (currentActiveClient.business ? ' (' + currentActiveClient.business + ')' : '');
            }

            const container = document.getElementById('confirm-changes-list');
            if (!container) return;

            const diffs = buildChangesDiff();

            if (diffs.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; color: #64748b; font-size: 0.8rem; padding: 0.75rem 0.5rem; background: #ffffff; border: 1px dashed #cbd5e1; border-radius: 6px;">
                        No settings were modified. Workspace parameters will remain unchanged.
                    </div>
                `;
            } else {
                let html = '';
                diffs.forEach(function (d) {
                    html += `
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.45rem 0.65rem; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.8rem;">
                            <div style="font-weight: 700; color: #475569; font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.03em;">
                                ${d.label}
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.35rem; font-family: var(--font-mono, monospace); font-size: 0.8rem;">
                                <span style="color: #64748b; font-size: 0.74rem;">from</span>
                                <span style="color: #94a3b8; text-decoration: line-through;">${d.oldVal}</span>
                                <span style="color: #64748b; font-size: 0.74rem; font-weight: 700;">to</span>
                                <span style="font-weight: 700; color: ${d.color}; background: ${d.bg}; padding: 0.12rem 0.45rem; border-radius: 4px;">${d.newVal}</span>
                            </div>
                        </div>
                    `;
                });
                container.innerHTML = html;
            }

            const modal = document.getElementById('confirm-save-modal');
            if (modal) modal.classList.add('open');
        }

        function handleClientFormSubmit(event) {
            if (isSubmitConfirmed) {
                return true;
            }
            if (event) {
                event.preventDefault();
            }

            // 1. Verify plan minimum floors
            if (!validateClientLimitsForm()) {
                return false;
            }

            // 2. Open confirmation modal showing changes diff
            showConfirmSaveModal();
            return false;
        }

        function executeConfirmedSubmit() {
            isSubmitConfirmed = true;
            const form = document.getElementById('edit-client-form');
            if (form) {
                form.submit();
            }
        }

        function setStatusFilter(status, btnEl) {
            currentStatusFilter = status;
            document.querySelectorAll('.filter-chip').forEach(function (chip) {
                chip.classList.remove('active');
            });
            if (btnEl) {
                btnEl.classList.add('active');
            }
            filterOperatorsTable();
        }

        function filterOperatorsTable() {
            const input = document.getElementById('operator-search-input');
            const query = input ? input.value.toLowerCase().trim() : '';
            const rows = document.querySelectorAll('#operators-directory-table tbody tr');
            rows.forEach(function (row) {
                const rowStatus = row.getAttribute('data-status') || '';
                const matchesStatus = (currentStatusFilter === 'all' || rowStatus === currentStatusFilter);
                const matchesQuery = (query === '' || row.textContent.toLowerCase().indexOf(query) !== -1);
                row.style.display = (matchesStatus && matchesQuery) ? '' : 'none';
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeSuperadminModals();
            }
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                const searchInput = document.getElementById('operator-search-input');
                if (searchInput) {
                    e.preventDefault();
                    searchInput.focus();
                } else {
                    e.preventDefault();
                    window.location.href = 'superadmin.php?tab=operators';
                }
            }
        });
    </script>
</body>
</html>
