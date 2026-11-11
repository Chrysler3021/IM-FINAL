<?php
/**
 * Inventory Adjustment History & Audit Trail
 */
require_once __DIR__ . '/config/auth.php';
$user = require_auth();
$db = get_db();
$userId = $user['id'];

$query = "
    SELECT 
        l.*,
        p.name AS product_name,
        p.sku AS product_sku,
        p.category AS product_category,
        m.name AS machine_name,
        m.location AS machine_location,
        u.name AS user_name
    FROM inventory_logs l
    JOIN products p ON l.product_id = p.id
    JOIN machines m ON l.machine_id = m.id
    JOIN users u ON l.user_id = u.id
    WHERE l.user_id = :uid
    ORDER BY l.created_at DESC
    LIMIT 200
";
$stmt = $db->prepare($query);
$stmt->execute([':uid' => $userId]);
$logs = $stmt->fetchAll();

$reasonLabels = [
    'restock' => 'Restock load',
    'manual_sale' => 'Sales tally',
    'damaged' => 'Damaged or expired',
    'set_exact' => 'Physical audit',
    'adjustment' => 'Record edit',
    'initial' => 'Initial stock',
];

$pageTitle = "Stock Audit Log";
$pageHeader = "Audit log";
require_once __DIR__ . '/includes/dashboard_header.php';
?>

<div class="space-y-5 tabular-nums">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h2 class="font-display font-bold text-xl text-enamel" style="font-size: 1.35rem;">Adjustment audit log (<?= count($logs) ?>)</h2>
            <p class="text-xs text-steel mt-0.5">Chronological record of every manual restock, sales tally, spoilage removal, and count audit.</p>
        </div>
    </div>

    <div class="bg-tray border border-coil overflow-hidden">
        <?php if (empty($logs)): ?>
            <div class="p-10 text-sm text-steel">
                No stock adjustments have been recorded yet. Adjust any SKU count on the <a href="inventory.php" class="font-bold text-tote underline">product ledger</a> to create the first entry.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-paper text-steel border-b border-coil">
                        <tr>
                            <th class="py-3 px-4 font-bold text-enamel">Timestamp</th>
                            <th class="py-3 px-3 font-bold text-enamel">Product &amp; SKU</th>
                            <th class="py-3 px-3 font-bold text-enamel">Machine</th>
                            <th class="py-3 px-3 text-right font-bold text-enamel">Previous</th>
                            <th class="py-3 px-3 text-right font-bold text-enamel">Change</th>
                            <th class="py-3 px-3 text-right font-bold text-enamel">Resulting</th>
                            <th class="py-3 px-3 font-bold text-enamel">Reason &amp; note</th>
                            <th class="py-3 px-4 font-bold text-enamel">Operator</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-coil text-steel">
                        <?php foreach ($logs as $log):
                            $reasonText = $reasonLabels[$log['change_type']] ?? ucfirst(str_replace('_', ' ', $log['change_type']));
                        ?>
                            <tr class="hover:bg-paper/60">
                                <td class="py-3.5 px-4 whitespace-nowrap text-steel">
                                    <?= date('M d, Y H:i', strtotime($log['created_at'])) ?>
                                </td>

                                <td class="py-3.5 px-3">
                                    <span class="font-bold text-enamel block text-sm"><?= e($log['product_name']) ?></span>
                                    <span class="text-steel"><?= e($log['product_sku']) ?> (<?= e($log['product_category']) ?>)</span>
                                </td>

                                <td class="py-3.5 px-3">
                                    <span class="font-bold text-enamel block"><?= e($log['machine_name']) ?></span>
                                    <span class="text-steel block"><?= e($log['machine_location']) ?></span>
                                </td>

                                <td class="py-3.5 px-3 text-right text-steel">
                                    <?= (int)$log['previous_qty'] ?>
                                </td>

                                <td class="py-3.5 px-3 text-right">
                                    <?php if ((int)$log['change_qty'] > 0): ?>
                                        <span class="inline-block px-2 py-0.5 rounded-tag font-bold text-parGreen bg-parGreenBg border border-parGreenBorder">
                                            +<?= (int)$log['change_qty'] ?>
                                        </span>
                                    <?php elseif ((int)$log['change_qty'] < 0): ?>
                                        <span class="inline-block px-2 py-0.5 rounded-tag font-bold text-stockout bg-stockoutBg border border-stockoutBorder">
                                            <?= (int)$log['change_qty'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-block px-2 py-0.5 rounded-tag text-steel bg-paper border border-coil font-bold">
                                            0
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-3 text-right font-bold text-enamel">
                                    <?= (int)$log['new_qty'] ?>
                                </td>

                                <td class="py-3.5 px-3">
                                    <span class="inline-block px-2 py-0.5 rounded-tag font-bold border <?= 
                                        $log['change_type'] === 'restock' ? 'bg-parGreenBg text-parGreen border-parGreenBorder' : 
                                        ($log['change_type'] === 'damaged' ? 'bg-stockoutBg text-stockout border-stockoutBorder' : 
                                        ($log['change_type'] === 'manual_sale' ? 'bg-toteTint text-tote border-coil' : 'bg-paper text-enamel border-coil'))
                                    ?>">
                                        <?= e($reasonText) ?>
                                    </span>
                                    <?php if (!empty($log['notes'])): ?>
                                        <span class="block text-steel mt-1"><?= e($log['notes']) ?></span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4 text-enamel font-bold">
                                    <?= e(!empty($log['actor_name']) ? $log['actor_name'] : $log['user_name']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/includes/dashboard_footer.php'; ?>
