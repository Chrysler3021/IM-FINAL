<?php
/**
 * Capabilities & System Scope Page
 * Clearly separates live workspace features from planned roadmap items
 */
$pageTitle = "Capabilities & System Scope";
require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<section class="bg-tray border-b border-coil py-12 lg:py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-left">
        <div class="max-w-3xl space-y-4">
            <h1 class="text-enamel">
                Operational capabilities and system scope.
            </h1>
            <p class="text-base sm:text-lg text-steel leading-relaxed prose-measure">
                Every workflow currently running in the VendFlow operator workspace, alongside our hardware boundary and planned roadmap items.
            </p>
        </div>
    </div>
</section>

<!-- System Scope & Hardware Boundary -->
<section id="specs" class="py-10 bg-enamel text-tray border-b border-enamelBorder">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
            <div class="lg:col-span-8 space-y-2">
                <h2 class="font-display font-bold text-tray" style="font-size: 1.5rem;">Digital recordkeeping scope</h2>
                <p class="text-sm text-enamelMuted leading-relaxed prose-measure">
                    VendFlow is an independent web application for manual vending inventory management. It does not wire into MDB harnesses, read optical drop sensors, or physically control vending machines. Stock levels reflect the restocks, sales tallies, and audits recorded by your operators.
                </p>
            </div>
            <div class="lg:col-span-4 lg:text-right">
                <a href="login.php?demo=1" class="inline-flex items-center justify-center px-5 py-2.5 rounded-control bg-tote hover:bg-toteHover text-sm font-bold text-tray transition-colors">
                    Open live demo
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Live Capabilities Specification Ledger -->
<section class="py-16 lg:py-20 bg-paper border-b border-coil">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-14">

        <div class="pb-4 border-b border-coil">
            <h2 class="text-enamel">Live in your workspace today</h2>
            <p class="text-sm text-steel mt-1">All nine capabilities below are active immediately upon registration.</p>
        </div>

        <!-- Group 1: Fleet & SKU Cataloging -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            <div class="lg:col-span-4">
                <h3 class="text-enamel">Machine &amp; SKU ledger</h3>
                <p class="text-sm text-steel mt-1 leading-relaxed">
                    Organize physical cabinets by building placement and maintain separate product counts per machine.
                </p>
            </div>
            <div class="lg:col-span-8 bg-tray border border-coil divide-y divide-coil">
                <div class="p-5 sm:p-6 space-y-1.5">
                    <h4 class="font-display font-semibold text-base text-enamel">Multi-machine fleet registry</h4>
                    <p class="text-sm text-steel leading-relaxed prose-measure">
                        Register each physical machine with an asset code, building address or floor note, and operating status (Active, Maintenance, or Inactive).
                    </p>
                </div>
                <div class="p-5 sm:p-6 space-y-1.5">
                    <h4 class="font-display font-semibold text-base text-enamel">Snack and beverage SKU catalog</h4>
                    <p class="text-sm text-steel leading-relaxed prose-measure">
                        Add chips, chocolate bars, bottled water, sodas, juices, and pastries with SKU codes, wholesale cost, vend price, and spiral slot notes.
                    </p>
                </div>
                <div class="p-5 sm:p-6 space-y-1.5">
                    <h4 class="font-display font-semibold text-base text-enamel">Instant SKU search, filtering, and sorting</h4>
                    <p class="text-sm text-steel leading-relaxed prose-measure">
                        Filter the ledger by assigned vending machine, snack category, or par status (All, Below par, Empty, or At par) and sort by unit count or recent updates.
                    </p>
                </div>
            </div>
        </div>

        <!-- Group 2: Restock Dispatch & Audits -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start pt-8 border-t border-coil">
            <div class="lg:col-span-4">
                <h3 class="text-enamel">Restock dispatch &amp; adjustments</h3>
                <p class="text-sm text-steel mt-1 leading-relaxed">
                    Spot empty spirals before loading totes and keep a permanent trail of every unit change.
                </p>
            </div>
            <div class="lg:col-span-8 bg-tray border border-coil divide-y divide-coil">
                <div class="p-5 sm:p-6 space-y-1.5">
                    <h4 class="font-display font-semibold text-base text-enamel">Low-stock pick-list and par alerts</h4>
                    <p class="text-sm text-steel leading-relaxed prose-measure">
                        Flags any product whose recorded quantity drops to or below its configured par threshold, calculates exact units needed to reach par, and opens a pre-filled restock dialog in one click.
                    </p>
                </div>
                <div class="p-5 sm:p-6 space-y-1.5">
                    <h4 class="font-display font-semibold text-base text-enamel">Four-mode manual stock adjustments</h4>
                    <p class="text-sm text-steel leading-relaxed prose-measure">
                        Log restock batches, deduct manual sales tallies, write off damaged or expired items, or set an exact physical count with automatic zero-floor protection.
                    </p>
                </div>
                <div class="p-5 sm:p-6 space-y-1.5">
                    <h4 class="font-display font-semibold text-base text-enamel">Dispatch summary &amp; retail valuation</h4>
                    <p class="text-sm text-steel leading-relaxed prose-measure">
                        View active machine count, cataloged SKUs, total stocked units, retail inventory value, and urgent restock rows on one screen.
                    </p>
                </div>
                <div class="p-5 sm:p-6 space-y-1.5">
                    <h4 class="font-display font-semibold text-base text-enamel">Immutable adjustment audit log</h4>
                    <p class="text-sm text-steel leading-relaxed prose-measure">
                        Every stock update records the timestamp, operator name, machine, previous count, net delta, resulting count, reason code, and note.
                    </p>
                </div>
            </div>
        </div>

        <!-- Group 3: Account Isolation & Licensing -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start pt-8 border-t border-coil">
            <div class="lg:col-span-4">
                <h3 class="text-enamel">Workspace isolation &amp; licensing</h3>
                <p class="text-sm text-steel mt-1 leading-relaxed">
                    Private operator accounts with tenant-level database isolation and configurable currency preferences.
                </p>
            </div>
            <div class="lg:col-span-8 bg-tray border border-coil divide-y divide-coil">
                <div class="p-5 sm:p-6 space-y-1.5">
                    <h4 class="font-display font-semibold text-base text-enamel">Multi-tenant operator isolation</h4>
                    <p class="text-sm text-steel leading-relaxed prose-measure">
                        Authenticated sessions use bcrypt password hashing, CSRF form verification, and strict `user_id` scoping on every SQL query.
                    </p>
                </div>
                <div class="p-5 sm:p-6 space-y-1.5">
                    <h4 class="font-display font-semibold text-base text-enamel">Software license tiers &amp; demo reset</h4>
                    <p class="text-sm text-steel leading-relaxed prose-measure">
                        Provision Starter, Business, or Enterprise license keys, configure default par thresholds and currency symbols, or reset sample fleet records in one click.
                    </p>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- Planned Roadmap (Clearly Distinguished from Live Software) -->
