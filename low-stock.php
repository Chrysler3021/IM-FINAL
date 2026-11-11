<?php
/**
 * VendFlow - Low-Stock Alerts & Restock Dispatch Queue
 * Operator Workspace • PostHog Design System
 */
require_once __DIR__ . '/includes/functions.php';

$user = require_auth();
$db = get_db();
$userId = get_workspace_owner_id($user);
$currency = $user['currency'] ?: '$';

// Handle POST Mutations (Par Advisor 1-Click Optimization)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security token expired. Please submit the request again.');
        redirect('low-stock.php');
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'apply_par_recommendation') {
        $prodId = (int)($_POST['product_id'] ?? 0);
        $newPar = max(1, (int)($_POST['recommended_par'] ?? 0));

        if ($prodId > 0 && $newPar > 0) {
            $ok = update_product_par_threshold($db, $userId, $prodId, $newPar);
            if ($ok) {
                set_flash('success', "Updated par capacity to {$newPar} units per Par Advisor recommendation.");
            } else {
                set_flash('error', "Could not update par threshold for the selected product.");
            }
        }
        redirect('low-stock.php');
    }
}

// Fetch all registered machines for operator dropdown filtering
$mStmt = $db->prepare("SELECT id, name, location FROM machines WHERE user_id = :uid ORDER BY name ASC");
$mStmt->execute([':uid' => $userId]);
$machinesList = $mStmt->fetchAll();

// Fetch low stock items across user's machines
$stmt = $db->prepare("
    SELECT 
        p.*, 
        m.name AS machine_name,
        m.location AS machine_location,
        (p.min_threshold - p.quantity) AS units_needed
    FROM products p
    JOIN machines m ON p.machine_id = m.id
    WHERE p.user_id = :uid AND p.quantity <= p.min_threshold
    ORDER BY (p.quantity = 0) DESC, units_needed DESC, p.quantity ASC, p.name ASC
");
$stmt->execute([':uid' => $userId]);
$lowStockItems = $stmt->fetchAll();

$totalLowItems        = count($lowStockItems);
$outOfStockItems      = array_filter($lowStockItems, fn($i) => (int)$i['quantity'] === 0);
$outOfStockCount      = count($outOfStockItems);
$lowStockCount        = $totalLowItems - $outOfStockCount;
$totalRestockNeeded   = array_sum(array_map(fn($i) => max(1, (int)$i['units_needed']), $lowStockItems));

$affectedMachineIds   = array_unique(array_map(fn($i) => (int)$i['machine_id'], $lowStockItems));
$affectedMachineCount = count($affectedMachineIds);
$totalMachines        = count($machinesList);

$pageTitle    = "Low Stock Alerts";
$pageHeader   = "Low stock alerts";
$pageSubtitle = "Route restock pick-list & deficit queue across your active vending fleet.";
require_once __DIR__ . '/includes/dashboard_header.php';
?>

<style>
/* Specific Enhancements for Low Stock Alert Ledger */
.lowstock-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.lowstock-kpi-card {
    background: #ffffff;
    border: 1px solid var(--border-light);
    border-radius: 10px;
    padding: 1.15rem 1.25rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    display: flex;
    flex-direction: column;
    transition: transform 0.15s ease, border-color 0.15s ease;
}

.lowstock-kpi-card.warning-tint {
    background: #fffbeb;
    border-color: #fde68a;
}

.lowstock-kpi-card.danger-tint {
    background: #fef2f2;
    border-color: #fecaca;
}

.kpi-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.35rem;
}

.kpi-title-text {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.kpi-title-row svg {
    width: 16px;
    height: 16px;
}

.kpi-big-num {
    font-size: 1.85rem;
    font-weight: 800;
    color: var(--text-main);
    line-height: 1.1;
    margin: 0.2rem 0;
}

.kpi-big-num.amber {
    color: #b45309;
}

.kpi-big-num.critical {
    color: #dc2626;
}

.kpi-big-num.blue {
    color: #2563eb;
}

.kpi-sub-desc {
    font-size: 0.75rem;
    color: var(--text-muted);
    line-height: 1.4;
}

/* Table Progress Bar */
.mini-par-track {
    width: 70px;
    height: 6px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
    margin-top: 4px;
}

.mini-par-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 0.3s ease;
}

.mini-par-fill.fill-out {
    width: 0%;
    background: #dc2626;
}

.mini-par-fill.fill-crit {
    background: #dc2626;
}

