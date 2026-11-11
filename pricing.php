<?php
/**
 * VendFlow - Operator Workspace: License Plans & Software Tiers
 * Full-screen PostHog Dashboard UI for managing Solo, Business, Max & Enterprise Licenses
 * Pure Vanilla PHP + MySQL PDO
 */
require_once __DIR__ . '/includes/functions.php';

// If visitor is not logged in, render the public WebOS desktop modal
if (!is_logged_in()) {
    $initialWindow = 'licenses';
    require_once __DIR__ . '/partner.php';
    exit;
}

// If user is Platform Superadmin, redirect to Superadmin license console
if (is_superadmin() && !isset($_SESSION['impersonator_id'])) {
    redirect('superadmin.php?tab=licenses');
}

$user = require_auth();

// If user is staff, license plans are managed by their operator
if (is_staff($user)) {
    set_flash('info', 'License plans and billing receipts are managed by your operator.');
    redirect('inventory.php');
}

$db = get_db();
$userId = (int)$user['id'];
$planInfo = get_user_plan_info($user);
$selfServicePlans = get_license_plans();

// 1. Fetch active license key record
$licStmt = $db->prepare("SELECT * FROM license_keys WHERE user_id = :uid ORDER BY id DESC LIMIT 1");
$licStmt->execute([':uid' => $userId]);
$activeLicense = $licStmt->fetch();
$licenseKey = $user['license_key'] ?: ($activeLicense['license_key'] ?? 'VF-SOL-ACTIVE');

// 2. Fetch live fleet, staff, catalog, and audit counters
$mStmt = $db->prepare("SELECT COUNT(*) FROM machines WHERE user_id = :uid");
$mStmt->execute([':uid' => $userId]);
$machineCount = (int)$mStmt->fetchColumn();

$sStmt = $db->prepare("SELECT COUNT(*) FROM staff_accounts WHERE operator_id = :uid");
$sStmt->execute([':uid' => $userId]);
$staffCount = (int)$sStmt->fetchColumn();

$pStmt = $db->prepare("SELECT COUNT(*) FROM products WHERE user_id = :uid");
$pStmt->execute([':uid' => $userId]);
$skuCount = (int)$pStmt->fetchColumn();

$aStmt = $db->prepare("SELECT COUNT(*) FROM inventory_logs WHERE user_id = :uid");
$aStmt->execute([':uid' => $userId]);
$auditCount = (int)$aStmt->fetchColumn();

// 3. Fetch billing transactions for this operator
$txStmt = $db->prepare("SELECT * FROM billing_transactions WHERE user_id = :uid ORDER BY id DESC LIMIT 10");
$txStmt->execute([':uid' => $userId]);
$billingHistory = $txStmt->fetchAll();

$machineLimit = (int)$planInfo['machine_limit'];
$staffLimit   = (int)$planInfo['staff_limit'];
$machinesRemaining = max(0, $machineLimit - $machineCount);
$staffRemaining    = max(0, $staffLimit - $staffCount);

$pageTitle    = 'License Plans';
$pageHeader   = 'License plans';
$pageSubtitle = 'Choose between Solo, Business, and Max license tiers — or contact admin for custom Enterprise limits.';

require_once __DIR__ . '/includes/dashboard_header.php';
?>

