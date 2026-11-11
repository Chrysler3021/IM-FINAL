<?php
/**
 * VendFlow - Vending Machine Fleet Registry & CRUD
 * Pure Vanilla PHP + MySQL PDO
 */
require_once __DIR__ . '/includes/functions.php';

$user = require_auth();
$db = get_db();
$userId = get_workspace_owner_id($user);
$currency = $user['currency'] ?: '$';
$planInfo = get_workspace_plan_info($db, $userId);

$allowedReturnPages = ['dashboard.php', 'inventory.php', 'machines.php', 'low-stock.php', 'audit-log.php', 'settings.php'];
function resolve_machine_return(array $allowed): string {
    $ret = basename($_POST['return_to'] ?? 'machines.php');
    return in_array($ret, $allowed, true) ? $ret : 'machines.php';
}

// -----------------------------------------------------------------------------
// Handle POST Mutations (Add Machine, Edit Machine, Delete Machine)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $returnPage = resolve_machine_return($allowedReturnPages);

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Your session security token expired. Please submit the form again.');
        redirect($returnPage);
    }

    $action = $_POST['action'] ?? '';

    // Enforce permission check for modifying fleet machines
    if (in_array($action, ['add_machine', 'edit_machine'], true) || ($action === 'delete_item' && ($_POST['type'] ?? '') === 'machine')) {
        if (!can_manage_machines($user)) {
            set_flash('error', 'You do not have permission to manage vending machines. Please contact your operator.');
            redirect($returnPage);
        }
    }

    if ($action === 'add_machine') {
        if (is_demo_user($user)) {
            set_flash('error', 'Demo limit: Please register an operator account');
            redirect($returnPage);
        }

        $cntStmt = $db->prepare("SELECT COUNT(*) FROM machines WHERE user_id = :uid");
        $cntStmt->execute([':uid' => $userId]);
        $currentMachineCount = (int)$cntStmt->fetchColumn();
        $machineLimit = (int)$planInfo['machine_limit'];

        if ($currentMachineCount >= $machineLimit) {
            set_flash(
                'error',
                "Plan limit reached: Your {$planInfo['label']} license allows up to {$machineLimit} vending machines ({$currentMachineCount}/{$machineLimit} used). Upgrade your tier on License Plans or contact admin for extra limits."
            );
            redirect($returnPage);
        }

        $name       = trim($_POST['name'] ?? '');
        $identifier = trim($_POST['identifier'] ?? '');
        $location   = trim($_POST['location'] ?? '');
        $notes      = trim($_POST['notes'] ?? '');
        $status     = in_array($_POST['status'] ?? '', ['active', 'maintenance', 'inactive'], true) ? $_POST['status'] : 'active';

        if ($name === '' || $location === '') {
            set_flash('error', 'Enter both a machine name and placement location.');
        } else {
            $autoCode = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 6) ?: 'VM') . '-' . rand(10, 99);
            $stmt = $db->prepare("
                INSERT INTO machines (user_id, name, identifier, location, notes, status)
                VALUES (:uid, :name, :identifier, :location, :notes, :status)
            ");
            $stmt->execute([
                ':uid'        => $userId,
                ':name'       => $name,
                ':identifier' => $identifier !== '' ? strtoupper($identifier) : $autoCode,
                ':location'   => $location,
                ':notes'      => $notes,
                ':status'     => $status
            ]);
            set_flash('success', "Registered vending machine '{$name}'.");
        }
        redirect($returnPage);
    }

    if ($action === 'edit_machine') {
        if (is_demo_user($user)) {
            set_flash('error', 'Demo limit: Please register an operator account');
            redirect('machines.php');
        }
        $machineId  = (int)($_POST['machine_id'] ?? 0);
        $name       = trim($_POST['name'] ?? '');
        $identifier = trim($_POST['identifier'] ?? '');
        $location   = trim($_POST['location'] ?? '');
        $notes      = trim($_POST['notes'] ?? '');
        $status     = in_array($_POST['status'] ?? '', ['active', 'maintenance', 'inactive'], true) ? $_POST['status'] : 'active';

        if ($name === '' || $location === '') {
            set_flash('error', 'Machine name and placement location cannot be empty.');
        } else {
            $stmt = $db->prepare("
                UPDATE machines
                SET name = :name,
                    identifier = :identifier,
                    location = :location,
                    notes = :notes,
                    status = :status
                WHERE id = :id AND user_id = :uid
            ");
            $stmt->execute([
                ':name'       => $name,
                ':identifier' => strtoupper($identifier),
                ':location'   => $location,
                ':notes'      => $notes,
                ':status'     => $status,
                ':id'         => $machineId,
                ':uid'        => $userId
            ]);
            set_flash('success', "Saved changes to '{$name}'.");
        }
        redirect('machines.php');
    }

    if ($action === 'delete_item' && ($_POST['type'] ?? '') === 'machine') {
        if (is_demo_user($user)) {
            set_flash('error', 'Demo limit: Please register an operator account');
            redirect('machines.php');
        }
        $machineId = (int)($_POST['id'] ?? 0);
        $del = $db->prepare("DELETE FROM machines WHERE id = :id AND user_id = :uid");
        $del->execute([':id' => $machineId, ':uid' => $userId]);

        set_flash('success', 'Vending machine record and assigned SKU entries deleted.');
        redirect('machines.php');
    }
}

