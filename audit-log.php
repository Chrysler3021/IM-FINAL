<?php
/**
 * VendFlow - Adjustment Audit Log (PostHog UI + Real MySQL Pagination by 10)
 * Pure Vanilla PHP + MySQL PDO
 */
require_once __DIR__ . '/includes/functions.php';

$user = require_auth();
$db = get_db();
$userId = get_workspace_owner_id($user);

// Optional filters (machine & reason)
$filterMachine = isset($_GET['machine']) ? (int)$_GET['machine'] : 0;
$filterType    = trim($_GET['type'] ?? '');

$machinesStmt = $db->prepare("SELECT id, name FROM machines WHERE user_id = :uid ORDER BY name ASC");
$machinesStmt->execute([':uid' => $userId]);
$filterMachinesList = $machinesStmt->fetchAll();

$whereSql = "l.user_id = :uid";
$params   = [':uid' => $userId];

if ($filterMachine > 0) {
    $whereSql .= " AND l.machine_id = :mid";
    $params[':mid'] = $filterMachine;
}
if ($filterType !== '' && in_array($filterType, ['restock', 'manual_sale', 'damaged', 'set_exact', 'adjustment', 'initial'], true)) {
    $whereSql .= " AND l.change_type = :ctype";
    $params[':ctype'] = $filterType;
}

