<?php
/**
 * VendFlow - Account, Unlimited License, Billing Receipts & Workspace Settings
 * Pure Vanilla PHP + MySQL PDO
 */
require_once __DIR__ . '/includes/functions.php';

$user = require_auth();
$db = get_db();
$userId = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Your session security token expired. Please submit your changes again.');
        redirect('settings.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name             = trim($_POST['name'] ?? '');
        $businessName     = trim($_POST['business_name'] ?? '');
        $currency         = trim($_POST['currency'] ?? '$');
        $defaultThreshold = max(1, (int)($_POST['default_threshold'] ?? 10));

        if ($name === '') {
            set_flash('error', 'Operator name cannot be blank.');
        } else {
            $stmt = $db->prepare("
                UPDATE users
                SET name = :name, business_name = :bname, currency = :curr, default_threshold = :thresh
                WHERE id = :id
            ");
            $stmt->execute([
                ':name'   => $name,
                ':bname'  => $businessName,
                ':curr'   => $currency,
                ':thresh' => $defaultThreshold,
                ':id'     => $userId,
            ]);
            $_SESSION['user_name'] = $name;
            log_platform_event(
                $db,
                $userId,
                $name,
                'operator',
                'profile_updated',
                "Updated operator workspace profile ({$businessName}) and ledger preferences.",
                $userId
            );
            set_flash('success', 'Saved operator profile and ledger defaults.');
        }
        redirect('settings.php');
    }

    if ($action === 'change_password') {
        if (is_demo_user($user)) {
            set_flash('error', 'Demo limit: Please register an operator account');
            redirect('settings.php');
        }
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        $pStmt = $db->prepare("SELECT password_hash FROM users WHERE id = :id");
        $pStmt->execute([':id' => $userId]);
        $row = $pStmt->fetch();

        if (!$row || !password_verify($currentPass, $row['password_hash'])) {
            set_flash('error', 'Current password did not match.');
        } elseif (strlen($newPass) < 6) {
            set_flash('error', 'New password must be at least 6 characters.');
        } elseif ($newPass !== $confirmPass) {
            set_flash('error', 'New password and confirmation password did not match.');
        } else {
            $newHash = password_hash($newPass, PASSWORD_BCRYPT);
            $up = $db->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
            $up->execute([':hash' => $newHash, ':id' => $userId]);

            log_platform_event(
                $db,
                $userId,
                $user['name'],
                'operator',
                'password_updated',
                "Changed operator account password for {$user['email']}.",
                $userId
            );
            set_flash('success', 'Operator password updated.');
        }
        redirect('settings.php');
    }

    // Load baseline 9-SKU sample dataset for Machine A & Machine B
    if ($action === 'reset_demo_data') {
        if (!is_operator($user)) {
            set_flash('error', 'Only operators can reset workspace demo data.');
            redirect('settings.php');
        }
        $db->prepare("DELETE FROM inventory_logs WHERE user_id = :uid")->execute([':uid' => $userId]);
        $db->prepare("DELETE FROM products WHERE user_id = :uid")->execute([':uid' => $userId]);
        $db->prepare("DELETE FROM machines WHERE user_id = :uid")->execute([':uid' => $userId]);

        seed_sample_inventory_data($db, $userId);

        set_flash('success', 'Loaded baseline sample fleet records for Machine A and Machine B (9 SKUs).');
        redirect('dashboard.php');
    }

    // Clear all products & audit logs (keep machines intact)
    if ($action === 'clear_products_data') {
        if (!is_operator($user)) {
            set_flash('error', 'Only operators can clear catalog data.');
            redirect('settings.php');
        }
        $db->prepare("DELETE FROM inventory_logs WHERE user_id = :uid")->execute([':uid' => $userId]);
        $db->prepare("DELETE FROM products WHERE user_id = :uid")->execute([':uid' => $userId]);

        set_flash('success', 'Cleared all product SKUs and audit log entries. Machines remain registered.');
        redirect('inventory.php');
    }

    // Operator updates staff permissions
    if ($action === 'update_staff_permissions') {
        if (!is_operator($user)) {
            set_flash('error', 'Only operators can manage staff permissions.');
            redirect('settings.php');
        }
        $staffId     = (int)($_POST['staff_id'] ?? 0);
        $canMachines = isset($_POST['can_manage_machines']) ? 1 : 0;
        $canPrices   = isset($_POST['can_manage_prices']) ? 1 : 0;

        $checkStaff = $db->prepare("SELECT id, name, email FROM users WHERE id = :sid AND operator_id = :oid AND role = 'staff'");
        $checkStaff->execute([':sid' => $staffId, ':oid' => $userId]);
        $targetStaff = $checkStaff->fetch();

        if ($targetStaff) {
            $upd = $db->prepare("UPDATE users SET can_manage_machines = :cm, can_manage_prices = :cp WHERE id = :sid");
            $upd->execute([':cm' => $canMachines, ':cp' => $canPrices, ':sid' => $staffId]);

            log_platform_event(
                $db,
                $userId,
                $user['name'],
                'operator',
                'staff_permissions_updated',
                "Updated permissions for staff '{$targetStaff['name']}': can_manage_machines={$canMachines}, can_manage_prices={$canPrices}.",
                $staffId
            );
            set_flash('success', "Updated permissions for staff member '{$targetStaff['name']}'.");
        } else {
            set_flash('error', 'Staff member record not found under your company.');
        }
        redirect('settings.php');
    }

    // Operator revokes staff access
    if ($action === 'revoke_staff') {
        if (!is_operator($user)) {
            set_flash('error', 'Only operators can revoke staff accounts.');
            redirect('settings.php');
        }
        $staffId = (int)($_POST['staff_id'] ?? 0);

        $checkStaff = $db->prepare("SELECT id, name, email FROM users WHERE id = :sid AND operator_id = :oid AND role = 'staff'");
        $checkStaff->execute([':sid' => $staffId, ':oid' => $userId]);
        $targetStaff = $checkStaff->fetch();

        if ($targetStaff) {
            $del = $db->prepare("DELETE FROM users WHERE id = :sid AND operator_id = :oid");
            $del->execute([':sid' => $staffId, ':oid' => $userId]);

            log_platform_event(
                $db,
                $userId,
                $user['name'],
                'operator',
                'staff_revoked',
                "Revoked staff access for '{$targetStaff['name']}' ({$targetStaff['email']}).",
                $staffId
            );
            set_flash('success', "Staff access revoked for '{$targetStaff['name']}'.");
        } else {
            set_flash('error', 'Staff member record not found under your company.');
        }
        redirect('settings.php');
    }
}