.mini-par-fill.fill-warn {
    background: #f59e0b;
}

.mini-par-fill.fill-nominal {
    background: #10b981;
}

/* Badge Tags */
.unit-load-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 0.22rem 0.65rem;
    border-radius: 999px;
    font-size: 0.76rem;
    font-weight: 800;
    line-height: 1.2;
}

.unit-load-pill.pill-out {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fca5a5;
}

.unit-load-pill.pill-low {
    background: #fef3c7;
    color: #b45309;
    border: 1px solid #fde68a;
}

.product-row-thumb {
    width: 36px;
    height: 36px;
    border-radius: 6px;
    object-fit: contain;
    background: #f8fafc;
    border: 1px solid var(--border-light);
    flex-shrink: 0;
}

.btn-restock-action {
    background: #f54e00;
    color: #ffffff;
    border: none;
    border-radius: 6px;
    padding: 0.4rem 0.85rem;
    font-size: 0.78rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    transition: background 0.15s ease, transform 0.12s ease;
}

.btn-restock-action:hover {
    background: #d84300;
    transform: translateY(-1px);
}

.btn-restock-action svg {
    width: 12px;
    height: 12px;
}

/* Info note at bottom */
.lowstock-info-card {
    background: #ffffff;
    border: 1px solid var(--border-light);
    border-radius: 10px;
    padding: 1.15rem 1.4rem;
    margin-top: 1.75rem;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    font-size: 0.82rem;
    color: var(--text-muted);
}

.lowstock-info-card svg {
    width: 20px;
    height: 20px;
    color: #3b82f6;
    flex-shrink: 0;
    margin-top: 2px;
}

