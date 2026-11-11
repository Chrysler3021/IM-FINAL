<?php
/**
 * VendFlow — Staff Member Field Restock Dashboard (UI/UX)
 * Pure UI/UX implementation focused on on-site route restockers and machine stock adjustments.
 */
require_once __DIR__ . '/includes/functions.php';

// Safe check: if an operator/demo is logged in, use their real fleet. Otherwise, supply realistic sample route data for preview.
$isSessionUser = is_logged_in();
$user = $isSessionUser ? current_user() : null;
$isStaffUser = is_staff();
$staff = $isStaffUser ? current_staff() : null;

$fleetBusiness = $user['business_name'] ?? 'Summit Vending Fleet Ops';
$operatorName  = $user['name'] ?? 'Alex Morgan (Operator)';

if ($isStaffUser && $staff) {
    $staffName     = $staff['name'];
    $staffUsername = $staff['username'];
    $staffRole     = 'Field Restocker';
} elseif ($isSessionUser && $user) {
    $staffName     = $user['name'] . ' (Preview)';
    $staffUsername = 'operator_preview';
    $staffRole     = 'Fleet Operator';
} else {
    $staffName     = 'Dave Miller';
    $staffUsername = 'dave_route1';
    $staffRole     = 'Field Restocker (Demo)';
}

$db = null;
$realMachines = [];
$realProducts = [];
$realLogs = [];