// Count total matching logs
$countStmt = $db->prepare("
    SELECT COUNT(*)
    FROM inventory_logs l
    JOIN products p ON l.product_id = p.id
    JOIN machines m ON l.machine_id = m.id
    WHERE {$whereSql}
");
$countStmt->execute($params);
$totalLogs = (int)$countStmt->fetchColumn();

// Paginate strictly by 10 rows per page
$perPage     = 10;
$totalPages  = max(1, (int)ceil($totalLogs / $perPage));
$pageNum     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
if ($pageNum > $totalPages) {
    $pageNum = $totalPages;
}
$offset      = ($pageNum - 1) * $perPage;
$startResult = $totalLogs > 0 ? ($offset + 1) : 0;
$endResult   = min($offset + $perPage, $totalLogs);

// Fetch paginated 10 rows
$logSql = "
    SELECT
        l.*,
        p.name AS product_name,
        p.sku AS product_sku,
        p.category AS product_category,
        m.name AS machine_name,
        m.location AS machine_location,
        COALESCE(l.actor_name, u.name) AS operator_name
    FROM inventory_logs l
    JOIN products p ON l.product_id = p.id
    JOIN machines m ON l.machine_id = m.id
    JOIN users u ON l.user_id = u.id
    WHERE {$whereSql}
    ORDER BY l.created_at DESC, l.id DESC
    LIMIT :limit OFFSET :offset
";
$logStmt = $db->prepare($logSql);
foreach ($params as $k => $v) {
    $logStmt->bindValue($k, $v);
}
$logStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$logStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$logStmt->execute();
$paginatedLogs = $logStmt->fetchAll();

// Helper for pagination URLs preserving filters
function build_audit_page_url(int $p, int $mach, string $type): string {
    $q = ['page' => $p];
    if ($mach > 0) $q['machine'] = $mach;
    if ($type !== '') $q['type'] = $type;
    return 'audit-log.php?' . http_build_query($q);
}

$pageTitle    = 'Audit Log';
$pageHeader   = 'Adjustment audit log (' . $totalLogs . ')';
$pageSubtitle = 'Chronological record of every manual restock, sales tally, spoilage removal, and count audit.';

require_once __DIR__ . '/includes/dashboard_header.php';
?>

<!-- Filter Bar -->
<form method="GET" action="audit-log.php" style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.15rem; flex-wrap: wrap;">
    <select name="machine" class="form-input-text" style="width: auto; min-width: 215px; padding: 0.48rem 0.8rem; font-size: 0.82rem;" onchange="this.form.submit()">
        <option value="0">All registered machines</option>
        <?php foreach ($filterMachinesList as $fm): ?>
            <option value="<?= (int)$fm['id'] ?>" <?= $filterMachine === (int)$fm['id'] ? 'selected' : '' ?>>
                <?= e($fm['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="type" class="form-input-text" style="width: auto; min-width: 195px; padding: 0.48rem 0.8rem; font-size: 0.82rem;" onchange="this.form.submit()">
        <option value="">All adjustment reasons</option>
        <option value="restock" <?= $filterType === 'restock' ? 'selected' : '' ?>>Restock load (+)</option>
        <option value="manual_sale" <?= $filterType === 'manual_sale' ? 'selected' : '' ?>>Sales tally (-)</option>
        <option value="damaged" <?= $filterType === 'damaged' ? 'selected' : '' ?>>Damaged / expired (-)</option>
        <option value="set_exact" <?= $filterType === 'set_exact' ? 'selected' : '' ?>>Physical audit</option>
        <option value="initial" <?= $filterType === 'initial' ? 'selected' : '' ?>>Initial stock</option>
        <option value="adjustment" <?= $filterType === 'adjustment' ? 'selected' : '' ?>>Record edit</option>
    </select>

    <?php if ($filterMachine > 0 || $filterType !== ''): ?>
        <a href="audit-log.php" class="btn-dash-outline" style="text-decoration: none; font-size: 0.78rem; padding: 0.45rem 0.75rem;">
            Clear filters
        </a>
    <?php endif; ?>
</form>

<!-- PostHog Audit Log Table Card -->
<div class="posthog-table-card">
    <div class="posthog-table-responsive">
        <table class="posthog-data-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Product &amp; SKU</th>
                    <th>Machine</th>
                    <th>Previous</th>
                    <th>Change</th>
                    <th>Resulting</th>
                    <th>Reason &amp; note</th>
                    <th>Operator</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($paginatedLogs)): ?>
                    <tr>
                        <td colspan="8" style="padding: 3rem 1.5rem; text-align: center; color: var(--text-muted);">
                            <div style="width:36px;height:36px;margin:0 auto 0.45rem;color:#94a3b8;"><?= posthog_icon('activity') ?></div>
                            <div style="font-weight: 800; color: var(--text-main); font-size: 0.95rem; margin-bottom: 0.25rem;">
                                No audit log entries recorded yet
                            </div>
                            <div style="font-size: 0.82rem;">
                                Add a product or log a restock adjustment to populate this chronological ledger.
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($paginatedLogs as $log):
                        $delta      = (int)$log['change_qty'];
                        $changeStr  = $delta > 0 ? '+' . $delta : (string)$delta;
                        $badgeInfo  = get_reason_badge_info($log['change_type']);
                        $tsFmt      = date('M j, Y H:i', strtotime($log['created_at']));
                        $skuAndCat  = $log['product_sku'] . ' (' . $log['product_category'] . ')';
                    ?>
                        <tr>
                            <!-- Timestamp -->
                            <td class="cell-timestamp">
                                <?= e($tsFmt) ?>
                            </td>

                            <!-- Product & SKU -->
                            <td>
                                <div class="cell-product-name">
                                    <?= e($log['product_name']) ?>
                                </div>
                                <div class="cell-product-sku">
                                    <?= e($skuAndCat) ?>
                                </div>
                            </td>

                            <!-- Machine -->
                            <td>
                                <div class="cell-machine-name">
                                    <?= e($log['machine_name']) ?>
                                </div>
                                <div class="cell-machine-loc">
                                    <?= e($log['machine_location']) ?>
                                </div>
                            </td>

                            <!-- Previous -->
                            <td class="cell-number">
                                <?= (int)$log['previous_qty'] ?>
                            </td>

                            <!-- Change -->
                            <td class="<?= $delta >= 0 ? 'change-positive' : 'change-negative' ?>">
                                <?= e($changeStr) ?>
                            </td>

                            <!-- Resulting -->
                            <td class="cell-number" style="font-weight: 700;">
                                <?= (int)$log['new_qty'] ?>
                            </td>

                            <!-- Reason & note -->
                            <td>
                                <span class="badge-pill <?= e($badgeInfo['class']) ?>">
                                    <?= e($badgeInfo['label']) ?>
                                </span>
                                <div class="cell-note">
                                    <?= e($log['notes']) ?>
                                </div>
                            </td>

                            <!-- Operator / Staff -->
                            <td class="cell-operator">
                                <?php if (!empty($log['staff_id'])): ?>
                                    <span style="font-weight: 700; color: #0284c7;"><?= e($log['operator_name']) ?></span>
                                    <span style="font-size: 0.68rem; background: #e0f2fe; color: #0369a1; padding: 0.12rem 0.4rem; border-radius: 4px; font-weight: 800; margin-left: 3px;">STAFF</span>
                                <?php else: ?>
                                    <?= e($log['operator_name']) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- PostHog Pagination (Limit by 10) -->
    <div class="posthog-pagination">
        <div>
            <?php if ($totalLogs > 0): ?>
                Showing <strong><?= $startResult ?></strong> to <strong><?= $endResult ?></strong> of <strong><?= $totalLogs ?></strong> entries
            <?php else: ?>
                Showing <strong>0</strong> entries (10 per page)
            <?php endif; ?>
        </div>

        <div class="pagination-pages">
            <?php if ($pageNum > 1): ?>
                <a href="<?= e(build_audit_page_url($pageNum - 1, $filterMachine, $filterType)) ?>" class="page-link-btn">&larr; Previous</a>
            <?php else: ?>
                <span class="page-link-btn disabled">&larr; Previous</span>
            <?php endif; ?>

            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="<?= e(build_audit_page_url($p, $filterMachine, $filterType)) ?>" class="page-link-btn <?= $p === $pageNum ? 'active' : '' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>

            <?php if ($pageNum < $totalPages): ?>
                <a href="<?= e(build_audit_page_url($pageNum + 1, $filterMachine, $filterType)) ?>" class="page-link-btn">Next &rarr;</a>
            <?php else: ?>
                <span class="page-link-btn disabled">Next &rarr;</span>
            <?php endif; ?>
        </div>
    </div>

</div>

<div style="margin-top: 1.75rem;">
    <a href="dashboard.php" class="back-landing-link">&larr; Back to Inventory Overview</a>
</div>

<?php require_once __DIR__ . '/includes/dashboard_footer.php'; ?>
