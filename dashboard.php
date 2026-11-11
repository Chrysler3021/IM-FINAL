<?php
/**
 * VendFlow - Inventory Overview (Live MySQL Workspace Dashboard)
 * Pure Vanilla PHP + MySQL PDO
 */
require_once __DIR__ . '/includes/functions.php';

$user = require_auth();
$db = get_db();
$userId = get_workspace_owner_id($user);
$currency = $user['currency'] ?: '$';

// 1. Live Top 4 KPI Metrics (via consolidated workspace stats helper)
$kpis = get_workspace_stats($db, $userId);

$totalSkus    = $kpis['total_skus'];
$totalUnits   = $kpis['total_units'];
$retailValue  = $kpis['retail_value'];
$belowParSkus = $kpis['low_stock_count'];

// 2. Live Machine Fleet List
$machStmt = $db->prepare("
    SELECT
        m.*,
        COUNT(p.id) AS sku_count,
        COALESCE(SUM(p.quantity), 0) AS stocked_units,
        COALESCE(SUM(CASE WHEN p.quantity <= p.min_threshold THEN 1 ELSE 0 END), 0) AS low_count
    FROM machines m
    LEFT JOIN products p ON m.id = p.machine_id
    WHERE m.user_id = :uid
    GROUP BY m.id
    ORDER BY m.created_at ASC, m.id ASC
");
$machStmt->execute([':uid' => $userId]);
$machinesFleet = $machStmt->fetchAll();
$machineCount = count($machinesFleet);

// 3. Live Restock Pick-List (Top 6 most urgent SKUs at or below par)
$pickStmt = $db->prepare("
    SELECT
        p.*,
        m.name AS machine_name,
        m.location AS machine_location
    FROM products p
    JOIN machines m ON p.machine_id = m.id
    WHERE p.user_id = :uid AND p.quantity <= p.min_threshold
    ORDER BY p.quantity ASC, (p.min_threshold - p.quantity) DESC
    LIMIT 6
");
$pickStmt->execute([':uid' => $userId]);
$pickList = $pickStmt->fetchAll();

// 4. Live Recent Stock Adjustments (Latest 5 rows from inventory_logs)
$recentStmt = $db->prepare("
    SELECT
        l.*,
        p.name AS product_name,
        p.sku AS product_sku,
        m.name AS machine_name
    FROM inventory_logs l
    JOIN products p ON l.product_id = p.id
    JOIN machines m ON l.machine_id = m.id
    WHERE l.user_id = :uid
    ORDER BY l.created_at DESC, l.id DESC
    LIMIT 5
");
$recentStmt->execute([':uid' => $userId]);
$recentAdjustments = $recentStmt->fetchAll();

$pageTitle    = 'Inventory Overview';
$pageHeader   = 'Inventory overview';
$pageSubtitle = 'Fleet inventory ledger, low-stock par alerts, and recent manual count adjustments.';

require_once __DIR__ . '/includes/dashboard_header.php';
?>

<!-- =======================================================================
     Top 4 KPI Metrics
     ======================================================================= -->
<div class="overview-kpi-grid">

    <div class="kpi-card" style="<?= $belowParSkus > 0 ? 'background: #fffbeb; border-color: #fde68a;' : '' ?>">
        <span class="kpi-label" style="<?= $belowParSkus > 0 ? 'color: #b45309;' : '' ?>">Items at or below par</span>
        <div class="kpi-value <?= $belowParSkus > 0 ? 'amber' : '' ?>">
            <?= $belowParSkus ?> <?= $belowParSkus === 1 ? 'SKU' : 'SKUs' ?>
        </div>
        <span class="kpi-subtext">
            <?= $belowParSkus > 0 ? 'Needs stock replenishment' : ($totalSkus === 0 ? 'No SKUs cataloged yet' : 'Every slot above par') ?>
        </span>
    </div>

    <div class="kpi-card">
        <span class="kpi-label">Registered machines</span>
        <div class="kpi-value"><?= $machineCount ?></div>
        <span class="kpi-subtext">Physical cabinet locations</span>
    </div>

    <div class="kpi-card">
        <span class="kpi-label">Cataloged SKUs</span>
        <div class="kpi-value"><?= $totalSkus ?></div>
        <span class="kpi-subtext">Snack &amp; beverage slots</span>
    </div>

    <div class="kpi-card">
        <span class="kpi-label">Total stocked units</span>
        <div class="kpi-value"><?= $totalUnits ?></div>
        <span class="kpi-subtext" style="color: #15803d; font-weight: 600;">
            Retail value <strong><?= format_currency($retailValue, $currency) ?></strong>
        </span>
    </div>

</div>

<!-- =======================================================================
     Middle Grid: Restock Pick-List (Left) & Machine Fleet (Right)
     ======================================================================= -->
<div class="overview-split-grid">

    <!-- Left: Live Low-Stock Alerts -->
    <div class="overview-card-box">
        <div class="overview-card-header">
            <div>
                <div class="overview-card-title">Low stock replenishment</div>
                <div class="overview-card-desc">SKUs where recorded units have reached or dropped below par</div>
            </div>
            <a href="low-stock.php" class="card-header-link">Open all alerts (<?= $belowParSkus ?>) &rarr;</a>
        </div>

        <?php if ($totalSkus === 0): ?>
            <div style="padding: 2rem 1rem; text-align: center; border: 1px dashed var(--border-light); border-radius: 8px; background: #fafaf9; margin-top: 0.25rem;">
                <div style="width: 36px; height: 36px; margin: 0 auto 0.4rem; color: #94a3b8;"><?= posthog_icon('box') ?></div>
                <div style="font-weight: 800; color: var(--text-main); font-size: 0.92rem; margin-bottom: 0.25rem;">No products cataloged yet</div>
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem;">Add your first snack or beverage SKU to monitor par thresholds.</div>
                <button type="button" class="btn-dash-primary" onclick="openAddProductModal()">+ Add first product</button>
            </div>
        <?php elseif (empty($pickList)): ?>
            <div style="padding: 2rem 1rem; text-align: center; border: 1px dashed #bbf7d0; border-radius: 8px; background: #f0fdf4; margin-top: 0.25rem;">
                <div style="width: 32px; height: 32px; margin: 0 auto 0.35rem; color: #16a34a;"><?= posthog_icon('check_circle') ?></div>
                <div style="font-weight: 800; color: #166534; font-size: 0.92rem;">Every machine slot is above par</div>
                <div style="font-size: 0.8rem; color: #15803d; margin-top: 0.2rem;">No urgent restock pulls needed across your <?= $totalSkus ?> cataloged SKUs.</div>
            </div>
        <?php else: ?>
            <div class="posthog-table-responsive" style="margin-top: 0.35rem;">
                <table class="posthog-data-table">
                    <thead>
                        <tr>
                            <th>Product &amp; SKU</th>
                            <th>Machine</th>
                            <th>Stock / Par</th>
                            <th>Load</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pickList as $item):
                            $qty    = (int)$item['quantity'];
                            $par    = (int)$item['min_threshold'];
                            $needed = max(1, $par - $qty);
                            $jsAdjustPayload = htmlspecialchars(json_encode([
                                'id'            => (int)$item['id'],
                                'name'          => $item['name'],
                                'machine_name'  => $item['machine_name'],
                                'quantity'      => $qty,
                                'min_threshold' => $par
                            ]), ENT_QUOTES, 'UTF-8');
                        ?>
                            <tr>
                                <td>
                                    <div class="cell-product-name"><?= e($item['name']) ?></div>
                                    <div class="cell-product-sku"><?= e($item['sku']) ?></div>
                                </td>
                                <td>
                                    <div class="cell-machine-name" style="font-size: 0.8rem;"><?= e($item['machine_name']) ?></div>
                                    <div class="cell-machine-loc"><?= e($item['machine_location']) ?></div>
                                </td>
                                <td>
                                    <span style="font-weight: 800; color: <?= $qty === 0 ? '#dc2626' : '#b45309' ?>;">
                                        <?= $qty ?>
                                    </span>
                                    <span style="color: var(--text-muted);">/ <?= $par ?></span>
                                </td>
                                <td>
                                    <span class="badge-load-green" style="background: #fef3c7; border-color: #fde68a; color: #b45309;">
                                        +<?= $needed ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn-log-restock" onclick="openAdjustStockModal(<?= $jsAdjustPayload ?>, 'restock')">
                                        Log restock
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right: Live Machine Fleet List -->
    <div class="overview-card-box">
        <div class="overview-card-header">
            <div>
                <div class="overview-card-title">Machine fleet</div>
                <div class="overview-card-desc">Stocked capacity by placement location</div>
            </div>
            <a href="machines.php" class="card-header-link">Manage machines &rarr;</a>
        </div>

        <?php if (empty($machinesFleet)): ?>
            <div style="padding: 2rem 1rem; text-align: center; border: 1px dashed var(--border-light); border-radius: 8px; background: #fafaf9;">
                <div style="font-weight: 800; color: var(--text-main); font-size: 0.9rem; margin-bottom: 0.35rem;">No machines registered</div>
                <button type="button" class="btn-dash-outline" onclick="openAddMachineModal()">+ Add machine</button>
            </div>
        <?php else: ?>
            <div class="machine-fleet-list">
                <?php foreach ($machinesFleet as $mf):
                    $mSkus  = (int)$mf['sku_count'];
                    $mUnits = (int)$mf['stocked_units'];
                    $mLow   = (int)$mf['low_count'];
                ?>
                    <div class="machine-fleet-card">
                        <div class="machine-card-top">
                            <div>
                                <a href="inventory.php?machine=<?= (int)$mf['id'] ?>" class="cell-machine-name" style="text-decoration: underline; color: var(--text-main);">
                                    <?= e($mf['name']) ?>
                                </a>
                                <div class="cell-machine-loc"><?= e($mf['location']) ?></div>
                            </div>
                            <?php if ($mf['status'] === 'active'): ?>
                                <span class="badge-active-green">Active</span>
                            <?php elseif ($mf['status'] === 'maintenance'): ?>
                                <span class="badge-below-par">Maintenance</span>
                            <?php else: ?>
                                <span class="badge-pill badge-initial-gray">Inactive</span>
                            <?php endif; ?>
                        </div>
                        <div class="machine-card-bottom">
                            <span><strong><?= $mSkus ?></strong> <?= $mSkus === 1 ? 'SKU' : 'SKUs' ?>, <strong><?= $mUnits ?></strong> units</span>
                            <?php if ($mSkus === 0): ?>
                                <button type="button" onclick="openAddProductModal(<?= (int)$mf['id'] ?>)" style="background: none; border: none; color: #2563eb; font-size: 0.75rem; font-weight: 700; cursor: pointer;">
                                    + Add SKU
                                </button>
                            <?php elseif ($mLow > 0): ?>
                                <span class="badge-below-par"><?= $mLow ?> below par</span>
                            <?php else: ?>
                                <span class="badge-active-green">All at par</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- =======================================================================
     Bottom Section: Recent Stock Adjustments Table
     ======================================================================= -->
<div class="overview-card-box" style="margin-bottom: 2rem;">
    <div class="overview-card-header">
        <div>
            <div class="overview-card-title">Recent stock adjustments</div>
            <div class="overview-card-desc">Latest manual restocks, sales tallies, and physical counts</div>
        </div>
        <a href="audit-log.php" class="card-header-link">Full audit log &rarr;</a>
    </div>

    <?php if (empty($recentAdjustments)): ?>
        <div style="padding: 1.75rem 1rem; text-align: center; border: 1px dashed var(--border-light); border-radius: 8px; background: #fafaf9; color: var(--text-muted); font-size: 0.84rem;">
            No stock adjustments logged yet. Adding a product or logging a restock will appear here immediately.
        </div>
    <?php else: ?>
        <div class="posthog-table-responsive">
            <table class="posthog-data-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Product</th>
                        <th>Machine</th>
                        <th>Previous</th>
                        <th>Delta</th>
                        <th>New count</th>
                        <th>Reason &amp; note</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentAdjustments as $adj):
                        $delta     = (int)$adj['change_qty'];
                        $deltaText = $delta > 0 ? '+' . $delta : (string)$delta;
                        $badgeInfo = get_reason_badge_info($adj['change_type']);
                        $tsFmt     = date('M j, H:i', strtotime($adj['created_at']));
                    ?>
                        <tr>
                            <td class="cell-timestamp"><?= e($tsFmt) ?></td>
                            <td>
                                <div class="cell-product-name"><?= e($adj['product_name']) ?></div>
                                <div class="cell-product-sku"><?= e($adj['product_sku']) ?></div>
                            </td>
                            <td>
                                <div class="cell-machine-name" style="font-size: 0.8rem;"><?= e($adj['machine_name']) ?></div>
                            </td>
                            <td class="cell-number"><?= (int)$adj['previous_qty'] ?></td>
                            <td class="<?= $delta >= 0 ? 'change-positive' : 'change-negative' ?>"><?= e($deltaText) ?></td>
                            <td class="cell-number" style="font-weight: 700;"><?= (int)$adj['new_qty'] ?></td>
                            <td>
                                <span class="badge-pill <?= e($badgeInfo['class']) ?>"><?= e($badgeInfo['label']) ?></span>
                                <div class="cell-note"><?= e($adj['notes']) ?></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div>
    <a href="partner.php" class="back-landing-link">&larr; Switch back to Operator Portal WebOS</a>
    &nbsp;&middot;&nbsp;
    <a href="index.php" class="back-landing-link">Public Vending Directory &rarr;</a>
</div>

<?php require_once __DIR__ . '/includes/dashboard_footer.php'; ?>