if ($isSessionUser && $user) {
    try {
        $db = get_db();
        $uid = (int)$user['id'];
        
        if ($isStaffUser && $staff) {
            $allowedMachineIds = get_staff_accessible_machine_ids($db, $staff);
            if (!empty($allowedMachineIds)) {
                $inClause = implode(',', array_map('intval', $allowedMachineIds));
                $mStmt = $db->query("SELECT * FROM machines WHERE id IN ($inClause) AND user_id = {$uid} ORDER BY name ASC");
                $realMachines = $mStmt->fetchAll();

                $pStmt = $db->query("
                    SELECT p.*, m.name AS machine_name, m.location AS machine_location 
                    FROM products p 
                    JOIN machines m ON p.machine_id = m.id 
                    WHERE p.user_id = {$uid} AND p.machine_id IN ($inClause)
                    ORDER BY (p.quantity <= p.min_threshold) DESC, (p.min_threshold - p.quantity) DESC, p.name ASC
                ");
                $realProducts = $pStmt->fetchAll();
            }
        } else {
            $mStmt = $db->prepare("SELECT * FROM machines WHERE user_id = :uid ORDER BY name ASC");
            $mStmt->execute([':uid' => $uid]);
            $realMachines = $mStmt->fetchAll();

            $pStmt = $db->prepare("
                SELECT p.*, m.name AS machine_name, m.location AS machine_location 
                FROM products p 
                JOIN machines m ON p.machine_id = m.id 
                WHERE p.user_id = :uid 
                ORDER BY (p.quantity <= p.min_threshold) DESC, (p.min_threshold - p.quantity) DESC, p.name ASC
            ");
            $pStmt->execute([':uid' => $uid]);
            $realProducts = $pStmt->fetchAll();
        }

        $lStmt = $db->prepare("
            SELECT l.*, p.name AS product_name, p.sku AS product_sku, m.name AS machine_name 
            FROM inventory_logs l 
            JOIN products p ON l.product_id = p.id 
            JOIN machines m ON l.machine_id = m.id 
            WHERE l.user_id = :uid 
            ORDER BY l.created_at DESC 
            LIMIT 8
        ");
        $lStmt->execute([':uid' => $uid]);
        $realLogs = $lStmt->fetchAll();
    } catch (Exception $e) {
        // Fallback to sample route dataset
    }
}

// Fallback sample data if no machines/products exist yet
if (empty($realMachines)) {
    $realMachines = [
        [
            'id' => 1,
            'name' => 'Machine A (Tech Towers)',
            'identifier' => 'MCH-001',
            'location' => '2nd Floor Breakroom, Tech Towers',
            'status' => 'active'
        ],
        [
            'id' => 2,
            'name' => 'Machine B (Campus Union)',
            'identifier' => 'MCH-002',
            'location' => 'Student Union Center, Building C',
            'status' => 'active'
        ]
    ];
}

if (empty($realProducts)) {
    $realProducts = [
        [
            'id' => 101,
            'machine_id' => 1,
            'machine_name' => 'Machine A (Tech Towers)',
            'machine_location' => '2nd Floor Breakroom, Tech Towers',
            'name' => 'Coca-Cola Classic (330ml Can)',
            'sku' => 'BEV-COK-01',
            'category' => 'Cold Drinks & Sodas',
            'quantity' => 2,
            'min_threshold' => 12,
            'coil_slot' => 'A1'
        ],
        [
            'id' => 102,
            'machine_id' => 1,
            'machine_name' => 'Machine A (Tech Towers)',
            'machine_location' => '2nd Floor Breakroom, Tech Towers',
            'name' => 'Lay\'s Classic Potato Chips (50g)',
            'sku' => 'SNK-LAY-01',
            'category' => 'Chips & Snacks',
            'quantity' => 0,
            'min_threshold' => 10,
            'coil_slot' => 'A2'
        ],
        [
            'id' => 103,
            'machine_id' => 1,
            'machine_name' => 'Machine A (Tech Towers)',
            'machine_location' => '2nd Floor Breakroom, Tech Towers',
            'name' => 'Snickers Chocolate Bar (48g)',
            'sku' => 'CND-SNK-01',
            'category' => 'Candy & Chocolate',
            'quantity' => 4,
            'min_threshold' => 15,
            'coil_slot' => 'A3'
        ],
        [
            'id' => 104,
            'machine_id' => 1,
            'machine_name' => 'Machine A (Tech Towers)',
            'machine_location' => '2nd Floor Breakroom, Tech Towers',
            'name' => 'Monster Energy Drink (500ml)',
            'sku' => 'BEV-MNS-01',
            'category' => 'Cold Drinks & Sodas',
            'quantity' => 12,
            'min_threshold' => 10,
            'coil_slot' => 'B1'
        ],
        [
            'id' => 105,
            'machine_id' => 2,
            'machine_name' => 'Machine B (Campus Union)',
            'machine_location' => 'Student Union Center, Building C',
            'name' => 'Doritos Nacho Cheese (60g)',
            'sku' => 'SNK-DOR-01',
            'category' => 'Chips & Snacks',
            'quantity' => 3,
            'min_threshold' => 12,
            'coil_slot' => 'A1'
        ],
        [
            'id' => 106,
            'machine_id' => 2,
            'machine_name' => 'Machine B (Campus Union)',
            'machine_location' => 'Student Union Center, Building C',
            'name' => 'Aquafina Purified Mineral Water (500ml)',
            'sku' => 'BEV-AQF-01',
            'category' => 'Bottled Water & Juices',
            'quantity' => 16,
            'min_threshold' => 14,
            'coil_slot' => 'B2'
        ]
    ];
}

// Compute staff route KPI metrics
$totalItemsBelowPar = 0;
$totalDeficitUnits = 0;
$outOfStockCount = 0;

foreach ($realProducts as $item) {
    $qty = (int)$item['quantity'];
    $par = (int)$item['min_threshold'];
    if (is_low_stock($qty, $par)) {
        $totalItemsBelowPar++;
        $totalDeficitUnits += max(0, $par - $qty);
    }
    if ($qty === 0) {
        $outOfStockCount++;
    }
}
$assignedMachinesCount = count($realMachines);
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Staff Restock Workspace | VendFlow</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:ital,wght@0,400;0,700;1,400&family=Bitter:wght@600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        paper: '#F6F7F4',
                        tray: '#FFFFFF',
                        enamel: '#182026',
                        enamelSurface: '#222D35',
                        enamelBorder: '#2E3B44',
                        enamelMuted: '#C5D0D6',
                        steel: '#4B5862',
                        coil: '#D4DCE0',
                        tote: '#1746A2',
                        toteHover: '#123882',
                        toteTint: '#EDF2FC',
                        stockout: '#B42318',
                        stockoutBg: '#FEF3F2',
                        stockoutBorder: '#FECDCA',
                        amberPar: '#9A5B00',
                        amberBg: '#FFFAEB',
                        amberBorder: '#FEDF89',
                        parGreen: '#166534',
                        parGreenBg: '#F0FDF4',
                        parGreenBorder: '#BBF7D0',
                    },
                    fontFamily: {
                        sans: ['"Atkinson Hyperlegible"', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'sans-serif'],
                        display: ['"Bitter"', 'Georgia', 'serif'],
                    },
                    borderRadius: {
                        frame: '12px',
                        control: '6px',
                        tag: '4px',
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        .tabular-nums { font-variant-numeric: tabular-nums; }
        .touch-action-manipulation { touch-action: manipulation; }
    </style>
</head>
<body class="h-full flex flex-col bg-paper font-sans text-enamel antialiased">

    <!-- Toast Notifications Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 flex flex-col space-y-2 max-w-sm w-full pointer-events-auto" aria-live="polite"></div>

    <!-- Top Navigation: Specialized Staff Header (No owner/billing clutter) -->
    <header class="bg-enamel text-tray border-b border-enamelBorder flex-shrink-0 z-30 sticky top-0">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="h-16 flex items-center justify-between gap-4">
                
                <!-- Brand & Role Badge -->
                <div class="flex items-center space-x-3 min-w-0">
                    <a href="staff-dashboard.php" class="flex items-center space-x-2.5 flex-shrink-0">
                        <span class="w-8 h-8 rounded-control bg-enamelSurface border border-enamelBorder text-tray flex items-center justify-center flex-shrink-0" aria-hidden="true">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <rect x="4" y="2.5" width="16" height="19" rx="2" />
                                <line x1="8" y1="7" x2="11" y2="7" />
                                <line x1="13" y1="7" x2="16" y2="7" />
                                <line x1="8" y1="11" x2="11" y2="11" />
                                <line x1="13" y1="11" x2="16" y2="11" />
                                <rect x="7.5" y="15.5" width="9" height="3" rx="0.8" />
                            </svg>
                        </span>
                        <div class="flex flex-col">
                            <span class="font-display font-bold text-base text-tray leading-tight">VendFlow</span>
                            <span class="text-xs text-enamelMuted leading-tight truncate"><?= htmlspecialchars($fleetBusiness) ?></span>
                        </div>
                    </a>

                    <!-- Staff Portal Tag -->
                    <span class="hidden sm:inline-flex items-center px-2.5 py-0.5 rounded-tag text-xs font-bold bg-tote text-tray border border-toteHover">
                        Route Staff Workspace
                    </span>
                </div>

                <!-- Staff Identity & Session Actions -->
                <div class="flex items-center space-x-3">
                    <div class="text-right hidden sm:block">
                        <div class="text-sm font-bold text-tray leading-tight"><?= htmlspecialchars($staffName) ?></div>
                        <div class="text-xs text-enamelMuted">@<?= htmlspecialchars($staffUsername) ?> &middot; <?= htmlspecialchars($staffRole) ?></div>
                    </div>

                    <div class="flex items-center space-x-2">
                        <?php if (!$isStaffUser && $isSessionUser): ?>
                            <a href="staff.php" class="inline-flex items-center px-3 py-1.5 text-xs font-bold rounded-control bg-enamelSurface hover:bg-enamelBorder text-enamelMuted hover:text-tray border border-enamelBorder transition-colors">
                                Return to staff team
                            </a>
                        <?php endif; ?>
                        <a href="logout.php" class="inline-flex items-center px-3 py-1.5 text-xs font-bold rounded-control border border-enamelBorder text-enamelMuted hover:text-tray hover:bg-enamelSurface transition-colors">
                            Sign out
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </header>

    <!-- Operational Context Sub-bar (Mobile Friendly Route Banner) -->
    <div class="bg-tray border-b border-coil py-2.5 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 text-xs">
            <div class="flex items-center space-x-2 text-steel">
                <span class="w-2 h-2 rounded-full bg-parGreen"></span>
                <span>Active route: <strong class="text-enamel"><?= $assignedMachinesCount ?> assigned physical machines</strong></span>
                <span class="text-coil">&bull;</span>
                <span>Fleet operator: <span class="text-enamel font-bold"><?= htmlspecialchars($operatorName) ?></span></span>
            </div>
            <div class="text-steel">
                <span>Servicing mode: <strong class="text-tote">Restock load &amp; stock audit counts</strong></span>
            </div>
        </div>
    </div>

    <!-- Main Workspace Content -->
    <main class="flex-1 overflow-y-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto space-y-6">

            <!-- 1. Route Restock KPI Strip (Flat, divided, zero-card slop per DESIGN.md) -->
            <section aria-label="Route Restock Summary" class="bg-tray border border-coil grid grid-cols-2 lg:grid-cols-4 divide-y lg:divide-y-0 sm:divide-x divide-coil tabular-nums">
                
                <div class="p-4 sm:p-5 <?= $totalItemsBelowPar > 0 ? 'bg-amberBg/50' : '' ?>">
                    <span class="text-xs font-bold <?= $totalItemsBelowPar > 0 ? 'text-amberPar' : 'text-steel' ?> block">Slots at or below par</span>
                    <div class="font-display font-bold text-2xl text-enamel mt-1">
                        <span id="kpi-low-count"><?= $totalItemsBelowPar ?></span> SKUs
                    </div>
                    <span class="text-xs <?= $outOfStockCount > 0 ? 'text-stockout font-bold' : 'text-steel' ?> block mt-0.5">
                        <?= $outOfStockCount > 0 ? "{$outOfStockCount} empty spirals (0 units)" : "Require cart restocking" ?>
                    </span>
                </div>

                <div class="p-4 sm:p-5 <?= $totalDeficitUnits > 0 ? 'bg-toteTint/50' : '' ?>">
                    <span class="text-xs font-bold <?= $totalDeficitUnits > 0 ? 'text-tote' : 'text-steel' ?> block">Units to load into tote</span>
                    <div class="font-display font-bold text-2xl text-enamel mt-1">
                        <span id="kpi-deficit-units">+<?= $totalDeficitUnits ?></span> units
                    </div>
                    <span class="text-xs text-steel block mt-0.5">Total deficit to reach full par</span>
                </div>

                <div class="p-4 sm:p-5">
                    <span class="text-xs text-steel block">Assigned route machines</span>
                    <div class="font-display font-bold text-2xl text-enamel mt-1"><?= $assignedMachinesCount ?></div>
                    <span class="text-xs text-steel block mt-0.5">On-site cabinet servicing</span>
                </div>

                <div class="p-4 sm:p-5">
                    <span class="text-xs text-steel block">Shift adjustments logged</span>
                    <div class="font-display font-bold text-2xl text-parGreen mt-1" id="kpi-shift-logged">0</div>
                    <span class="text-xs text-steel block mt-0.5">Recorded by <?= htmlspecialchars($staffName) ?></span>
                </div>

            </section>

            <!-- 2. On-Site Machine Selector & Quick Controls -->
            <section aria-label="Machine Route Filters" class="bg-tray border border-coil p-4 space-y-3">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                    
                    <!-- Machine Location Filter Tabs -->
                    <div>
                        <span class="text-xs font-bold text-steel block mb-1.5">Select machine you are currently servicing:</span>
                        <div class="flex flex-wrap gap-1.5" id="machine-filter-buttons">
                            <button type="button" onclick="filterByMachine('all', this)" class="machine-filter-btn px-3 py-1.5 rounded-control text-xs font-bold border transition-colors bg-enamel text-tray border-enamel">
                                All machines (<?= count($realProducts) ?> slots)
                            </button>
                            <?php foreach ($realMachines as $m): 
                                $mCount = 0;
                                foreach ($realProducts as $p) {
                                    if ($p['machine_id'] == $m['id']) $mCount++;
                                }
                            ?>
                                <button type="button" onclick="filterByMachine('<?= (int)$m['id'] ?>', this)" class="machine-filter-btn px-3 py-1.5 rounded-control text-xs font-bold border transition-colors bg-tray hover:bg-paper text-enamel border-coil">
                                    <?= htmlspecialchars($m['name']) ?> (<?= $mCount ?>)
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Quick Search Input -->
                    <div class="w-full sm:w-64">
                        <label for="slot-search" class="text-xs font-bold text-steel block mb-1">Quick SKU / Product search:</label>
                        <div class="relative">
                            <input 
                                type="text" 
                                id="slot-search" 
                                oninput="handleSearch(this.value)" 
                                placeholder="Filter products or SKUs..." 
                                class="w-full px-3 py-1.5 text-xs rounded-control border border-coil bg-tray text-enamel placeholder-steel/60 focus:outline-none focus:border-tote"
                            >
                            <button type="button" onclick="clearSearch()" id="clear-search-btn" class="hidden absolute right-2 top-1.5 text-steel hover:text-enamel text-xs font-bold">
                                &times;
                            </button>
                        </div>
                    </div>

                </div>

                <!-- Secondary Filter Strip: Show All vs Only Deficits -->
                <div class="pt-3 border-t border-coil flex flex-wrap items-center justify-between gap-2 text-xs">
                    <div class="flex items-center space-x-2">
                        <button type="button" id="toggle-deficits-btn" onclick="toggleDeficitsOnly()" class="px-2.5 py-1 rounded-tag font-bold border text-xs bg-amberBg text-amberPar border-amberBorder">
                            Showing: Deficits &amp; Low Stock First
                        </button>
                        <span class="text-steel" id="active-filter-status">Showing <?= count($realProducts) ?> items</span>
                    </div>

                    <div class="flex items-center space-x-2 text-steel">
                        <span>Tip: Tap <strong>"Fill to par"</strong> for 1-tap cart restock, or <strong>"Adjust"</strong> for sales tallies &amp; audits.</span>
                    </div>
                </div>
            </section>

            <!-- 3. Primary Staff Restock Ledger (Mobile First, High-Contrast Action Rows) -->
            <section aria-label="Restock Items List" class="bg-tray border border-coil">
                <div class="px-5 py-4 border-b border-coil flex items-center justify-between">
                    <div>
                        <h2 class="font-display font-bold text-enamel" style="font-size: 1.25rem;">Machine slot inventory &amp; restock actions</h2>
                        <p class="text-xs text-steel mt-0.5">Tap on any slot to increment restock load or update physical shelf counts</p>
                    </div>
                    <span class="text-xs font-bold text-steel tabular-nums" id="item-counter-badge"><?= count($realProducts) ?> active slots</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs tabular-nums" id="restock-table">
                        <thead class="bg-paper text-steel border-b border-coil">
                            <tr>
                                <th class="py-3 px-4 font-bold text-enamel">Coil / SKU</th>
                                <th class="py-3 px-4 font-bold text-enamel">Product description</th>
                                <th class="py-3 px-3 font-bold text-enamel">Machine location</th>
                                <th class="py-3 px-3 text-right font-bold text-enamel">Current stock</th>
                                <th class="py-3 px-3 text-right font-bold text-enamel">Target par</th>
                                <th class="py-3 px-3 text-center font-bold text-enamel">Tote load status</th>
                                <th class="py-3 px-4 text-right font-bold text-enamel">Restock action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-coil" id="product-rows-container">
                            <?php foreach ($realProducts as $item): 
                                $qty = (int)$item['quantity'];
                                $par = (int)$item['min_threshold'];
                                $isStockout = ($qty === 0);
                                $isBelowPar = ($qty < $par);
                                $deficit = max(0, $par - $qty);
                                $slotLabel = $item['coil_slot'] ?? 'Slot';
                            ?>
                                <tr 
                                    class="product-row hover:bg-paper/70 transition-colors <?= $isStockout ? 'bg-stockoutBg/40' : ($isBelowPar ? 'bg-amberBg/25' : '') ?>" 
                                    data-machine-id="<?= (int)$item['machine_id'] ?>"
                                    data-product-id="<?= (int)$item['id'] ?>"
                                    data-is-deficit="<?= $isBelowPar ? '1' : '0' ?>"
                                    data-search-text="<?= htmlspecialchars(strtolower($item['name'] . ' ' . $item['sku'] . ' ' . $slotLabel . ' ' . $item['machine_name'])) ?>"
                                >
                                    <!-- Coil / SKU -->
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-control bg-enamel text-tray font-bold text-xs mr-2 border border-enamelBorder">
                                            <?= htmlspecialchars($slotLabel) ?>
                                        </span>
                                        <span class="font-bold text-steel"><?= htmlspecialchars($item['sku']) ?></span>
                                    </td>

                                    <!-- Product Name & Category -->
                                    <td class="py-3.5 px-4 min-w-[200px]">
                                        <div class="font-bold text-enamel text-sm"><?= htmlspecialchars($item['name']) ?></div>
                                        <div class="text-steel text-xs mt-0.5"><?= htmlspecialchars($item['category'] ?? 'General') ?></div>
                                    </td>

                                    <!-- Machine Location -->
                                    <td class="py-3.5 px-3 whitespace-nowrap">
                                        <span class="font-bold text-enamel block"><?= htmlspecialchars($item['machine_name']) ?></span>
                                        <span class="text-steel text-xs truncate max-w-[180px] block"><?= htmlspecialchars($item['machine_location'] ?? '') ?></span>
                                    </td>

                                    <!-- Current Quantity -->
                                    <td class="py-3.5 px-3 text-right">
                                        <span class="font-display font-bold text-base item-current-qty <?= $isStockout ? 'text-stockout' : ($isBelowPar ? 'text-amberPar' : 'text-parGreen') ?>">
                                            <?= $qty ?>
                                        </span>
                                        <span class="text-steel text-xs block">units</span>
                                    </td>

                                    <!-- Target Par Capacity -->
                                    <td class="py-3.5 px-3 text-right text-steel">
                                        <span class="font-bold text-sm text-enamel item-par-capacity"><?= $par ?></span>
                                        <span class="text-xs block">par cap</span>
                                    </td>

                                    <!-- Status Badge -->
                                    <td class="py-3.5 px-3 text-center whitespace-nowrap">
                                        <?php if ($isStockout): ?>
                                            <span class="inline-block px-2.5 py-1 rounded-tag text-xs font-bold bg-stockoutBg text-stockout border border-stockoutBorder">
                                                Stockout (+<?= $deficit ?> needed)
                                            </span>
                                        <?php elseif ($isBelowPar): ?>
                                            <span class="inline-block px-2.5 py-1 rounded-tag text-xs font-bold bg-amberBg text-amberPar border border-amberBorder">
                                                Deficit: +<?= $deficit ?> needed
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-block px-2.5 py-1 rounded-tag text-xs font-bold bg-parGreenBg text-parGreen border border-parGreenBorder">
                                                Full par (0 deficit)
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Quick Restock Actions -->
                                    <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                        <div class="inline-flex items-center space-x-1.5">
                                            <?php if ($deficit > 0): ?>
                                                <!-- One-Tap Restock to Par Button -->
                                                <button 
                                                    type="button" 
                                                    onclick="quickFillToPar(<?= (int)$item['id'] ?>, <?= $deficit ?>, '<?= htmlspecialchars(addslashes($item['name'])) ?>')"
                                                    class="touch-action-manipulation px-2.5 py-1.5 rounded-control text-xs font-bold bg-tote hover:bg-toteHover text-tray transition-colors cursor-pointer"
                                                    title="Add <?= $deficit ?> units immediately to reach par"
                                                >
                                                    + Fill to par (<?= $deficit ?>)
                                                </button>
                                            <?php endif; ?>

                                            <!-- Standard Adjust Modal Trigger -->
                                            <button 
                                                type="button" 
                                                onclick="openStaffAdjustModal(<?= htmlspecialchars(json_encode([
                                                    'id' => $item['id'],
                                                    'name' => $item['name'],
                                                    'sku' => $item['sku'],
                                                    'machine_name' => $item['machine_name'],
                                                    'quantity' => $qty,
                                                    'min_threshold' => $par,
                                                    'slot' => $slotLabel
                                                ]), ENT_QUOTES, 'UTF-8') ?>)"
                                                class="touch-action-manipulation px-2.5 py-1.5 rounded-control text-xs font-bold border border-coil bg-tray hover:bg-paper text-enamel transition-colors cursor-pointer"
                                            >
                                                Adjust...
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Empty Search Fallback State -->
                <div id="no-matching-items" class="hidden p-8 text-center space-y-2">
                    <p class="text-sm font-bold text-steel">No machine slots match your filter.</p>
                    <button type="button" onclick="resetAllFilters()" class="text-xs font-bold text-tote underline">
                        Reset filters to view all slots
                    </button>
                </div>
            </section>

            <!-- 4. Staff Shift Activity Ledger (Real-time Feedback of What Was Logged) -->
            <section aria-label="Staff Shift Activity" class="bg-tray border border-coil">
                <div class="px-5 py-4 border-b border-coil flex items-center justify-between">
                    <div>
                        <h2 class="font-display font-bold text-enamel" style="font-size: 1.25rem;">Shift restock activity trail</h2>
                        <p class="text-xs text-steel mt-0.5">Recorded stock changes tagged with your staff credentials</p>
                    </div>
                    <span class="text-xs font-bold text-parGreen" id="shift-sync-status">&bull; Shift ledger active</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs tabular-nums">
                        <thead class="bg-paper text-steel border-b border-coil">
                            <tr>
                                <th class="py-2.5 px-4 font-bold text-enamel">Timestamp</th>
                                <th class="py-2.5 px-3 font-bold text-enamel">Product &amp; SKU</th>
                                <th class="py-2.5 px-3 font-bold text-enamel">Machine</th>
                                <th class="py-2.5 px-3 text-right font-bold text-enamel">Previous</th>
                                <th class="py-2.5 px-3 text-right font-bold text-enamel">Adjustment</th>
                                <th class="py-2.5 px-3 text-right font-bold text-enamel">New count</th>
                                <th class="py-2.5 px-4 font-bold text-enamel">Staff note &amp; reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-coil text-steel" id="staff-recent-log-body">
                            <!-- Populated dynamically via JS when staff clicks restock -->
                            <tr class="hover:bg-paper/50">
                                <td class="py-3 px-4 whitespace-nowrap text-steel">Just now</td>
                                <td class="py-3 px-3">
                                    <span class="font-bold text-enamel block">Doritos Nacho Cheese (60g)</span>
                                    <span class="text-steel">SNK-DOR-01</span>
                                </td>
                                <td class="py-3 px-3 text-enamel">Machine B (Campus Union)</td>
                                <td class="py-3 px-3 text-right">3</td>
                                <td class="py-3 px-3 text-right font-bold text-parGreen">+9</td>
                                <td class="py-3 px-3 text-right font-bold text-enamel">12</td>
                                <td class="py-3 px-4">
                                    <span class="inline-block px-2 py-0.5 rounded-tag text-xs font-bold bg-parGreenBg text-parGreen border border-parGreenBorder mr-1.5">
                                        Restock load
                                    </span>
                                    <span class="text-steel">Loaded fresh box &middot; <strong class="text-enamel">Logged by Dave Miller</strong></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>
    </main>

    <!-- 5. Specialized Staff Stock Adjustment Modal (Touch-Optimized Steppers) -->
    <div id="staff-adjust-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="staff-modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4">
            
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-enamel/70 transition-opacity" onclick="closeStaffModal()"></div>

            <!-- Modal Panel -->
            <div class="relative bg-tray rounded-frame text-left overflow-hidden shadow-2xl max-w-md w-full border border-coil z-10">
                <form id="staff-adjust-form" onsubmit="handleStaffAdjustSubmit(event)">
                    
                    <input type="hidden" id="modal-product-id" value="">
                    <input type="hidden" id="modal-current-qty-raw" value="0">
                    <input type="hidden" id="modal-par-threshold-raw" value="10">

                    <!-- Modal Header -->
                    <div class="px-6 pt-5 pb-4 border-b border-coil bg-paper/60 flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold text-steel" id="modal-slot-chip">Coil Slot A1</span>
                            <h3 class="font-display font-bold text-enamel text-lg" id="staff-modal-title">Log stock adjustment</h3>
                        </div>
                        <button type="button" onclick="closeStaffModal()" aria-label="Close dialog" class="text-steel hover:text-enamel p-1.5 rounded-control cursor-pointer">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <!-- Product & Machine Context Banner -->
                    <div class="px-6 py-3.5 bg-tray border-b border-coil tabular-nums">
                        <div class="text-sm font-bold text-enamel" id="modal-product-name">Product Name</div>
                        <div class="text-xs text-steel mt-0.5 flex items-center justify-between">
                            <span id="modal-machine-name">Machine location</span>
                            <span>SKU: <strong class="text-enamel" id="modal-product-sku">SKU-001</strong></span>
                        </div>
                        <div class="mt-2.5 grid grid-cols-2 gap-2 bg-paper p-2.5 rounded-control border border-coil text-xs">
                            <div>
                                <span class="text-steel block">Current units:</span>
                                <span class="font-display font-bold text-base text-enamel" id="modal-current-display">0 units</span>
                            </div>
                            <div class="text-right">
                                <span class="text-steel block">Target par cap:</span>
                                <span class="font-display font-bold text-base text-enamel" id="modal-par-display">12 units</span>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Body Form -->
                    <div class="p-6 space-y-4">
                        
                        <!-- Adjustment Reason / Type -->
                        <div>
                            <label class="block text-xs font-bold text-enamel mb-1.5">Adjustment type *</label>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <label class="cursor-pointer border border-coil rounded-control p-2.5 flex items-center space-x-2 bg-paper/60 hover:bg-paper has-[:checked]:border-tote has-[:checked]:bg-toteTint">
                                    <input type="radio" name="adjust_type" value="restock" checked onchange="updateModalCalculation()" class="accent-tote">
                                    <span class="font-bold text-enamel">Restock (+ add)</span>
                                </label>
                                <label class="cursor-pointer border border-coil rounded-control p-2.5 flex items-center space-x-2 bg-paper/60 hover:bg-paper has-[:checked]:border-tote has-[:checked]:bg-toteTint">
                                    <input type="radio" name="adjust_type" value="manual_sale" onchange="updateModalCalculation()" class="accent-tote">
                                    <span class="font-bold text-enamel">Sales tally (- count)</span>
                                </label>
                                <label class="cursor-pointer border border-coil rounded-control p-2.5 flex items-center space-x-2 bg-paper/60 hover:bg-paper has-[:checked]:border-tote has-[:checked]:bg-toteTint">
                                    <input type="radio" name="adjust_type" value="damaged" onchange="updateModalCalculation()" class="accent-tote">
                                    <span class="font-bold text-enamel">Damaged / Expired (-)</span>
                                </label>
                                <label class="cursor-pointer border border-coil rounded-control p-2.5 flex items-center space-x-2 bg-paper/60 hover:bg-paper has-[:checked]:border-tote has-[:checked]:bg-toteTint">
                                    <input type="radio" name="adjust_type" value="set_exact" onchange="updateModalCalculation()" class="accent-tote">
                                    <span class="font-bold text-enamel">Exact audit (= set)</span>
                                </label>
                            </div>
                        </div>

                        <!-- Stepper & Direct Unit Entry (Optimized for 1-handed field use) -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label for="modal-input-amount" class="text-xs font-bold text-enamel">Units count to apply *</label>
                                <span class="text-xs text-steel font-bold" id="stepper-helper-text">Add to current stock</span>
                            </div>

                            <div class="flex items-stretch space-x-2">
                                <button type="button" onclick="stepAmount(-1)" class="w-12 h-11 flex items-center justify-center rounded-control border border-coil bg-tray hover:bg-paper font-bold text-lg text-enamel cursor-pointer touch-action-manipulation">
                                    -
                                </button>
                                <input 
                                    type="number" 
                                    id="modal-input-amount" 
                                    min="0" 
                                    value="5" 
                                    required 
                                    oninput="updateModalCalculation()" 
                                    class="flex-1 text-center font-display font-bold text-xl rounded-control border border-coil bg-tray text-enamel tabular-nums focus:outline-none focus:border-tote"
                                >
                                <button type="button" onclick="stepAmount(1)" class="w-12 h-11 flex items-center justify-center rounded-control border border-coil bg-tray hover:bg-paper font-bold text-lg text-enamel cursor-pointer touch-action-manipulation">
                                    +
                                </button>
                            </div>

                            <!-- Quick Preset Tap Chips -->
                            <div class="flex items-center space-x-2 mt-2 text-xs">
                                <span class="text-steel">Quick:</span>
                                <button type="button" onclick="setPresetAmount(1)" class="px-2 py-1 rounded-tag border border-coil bg-paper hover:bg-coil/50 font-bold text-enamel">
                                    +1
                                </button>
                                <button type="button" onclick="setPresetAmount(5)" class="px-2 py-1 rounded-tag border border-coil bg-paper hover:bg-coil/50 font-bold text-enamel">
                                    +5
                                </button>
                                <button type="button" onclick="setPresetAmount(10)" class="px-2 py-1 rounded-tag border border-coil bg-paper hover:bg-coil/50 font-bold text-enamel">
                                    +10
                                </button>
                                <button type="button" id="modal-fill-par-preset" onclick="presetFillToPar()" class="px-2.5 py-1 rounded-tag border border-tote bg-toteTint text-tote font-bold">
                                    Fill to par
                                </button>
                            </div>
                        </div>

                        <!-- Result Live Calculation Preview -->
                        <div class="bg-paper p-3 rounded-control border border-coil flex items-center justify-between tabular-nums">
                            <div>
                                <span class="text-xs text-steel block">Updated recorded units:</span>
                                <span class="text-xs text-steel" id="calculation-formula-note">Current 2 + Restock 5</span>
                            </div>
                            <div class="text-right">
                                <span class="font-display font-bold text-xl text-tote" id="modal-result-preview">7 units</span>
                            </div>
                        </div>

                        <!-- Optional Staff Restock Note -->
                        <div>
                            <label for="modal-notes" class="block text-xs font-bold text-enamel mb-1">Restock note (optional):</label>
                            <input 
                                type="text" 
                                id="modal-notes" 
                                placeholder="e.g. Exp 12/26, Loaded from tray box, coin jam resolved..." 
                                class="w-full px-3 py-2 text-xs rounded-control border border-coil bg-tray text-enamel placeholder-steel/60 focus:outline-none focus:border-tote"
                            >
                        </div>

                    </div>

                    <!-- Modal Actions Footer -->
                    <div class="bg-paper px-6 py-3.5 border-t border-coil flex items-center justify-between">
                        <button type="button" onclick="closeStaffModal()" class="px-3.5 py-2 rounded-control border border-coil bg-tray hover:bg-paper text-xs font-bold text-enamel cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-control bg-tote hover:bg-toteHover text-tray text-xs font-bold shadow-sm transition-colors cursor-pointer">
                            Confirm stock update
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <!-- Interactive Client Scripts for Pure UI/UX Testing -->
    <script>
        let currentMachineFilter = 'all';
        let deficitsOnly = false;
        let searchQuery = '';
        let shiftAdjustmentsCount = 0;

        // Filter by Machine
        function filterByMachine(machineId, btn) {
            currentMachineFilter = machineId;
            document.querySelectorAll('.machine-filter-btn').forEach(b => {
                b.className = 'machine-filter-btn px-3 py-1.5 rounded-control text-xs font-bold border transition-colors bg-tray hover:bg-paper text-enamel border-coil';
            });
            btn.className = 'machine-filter-btn px-3 py-1.5 rounded-control text-xs font-bold border transition-colors bg-enamel text-tray border-enamel';
            applyRowFilters();
        }

        // Toggle Deficits Only
        function toggleDeficitsOnly() {
            deficitsOnly = !deficitsOnly;
            const btn = document.getElementById('toggle-deficits-btn');
            if (deficitsOnly) {
                btn.className = 'px-2.5 py-1 rounded-tag font-bold border text-xs bg-stockoutBg text-stockout border-stockoutBorder';
                btn.textContent = 'Showing: Deficits Only (Low & Stockout)';
            } else {
                btn.className = 'px-2.5 py-1 rounded-tag font-bold border text-xs bg-amberBg text-amberPar border-amberBorder';
                btn.textContent = 'Showing: All Items (Deficits First)';
            }
            applyRowFilters();
        }

        // Search Filter
        function handleSearch(val) {
            searchQuery = (val || '').trim().toLowerCase();
            const clearBtn = document.getElementById('clear-search-btn');
            if (searchQuery.length > 0) {
                clearBtn.classList.remove('hidden');
            } else {
                clearBtn.classList.add('hidden');
            }
            applyRowFilters();
        }

        function clearSearch() {
            document.getElementById('slot-search').value = '';
            handleSearch('');
        }

        function resetAllFilters() {
            currentMachineFilter = 'all';
            deficitsOnly = false;
            searchQuery = '';
            document.getElementById('slot-search').value = '';
            const allBtn = document.querySelector('.machine-filter-btn');
            if (allBtn) filterByMachine('all', allBtn);
            const defBtn = document.getElementById('toggle-deficits-btn');
            if (defBtn) {
                defBtn.className = 'px-2.5 py-1 rounded-tag font-bold border text-xs bg-amberBg text-amberPar border-amberBorder';
                defBtn.textContent = 'Showing: All Items (Deficits First)';
            }
            applyRowFilters();
        }

        // Core Filter Application Engine
        function applyRowFilters() {
            const rows = document.querySelectorAll('.product-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const rowMachineId = row.getAttribute('data-machine-id');
                const isDeficit = row.getAttribute('data-is-deficit') === '1';
                const searchData = row.getAttribute('data-search-text') || '';

                const matchesMachine = (currentMachineFilter === 'all' || rowMachineId === currentMachineFilter);
                const matchesDeficit = (!deficitsOnly || isDeficit);
                const matchesSearch = (!searchQuery || searchData.includes(searchQuery));

                if (matchesMachine && matchesDeficit && matchesSearch) {
                    row.classList.remove('hidden');
                    visibleCount++;
                } else {
                    row.classList.add('hidden');
                }
            });

            document.getElementById('active-filter-status').textContent = `Showing ${visibleCount} items`;
            document.getElementById('item-counter-badge').textContent = `${visibleCount} active slots`;

            const noItems = document.getElementById('no-matching-items');
            if (visibleCount === 0) {
                noItems.classList.remove('hidden');
            } else {
                noItems.classList.add('hidden');
            }
        }

        // 1-Tap Quick Fill To Par
        function quickFillToPar(productId, deficitAmount, productName) {
            const row = document.querySelector(`.product-row[data-product-id="${productId}"]`);
            if (!row) return;

            const qtyEl = row.querySelector('.item-current-qty');
            const parEl = row.querySelector('.item-par-capacity');
            const currentQty = parseInt(qtyEl.textContent.trim(), 10) || 0;
            const parCapacity = parseInt(parEl.textContent.trim(), 10) || (currentQty + deficitAmount);

            // Update UI
            qtyEl.textContent = parCapacity;
            qtyEl.className = 'font-display font-bold text-base item-current-qty text-parGreen';
            row.setAttribute('data-is-deficit', '0');
            row.classList.remove('bg-stockoutBg/40', 'bg-amberBg/25');

            // Replace action buttons
            const actionTd = row.querySelector('td:last-child');
            actionTd.innerHTML = `
                <button type="button" onclick="openStaffAdjustModal({id: ${productId}, name: '${productName.replace(/'/g, "\\'")}', quantity: ${parCapacity}, min_threshold: ${parCapacity}, slot: 'Slot'})" class="px-2.5 py-1.5 rounded-control text-xs font-bold border border-coil bg-tray hover:bg-paper text-enamel transition-colors cursor-pointer">
                    Adjust...
                </button>
            `;

            // Replace status badge
            const statusTd = row.querySelector('td:nth-last-child(2)');
            statusTd.innerHTML = `
                <span class="inline-block px-2.5 py-1 rounded-tag text-xs font-bold bg-parGreenBg text-parGreen border border-parGreenBorder">
                    Full par (0 deficit)
                </span>
            `;

            shiftAdjustmentsCount++;
            document.getElementById('kpi-shift-logged').textContent = shiftAdjustmentsCount;

            // Recalculate KPI deficits
            recalcKpis();

            // Append to shift log table
            prependShiftLog(productName, currentQty, deficitAmount, parCapacity, 'Restock load', 'One-tap restock to full par');
            showToast(`Restocked +${deficitAmount} units to reach full par for ${productName}`, 'success');

            // Persist to database via API
            persistAdjustmentToApi(productId, 'restock', deficitAmount, 'One-tap restock to full par');
        }

        // Open Staff Modal
        function openStaffAdjustModal(item) {
            document.getElementById('modal-product-id').value = item.id;
            document.getElementById('modal-current-qty-raw').value = item.quantity;
            document.getElementById('modal-par-threshold-raw').value = item.min_threshold;

            document.getElementById('modal-slot-chip').textContent = item.slot ? `Coil Slot ${item.slot}` : 'Coil Slot';
            document.getElementById('modal-product-name').textContent = item.name;
            document.getElementById('modal-product-sku').textContent = item.sku || 'SKU';
            document.getElementById('modal-machine-name').textContent = item.machine_name || 'Machine Location';

            document.getElementById('modal-current-display').textContent = `${item.quantity} units`;
            document.getElementById('modal-par-display').textContent = `${item.min_threshold} units`;

            const current = parseInt(item.quantity, 10) || 0;
            const par = parseInt(item.min_threshold, 10) || 10;
            const deficit = Math.max(1, par - current);

            document.getElementById('modal-input-amount').value = (par > current) ? deficit : 5;
            document.querySelector('input[name="adjust_type"][value="restock"]').checked = true;
            document.getElementById('modal-notes').value = '';

            updateModalCalculation();
            document.getElementById('staff-adjust-modal').classList.remove('hidden');
        }

        function closeStaffModal() {
            document.getElementById('staff-adjust-modal').classList.add('hidden');
        }

        function stepAmount(step) {
            const input = document.getElementById('modal-input-amount');
            let current = parseInt(input.value, 10) || 0;
            input.value = Math.max(0, current + step);
            updateModalCalculation();
        }

        function setPresetAmount(amt) {
            document.getElementById('modal-input-amount').value = amt;
            updateModalCalculation();
        }

        function presetFillToPar() {
            const current = parseInt(document.getElementById('modal-current-qty-raw').value, 10) || 0;
            const par = parseInt(document.getElementById('modal-par-threshold-raw').value, 10) || 10;
            const needed = Math.max(0, par - current);
            document.querySelector('input[name="adjust_type"][value="restock"]').checked = true;
            document.getElementById('modal-input-amount').value = needed;
            updateModalCalculation();
        }

        function calculateNewStockQuantity(current, type, amount) {
            const cur = parseInt(current, 10) || 0;
            const amt = Math.max(0, parseInt(amount, 10) || 0);
            if (type === 'restock') return cur + amt;
            if (type === 'manual_sale' || type === 'damaged') return Math.max(0, cur - amt);
            if (type === 'set_exact') return amt;
            return cur;
        }

        function updateModalCalculation() {
            const current = parseInt(document.getElementById('modal-current-qty-raw').value, 10) || 0;
            const amount = parseInt(document.getElementById('modal-input-amount').value, 10) || 0;
            const selectedType = document.querySelector('input[name="adjust_type"]:checked')?.value || 'restock';

            const updated = calculateNewStockQuantity(current, selectedType, amount);
            let formula = '';

            if (selectedType === 'restock') {
                formula = `Current ${current} + Restock ${amount}`;
            } else if (selectedType === 'manual_sale') {
                formula = `Current ${current} - Sales ${amount}`;
            } else if (selectedType === 'damaged') {
                formula = `Current ${current} - Damaged ${amount}`;
            } else if (selectedType === 'set_exact') {
                formula = `Audit count set to ${amount}`;
            }

            const preview = document.getElementById('modal-result-preview');
            preview.textContent = `${updated} units`;
            document.getElementById('calculation-formula-note').textContent = formula;

            const modalPar = parseInt(document.getElementById('modal-par-capacity')?.value || '10', 10);
            if (updated === 0) {
                preview.className = 'font-display font-bold text-xl text-stockout';
            } else if (updated <= modalPar) {
                preview.className = 'font-display font-bold text-xl text-amberPar';
            } else {
                preview.className = 'font-display font-bold text-xl text-parGreen';
            }
        }

        function handleStaffAdjustSubmit(e) {
            e.preventDefault();
            const prodId = document.getElementById('modal-product-id').value;
            const current = parseInt(document.getElementById('modal-current-qty-raw').value, 10) || 0;
            const amount = parseInt(document.getElementById('modal-input-amount').value, 10) || 0;
            const type = document.querySelector('input[name="adjust_type"]:checked')?.value || 'restock';
            const notes = document.getElementById('modal-notes').value.trim();
            const prodName = document.getElementById('modal-product-name').textContent;

            const updatedQty = calculateNewStockQuantity(current, type, amount);
            const delta = updatedQty - current;
            const reasonLabels = {
                'restock': 'Restock load',
                'manual_sale': 'Sales tally',
                'damaged': 'Damaged / Expired',
                'set_exact': 'Physical count audit'
            };
            const typeLabel = reasonLabels[type] || 'Restock load';

            // Update row in DOM
            const row = document.querySelector(`.product-row[data-product-id="${prodId}"]`);
            if (row) {
                const qtyEl = row.querySelector('.item-current-qty');
                const par = parseInt(row.querySelector('.item-par-capacity').textContent, 10) || 10;
                qtyEl.textContent = updatedQty;

                if (updatedQty === 0) {
                    qtyEl.className = 'font-display font-bold text-base item-current-qty text-stockout';
                    row.setAttribute('data-is-deficit', '1');
                    row.className = 'product-row hover:bg-paper/70 transition-colors bg-stockoutBg/40';
                } else if (updatedQty < par) {
                    qtyEl.className = 'font-display font-bold text-base item-current-qty text-amberPar';
                    row.setAttribute('data-is-deficit', '1');
                    row.className = 'product-row hover:bg-paper/70 transition-colors bg-amberBg/25';
                } else {
                    qtyEl.className = 'font-display font-bold text-base item-current-qty text-parGreen';
                    row.setAttribute('data-is-deficit', '0');
                    row.className = 'product-row hover:bg-paper/70 transition-colors';
                }
            }

            closeStaffModal();
            shiftAdjustmentsCount++;
            document.getElementById('kpi-shift-logged').textContent = shiftAdjustmentsCount;

            prependShiftLog(prodName, current, delta, updatedQty, typeLabel, notes);
            recalcKpis();
            showToast(`Saved stock update: ${prodName} is now ${updatedQty} units`, 'success');

            // Persist to database via API
            persistAdjustmentToApi(prodId, type, amount, notes);
        }

        function persistAdjustmentToApi(productId, type, amount, notes) {
            const formData = new FormData();
            formData.append('csrf_token', '<?= csrf_token() ?>');
            formData.append('product_id', productId);
            formData.append('change_type', type);
            formData.append('amount', amount);
            formData.append('notes', notes);

            fetch('api.php?action=adjust_stock', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    showToast(data.error || 'Could not save adjustment to database', 'error');
                }
            })
            .catch(err => {
                console.warn('API error:', err);
            });
        }

        function prependShiftLog(productName, prevQty, delta, newQty, reason, notes) {
            const tbody = document.getElementById('staff-recent-log-body');
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-paper/50 bg-toteTint/20 transition-colors';
            
            const deltaBadge = delta > 0 
                ? `<span class="text-parGreen font-bold">+${delta}</span>` 
                : (delta < 0 ? `<span class="text-stockout font-bold">${delta}</span>` : `<span class="text-steel font-bold">0</span>`);

            tr.innerHTML = `
                <td class="py-3 px-4 whitespace-nowrap text-steel">Just now</td>
                <td class="py-3 px-3 font-bold text-enamel">${escapeHtml(productName)}</td>
                <td class="py-3 px-3 text-steel">Assigned machine</td>
                <td class="py-3 px-3 text-right">${prevQty}</td>
                <td class="py-3 px-3 text-right">${deltaBadge}</td>
                <td class="py-3 px-3 text-right font-bold text-enamel">${newQty}</td>
                <td class="py-3 px-4">
                    <span class="inline-block px-2 py-0.5 rounded-tag text-xs font-bold bg-parGreenBg text-parGreen border border-parGreenBorder mr-1.5">
                        ${escapeHtml(reason)}
                    </span>
                    <span class="text-steel">${notes ? escapeHtml(notes) + ' &middot; ' : ''}<strong class="text-enamel">Logged by Dave Miller</strong></span>
                </td>
            `;

            tbody.insertBefore(tr, tbody.firstChild);
        }

        function recalcKpis() {
            let lowCount = 0;
            let deficitTotal = 0;
            document.querySelectorAll('.product-row').forEach(row => {
                const qty = parseInt(row.querySelector('.item-current-qty').textContent, 10) || 0;
                const par = parseInt(row.querySelector('.item-par-capacity').textContent, 10) || 0;
                if (qty <= par) {
                    lowCount++;
                    deficitTotal += Math.max(0, par - qty);
                }
            });
            document.getElementById('kpi-low-count').textContent = lowCount;
            document.getElementById('kpi-deficit-units').textContent = '+' + deficitTotal;
        }

        function showToast(msg, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            const bgClass = type === 'success' ? 'bg-parGreenBg text-parGreen border-parGreenBorder' : 'bg-stockoutBg text-stockout border-stockoutBorder';
            toast.className = `p-3.5 text-xs font-bold rounded-control border shadow-lg flex items-center justify-between ${bgClass}`;
            toast.innerHTML = `
                <span>${escapeHtml(msg)}</span>
                <button type="button" onclick="this.parentElement.remove()" class="ml-3 text-current opacity-70 hover:opacity-100">&times;</button>
            `;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 200);
            }, 4500);
        }

        function escapeHtml(str) {
            return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
    </script>
</body>
</html>
