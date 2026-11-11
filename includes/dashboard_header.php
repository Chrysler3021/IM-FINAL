<?php
/**
 * Shared PostHog Dashboard Shell Header & Sidebar Navigation
 * VendFlow Operator Workspace
 */
require_once __DIR__ . '/functions.php';

$user = require_auth();

// Keep root Superadmin inside superadmin.php unless actively impersonating a client workspace
if (($user['role'] ?? '') === 'superadmin' && !isset($_SESSION['impersonator_id'])) {
    header("Location: superadmin.php");
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']);
$db = get_db();
$userId = (int)$user['id'];
$workspaceOwnerId = get_workspace_owner_id($user);

// Fetch live sidebar badge counts via consolidated workspace stats helper
$workspaceStats = get_workspace_stats($db, $workspaceOwnerId);
$sidebarLowStockCount = $workspaceStats['low_stock_count'];
$sidebarProductsCount = $workspaceStats['total_skus'];
$sidebarMachinesCount = $workspaceStats['total_machines'];
$sidebarStaffCount    = $workspaceStats['total_staff'];

$projectName  = !empty($user['business_name']) ? $user['business_name'] : 'Metro Vending Co.';
$pageTitle    = $pageTitle ?? 'VendFlow Workspace';
$pageHeader   = $pageHeader ?? 'Inventory overview';
$pageSubtitle = $pageSubtitle ?? 'Digital vending machine inventory recordkeeping.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> • VendFlow</title>
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
</head>
<body class="dashboard-body">

    <!-- Thin Top Purple Accent Stripe -->
    <div class="top-accent-bar"></div>

    <?php if (isset($_SESSION['impersonator_id'])): ?>
        <div style="background: #18181b; color: #f4f4f5; padding: 0.42rem 1.25rem; font-size: 0.8rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-shrink: 0; border-bottom: 1px solid #27272a;">
            <div style="display: flex; align-items: center; gap: 0.55rem;">
                <span style="background: #f54e00; color: #ffffff; font-size: 0.68rem; font-weight: 800; padding: 0.1rem 0.45rem; border-radius: 4px; text-transform: uppercase;">Impersonating</span>
                <span>Viewing operator workspace as <strong><?= e($user['name']) ?></strong> (<?= e($user['business_name'] ?: $user['email']) ?>)</span>
            </div>
            <a href="superadmin.php?exit_impersonate=1" style="background: #ffffff; color: #111827; padding: 0.24rem 0.7rem; border-radius: 5px; text-decoration: none; font-size: 0.75rem; font-weight: 700; white-space: nowrap;">
                Exit to Superadmin &rarr;
            </a>
        </div>
    <?php endif; ?>

    <div class="dashboard-wrapper">

        <!-- ===============================================================
             Left Sidebar (PostHog Dashboard Layout)
             =============================================================== -->
        <aside class="sidebar">

            <!-- Project Selector -->
            <a href="settings.php" class="project-selector" title="Workspace Settings" style="text-decoration: none;">
                <div class="project-badge">
                    <span class="badge-avatar"><?= e(strtoupper(substr($projectName, 0, 1))) ?></span>
                    <span style="max-width: 135px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <?= e($projectName) ?>
                    </span>
                    <span style="width: 12px; height: 12px; display: inline-flex; color: var(--text-muted);"><?= posthog_icon('chevron_down') ?></span>
                </div>
                <span class="kbd-shortcut">Ctrl K</span>
            </a>

            <!-- Operator Meta Strip -->
            <div class="sidebar-operator-meta">
                <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 145px; display: inline-flex; align-items: center; gap: 4px;">
                    <span style="width: 13px; height: 13px; display: inline-flex; color: var(--text-muted); flex-shrink: 0;"><?= posthog_icon('user') ?></span>
                    <span><?= e($user['name']) ?></span>
                </span>
                <span class="tier-tag-mini"><?= is_staff($user) ? 'STAFF' : e($user['plan_tier'] ?: 'unlimited') ?></span>
            </div>

            <!-- Primary Operational Navigation Menu -->
            <ul class="sidebar-menu">
                <li>
                    <a href="dashboard.php" class="menu-item <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                        <div class="menu-left">
                            <span class="menu-icon"><?= posthog_icon('dashboard') ?></span>
                            <span>Inventory overview</span>
                        </div>
                    </a>
                </li>

                <li>
                    <a href="low-stock.php" class="menu-item <?= $currentPage === 'low-stock.php' ? 'active' : '' ?>">
                        <div class="menu-left">
                            <span class="menu-icon" style="color:#d97706;"><?= posthog_icon('warning') ?></span>
                            <span>Low stock alerts</span>
                        </div>
                        <?php if ($sidebarLowStockCount > 0): ?>
                            <span class="sidebar-pill-badge alert"><?= $sidebarLowStockCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li>
                    <a href="inventory.php" class="menu-item <?= $currentPage === 'inventory.php' ? 'active' : '' ?>">
                        <div class="menu-left">
                            <span class="menu-icon"><?= posthog_icon('box') ?></span>
                            <span>Products &amp; stock</span>
                        </div>
                        <span class="sidebar-pill-badge"><?= $sidebarProductsCount ?></span>
                    </a>
                </li>

                <li>
                    <a href="machines.php" class="menu-item <?= $currentPage === 'machines.php' ? 'active' : '' ?>">
                        <div class="menu-left">
                            <span class="menu-icon"><?= posthog_icon('building') ?></span>
                            <span>Machines</span>
                        </div>
                        <span class="sidebar-pill-badge"><?= $sidebarMachinesCount ?></span>
                    </a>
                </li>

                <li>
                    <a href="audit-log.php" class="menu-item <?= in_array($currentPage, ['audit-log.php', 'history.php'], true) ? 'active' : '' ?>">
                        <div class="menu-left">
                            <span class="menu-icon"><?= posthog_icon('activity') ?></span>
                            <span>Audit log / Activity</span>
                        </div>
                    </a>
                </li>

                <?php if (!is_staff($user)): ?>
                <li>
                    <a href="staff.php" class="menu-item <?= $currentPage === 'staff.php' ? 'active' : '' ?>">
                        <div class="menu-left">
                            <span class="menu-icon" style="color:#0ea5e9;"><?= posthog_icon('people') ?></span>
                            <span>Staff &amp; team</span>
                        </div>
                        <?php if ($sidebarStaffCount > 0): ?>
                            <span class="sidebar-pill-badge"><?= $sidebarStaffCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (is_staff($user)): ?>
                <li>
                    <a href="staff-dashboard.php" class="menu-item <?= $currentPage === 'staff-dashboard.php' ? 'active' : '' ?>">
                        <div class="menu-left">
                            <span class="menu-icon" style="color:#0ea5e9;"><?= posthog_icon('document') ?></span>
                            <span>Staff restock portal</span>
                        </div>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Section: Workspace & Licensing -->
                <li class="menu-section-header">
                    <span>Workspace</span>
                    <span style="width: 12px; height: 12px; display: inline-flex; color: var(--text-muted);"><?= posthog_icon('chevron_down') ?></span>
                </li>

                <?php if (isset($_SESSION['impersonator_id']) || ($user['role'] ?? '') === 'superadmin'): ?>
                <li>
                    <a href="superadmin.php?exit_impersonate=1" class="menu-item">
                        <div class="menu-left">
                            <span class="menu-icon" style="color:#f59e0b;"><?= posthog_icon('shield_lock') ?></span>
                            <span>Superadmin console</span>
                        </div>
                    </a>
                </li>
                <?php endif; ?>

                <li>
                    <a href="settings.php" class="menu-item <?= $currentPage === 'settings.php' ? 'active' : '' ?>">
                        <div class="menu-left">
                            <span class="menu-icon" style="color:#6366f1;"><?= posthog_icon('gear') ?></span>
                            <span>Account &amp; settings</span>
                        </div>
                    </a>
                </li>

                <?php if (!is_staff($user)): ?>
                <li>
                    <a href="pricing.php" class="menu-item <?= $currentPage === 'pricing.php' ? 'active' : '' ?>">
                        <div class="menu-left">
                            <span class="menu-icon" style="color:#f59e0b;"><?= posthog_icon('receipt') ?></span>
                            <span>License plans</span>
                        </div>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Section: Starred Placeholders -->
                <li class="menu-section-header">
                    <span>Starred</span>
                    <span style="width: 12px; height: 12px; display: inline-flex; color: var(--text-muted);"><?= posthog_icon('chevron_down') ?></span>
                </li>

                <li>
                    <a href="contact.php" class="menu-item">
                        <div class="menu-left">
                            <span class="menu-icon" style="color:#ec4899;"><?= posthog_icon('support') ?></span>
                            <span>Support</span>
                        </div>
                    </a>
                </li>
            </ul>

            <!-- Bottom Sidebar Links & Logout -->
            <div class="sidebar-bottom">
                <a href="settings.php" class="menu-item" title="Account Settings">
                    <div class="menu-left">
                        <span class="menu-icon"><?= posthog_icon('gear') ?></span>
                        <span>Settings</span>
                    </div>
                </a>
                <a href="logout.php" class="menu-item" title="Log out & return to Landing Page">
                    <div class="menu-left">
                        <span class="menu-icon"><?= posthog_icon('leave') ?></span>
                        <span>Log out</span>
                    </div>
                    <span style="width: 13px; height: 13px; display: inline-flex; color: var(--text-muted);"><?= posthog_icon('arrow_right') ?></span>
                </a>
            </div>

        </aside>

        <!-- ===============================================================
             Main Canvas
             =============================================================== -->
        <main class="main-canvas">

            <!-- Flash Messages -->
            <?php displayFlashMessage(); ?>

            <!-- Shared Page Title & Global Quick Actions -->
            <div class="dash-content-header">
                <div class="dash-title-group">
                    <h1><?= e($pageHeader) ?></h1>
                    <?php if (!empty($pageSubtitle)): ?>
                        <p class="dash-subtitle"><?= e($pageSubtitle) ?></p>
                    <?php endif; ?>
                </div>
                <div class="dash-action-group">
                    <?php if (!is_staff($user)): ?>
                        <button type="button" class="btn-dash-outline" onclick="openAddStaffModal()">+ Add staff</button>
                    <?php endif; ?>
                    <?php if (can_manage_machines($user)): ?>
                        <button type="button" class="btn-dash-outline" onclick="openAddMachineModal()">Add machine</button>
                    <?php endif; ?>
                    <button type="button" class="btn-dash-primary" onclick="openAddProductModal()">+ Add product</button>
                </div>
            </div>