// Re-fetch fresh user record + active license + billing receipts
$uStmt = $db->prepare("SELECT * FROM users WHERE id = :id");
$uStmt->execute([':id' => $userId]);
$user = $uStmt->fetch() ?: $user;

$teamStaff = [];
$operatorOwner = null;

if (is_operator($user)) {
    $staffStmt = $db->prepare("SELECT id, name, email, status, email_verified_at, can_manage_machines, can_manage_prices, created_at FROM users WHERE operator_id = :oid AND role = 'staff' ORDER BY id ASC");
    $staffStmt->execute([':oid' => $userId]);
    $teamStaff = $staffStmt->fetchAll();
} elseif (is_staff($user) && !empty($user['operator_id'])) {
    $opStmt = $db->prepare("SELECT id, name, email, business_name FROM users WHERE id = :oid LIMIT 1");
    $opStmt->execute([':oid' => $user['operator_id']]);
    $operatorOwner = $opStmt->fetch();
}

$licStmt = $db->prepare("SELECT * FROM license_keys WHERE user_id = :uid ORDER BY id DESC LIMIT 1");
$licStmt->execute([':uid' => $userId]);
$activeLicenseRow = $licStmt->fetch();

$txnStmt = $db->prepare("SELECT * FROM billing_transactions WHERE user_id = :uid ORDER BY id DESC LIMIT 10");
$txnStmt->execute([':uid' => $userId]);
$billingHistory = $txnStmt->fetchAll();

