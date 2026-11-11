<?php
/**
 * VendFlow — Operator Staff & Route Restocker Management
 * Pure Vanilla PHP + MySQL PDO
 * Allows operators to manage route restock personnel, assign machines, and maintain secure credentials.
 */
require_once __DIR__ . '/includes/functions.php';

$user = require_auth();
if (is_staff($user)) {
    redirect('inventory.php');
}

$db = get_db();
$userId = (int)$user['id'];
$planInfo = get_workspace_plan_info($db, $userId);
$staffLimit = (int)$planInfo['staff_limit'];

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Your session security token expired. Please try again.');
        redirect('staff.php');
    }

    // Guard demo operator account from modifying or creating staff
    if (is_demo_user($user)) {
        set_flash('error', 'Demo limit: Please register an operator account');
        $redirectUrl = !empty($_POST['redirect']) ? $_POST['redirect'] : 'staff.php';
        redirect($redirectUrl);
    }

    $action = $_POST['action'] ?? '';

    // 1. Add Staff Member
    if ($action === 'add_staff') {
        $redirectUrl = !empty($_POST['redirect']) ? $_POST['redirect'] : 'staff.php';

        $cntStmt = $db->prepare("SELECT COUNT(*) FROM staff_accounts WHERE operator_id = :uid");
        $cntStmt->execute([':uid' => $userId]);
        $currentStaffCount = (int)$cntStmt->fetchColumn();

        if ($currentStaffCount >= $staffLimit) {
            set_flash(
                'error',
                "Plan limit reached: Your {$planInfo['label']} license allows up to {$staffLimit} route staff " . ($staffLimit === 1 ? 'account' : 'accounts') . " ({$currentStaffCount}/{$staffLimit} used). Upgrade your tier on License Plans or contact admin for extra limits."
            );
            redirect($redirectUrl);
        }

        $name = trim($_POST['name'] ?? '');
        $username = strtolower(trim($_POST['username'] ?? ''));
        $username = ltrim($username, '@');
        $password = $_POST['password'] ?? '';
        $accessScope = ($_POST['access_scope'] ?? 'all') === 'selected' ? 'selected' : 'all';
        $selectedMachines = isset($_POST['machine_ids']) && is_array($_POST['machine_ids']) ? array_map('intval', $_POST['machine_ids']) : [];

        if (empty($name)) {
            set_flash('error', 'Staff member full name is required.');
        } elseif (empty($username)) {
            set_flash('error', 'A unique username is required for the staff account.');
        } elseif (!preg_match('/^[a-z0-9_\-\.]{3,30}$/', $username)) {
            set_flash('error', 'Username must be 3 to 30 characters and contain only lowercase letters, numbers, hyphens, periods, or underscores.');
        } elseif (is_staff_username_taken($db, $username)) {
            set_flash('error', "The username '@{$username}' is already taken. Please choose another username.");
        } elseif (strlen($password) < 6) {
            set_flash('error', 'Initial password must be at least 6 characters.');
        } else {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $db->prepare("
                INSERT INTO staff_accounts (operator_id, name, username, password_hash, access_scope, status)
                VALUES (:oid, :name, :u, :hash, :scope, 'active')
            ");
            $stmt->execute([
                ':oid'   => $userId,
                ':name'  => $name,
                ':u'     => $username,
                ':hash'  => $passwordHash,
                ':scope' => $accessScope
            ]);
            $newStaffId = (int)$db->lastInsertId();

            if ($accessScope === 'selected') {
                sync_staff_machine_access($db, $newStaffId, $userId, $accessScope, $selectedMachines);
            }

            log_platform_event(
                $db,
                $userId,
                $user['name'],
                $user['role'] ?? 'operator',
                'staff_created',
                "Created route staff account for '{$name}' (@{$username}) with scope: {$accessScope}.",
                $userId
            );

            set_flash('success', "Staff account created for {$name} (@{$username}). They can now sign in with their username and password.");
        }

        redirect($redirectUrl);
    }

    // 2. Edit Machine Access Scope
    if ($action === 'edit_access') {
        $staffId = (int)($_POST['staff_id'] ?? 0);
        $accessScope = ($_POST['access_scope'] ?? 'all') === 'selected' ? 'selected' : 'all';
        $selectedMachines = isset($_POST['machine_ids']) && is_array($_POST['machine_ids']) ? array_map('intval', $_POST['machine_ids']) : [];

        $chk = $db->prepare("SELECT id, name FROM staff_accounts WHERE id = :sid AND operator_id = :oid");
        $chk->execute([':sid' => $staffId, ':oid' => $userId]);
        $staff = $chk->fetch();

        if ($staff) {
            sync_staff_machine_access($db, $staffId, $userId, $accessScope, $selectedMachines);
            set_flash('success', "Machine access scope updated for {$staff['name']}.");
        }
        redirect('staff.php');
    }

    // 3. Reset Staff Password
    if ($action === 'reset_password') {
        $staffId = (int)($_POST['staff_id'] ?? 0);
        $newPass = $_POST['new_password'] ?? '';

        if (strlen($newPass) < 6) {
            set_flash('error', 'New password must be at least 6 characters.');
        } else {
            $chk = $db->prepare("SELECT id, name, username FROM staff_accounts WHERE id = :sid AND operator_id = :oid");
            $chk->execute([':sid' => $staffId, ':oid' => $userId]);
            $staff = $chk->fetch();

            if ($staff) {
                $hash = password_hash($newPass, PASSWORD_BCRYPT);
                $up = $db->prepare("UPDATE staff_accounts SET password_hash = :hash WHERE id = :sid");
                $up->execute([':hash' => $hash, ':sid' => $staffId]);
                set_flash('success', "Password successfully updated for {$staff['name']} (@{$staff['username']}).");
            }
        }
        redirect('staff.php');
    }

    // 4. Toggle Status (Active / Suspended)
    if ($action === 'toggle_status') {
        $staffId = (int)($_POST['staff_id'] ?? 0);
        $newStatus = ($_POST['new_status'] ?? '') === 'suspended' ? 'suspended' : 'active';

        $chk = $db->prepare("SELECT id, name FROM staff_accounts WHERE id = :sid AND operator_id = :oid");
        $chk->execute([':sid' => $staffId, ':oid' => $userId]);
        $staff = $chk->fetch();

        if ($staff) {
            $up = $db->prepare("UPDATE staff_accounts SET status = :st WHERE id = :sid");
            $up->execute([':st' => $newStatus, ':sid' => $staffId]);
            $statusLabel = $newStatus === 'active' ? 'reactivated' : 'suspended';
            set_flash('success', "Staff account for {$staff['name']} has been {$statusLabel}.");
        }
        redirect('staff.php');
    }

    // 5. Delete Staff Member
    if ($action === 'delete_staff') {
        $staffId = (int)($_POST['staff_id'] ?? 0);

        $chk = $db->prepare("SELECT id, name, username FROM staff_accounts WHERE id = :sid AND operator_id = :oid");
        $chk->execute([':sid' => $staffId, ':oid' => $userId]);
        $staff = $chk->fetch();

        if ($staff) {
            $delSm = $db->prepare("DELETE FROM staff_machines WHERE staff_id = :sid");
            $delSm->execute([':sid' => $staffId]);

            $del = $db->prepare("DELETE FROM staff_accounts WHERE id = :sid AND operator_id = :oid");
            $del->execute([':sid' => $staffId, ':oid' => $userId]);
            set_flash('success', "Staff account for {$staff['name']} (@{$staff['username']}) was permanently deleted.");
        }
        redirect('staff.php');
    }
}