<section class="py-16 lg:py-20 bg-tray">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

            <div class="lg:col-span-4 space-y-2">
                <h2 class="text-enamel">Planned roadmap</h2>
                <p class="text-sm text-steel leading-relaxed prose-measure">
                    Scheduled enhancements currently in design. These items are not yet active in the current license build.
                </p>
            </div>

            <div class="lg:col-span-8 border-t border-b border-coil divide-y divide-coil">
                <div class="py-5 space-y-1">
                    <h3 class="text-enamel">Smartphone camera barcode lookup</h3>
                    <p class="text-sm text-steel leading-relaxed prose-measure">
                        Scan a snack wrapper or bottle UPC with a phone camera to jump straight to that SKU's stock adjustment dialog at the machine.
                    </p>
                </div>
                <div class="py-5 space-y-1">
                    <h3 class="text-enamel">Printable CSV and PDF tote packing sheet</h3>
                    <p class="text-sm text-steel leading-relaxed prose-measure">
                        Export a grouped warehouse pull-sheet totaling case and unit quantities across every machine scheduled on that morning's route.
                    </p>
                </div>
                <div class="py-5 space-y-1">
                    <h3 class="text-enamel">Stop-by-stop route sequence list</h3>
                    <p class="text-sm text-steel leading-relaxed prose-measure">
                        Order buildings with low-stock alerts into a sequential driver stop list before loading the van.
                    </p>
                </div>
            </div>

        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