$pageTitle    = 'Account & Settings';
$pageHeader   = 'Account & settings';
$pageSubtitle = is_staff($user)
    ? 'Manage your staff profile, credentials, and view workspace permissions.'
    : 'Manage operator profile, team staff permissions, software license, and security.';

require_once __DIR__ . '/includes/dashboard_header.php';
?>

<div class="settings-page-container">

    <!-- 1. Profile & Preferences -->
    <section class="settings-section-card">
        <div class="settings-section-title"><?= is_staff($user) ? 'Staff Profile & Credentials' : 'Operator profile & ledger defaults' ?></div>
        <div class="settings-section-desc">
            <?= is_staff($user)
                ? 'Your personal account details connected to ' . e($user['business_name']) . '.'
                : 'Business name, currency formatting, and default par threshold applied when adding new SKUs.' ?>
        </div>

        <form action="settings.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="update_profile">

            <div class="form-grid-2" style="margin-bottom: 1rem;">
                <div class="form-group-item">
                    <label class="form-label" for="set-name"><?= is_staff($user) ? 'Your Full Name *' : 'Operator Name *' ?></label>
                    <input type="text" id="set-name" name="name" required value="<?= e($user['name']) ?>" class="form-input-text">
                </div>
                <div class="form-group-item">
                    <label class="form-label" for="set-business"><?= is_staff($user) ? 'Company (Operator)' : 'Business or Company Name' ?></label>
                    <input type="text" id="set-business" name="business_name" <?= is_staff($user) ? 'disabled style="background: #f3f4f6; color: var(--text-muted); cursor: not-allowed;"' : '' ?> value="<?= e($user['business_name']) ?>" class="form-input-text">
                </div>
            </div>

            <div class="form-group-item" style="margin-bottom: 1rem;">
                <label class="form-label" for="set-email">Account Email</label>
                <input type="email" id="set-email" disabled value="<?= e($user['email']) ?>" class="form-input-text" style="background: #f3f4f6; color: var(--text-muted); cursor: not-allowed;">
                <span style="font-size: 0.74rem; color: var(--text-muted);">
                    <?= is_staff($user) ? 'Connected to operator license ' . e($user['license_key']) : 'Bound to your active VendFlow Unlimited software license.' ?>
                </span>
            </div>

            <?php if (!is_staff($user)): ?>
            <div class="form-grid-2" style="margin-bottom: 1.25rem;">
                <div class="form-group-item">
                    <label class="form-label" for="set-currency">Currency Symbol</label>
                    <select id="set-currency" name="currency" class="form-input-text">
                        <option value="$" <?= $user['currency'] === '$' ? 'selected' : '' ?>>USD ($)</option>
                        <option value="₱" <?= $user['currency'] === '₱' ? 'selected' : '' ?>>PHP (₱)</option>
                        <option value="€" <?= $user['currency'] === '€' ? 'selected' : '' ?>>EUR (€)</option>
                        <option value="£" <?= $user['currency'] === '£' ? 'selected' : '' ?>>GBP (£)</option>
                        <option value="C$" <?= $user['currency'] === 'C$' ? 'selected' : '' ?>>CAD (C$)</option>
                    </select>
                </div>
                <div class="form-group-item">
                    <label class="form-label" for="set-threshold">Default Par Threshold</label>
                    <input type="number" id="set-threshold" name="default_threshold" min="1" value="<?= (int)($user['default_threshold'] ?: 10) ?>" class="form-input-text">
                </div>
            </div>
            <?php endif; ?>

            <button type="submit" class="btn-modal-save">Save profile changes</button>
        </form>
    </section>

    <?php if (is_staff($user)): ?>
    <!-- Staff Member Permissions Overview -->
    <section class="settings-section-card">
        <div class="settings-section-title">Your Team Permissions</div>
        <div class="settings-section-desc">
            Your permissions are configured and managed directly by your operator (<?= e($operatorOwner['name'] ?? 'Company Operator') ?>).
        </div>

        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <div style="background: #ffffff; border: 1px solid var(--border-light); border-radius: 8px; padding: 0.85rem 1rem; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <strong style="font-size: 0.88rem; color: var(--text-main);">Daily Stock Adjustments &amp; Restocks</strong>
                    <div style="font-size: 0.78rem; color: var(--text-muted);">Record physical restocks, cash-box sales tallies, and spoilage adjustments.</div>
                </div>
                <span class="badge-active-green"><span style="width:12px;height:12px;display:inline-flex;margin-right:3px;"><?= posthog_icon('check') ?></span> Enabled</span>
            </div>

            <div style="background: #ffffff; border: 1px solid var(--border-light); border-radius: 8px; padding: 0.85rem 1rem; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <strong style="font-size: 0.88rem; color: var(--text-main);">Vending Machine Management</strong>
                    <div style="font-size: 0.78rem; color: var(--text-muted);">Register new placement machines or remove existing ones.</div>
                </div>
                <?php if (can_manage_machines($user)): ?>
                    <span class="badge-active-green"><span style="width:12px;height:12px;display:inline-flex;margin-right:3px;"><?= posthog_icon('check') ?></span> Granted by Operator</span>
                <?php else: ?>
                    <span style="font-size: 0.74rem; font-weight: 700; color: #6b7280; background: #f3f4f6; padding: 0.2rem 0.55rem; border-radius: 999px;"><span style="width:12px;height:12px;display:inline-flex;margin-right:3px;vertical-align:-1px;"><?= posthog_icon('lock') ?></span> Restricted (Operator Permission Required)</span>
                <?php endif; ?>
            </div>

            <div style="background: #ffffff; border: 1px solid var(--border-light); border-radius: 8px; padding: 0.85rem 1rem; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <strong style="font-size: 0.88rem; color: var(--text-main);">Product Cost &amp; Sell Price Editing</strong>
                    <div style="font-size: 0.78rem; color: var(--text-muted);">Update price points and cost calculations for cataloged SKUs.</div>
                </div>
                <?php if (can_manage_prices($user)): ?>
                    <span class="badge-active-green"><span style="width:12px;height:12px;display:inline-flex;margin-right:3px;"><?= posthog_icon('check') ?></span> Granted by Operator</span>
                <?php else: ?>
                    <span style="font-size: 0.74rem; font-weight: 700; color: #6b7280; background: #f3f4f6; padding: 0.2rem 0.55rem; border-radius: 999px;"><span style="width:12px;height:12px;display:inline-flex;margin-right:3px;vertical-align:-1px;"><?= posthog_icon('lock') ?></span> Restricted (Operator Permission Required)</span>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (is_operator($user)): ?>
    <!-- Staff & Team Members Management for Operators -->
    <section class="settings-section-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; margin-bottom: 0.6rem;">
            <div>
                <div class="settings-section-title">Staff &amp; Team Members</div>
                <div class="settings-section-desc">
                    Manage team members assisting with physical machine restocks, inventory counts, and daily tallies.
                </div>
            </div>
            <span class="badge-active-green"><?= count($teamStaff) ?> Active Staff</span>
        </div>

        <!-- License Key Invitation Box -->
        <div style="background: #fafaf9; border: 1.5px dashed #fdba74; border-radius: 8px; padding: 0.95rem 1.15rem; margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <div>
                <div style="font-size: 0.78rem; font-weight: 800; color: #9a3412; text-transform: uppercase;">
                    Staff Join Pass (License Key)
                </div>
                <div style="font-size: 0.82rem; color: #4b5563; margin-top: 0.2rem;">
                    To add staff, have them choose <strong>"Staff Member (Join Company)"</strong> on the registration form and enter this key:
                </div>
                <code id="staff-share-key" style="display: inline-block; margin-top: 0.35rem; font-size: 1.05rem; font-weight: 800; color: #c2410c; background: #fff7ed; border: 1px solid #fed7aa; padding: 0.2rem 0.55rem; border-radius: 6px;">
                    <?= e($user['license_key'] ?: 'VF-UNL-ACTIVE') ?>
                </code>
            </div>
            <button type="button" class="btn-dash-outline" onclick="copyStaffKey(this)" style="font-size: 0.78rem; padding: 0.38rem 0.85rem;">
                <span style="width:13px;height:13px;display:inline-flex;vertical-align:-1px;margin-right:3px;"><?= posthog_icon('receipt') ?></span> Copy Key
            </button>
        </div>

        <?php if (!empty($teamStaff)): ?>
            <div style="display: flex; flex-direction: column; gap: 0.9rem;">
                <?php foreach ($teamStaff as $staff): ?>
                    <div style="background: #ffffff; border: 1px solid var(--border-light); border-radius: 8px; padding: 1rem 1.15rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                <strong style="font-size: 0.95rem; color: var(--text-main);"><?= e($staff['name']) ?></strong>
                                <span class="tier-tag-mini" style="background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe;">Staff</span>
                                <?php if (!empty($staff['email_verified_at'])): ?>
                                    <span class="badge-active-green" style="font-size: 0.68rem; padding: 0.12rem 0.45rem;">Verified</span>
                                <?php else: ?>
                                    <span style="font-size: 0.68rem; font-weight: 700; color: #b45309; background: #fef3c7; border: 1px solid #fde68a; padding: 0.12rem 0.45rem; border-radius: 999px;">Pending Email Verification</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;">
                                <?= e($staff['email']) ?> &bull; Joined <?= date('M j, Y', strtotime($staff['created_at'])) ?>
                            </div>
                        </div>

                        <!-- Permissions Form -->
                        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                            <form action="settings.php" method="POST" style="display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap;">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="action" value="update_staff_permissions">
                                <input type="hidden" name="staff_id" value="<?= (int)$staff['id'] ?>">

                                <label style="display: flex; align-items: center; gap: 0.35rem; font-size: 0.78rem; color: var(--text-main); cursor: pointer;">
                                    <input type="checkbox" name="can_manage_machines" value="1" <?= !empty($staff['can_manage_machines']) ? 'checked' : '' ?>>
                                    <span>Manage machines</span>
                                </label>

                                <label style="display: flex; align-items: center; gap: 0.35rem; font-size: 0.78rem; color: var(--text-main); cursor: pointer;">
                                    <input type="checkbox" name="can_manage_prices" value="1" <?= !empty($staff['can_manage_prices']) ? 'checked' : '' ?>>
                                    <span>Edit prices</span>
                                </label>

                                <button type="submit" class="btn-dash-outline" style="font-size: 0.76rem; padding: 0.3rem 0.65rem;">Save</button>
                            </form>

                            <form action="settings.php" method="POST" onsubmit="return confirm('Revoke staff access for <?= htmlspecialchars($staff['name'], ENT_QUOTES, 'UTF-8') ?>?');">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="action" value="revoke_staff">
                                <input type="hidden" name="staff_id" value="<?= (int)$staff['id'] ?>">
                                <button type="submit" class="btn-danger-outline" style="font-size: 0.76rem; padding: 0.3rem 0.6rem;">Revoke</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="background: #fafaf9; border: 1px dashed var(--border-light); border-radius: 8px; padding: 1.5rem; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                <div style="width:36px;height:36px;margin:0 auto 0.35rem;color:#94a3b8;"><?= posthog_icon('people') ?></div>
                <div style="font-weight: 700; color: var(--text-main); margin-bottom: 0.2rem;">No staff members joined yet</div>
                Share your license key with your team so they can register as staff and assist with daily inventory logs.
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if (!is_staff($user)): ?>
    <!-- 2. Active Unlimited Software License & Billing Receipts -->
    <section class="settings-section-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
            <div>
                <div class="settings-section-title">Active software license &amp; billing receipts</div>
                <div class="settings-section-desc">
                    Automatically provisioned upon account purchase — unlocks unlimited vending machines, SKUs, and lifetime logs.
                </div>
            </div>
            <span class="badge-active-green"><span style="width:12px;height:12px;display:inline-flex;margin-right:3px;"><?= posthog_icon('check') ?></span> Auto-Provisioned</span>
        </div>

        <div class="machine-reg-metrics" style="border: 1px solid var(--border-light); border-radius: 8px; overflow: hidden; margin-bottom: 1.25rem;">
            <div class="machine-metric-cell">
                <div class="machine-metric-label">Software plan</div>
                <div class="machine-metric-val" style="font-size: 1rem;">Unlimited ($49/mo)</div>
                <span style="font-size: 0.72rem; color: #15803d; font-weight: 700;">All limits unlocked</span>
            </div>
            <div class="machine-metric-cell" style="grid-column: span 2;">
                <div class="machine-metric-label">Bound license key</div>
                <div class="machine-metric-val" style="font-size: 0.95rem; font-family: monospace; word-break: break-all;">
                    <?= e($user['license_key'] ?: 'VF-UNL-ACTIVE') ?>
                </div>
                <?php if ($activeLicenseRow && !empty($activeLicenseRow['activated_at'])): ?>
                    <span style="font-size: 0.72rem; color: var(--text-muted);">
                        Activated <?= e(date('M j, Y H:i', strtotime($activeLicenseRow['activated_at']))) ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="machine-metric-cell">
                <div class="machine-metric-label">Machine &amp; SKU cap</div>
                <div class="machine-metric-val" style="font-size: 1rem;">Unlimited</div>
                <span style="font-size: 0.72rem; color: var(--text-muted);">Lifetime audit log</span>
            </div>
        </div>

        <?php if (!empty($billingHistory)): ?>
            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-sub); margin-bottom: 0.5rem;">
                Simulated license purchase receipts
            </div>
            <div class="posthog-table-card">
                <div class="posthog-table-responsive">
                    <table class="posthog-data-table">
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>License key</th>
                                <th>Payment method</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($billingHistory as $txn): ?>
                                <tr>
                                    <td><code style="font-weight: 700; font-size: 0.76rem;"><?= e($txn['transaction_ref']) ?></code></td>
                                    <td><code style="font-size: 0.76rem; color: var(--text-sub);"><?= e($txn['license_key']) ?></code></td>
                                    <td style="font-size: 0.8rem;"><?= e($txn['payment_method']) ?></td>
                                    <td style="font-weight: 700;">$<?= number_format((float)$txn['amount'], 2) ?></td>
                                    <td><span class="badge-active-green"><?= e(ucfirst($txn['status'])) ?></span></td>
                                    <td class="cell-timestamp"><?= e(date('M j, Y H:i', strtotime($txn['created_at']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if (is_operator($user)): ?>
    <div class="settings-grid-split">
        <!-- 3. Password Security -->
        <section class="settings-section-card">
            <div class="settings-section-title">Update password</div>
            <div class="settings-section-desc">
                Replace your operator sign-in password. Stored securely with bcrypt hashing.
            </div>

            <form action="settings.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="change_password">

                <div class="form-group-item" style="margin-bottom: 0.85rem;">
                    <label class="form-label" for="cur-pass">Current Password *</label>
                    <input type="password" id="cur-pass" name="current_password" required class="form-input-text">
                </div>

                <div class="form-group-item" style="margin-bottom: 0.85rem;">
                    <label class="form-label" for="upd-pass">New Password *</label>
                    <input type="password" id="upd-pass" name="new_password" required minlength="6" class="form-input-text">
                </div>

                <div class="form-group-item" style="margin-bottom: 1.15rem;">
                    <label class="form-label" for="cnf-pass">Confirm New Password *</label>
                    <input type="password" id="cnf-pass" name="confirm_password" required minlength="6" class="form-input-text">
                </div>

                <button type="submit" class="btn-dash-outline" style="padding: 0.5rem 1.1rem;">Update password</button>
            </form>
        </section>

        <!-- 4. Workspace Sample Dataset & Clean Reset Controls -->
        <section class="settings-section-card" style="display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div class="settings-section-title">Workspace dataset controls</div>
                <div class="settings-section-desc">
                    Populate the 9-SKU evaluation catalog or reset products &amp; audit logs back to an empty slate.
                </div>
                <p style="font-size: 0.82rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 1.25rem;">
                    Restoring the sample dataset replenishes Machine A and Machine B with baseline inventory records for quick demo and testing. Clearing the workspace empties cataloged products and transaction logs.
                </p>
            </div>

            <div style="display: flex; flex-wrap: wrap; gap: 0.85rem; padding-top: 1rem; border-top: 1px solid var(--border-light);">
                <form action="settings.php" method="POST" onsubmit="return confirm('Load Machine A & Machine B with 9 sample snack and beverage SKUs?');">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="reset_demo_data">
                    <button type="submit" class="btn-dash-outline">
                        <span style="width:13px;height:13px;display:inline-flex;vertical-align:-1px;margin-right:3px;"><?= posthog_icon('box') ?></span> Load sample 9-SKU dataset
                    </button>
                </form>

                <form action="settings.php" method="POST" onsubmit="return confirm('Clear all product SKUs and audit log rows in this workspace?');">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="clear_products_data">
                    <button type="submit" class="btn-danger-outline" style="padding: 0.45rem 0.9rem;">
                        <span style="width:13px;height:13px;display:inline-flex;vertical-align:-1px;margin-right:3px;"><?= posthog_icon('trash') ?></span> Empty products &amp; audit log
                    </button>
                </form>
            </div>
        </section>
    </div>
    <?php else: ?>
    <!-- 3. Password Security (Staff) -->
    <section class="settings-section-card" style="max-width: 600px;">
        <div class="settings-section-title">Update password</div>
        <div class="settings-section-desc">
            Replace your staff sign-in password. Stored securely with bcrypt hashing.
        </div>

        <form action="settings.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="change_password">

            <div class="form-group-item" style="margin-bottom: 0.85rem;">
                <label class="form-label" for="cur-pass">Current Password *</label>
                <input type="password" id="cur-pass" name="current_password" required class="form-input-text">
            </div>

            <div class="form-group-item" style="margin-bottom: 0.85rem;">
                <label class="form-label" for="upd-pass">New Password *</label>
                <input type="password" id="upd-pass" name="new_password" required minlength="6" class="form-input-text">
            </div>

            <div class="form-group-item" style="margin-bottom: 1.15rem;">
                <label class="form-label" for="cnf-pass">Confirm New Password *</label>
                <input type="password" id="cnf-pass" name="confirm_password" required minlength="6" class="form-input-text">
            </div>

            <button type="submit" class="btn-dash-outline" style="padding: 0.5rem 1.1rem;">Update password</button>
        </form>
    </section>
    <?php endif; ?>

</div>

<script>
function copyStaffKey(btn) {
    const key = document.getElementById('staff-share-key')?.innerText?.trim();
    if (!key) return;

    const showCopied = () => {
        const original = btn.innerHTML;
        btn.innerHTML = '✓ Copied!';
        btn.style.color = '#15803d';
        setTimeout(() => {
            btn.innerHTML = original;
            btn.style.color = '';
        }, 2000);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(key).then(showCopied);
    } else {
        const ta = document.createElement('textarea');
        ta.value = key;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try {
            if (document.execCommand('copy')) {
                showCopied();
            } else {
                prompt('Copy your license key:', key);
            }
        } catch (e) {
            prompt('Copy your license key:', key);
        }
        document.body.removeChild(ta);
    }
}
</script>

<?php require_once __DIR__ . '/includes/dashboard_footer.php'; ?>