// Fetch all machines owned by this operator
$mStmt = $db->prepare("SELECT id, name, location, identifier FROM machines WHERE user_id = :uid ORDER BY name ASC");
$mStmt->execute([':uid' => $userId]);
$fleetMachines = $mStmt->fetchAll();

// Fetch all staff members belonging to this operator
$sStmt = $db->prepare("
    SELECT * FROM staff_accounts 
    WHERE operator_id = :uid 
    ORDER BY created_at DESC
");
$sStmt->execute([':uid' => $userId]);
$staffMembers = $sStmt->fetchAll();

// Map assigned machines for each staff member
$staffMachinesMap = [];
if (!empty($staffMembers)) {
    $smStmt = $db->prepare("
        SELECT sm.staff_id, sm.machine_id, m.name AS machine_name
        FROM staff_machines sm
        JOIN machines m ON sm.machine_id = m.id
        WHERE m.user_id = :uid
    ");
    $smStmt->execute([':uid' => $userId]);
    while ($row = $smStmt->fetch()) {
        $staffMachinesMap[$row['staff_id']][] = [
            'id' => (int)$row['machine_id'],
            'name' => $row['machine_name']
        ];
    }
}

// Summary metrics
$totalStaff = count($staffMembers);
$activeStaff = 0;
$suspendedStaff = 0;
foreach ($staffMembers as $s) {
    if ($s['status'] === 'active') {
        $activeStaff++;
    } else {
        $suspendedStaff++;
    }
}

$pageTitle    = 'Staff & Team';
$pageHeader   = 'Staff & team (' . $totalStaff . ' / ' . $staffLimit . ')';
$pageSubtitle = "Create field staff accounts, assign machines, and set route restocker credentials ({$planInfo['label']} plan: up to {$staffLimit} staff).";

require_once __DIR__ . '/includes/dashboard_header.php';
?>

<?php if ($totalStaff >= $staffLimit): ?>
    <div class="dash-flash dash-flash-warning" style="margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
        <div style="display: flex; align-items: center; gap: 0.6rem;">
            <span style="width:16px;height:16px;display:inline-flex;color:#d97706;flex-shrink:0;"><?= posthog_icon('warning') ?></span>
            <span><strong><?= e($planInfo['label']) ?> plan staff limit reached (<?= $totalStaff ?> / <?= $staffLimit ?>):</strong> Upgrade your license plan or contact admin to add more route staff accounts.</span>
        </div>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <a href="pricing.php" class="btn-dash-primary" style="font-size: 0.78rem; padding: 0.35rem 0.85rem; text-decoration: none;">Upgrade Plan &rarr;</a>
            <a href="contact.php?inquiry=enterprise" class="btn-dash-outline" style="font-size: 0.78rem; padding: 0.35rem 0.85rem; text-decoration: none;">Contact Admin</a>
        </div>
    </div>
<?php endif; ?>

<!-- KPI Stat Summary Cards -->
<section class="stat-strip" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="stat-card">
        <span class="stat-label">Staff quota used</span>
        <div class="stat-value" style="color: var(--text-main); font-size: 1.8rem; font-weight: 800;"><?= $totalStaff ?> / <?= $staffLimit ?></div>
        <span class="stat-sub"><?= e($planInfo['label']) ?> plan allowance</span>
    </div>

    <div class="stat-card">
        <span class="stat-label">Active staff</span>
        <div class="stat-value" style="color: #16a34a; font-size: 1.8rem; font-weight: 800;"><?= $activeStaff ?></div>
        <span class="stat-sub">Authorized for restock adjustments</span>
    </div>

    <div class="stat-card" style="<?= $suspendedStaff > 0 ? 'border-color: #fca5a5; background: #fff5f5;' : '' ?>">
        <span class="stat-label" style="<?= $suspendedStaff > 0 ? 'color: #dc2626; font-weight: 700;' : '' ?>">Suspended accounts</span>
        <div class="stat-value" style="color: <?= $suspendedStaff > 0 ? '#dc2626' : 'var(--text-main)' ?>; font-size: 1.8rem; font-weight: 800;"><?= $suspendedStaff ?></div>
        <span class="stat-sub"><?= $suspendedStaff > 0 ? 'Login currently locked' : 'Zero accounts suspended' ?></span>
    </div>

    <div class="stat-card">
        <span class="stat-label">Fleet machines</span>
        <div class="stat-value" style="color: var(--text-main); font-size: 1.8rem; font-weight: 800;"><?= count($fleetMachines) ?> / <?= (int)$planInfo['machine_limit'] ?></div>
        <span class="stat-sub">Available for route assignments</span>
    </div>
</section>

<!-- Action Header Strip -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem;">
    <div>
        <h3 style="margin: 0; font-size: 1.05rem; font-weight: 800; color: var(--text-main);">Team Member Directory</h3>
        <span style="font-size: 0.8rem; color: var(--text-muted);">Staff sign in with their assigned username and password to log restocks and audit physical counts.</span>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <a href="staff-dashboard.php" class="btn-dash-outline" style="text-decoration: none; font-size: 0.82rem; padding: 0.45rem 0.85rem;" target="_blank">
            Preview restocker view ↗
        </a>
        <button type="button" onclick="openAddStaffModal()" class="btn-dash-primary" style="font-size: 0.82rem; padding: 0.45rem 0.95rem;">
            + Add staff member
        </button>
    </div>
</div>

<!-- PostHog Staff Roster Table Card -->
<div class="posthog-table-card">
    <div class="posthog-table-responsive">
        <table class="posthog-data-table">
            <thead>
                <tr>
                    <th style="min-width: 180px;">Team member</th>
                    <th style="min-width: 140px;">Sign-in Username</th>
                    <th style="min-width: 220px;">Assigned machines</th>
                    <th style="min-width: 100px;">Status</th>
                    <th style="min-width: 140px;">Last login</th>
                    <th style="min-width: 200px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($staffMembers)): ?>
                    <tr>
                        <td colspan="6" style="padding: 3.5rem 1.5rem; text-align: center; color: var(--text-muted);">
                            <div style="width:40px;height:40px;margin:0 auto 0.5rem;color:#94a3b8;"><?= posthog_icon('people') ?></div>
                            <div style="font-weight: 800; color: var(--text-main); font-size: 1rem; margin-bottom: 0.35rem;">
                                No staff accounts created yet
                            </div>
                            <div style="font-size: 0.85rem; max-width: 440px; margin: 0 auto 1.25rem; line-height: 1.45;">
                                Create username and password credentials for your route restockers so they can service vending machines and adjust inventory without viewing sensitive operator financials.
                            </div>
                            <button type="button" onclick="openAddStaffModal()" class="btn-dash-primary" style="font-size: 0.85rem; padding: 0.55rem 1.15rem;">
                                + Create first staff account
                            </button>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($staffMembers as $s):
                        $assignedList = $staffMachinesMap[$s['id']] ?? [];
                        $isAll = ($s['access_scope'] ?? 'all') === 'all';
                        $isActive = ($s['status'] ?? 'active') === 'active';
                        $lastSeen = !empty($s['last_login_at']) ? date('M j, Y H:i', strtotime($s['last_login_at'])) : 'Never logged in';
                        $initial = strtoupper(substr($s['name'], 0, 1));
                    ?>
                        <tr>
                            <!-- Team member & Avatar -->
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.65rem;">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: #e0e7ff; color: #4338ca; font-weight: 800; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; flex-shrink: 0;">
                                        <?= e($initial) ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 700; color: var(--text-main);"><?= e($s['name']) ?></div>
                                        <div style="font-size: 0.72rem; color: var(--text-muted);">Restocker</div>
                                    </div>
                                </div>
                            </td>

                            <!-- Sign-in Username -->
                            <td>
                                <code style="background: #f1f5f9; color: #0f172a; padding: 0.2rem 0.45rem; border-radius: 4px; font-size: 0.82rem; font-weight: 700; border: 1px solid #e2e8f0;">
                                    @<?= e($s['username']) ?>
                                </code>
                            </td>

                            <!-- Assigned machines -->
                            <td>
                                <?php if ($isAll): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 0.3rem; background: #ecfdf5; color: #065f46; font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 999px; border: 1px solid #a7f3d0;">
                                        <span style="width:12px;height:12px;display:inline-flex;"><?= posthog_icon('check') ?></span> All fleet machines (<?= count($fleetMachines) ?>)
                                    </span>
                                <?php elseif (!empty($assignedList)): ?>
                                    <div style="display: flex; flex-wrap: wrap; gap: 0.35rem;">
                                        <?php foreach ($assignedList as $am): ?>
                                            <span style="background: #f3f4f6; color: #1f2937; font-size: 0.72rem; font-weight: 600; padding: 0.15rem 0.45rem; border-radius: 4px; border: 1px solid #e5e7eb;">
                                                <span style="width:12px;height:12px;display:inline-flex;vertical-align:-1px;margin-right:3px;color:#64748b;"><?= posthog_icon('building') ?></span><?= e($am['name']) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #ef4444; font-size: 0.75rem; font-weight: 700;"><span style="width:12px;height:12px;display:inline-flex;vertical-align:-1px;margin-right:3px;"><?= posthog_icon('warning') ?></span> No machines assigned</span>
                                <?php endif; ?>
                            </td>

                            <!-- Status -->
                            <td>
                                <?php if ($isActive): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; background: #ecfdf5; color: #047857; font-size: 0.72rem; font-weight: 800; padding: 0.18rem 0.5rem; border-radius: 999px;">
                                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span>
                                        Active
                                    </span>
                                <?php else: ?>
                                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; background: #fef2f2; color: #b91c1c; font-size: 0.72rem; font-weight: 800; padding: 0.18rem 0.5rem; border-radius: 999px;">
                                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #ef4444;"></span>
                                        Suspended
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Last login -->
                            <td style="font-size: 0.8rem; color: var(--text-muted);">
                                <?= e($lastSeen) ?>
                            </td>

                            <!-- Actions -->
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 0.35rem; align-items: center; justify-content: flex-end;">
                                    <button type="button" 
                                            class="btn-dash-outline" 
                                            style="padding: 0.28rem 0.55rem; font-size: 0.75rem;" 
                                            onclick='openEditAccessModal(<?= json_encode($s) ?>, <?= json_encode(array_column($assignedList, "id")) ?>)'>
                                        Machines
                                    </button>

                                    <button type="button" 
                                            class="btn-dash-outline" 
                                            style="padding: 0.28rem 0.55rem; font-size: 0.75rem;" 
                                            onclick='openResetPasswordModal(<?= (int)$s["id"] ?>, <?= json_encode($s["name"]) ?>, <?= json_encode($s["username"]) ?>)'>
                                        Password
                                    </button>

                                    <form method="POST" action="staff.php" style="display: inline; margin: 0;">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="staff_id" value="<?= (int)$s['id'] ?>">
                                        <input type="hidden" name="new_status" value="<?= $isActive ? 'suspended' : 'active' ?>">
                                        <button type="submit" 
                                                class="btn-dash-outline" 
                                                style="padding: 0.28rem 0.55rem; font-size: 0.75rem; color: <?= $isActive ? '#b91c1c' : '#047857' ?>;" 
                                                title="<?= $isActive ? 'Suspend access' : 'Reactivate access' ?>"
                                                onclick="return confirm('<?= $isActive ? 'Suspend login access for this staff member?' : 'Reactivate login access for this staff member?' ?>');">
                                            <?= $isActive ? 'Suspend' : 'Reactivate' ?>
                                        </button>
                                    </form>

                                    <form method="POST" action="staff.php" style="display: inline; margin: 0;">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="delete_staff">
                                        <input type="hidden" name="staff_id" value="<?= (int)$s['id'] ?>">
                                        <button type="submit" 
                                                class="btn-dash-outline" 
                                                style="padding: 0.28rem 0.45rem; font-size: 0.75rem; color: #dc2626; border-color: #fee2e2;" 
                                                title="Delete staff account"
                                                onclick="return confirm('Permanently delete staff account for <?= addslashes($s['name']) ?> (@<?= addslashes($s['username']) ?>)? This cannot be undone.');">
                                            <span style="width:12px;height:12px;display:inline-flex;"><?= posthog_icon('trash') ?></span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- =========================================================================
     Modal: Edit Machine Access Scope
     ========================================================================= -->
<div class="modal-overlay" id="edit-access-modal" onclick="handleModalOverlayClick(event, 'edit-access-modal')">
    <div class="modal-container" style="max-width: 460px;">
        <div class="modal-header">
            <div class="modal-title" id="edit-access-title">Edit Machine Permissions</div>
            <button type="button" class="modal-close-btn" onclick="closeModalById('edit-access-modal')" style="display:inline-flex;align-items:center;justify-content:center;"><?= posthog_icon('x', '', 'width:12px;height:12px;') ?></button>
        </div>
        <form method="POST" action="staff.php">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="edit_access">
            <input type="hidden" name="staff_id" id="edit-access-staff-id" value="">

            <div class="modal-body">
                <p id="edit-access-desc" style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;"></p>

                <div class="form-group-item">
                    <label class="form-label">Access Scope</label>
                    <div style="display: flex; flex-direction: column; gap: 8px; font-size: 0.85rem;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="access_scope" value="all" id="scope-all" onchange="toggleEditScope(false)">
                            <span style="font-weight: 600;">All machines in fleet (recommended)</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="access_scope" value="selected" id="scope-selected" onchange="toggleEditScope(true)">
                            <span style="font-weight: 600;">Specific machines only</span>
                        </label>
                    </div>

                    <div id="edit-machines-list" style="margin-top: 10px; max-height: 160px; overflow-y: auto; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px;">
                        <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 6px;">Select authorized machines:</span>
                        <?php foreach ($fleetMachines as $m): ?>
                            <label style="display: flex; align-items: center; gap: 8px; font-size: 0.82rem; padding: 4px 0; cursor: pointer;">
                                <input type="checkbox" name="machine_ids[]" value="<?= (int)$m['id'] ?>" class="edit-mach-checkbox" id="edit-mach-<?= (int)$m['id'] ?>">
                                <span><strong><?= e($m['name']) ?></strong> <span style="color: var(--text-muted);">(<?= e($m['location']) ?>)</span></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" onclick="closeModalById('edit-access-modal')">Cancel</button>
                <button type="submit" class="btn-modal-save">Save Machine Access</button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     Modal: Reset Password
     ========================================================================= -->
<div class="modal-overlay" id="reset-password-modal" onclick="handleModalOverlayClick(event, 'reset-password-modal')">
    <div class="modal-container" style="max-width: 420px;">
        <div class="modal-header">
            <div class="modal-title">Reset Staff Password</div>
            <button type="button" class="modal-close-btn" onclick="closeModalById('reset-password-modal')" style="display:inline-flex;align-items:center;justify-content:center;"><?= posthog_icon('x', '', 'width:12px;height:12px;') ?></button>
        </div>
        <form method="POST" action="staff.php">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="staff_id" id="reset-pass-staff-id" value="">

            <div class="modal-body">
                <p id="reset-pass-desc" style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;"></p>

                <div class="form-group-item">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <label class="form-label" for="reset-pass-input" style="margin-bottom: 0;">New Password *</label>
                        <button type="button" onclick="generateResetStaffPassword()" style="font-size: 0.75rem; color: #2563eb; background: none; border: none; font-weight: 700; cursor: pointer;">Generate random</button>
                    </div>
                    <input type="text" name="new_password" id="reset-pass-input" required minlength="6" placeholder="At least 6 characters" class="form-input-text" style="font-family: monospace;">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" onclick="closeModalById('reset-password-modal')">Cancel</button>
                <button type="submit" class="btn-modal-save">Update Password</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditAccessModal(staff, assignedIds) {
    document.getElementById('edit-access-staff-id').value = staff.id;
    document.getElementById('edit-access-title').textContent = 'Machine Permissions — ' + staff.name;
    document.getElementById('edit-access-desc').textContent = 'Configure which machines @' + staff.username + ' can service and restock.';
    
    const isAll = (staff.access_scope === 'all');
    document.getElementById('scope-all').checked = isAll;
    document.getElementById('scope-selected').checked = !isAll;
    
    document.querySelectorAll('.edit-mach-checkbox').forEach(cb => {
        cb.checked = assignedIds.includes(parseInt(cb.value));
    });
    
    toggleEditScope(!isAll);
    openModalById('edit-access-modal');
}

function toggleEditScope(showList) {
    const list = document.getElementById('edit-machines-list');
    if (list) {
        list.style.display = showList ? 'block' : 'none';
    }
}

function openResetPasswordModal(staffId, staffName, username) {
    document.getElementById('reset-pass-staff-id').value = staffId;
    document.getElementById('reset-pass-desc').textContent = 'Set a new password for ' + staffName + ' (@' + username + '). Provide this password to the staff member so they can log in.';
    document.getElementById('reset-pass-input').value = '';
    openModalById('reset-password-modal');
}

function generateResetStaffPassword() {
    const chars = 'abcdefghjkmnpqrstuvwxyz23456789!@#$%';
    let pass = '';
    for (let i = 0; i < 10; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('reset-pass-input').value = pass;
}
</script>

<div>
    <a href="dashboard.php" class="back-landing-link">&larr; Back to Inventory Overview</a>
</div>

<?php require_once __DIR__ . '/includes/dashboard_footer.php'; ?>