<style>
.dash-content-header {
    width: 100%;
    margin-bottom: 1.5rem;
}
.pricing-page-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    width: 100%;
}
.pricing-tiers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1.25rem;
    align-items: stretch;
    width: 100%;
}
@media (max-width: 1100px) {
    .pricing-tiers-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="pricing-page-container">

    <!-- ===================================================================
         1. Active License Hero & Quota Usage Banner
         =================================================================== -->
    <div style="background: #ffffff; border: 1.5px solid #e5e7eb; border-radius: 12px; padding: 1.6rem 1.8rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1.25rem; flex-wrap: wrap; margin-bottom: 1.25rem;">
            <div>
                <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.4rem; flex-wrap: wrap;">
                    <span style="font-size: 0.74rem; font-weight: 800; color: #ea580c; text-transform: uppercase; letter-spacing: 0.05em;">Current Subscription</span>
                    <span class="badge-active-green" style="display:inline-flex;align-items:center;gap:4px;"><span style="width:12px;height:12px;display:inline-flex;"><?= posthog_icon('check') ?></span> Active &amp; Verified</span>
                    <span style="font-size: 0.7rem; font-weight: 800; background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; padding: 0.16rem 0.55rem; border-radius: 999px; text-transform: uppercase;">
                        <?= e($planInfo['label']) ?> Tier
                    </span>
                </div>
                <h2 style="font-size: 1.6rem; font-weight: 800; color: #111827; margin: 0 0 0.25rem;">
                    <?= e($planInfo['plan_name']) ?>
                </h2>
                <p style="font-size: 0.9rem; color: #4b5563; margin: 0;">
                    Attached to <strong><?= e($user['business_name'] ?: $user['name']) ?></strong> &bull; Includes up to <strong><?= $machineLimit ?> machines</strong> and <strong><?= $staffLimit ?> route staff <?= $staffLimit === 1 ? 'account' : 'accounts' ?></strong>.
                </p>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 1.85rem; font-weight: 900; color: #111827;">
                    $<?= number_format((float)$planInfo['price'], 2) ?><span style="font-size: 0.88rem; font-weight: 600; color: #6b7280;">/month</span>
                </div>
                <span style="font-size: 0.76rem; color: #15803d; font-weight: 700;">
                    <?= !empty($planInfo['is_custom']) ? 'Custom Admin-Provisioned Enterprise Plan' : 'Flat monthly tier &bull; $0.00 per-machine surcharges' ?>
                </span>
            </div>
        </div>

        <!-- Active License Key Box -->
        <div style="background: #fafaf9; border: 1px solid #e5e7eb; border-radius: 8px; padding: 0.95rem 1.15rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.85rem; margin-bottom: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap;">
                <span style="font-size: 0.8rem; font-weight: 800; color: #6b7280; text-transform: uppercase;">Active License Key:</span>
                <code id="active-lic-key" style="font-size: 1.15rem; font-weight: 800; color: #c2410c; background: #fff7ed; border: 1.5px dashed #fdba74; padding: 0.25rem 0.65rem; border-radius: 6px; letter-spacing: 0.04em;">
                    <?= e($licenseKey) ?>
                </code>
                <button type="button" class="btn-dash-outline" onclick="copyKeyText('<?= e($licenseKey) ?>', this)" style="padding: 0.32rem 0.75rem; font-size: 0.78rem;">
                    <span style="width:13px;height:13px;display:inline-flex;vertical-align:-1px;margin-right:4px;"><?= posthog_icon('receipt') ?></span> Copy Key
                </button>
            </div>
            <div style="font-size: 0.8rem; color: #6b7280;">
                Status: <strong style="color: #15803d;">Active</strong> &bull; Currency: <strong><?= e($user['currency'] ?? '$') ?></strong>
            </div>
        </div>

        <!-- 4-Stat Quota Strip -->
        <div class="machine-reg-metrics" style="border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
            <div class="machine-metric-cell">
                <div class="machine-metric-label">Registered Fleet</div>
                <div class="machine-metric-val"><?= $machineCount ?> / <?= $machineLimit ?> Machines</div>
                <?php if ($machineCount >= $machineLimit): ?>
                    <span style="font-size: 0.72rem; color: #d97706; font-weight: 700;">Plan machine limit reached</span>
                <?php else: ?>
                    <span style="font-size: 0.72rem; color: #15803d; font-weight: 700;"><?= $machinesRemaining ?> machine <?= $machinesRemaining === 1 ? 'slot' : 'slots' ?> remaining</span>
                <?php endif; ?>
            </div>
            <div class="machine-metric-cell">
                <div class="machine-metric-label">Staff Accounts</div>
                <div class="machine-metric-val"><?= $staffCount ?> / <?= $staffLimit ?> Staff</div>
                <?php if ($staffCount >= $staffLimit): ?>
                    <span style="font-size: 0.72rem; color: #d97706; font-weight: 700;">Plan staff limit reached</span>
                <?php else: ?>
                    <span style="font-size: 0.72rem; color: #15803d; font-weight: 700;"><?= $staffRemaining ?> staff <?= $staffRemaining === 1 ? 'slot' : 'slots' ?> remaining</span>
                <?php endif; ?>
            </div>
            <div class="machine-metric-cell">
                <div class="machine-metric-label">Cataloged SKUs</div>
                <div class="machine-metric-val"><?= $skuCount ?> Items</div>
                <span style="font-size: 0.72rem; color: #15803d; font-weight: 700;">Unlimited Catalog</span>
            </div>
            <div class="machine-metric-cell">
                <div class="machine-metric-label">Audit History</div>
                <div class="machine-metric-val"><?= $auditCount ?> Events</div>
                <span style="font-size: 0.72rem; color: #15803d; font-weight: 700;">Lifetime Retention</span>
            </div>
        </div>
    </div>

    <!-- ===================================================================
         2. 3-Tier License Selector (Solo / Business / Max)
         =================================================================== -->
    <div>
        <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.85rem;">
            <div>
                <h3 style="font-size: 1.2rem; font-weight: 800; color: #111827; margin: 0;">Available License Tiers</h3>
                <p style="font-size: 0.84rem; color: #6b7280; margin: 0.15rem 0 0;">
                    Upgrade or switch plans anytime via simulated checkout ($0.00 sandbox charge). Every tier includes unlimited SKU items and permanent audit logs.
                </p>
            </div>
        </div>

        <div class="pricing-tiers-grid">
            <?php foreach ($selfServicePlans as $tKey => $tSpec):
                $isCurrent = ($planInfo['tier'] === $tKey);
                $isFeatured = !empty($tSpec['featured']);
                $tMachineCap = (int)$tSpec['machine_limit'];
                $tStaffCap   = (int)$tSpec['staff_limit'];
                $exceedsMachines = ($machineCount > $tMachineCap);
                $exceedsStaff    = ($staffCount > $tStaffCap);
                $blockedDowngrade = (!$isCurrent && ($exceedsMachines || $exceedsStaff));
                $isUpgrade = ((float)$tSpec['price'] > (float)$planInfo['price']);
            ?>
                <div style="background: #ffffff; border: <?= $isCurrent ? '2px solid #16a34a' : ($isFeatured ? '2px solid #ea580c' : '1.5px solid #e5e7eb') ?>; border-radius: 12px; padding: 1.55rem; box-shadow: <?= ($isCurrent || $isFeatured) ? '0 4px 16px rgba(0, 0, 0, 0.06)' : '0 1px 3px rgba(0,0,0,0.04)' ?>; display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; margin-bottom: 0.65rem;">
                            <span style="font-size: 0.7rem; font-weight: 800; background: <?= $isFeatured ? '#fff7ed' : '#f3f4f6' ?>; color: <?= $isFeatured ? '#c2410c' : '#374151' ?>; border: 1px solid <?= $isFeatured ? '#fed7aa' : '#e5e7eb' ?>; padding: 0.2rem 0.6rem; border-radius: 999px; text-transform: uppercase;">
                                <?= $isFeatured ? 'Most Popular' : e($tSpec['tagline']) ?>
                            </span>
                            <?php if ($isCurrent): ?>
                                <span style="font-size: 0.78rem; font-weight: 800; color: #15803d; display:inline-flex;align-items:center;gap:4px;">
                                    <span style="width:13px;height:13px;display:inline-flex;"><?= posthog_icon('check_circle') ?></span> Current Plan
                                </span>
                            <?php endif; ?>
                        </div>

                        <h3 style="font-size: 1.4rem; font-weight: 900; color: #111827; margin: 0 0 0.3rem;">
                            VendFlow <?= e($tSpec['label']) ?>
                        </h3>
                        <p style="font-size: 0.85rem; color: #4b5563; margin: 0 0 1rem; line-height: 1.5;">
                            <?= e($tSpec['description']) ?>
                        </p>

                        <div style="font-size: 2.2rem; font-weight: 900; color: #111827; margin-bottom: 1.1rem;">
                            $<?= number_format($tSpec['price'], 0) ?><span style="font-size: 0.9rem; font-weight: 600; color: #6b7280;">/month</span>
                        </div>

                        <!-- Machine & Staff Cap Highlights -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem; margin-bottom: 1.15rem;">
                            <div style="background: #fafaf9; border: 1px solid #e5e7eb; border-radius: 8px; padding: 0.6rem 0.7rem;">
                                <div style="font-size: 0.68rem; font-weight: 800; color: #6b7280; text-transform: uppercase;">Machine Limit</div>
                                <div style="font-size: 1.05rem; font-weight: 900; color: #111827;"><?= $tMachineCap ?> Machines</div>
                            </div>
                            <div style="background: #fafaf9; border: 1px solid #e5e7eb; border-radius: 8px; padding: 0.6rem 0.7rem;">
                                <div style="font-size: 0.68rem; font-weight: 800; color: #6b7280; text-transform: uppercase;">Staff Limit</div>
                                <div style="font-size: 1.05rem; font-weight: 900; color: #111827;"><?= $tStaffCap ?> <?= $tStaffCap === 1 ? 'Account' : 'Accounts' ?></div>
                            </div>
                        </div>

                        <ul style="list-style: none; padding: 0; margin: 0 0 1.35rem; display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.85rem; color: #374151;">
                            <li style="display: flex; gap: 0.55rem; align-items: flex-start;">
                                <span style="color: #15803d; display: inline-flex; width: 14px; height: 14px; flex-shrink: 0; margin-top: 2px;"><?= posthog_icon('check') ?></span>
                                <span>Up to <strong><?= $tMachineCap ?> registered vending machines</strong></span>
                            </li>
                            <li style="display: flex; gap: 0.55rem; align-items: flex-start;">
                                <span style="color: #15803d; display: inline-flex; width: 14px; height: 14px; flex-shrink: 0; margin-top: 2px;"><?= posthog_icon('check') ?></span>
                                <span>Up to <strong><?= $tStaffCap ?> route staff <?= $tStaffCap === 1 ? 'account' : 'accounts' ?></strong></span>
                            </li>
                            <li style="display: flex; gap: 0.55rem; align-items: flex-start;">
                                <span style="color: #15803d; display: inline-flex; width: 14px; height: 14px; flex-shrink: 0; margin-top: 2px;"><?= posthog_icon('check') ?></span>
                                <span><strong>Unlimited cataloged SKUs</strong> &amp; product photo uploads</span>
                            </li>
                            <li style="display: flex; gap: 0.55rem; align-items: flex-start;">
                                <span style="color: #15803d; display: inline-flex; width: 14px; height: 14px; flex-shrink: 0; margin-top: 2px;"><?= posthog_icon('check') ?></span>
                                <span><strong>Real-time low-stock par alerts</strong> &amp; restock queues</span>
                            </li>
                            <li style="display: flex; gap: 0.55rem; align-items: flex-start;">
                                <span style="color: #15803d; display: inline-flex; width: 14px; height: 14px; flex-shrink: 0; margin-top: 2px;"><?= posthog_icon('check') ?></span>
                                <span><strong>Lifetime immutable audit ledger</strong> &amp; $0 machine fees</span>
                            </li>
                        </ul>
                    </div>

                    <div style="border-top: 1px solid #f3f4f6; padding-top: 1rem;">
                        <?php if ($isCurrent): ?>
                            <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; border-radius: 8px; padding: 0.62rem 0.75rem; font-size: 0.82rem; font-weight: 800; text-align: center;">
                                <span style="width:13px;height:13px;display:inline-flex;vertical-align:-2px;margin-right:4px;"><?= posthog_icon('check') ?></span> Active on Workspace
                            </div>
                        <?php elseif ($blockedDowngrade): ?>
                            <button type="button" disabled style="width: 100%; padding: 0.6rem 0.85rem; border-radius: 8px; border: 1px solid #e5e7eb; background: #f3f4f6; color: #9ca3af; font-size: 0.82rem; font-weight: 700; cursor: not-allowed;">
                                Usage Exceeds <?= e($tSpec['label']) ?> Limits
                            </button>
                            <div style="font-size: 0.75rem; color: #b45309; margin-top: 0.45rem; line-height: 1.4;">
                                Requires &le;<?= $tMachineCap ?> machines &amp; &le;<?= $tStaffCap ?> staff (currently <?= $machineCount ?> machines, <?= $staffCount ?> staff).
                            </div>
                        <?php else: ?>
                            <a href="checkout.php?plan=<?= e($tKey) ?>" class="<?= $isUpgrade || $isFeatured ? 'btn-dash-primary' : 'btn-dash-outline' ?>" style="display: block; width: 100%; text-align: center; text-decoration: none; padding: 0.62rem 0.85rem; font-weight: 700;">
                                <?= $isUpgrade ? 'Upgrade to ' : 'Switch to ' ?><?= e($tSpec['label']) ?> ($<?= number_format($tSpec['price'], 0) ?>/mo) &rarr;
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Enterprise Custom Limits Note / CTA Below the 3 Tiers -->
        <div style="margin-top: 1.25rem; background: linear-gradient(135deg, #18181b 0%, #27272a 100%); color: #ffffff; border: 1.5px solid #3f3f46; border-radius: 12px; padding: 1.3rem 1.6rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; box-shadow: 0 4px 14px rgba(0,0,0,0.08);">
            <div style="max-width: 680px;">
                <div style="display: inline-flex; align-items: center; gap: 0.45rem; font-size: 0.7rem; font-weight: 800; color: #fdba74; background: rgba(245, 78, 0, 0.18); border: 1px solid rgba(251, 146, 60, 0.35); padding: 0.18rem 0.6rem; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.4rem;">
                    <span style="width:12px;height:12px;display:inline-flex;"><?= posthog_icon('shield_lock') ?></span> Enterprise Tier
                </div>
                <div style="font-size: 1.08rem; font-weight: 800; color: #ffffff; margin-bottom: 0.2rem;">
                    Or contact admin for specific prices and extra limits
                </div>
                <p style="font-size: 0.84rem; color: #d4d4d8; margin: 0; line-height: 1.45;">
                    Managing more than 50 vending cabinets or over 18 route staff members? Contact the platform administrator for custom Enterprise pricing and tailored fleet/staff allowances.
                </p>
            </div>
            <a href="contact.php?inquiry=enterprise" class="btn-dash-primary" style="text-decoration: none; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px; padding: 0.62rem 1.1rem;">
                <span style="width:14px;height:14px;display:inline-flex;"><?= posthog_icon('support') ?></span> Contact Admin &rarr;
            </a>
        </div>
    </div>

    <!-- ===================================================================
         3. Billing Receipts & License History Table
         =================================================================== -->
    <div style="background: #ffffff; border: 1.5px solid #e5e7eb; border-radius: 12px; padding: 1.5rem 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.15rem; flex-wrap: wrap; gap: 0.75rem;">
            <div>
                <h3 style="font-size: 1.25rem; font-weight: 800; color: #111827; margin: 0 0 0.2rem;">
                    Billing Receipts &amp; License Activations
                </h3>
                <p style="font-size: 0.84rem; color: #6b7280; margin: 0;">
                    Complete transaction receipts and license allocations attached to your workspace.
                </p>
            </div>
            <span class="badge-active-green">Paid in full ($0.00 sandbox charge)</span>
        </div>

        <?php if (!empty($billingHistory)): ?>
            <div style="overflow-x: auto; border: 1px solid #e5e7eb; border-radius: 8px;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.84rem;">
                    <thead>
                        <tr style="background: #fafaf9; border-bottom: 1px solid #e5e7eb;">
                            <th style="padding: 0.7rem 0.9rem; text-align: left; font-weight: 800; color: #374151;">Transaction Ref</th>
                            <th style="padding: 0.7rem 0.9rem; text-align: left; font-weight: 800; color: #374151;">Plan Name</th>
                            <th style="padding: 0.7rem 0.9rem; text-align: left; font-weight: 800; color: #374151;">Amount</th>
                            <th style="padding: 0.7rem 0.9rem; text-align: left; font-weight: 800; color: #374151;">Payment Method</th>
                            <th style="padding: 0.7rem 0.9rem; text-align: left; font-weight: 800; color: #374151;">License Key</th>
                            <th style="padding: 0.7rem 0.9rem; text-align: left; font-weight: 800; color: #374151;">Status</th>
                            <th style="padding: 0.7rem 0.9rem; text-align: right; font-weight: 800; color: #374151;">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($billingHistory as $b): ?>
                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                <td style="padding: 0.75rem 0.9rem; font-weight: 800; font-family: monospace; color: #111827;">
                                    <?= e($b['transaction_ref']) ?>
                                </td>
                                <td style="padding: 0.75rem 0.9rem; font-weight: 700;">
                                    <?= e($b['plan_name']) ?>
                                </td>
                                <td style="padding: 0.75rem 0.9rem; font-weight: 800; color: #111827;">
                                    $<?= number_format((float)$b['amount'], 2) ?> <?= e($b['currency']) ?>
                                </td>
                                <td style="padding: 0.75rem 0.9rem; color: #4b5563;">
                                    <?= e($b['payment_method']) ?>
                                </td>
                                <td style="padding: 0.75rem 0.9rem;">
                                    <code style="background: #fff7ed; color: #c2410c; padding: 0.15rem 0.45rem; border-radius: 4px; font-weight: 700; border: 1px solid #fed7aa; font-size: 0.8rem;">
                                        <?= e($b['license_key']) ?>
                                    </code>
                                </td>
                                <td style="padding: 0.75rem 0.9rem;">
                                    <span class="badge-active-green" style="text-transform: uppercase; font-size: 0.7rem;">
                                        <?= e($b['status']) ?>
                                    </span>
                                </td>
                                <td style="padding: 0.75rem 0.9rem; text-align: right; color: #6b7280; font-size: 0.8rem;">
                                    <?= e(date('M j, Y g:ia', strtotime($b['created_at']))) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="background: #fafaf9; border: 1px dashed #d1d5db; border-radius: 8px; padding: 1.5rem; text-align: center;">
                <div style="width: 32px; height: 32px; margin: 0 auto 0.35rem; color: #ea580c;"><?= posthog_icon('lock') ?></div>
                <div style="font-weight: 700; color: #111827;">Active <?= e($planInfo['plan_name']) ?>: <?= e($licenseKey) ?></div>
                <p style="font-size: 0.82rem; color: #6b7280; margin: 0.35rem 0 0;">
                    Your license was automatically provisioned with $0.00 sandbox charge. Detailed billing records appear here as payments are logged.
                </p>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
function copyKeyText(key, btn) {
    const showCopied = () => {
        const original = btn.innerHTML;
        btn.innerHTML = 'Copied!';
        btn.style.color = '#15803d';
        btn.style.borderColor = '#86efac';
        setTimeout(() => {
            btn.innerHTML = original;
            btn.style.color = '';
            btn.style.borderColor = '';
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