// -----------------------------------------------------------------------------
// Query Registered Vending Machines with Aggregated SKU Metrics
// -----------------------------------------------------------------------------
$isStaffUser = is_staff($user);
$staffAccount = $isStaffUser ? current_staff() : null;
$allowedMachineIds = ($isStaffUser && $staffAccount) ? get_staff_accessible_machine_ids($db, $staffAccount) : null;

if ($allowedMachineIds !== null) {
    if (empty($allowedMachineIds)) {
        $machines = [];
    } else {
        $inClause = implode(',', array_map('intval', $allowedMachineIds));
        $stmt = $db->query("
            SELECT
                m.*,
                COUNT(p.id) AS product_count,
                COALESCE(SUM(p.quantity), 0) AS total_units,
                COALESCE(SUM(p.quantity * p.sell_price), 0) AS total_value,
                COALESCE(SUM(CASE WHEN p.quantity <= p.min_threshold THEN 1 ELSE 0 END), 0) AS low_count
            FROM machines m
            LEFT JOIN products p ON m.id = p.machine_id
            WHERE m.user_id = {$userId} AND m.id IN ($inClause)
            GROUP BY m.id
            ORDER BY m.created_at ASC, m.id ASC
        ");
        $machines = $stmt->fetchAll();
    }
} else {
    $stmt = $db->prepare("
        SELECT
            m.*,
            COUNT(p.id) AS product_count,
            COALESCE(SUM(p.quantity), 0) AS total_units,
            COALESCE(SUM(p.quantity * p.sell_price), 0) AS total_value,
            COALESCE(SUM(CASE WHEN p.quantity <= p.min_threshold THEN 1 ELSE 0 END), 0) AS low_count
        FROM machines m
        LEFT JOIN products p ON m.id = p.machine_id
        WHERE m.user_id = :uid
        GROUP BY m.id
        ORDER BY m.created_at ASC, m.id ASC
    ");
    $stmt->execute([':uid' => $userId]);
    $machines = $stmt->fetchAll();
}

$pageTitle    = 'Machines';
$pageHeader   = $isStaffUser
    ? 'Assigned machines (' . count($machines) . ')'
    : 'Machines (' . count($machines) . ' / ' . (int)$planInfo['machine_limit'] . ')';
$pageSubtitle = $isStaffUser
    ? 'Vending cabinets assigned to your route restocking roster.'
    : "Digital cabinet placement registry ({$planInfo['label']} plan: up to {$planInfo['machine_limit']} machines).";

require_once __DIR__ . '/includes/dashboard_header.php';
?>

<?php if (!$isStaffUser && count($machines) >= (int)$planInfo['machine_limit']): ?>
    <div class="dash-flash dash-flash-warning" style="margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
        <div style="display: flex; align-items: center; gap: 0.6rem;">
            <span style="width:16px;height:16px;display:inline-flex;color:#d97706;flex-shrink:0;"><?= posthog_icon('warning') ?></span>
            <span><strong><?= e($planInfo['label']) ?> plan machine limit reached (<?= count($machines) ?> / <?= (int)$planInfo['machine_limit'] ?>):</strong> Upgrade your license plan or contact admin to register additional vending machines.</span>
        </div>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <a href="pricing.php" class="btn-dash-primary" style="font-size: 0.78rem; padding: 0.35rem 0.85rem; text-decoration: none;">Upgrade Plan &rarr;</a>
            <a href="contact.php?inquiry=enterprise" class="btn-dash-outline" style="font-size: 0.78rem; padding: 0.35rem 0.85rem; text-decoration: none;">Contact Admin</a>
        </div>
    </div>
<?php endif; ?>

<?php if (empty($machines)): ?>

    <div class="empty-state-box">
        <div class="empty-icon-wrap" style="width:36px;height:36px;margin:0 auto;color:#94a3b8;"><?= posthog_icon('building') ?></div>
        <h3 class="empty-title">No vending machines registered yet</h3>
        <p class="empty-desc">
            Register your first physical cabinet location (for example, an office breakroom or student lobby) before cataloging snacks and drinks.
        </p>
        <?php if (can_manage_machines($user)): ?>
        <button type="button" class="btn-dash-primary" style="padding: 0.65rem 1.4rem; font-size: 0.9rem;" onclick="openAddMachineModal()">
            + Register vending machine
        </button>
        <?php endif; ?>
    </div>

<?php else: ?>

    <div class="machine-registry-list">
        <?php foreach ($machines as $m):
            $skuCount   = (int)$m['product_count'];
            $totalUnits = (int)$m['total_units'];
            $totalValue = (float)$m['total_value'];
            $lowCount   = (int)$m['low_count'];

            $jsMachinePayload = htmlspecialchars(json_encode([
                'id'         => (int)$m['id'],
                'name'       => $m['name'],
                'identifier' => $m['identifier'] ?? '',
                'location'   => $m['location'],
                'status'     => $m['status'],
                'notes'      => $m['notes'] ?? ''
            ]), ENT_QUOTES, 'UTF-8');
        ?>
            <article class="machine-registry-card">

                <!-- Header -->
                <div class="machine-reg-header">
                    <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
                        <span class="machine-code-tag"><?= e($m['identifier'] ?: ('VM-' . $m['id'])) ?></span>
                        <div>
                            <div style="font-size: 1.05rem; font-weight: 800; color: var(--text-main);">
                                <?= e($m['name']) ?>
                            </div>
                            <div style="font-size: 0.78rem; color: var(--text-muted);">
                                <span style="width:12px;height:12px;display:inline-flex;color:#64748b;vertical-align:-1px;margin-right:2px;"><?= posthog_icon('map_pin') ?></span><?= e($m['location']) ?>
                            </div>
                        </div>
                    </div>

                    <div>
                        <?php if ($m['status'] === 'active'): ?>
                            <span class="badge-active-green">Active</span>
                        <?php elseif ($m['status'] === 'maintenance'): ?>
                            <span class="badge-below-par">Maintenance</span>
                        <?php else: ?>
                            <span class="badge-pill badge-initial-gray">Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 4 Metrics Strip -->
                <div class="machine-reg-metrics">
                    <div class="machine-metric-cell">
                        <div class="machine-metric-label">Cataloged SKUs</div>
                        <div class="machine-metric-val"><?= $skuCount ?></div>
                    </div>
                    <div class="machine-metric-cell">
                        <div class="machine-metric-label">Stocked units</div>
                        <div class="machine-metric-val"><?= $totalUnits ?></div>
                    </div>
                    <div class="machine-metric-cell">
                        <div class="machine-metric-label">Retail stock value</div>
                        <div class="machine-metric-val"><?= format_currency($totalValue, $currency) ?></div>
                    </div>
                    <div class="machine-metric-cell" style="<?= $lowCount > 0 ? 'background: #fffbeb;' : '' ?>">
                        <div class="machine-metric-label">Par status</div>
                        <div class="machine-metric-val" style="font-size: 0.95rem; margin-top: 0.45rem; color: <?= $lowCount > 0 ? '#b45309' : '#15803d' ?>;">
                            <?php if ($skuCount === 0): ?>
                                <span style="color: var(--text-muted); font-weight: 600;">No SKUs yet</span>
                            <?php elseif ($lowCount > 0): ?>
                                <?= $lowCount ?> below par
                            <?php else: ?>
                                All slots at par
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Location Notes & Actions -->
                <div class="machine-reg-footer">
                    <div>
                        <?php if (!empty($m['notes'])): ?>
                            <strong style="color: var(--text-main);">Note:</strong> <?= e($m['notes']) ?>
                        <?php else: ?>
                            <span>No notes recorded.</span>
                        <?php endif; ?>
                    </div>

                    <div class="machine-reg-actions">
                        <a href="inventory.php?machine=<?= (int)$m['id'] ?>" class="btn-dash-outline" style="text-decoration: none; font-size: 0.78rem; padding: 0.38rem 0.75rem;">
                            View stock (<?= $skuCount ?>)
                        </a>
                        <button type="button" class="btn-log-restock" onclick="openAddProductModal(<?= (int)$m['id'] ?>)">
                            + Add SKU
                        </button>
                        <?php if (can_manage_machines($user)): ?>
                        <button type="button" class="btn-card-adjust" style="padding: 0.38rem 0.7rem; font-size: 0.78rem;" onclick="openEditMachineModal(<?= $jsMachinePayload ?>)">
                            Edit
                        </button>
                        <button type="button" class="btn-danger-outline" onclick="openDeleteModal('machine', <?= (int)$m['id'] ?>, <?= htmlspecialchars(json_encode($m['name']), ENT_QUOTES, 'UTF-8') ?>)">
                            Delete
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

            </article>
        <?php endforeach; ?>
    </div>

<?php endif; ?>

<div>
    <a href="dashboard.php" class="back-landing-link">&larr; Back to Inventory Overview</a>
</div>

<?php require_once __DIR__ . '/includes/dashboard_footer.php'; ?>