/* Print Styles */
@media print {
    .sidebar,
    .top-accent-bar,
    .dash-action-group,
    .catalog-toolbar,
    .btn-restock-action,
    .lowstock-info-card,
    .no-print {
        display: none !important;
    }

    body,
    .dashboard-body,
    .dashboard-wrapper,
    .main-canvas {
        background: #ffffff !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }

    .overview-card-box {
        border: 1px solid #cbd5e1 !important;
        box-shadow: none !important;
    }

    .posthog-data-table th,
    .posthog-data-table td {
        padding: 0.5rem 0.6rem !important;
        font-size: 9pt !important;
    }
}
</style>

<!-- 1. Top KPI Summary Grid -->
<div class="lowstock-kpi-grid">

    <!-- Card 1: Total SKUs Below Par -->
    <div class="lowstock-kpi-card <?= $totalLowItems > 0 ? 'warning-tint' : '' ?>">
        <div class="kpi-title-row">
            <span class="kpi-title-text" style="<?= $totalLowItems > 0 ? 'color: #b45309;' : '' ?>">SKUs at or below par</span>
            <span style="color: <?= $totalLowItems > 0 ? '#b45309' : '#94a3b8' ?>;"><?= posthog_icon('warning') ?></span>
        </div>
        <div class="kpi-big-num <?= $totalLowItems > 0 ? 'amber' : '' ?>">
            <?= $totalLowItems ?> <span style="font-size: 0.95rem; font-weight: 600;"><?= $totalLowItems === 1 ? 'item' : 'items' ?></span>
        </div>
        <div class="kpi-sub-desc">
            <?= $totalLowItems > 0 ? 'Recorded inventory &le; par threshold' : 'All spirals are currently healthy' ?>
        </div>
    </div>

    <!-- Card 2: Empty Spirals (0 Units) -->
    <div class="lowstock-kpi-card <?= $outOfStockCount > 0 ? 'danger-tint' : '' ?>">
        <div class="kpi-title-row">
            <span class="kpi-title-text" style="<?= $outOfStockCount > 0 ? 'color: #dc2626;' : '' ?>">Empty spirals (0 units)</span>
            <span style="color: <?= $outOfStockCount > 0 ? '#dc2626' : '#94a3b8' ?>;"><?= posthog_icon('trash') ?></span>
        </div>
        <div class="kpi-big-num <?= $outOfStockCount > 0 ? 'critical' : '' ?>">
            <?= $outOfStockCount ?> <span style="font-size: 0.95rem; font-weight: 600;"><?= $outOfStockCount === 1 ? 'item' : 'items' ?></span>
        </div>
        <div class="kpi-sub-desc">
            <?= $outOfStockCount > 0 ? 'Immediate customer stockout risk' : 'Zero completely depleted items' ?>
        </div>
    </div>

    <!-- Card 3: Total Load Needed -->
    <div class="lowstock-kpi-card">
        <div class="kpi-title-row">
            <span class="kpi-title-text">Tote load needed</span>
            <span style="color: #2563eb;"><?= posthog_icon('box') ?></span>
        </div>
        <div class="kpi-big-num blue">
            <?= $totalRestockNeeded ?> <span style="font-size: 0.95rem; font-weight: 600;"><?= $totalRestockNeeded === 1 ? 'unit' : 'units' ?></span>
        </div>
        <div class="kpi-sub-desc">
            Minimum product pull to restore full route par
        </div>
    </div>

    <!-- Card 4: Service Stops -->
    <div class="lowstock-kpi-card">
        <div class="kpi-title-row">
            <span class="kpi-title-text">Service stops needed</span>
            <span style="color: #6366f1;"><?= posthog_icon('building') ?></span>
        </div>
        <div class="kpi-big-num" style="color: #4338ca;">
            <?= $affectedMachineCount ?> <span style="font-size: 0.95rem; font-weight: 600;">/ <?= $totalMachines ?> machines</span>
        </div>
        <div class="kpi-sub-desc">
            <?= $affectedMachineCount > 0 ? 'Cabinets with depleted slots requiring dispatch' : 'No service dispatches required today' ?>
        </div>
    </div>

</div>

<!-- 2. Interactive Search & Filter Toolbar -->
<div class="catalog-toolbar no-print">
    <div style="display: flex; align-items: center; gap: 0.75rem; flex: 1; flex-wrap: wrap;">
        
        <!-- Search Input -->
        <div class="search-box-wrapper" style="max-width: 320px;">
            <span class="search-icon-inside" style="width:14px;height:14px;display:inline-flex;align-items:center;color:#94a3b8;">
                <?= posthog_icon('search') ?>
            </span>
            <input
                type="text"
                class="catalog-search-input"
                placeholder="Search snack, SKU, or machine..."
                id="lowstock-search"
                oninput="filterLowStockQueue()"
            >
        </div>

        <!-- Machine Filter Dropdown -->
        <select
            id="lowstock-machine-filter"
            class="form-input-text"
            style="width: auto; min-width: 200px; padding: 0.48rem 0.85rem; font-size: 0.82rem;"
            onchange="filterLowStockQueue()"
        >
            <option value="">All machines (<?= $totalMachines ?>)</option>
            <?php foreach ($machinesList as $m): ?>
                <option value="<?= (int)$m['id'] ?>"><?= e($m['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <!-- Status Filter Pills -->
        <div class="filter-pills-container" style="margin-bottom: 0;">
            <button type="button" class="filter-chip active" id="chip-all" onclick="setStatusFilter('all', this)">
                All Alerts <span class="chip-count"><?= $totalLowItems ?></span>
            </button>
            <button type="button" class="filter-chip" id="chip-out" onclick="setStatusFilter('out', this)">
                <span style="color: #dc2626; margin-right: 4px;"><?= posthog_icon('warning') ?></span>
                Empty (0) <span class="chip-count"><?= $outOfStockCount ?></span>
            </button>
            <button type="button" class="filter-chip chip-warning" id="chip-low" onclick="setStatusFilter('low', this)">
                Low Stock (&le; Par) <span class="chip-count"><?= $lowStockCount ?></span>
            </button>
        </div>

    </div>

    <!-- Right: Print and Nav Actions -->
    <div style="display: flex; align-items: center; gap: 0.65rem;">
        <button
            type="button"
            class="btn-dash-outline"
            onclick="window.print()"
            title="Print physical warehouse pick sheet"
        >
            <span style="width: 14px; height: 14px; display: inline-flex; vertical-align: -2px; margin-right: 4px;"><?= posthog_icon('document') ?></span>
            Print pick-list
        </button>
        <a href="inventory.php" class="btn-dash-outline" style="text-decoration: none;">
            Full catalog &rarr;
        </a>
    </div>
</div>

<!-- 3. Dispatch Pick-List Table Card -->
<div class="overview-card-box">
    
    <div class="overview-card-header">
        <div>
            <div class="overview-card-title">Route restock dispatch queue</div>
            <div class="overview-card-desc">Ordered from empty spirals first to largest unit deficit</div>
        </div>
        <div>
            <span class="badge-pill badge-initial-gray" id="visible-count-badge">
                <?= $totalLowItems ?> <?= $totalLowItems === 1 ? 'alert' : 'alerts' ?> listed
            </span>
        </div>
    </div>

    <?php if (empty($lowStockItems)): ?>
        <!-- Healthy Fleet State -->
        <div style="padding: 3.5rem 1.5rem; text-align: center; background: #f0fdf4; border: 1px dashed #bbf7d0; border-radius: 8px;">
            <div style="width: 44px; height: 44px; margin: 0 auto 0.75rem; color: #16a34a;"><?= posthog_icon('check_circle') ?></div>
            <h3 style="font-size: 1.15rem; font-weight: 800; color: #166534; margin-bottom: 0.35rem;">Every machine slot is above par!</h3>
            <p style="font-size: 0.88rem; color: #15803d; max-width: 500px; margin: 0 auto 1.25rem;">
                No snack or drink items currently require restocking across your <?= $totalMachines ?> registered vending machines.
            </p>
            <div style="display: flex; gap: 0.75rem; justify-content: center;">
                <a href="inventory.php" class="btn-dash-primary" style="text-decoration: none; padding: 0.55rem 1.25rem;">
                    View product &amp; stock ledger
                </a>
                <a href="dashboard.php" class="btn-dash-outline" style="text-decoration: none; padding: 0.55rem 1.25rem;">
                    Back to overview
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="posthog-table-responsive">
            <table class="posthog-data-table" id="lowstock-table">
                <thead>
                    <tr>
                        <th style="width: 32%;">Product &amp; Category</th>
                        <th style="width: 14%;">SKU</th>
                        <th style="width: 22%;">Machine &amp; Placement</th>
                        <th style="width: 12%; text-align: right;">Current / Par</th>
                        <th style="width: 10%; text-align: right;">Tote Load</th>
                        <th style="width: 10%; text-align: right;" class="no-print">Action</th>
                    </tr>
                </thead>
                <tbody id="lowstock-tbody">
                    <?php foreach ($lowStockItems as $item):
                        $qty       = (int)$item['quantity'];
                        $par       = max(1, (int)$item['min_threshold']);
                        $isOut     = $qty === 0;
                        $needed    = max(1, $par - $qty);
                        $percent   = min(100, (int)round(($qty / $par) * 100));
                        $fillClass = $isOut ? 'fill-out' : ($percent <= 40 ? 'fill-crit' : 'fill-warn');
                        $imageSrc  = get_product_image($item);

                        $searchHaystack = strtolower(
                            $item['name'] . ' ' .
                            $item['sku'] . ' ' .
                            $item['category'] . ' ' .
                            $item['machine_name'] . ' ' .
                            $item['machine_location']
                        );

                        $jsPayload = htmlspecialchars(json_encode([
                            'id'            => (int)$item['id'],
                            'name'          => $item['name'],
                            'machine_name'  => $item['machine_name'],
                            'quantity'      => $qty,
                            'min_threshold' => $par
                        ]), ENT_QUOTES, 'UTF-8');
                    ?>
                        <tr
                            class="lowstock-row <?= $isOut ? 'stockout-row' : '' ?>"
                            data-machine-id="<?= (int)$item['machine_id'] ?>"
                            data-is-out="<?= $isOut ? '1' : '0' ?>"
                            data-search-haystack="<?= e($searchHaystack) ?>"
                        >
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <img
                                        src="<?= e($imageSrc) ?>"
                                        alt="<?= e($item['name']) ?>"
                                        class="product-row-thumb"
                                        onerror="this.src='assets/images/vendos/vendo-candies.jpg'"
                                    >
                                    <div>
                                        <div class="cell-product-name"><?= e($item['name']) ?></div>
                                        <div class="cell-product-sku"><?= e($item['category']) ?></div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <span class="badge-pill badge-initial-gray" style="font-family: monospace; font-size: 0.75rem;">
                                    <?= e($item['sku']) ?>
                                </span>
                            </td>

                            <td>
                                <div class="cell-machine-name"><?= e($item['machine_name']) ?></div>
                                <div class="cell-machine-loc" style="display: flex; align-items: center; gap: 3px;">
                                    <span style="width: 11px; height: 11px; display: inline-flex; color: var(--text-muted); flex-shrink: 0;"><?= posthog_icon('mappin') ?></span>
                                    <span><?= e($item['machine_location']) ?></span>
                                </div>
                            </td>

                            <td style="text-align: right;">
                                <div style="font-weight: 800; font-size: 0.88rem; color: <?= $isOut ? '#dc2626' : '#b45309' ?>;">
                                    <?= $qty ?> <span style="font-size: 0.76rem; font-weight: 600; color: var(--text-muted);">/ <?= $par ?></span>
                                </div>
                                <div class="mini-par-track" style="margin-left: auto;">
                                    <div class="mini-par-fill <?= $fillClass ?>" style="width: <?= $percent ?>%;"></div>
                                </div>
                            </td>

                            <td style="text-align: right;">
                                <span class="unit-load-pill <?= $isOut ? 'pill-out' : 'pill-low' ?>">
                                    +<?= $needed ?> <?= $needed === 1 ? 'unit' : 'units' ?>
                                </span>
                            </td>

                            <td style="text-align: right;" class="no-print">
                                <button
                                    type="button"
                                    class="btn-restock-action"
                                    onclick="openAdjustStockModal(<?= $jsPayload ?>)"
                                    title="Log restock adjustment for <?= e($item['name']) ?>"
                                >
                                    <?= posthog_icon('bolt') ?>
                                    <span>Log restock</span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Filter Empty State -->
        <div id="filter-empty-state" style="display: none; padding: 2.5rem 1rem; text-align: center;">
            <div style="width: 32px; height: 32px; margin: 0 auto 0.4rem; color: #94a3b8;"><?= posthog_icon('search') ?></div>
            <div style="font-weight: 700; color: var(--text-main); font-size: 0.92rem;">No matching alert items</div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;">Try adjusting your search query or selecting a different machine filter.</div>
            <button type="button" class="btn-dash-outline" style="margin-top: 0.75rem; font-size: 0.8rem;" onclick="resetLowStockFilters()">
                Reset filters
            </button>
        </div>
    <?php endif; ?>

</div>

<!-- 4. Operational Informational Card -->
<div class="lowstock-info-card no-print">
    <?= posthog_icon('support') ?>
    <div>
        <strong style="color: var(--text-main); font-size: 0.84rem;">How VendFlow calculates low-stock alerts:</strong>
        <p style="margin-top: 0.2rem; line-height: 1.45;">
            Alerts evaluate recorded quantities in your database against each product's configured par threshold (e.g. min 10 units).
            When field restockers log counts or deliveries, quantities update in real-time, removing restored slots from this queue immediately.
        </p>
    </div>
</div>

<script>
let currentStatusFilter = 'all';

function filterLowStockQueue() {
    const q = (document.getElementById('lowstock-search').value || '').trim().toLowerCase();
    const selectedMachineId = document.getElementById('lowstock-machine-filter').value;
    const rows = Array.from(document.querySelectorAll('.lowstock-row'));
    let visibleCount = 0;

    rows.forEach(row => {
        const haystack = (row.getAttribute('data-search-haystack') || '').toLowerCase();
        const machineId = row.getAttribute('data-machine-id') || '';
        const isOut = row.getAttribute('data-is-out') === '1';

        const matchesQuery = (q === '' || haystack.includes(q));
        const matchesMachine = (selectedMachineId === '' || machineId === selectedMachineId);
        
        let matchesStatus = true;
        if (currentStatusFilter === 'out') {
            matchesStatus = isOut;
        } else if (currentStatusFilter === 'low') {
            matchesStatus = !isOut;
        }

        const shouldShow = matchesQuery && matchesMachine && matchesStatus;
        row.style.display = shouldShow ? '' : 'none';
        if (shouldShow) {
            visibleCount++;
        }
    });

    const badge = document.getElementById('visible-count-badge');
    if (badge) {
        badge.textContent = `Showing ${visibleCount} alert${visibleCount === 1 ? '' : 's'}`;
    }

    const emptyBox = document.getElementById('filter-empty-state');
    if (emptyBox) {
        emptyBox.style.display = (visibleCount === 0 && rows.length > 0) ? 'block' : 'none';
    }
}

function setStatusFilter(filterType, btnEl) {
    currentStatusFilter = filterType;
    document.querySelectorAll('.catalog-toolbar .filter-chip').forEach(c => c.classList.remove('active'));
    if (btnEl) {
        btnEl.classList.add('active');
    }
    filterLowStockQueue();
}

function resetLowStockFilters() {
    document.getElementById('lowstock-search').value = '';
    document.getElementById('lowstock-machine-filter').value = '';
    setStatusFilter('all', document.getElementById('chip-all'));
}
</script>

<?php require_once __DIR__ . '/includes/dashboard_footer.php'; ?>
