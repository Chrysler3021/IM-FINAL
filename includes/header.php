<?php
/**
 * Public Site Header
 */
require_once __DIR__ . '/../config/auth.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' | VendFlow' : 'VendFlow | Vending Machine Inventory Ledger' ?></title>
    <meta name="description" content="Manual inventory ledger and restock pick-list software for snack and drink vending machine operators.">

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
                    },
                    maxWidth: {
                        prose: '46ch',
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body class="min-h-full flex flex-col bg-paper text-enamel antialiased">

    <!-- Primary Navigation -->
    <header class="sticky top-0 z-40 bg-tray border-b border-coil">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">

                <!-- Brand Mark + Navigation -->
                <div class="flex items-center space-x-4 lg:space-x-8">
                    <a href="index.php" class="flex items-center space-x-2.5 text-enamel min-h-[44px]">
                        <span class="w-9 h-9 rounded-control bg-enamel text-tray flex items-center justify-center flex-shrink-0" aria-hidden="true">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <rect x="4" y="2.5" width="16" height="19" rx="2" />
                                <line x1="8" y1="7" x2="11" y2="7" />
                                <line x1="13" y1="7" x2="16" y2="7" />
                                <line x1="8" y1="11" x2="11" y2="11" />
                                <line x1="13" y1="11" x2="16" y2="11" />
                                <rect x="7.5" y="15.5" width="9" height="3" rx="0.8" />
                            </svg>
                        </span>
                        <span class="font-display font-bold text-xl tracking-tight text-enamel">VendFlow</span>
                    </a>

                    <nav class="hidden md:flex items-center space-x-1" aria-label="Main navigation">
                        <a href="index.php" <?= $currentPage === 'index.php' ? 'aria-current="page"' : '' ?> class="px-3 py-2 min-h-[44px] inline-flex items-center rounded-control text-sm <?= $currentPage === 'index.php' ? 'font-bold text-tote bg-toteTint' : 'text-steel hover:text-enamel' ?>">
                            Overview
                        </a>
                        <a href="features.php" <?= $currentPage === 'features.php' ? 'aria-current="page"' : '' ?> class="px-3 py-2 min-h-[44px] inline-flex items-center rounded-control text-sm <?= $currentPage === 'features.php' ? 'font-bold text-tote bg-toteTint' : 'text-steel hover:text-enamel' ?>">
                            Capabilities
                        </a>
                        <a href="index.php#how-it-works" class="px-3 py-2 min-h-[44px] inline-flex items-center rounded-control text-sm text-steel hover:text-enamel">
                            How it works
                        </a>
                        <a href="pricing.php" <?= $currentPage === 'pricing.php' ? 'aria-current="page"' : '' ?> class="px-3 py-2 min-h-[44px] inline-flex items-center rounded-control text-sm <?= $currentPage === 'pricing.php' ? 'font-bold text-tote bg-toteTint' : 'text-steel hover:text-enamel' ?>">
                            Pricing
                        </a>
                    </nav>
                </div>

                <!-- Right Operator Actions -->
                <div class="flex items-center space-x-2 sm:space-x-2.5">
                    <?php if (is_logged_in()): ?>
                        <a href="<?= is_staff() ? 'staff-dashboard.php' : 'dashboard.php' ?>" class="hidden sm:inline-flex items-center px-3.5 py-2 min-h-[44px] rounded-control text-sm font-bold text-tray bg-tote hover:bg-toteHover transition-colors">
                            Open workspace
                        </a>
                        <a href="logout.php" class="hidden sm:inline-flex items-center px-3 py-2 min-h-[44px] rounded-control text-sm text-steel hover:text-enamel">
                            Sign out
                        </a>
                    <?php else: ?>
                        <a href="login.php?demo=1" class="hidden md:inline-flex items-center px-3 py-2 min-h-[44px] rounded-control text-sm text-steel hover:text-enamel">
                            Live demo
                        </a>
                        <a href="login.php" class="hidden sm:inline-flex items-center px-3 py-2 min-h-[44px] rounded-control text-sm font-bold text-enamel border border-coil bg-tray hover:bg-paper transition-colors">
                            Sign in
                        </a>
                        <a href="register.php" class="hidden sm:inline-flex items-center px-4 py-2 min-h-[44px] rounded-control text-sm font-bold text-tray bg-tote hover:bg-toteHover transition-colors">
                            Create account
                        </a>
                    <?php endif; ?>

                    <!-- Mobile Menu Toggle -->
                    <button type="button" id="mobile-menu-btn" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="mobile-nav" class="md:hidden text-enamel hover:bg-paper min-w-[44px] min-h-[44px] w-11 h-11 flex items-center justify-center rounded-control border border-coil">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation Drawer -->
        <div id="mobile-nav" class="hidden md:hidden border-t border-coil bg-tray px-4 pt-3 pb-4 space-y-1.5">
            <a href="index.php" <?= $currentPage === 'index.php' ? 'aria-current="page"' : '' ?> class="flex items-center min-h-[44px] px-3 py-2 rounded-control text-sm font-bold text-enamel hover:bg-paper">Overview</a>
            <a href="features.php" <?= $currentPage === 'features.php' ? 'aria-current="page"' : '' ?> class="flex items-center min-h-[44px] px-3 py-2 rounded-control text-sm font-bold text-enamel hover:bg-paper">Capabilities</a>
            <a href="index.php#how-it-works" class="flex items-center min-h-[44px] px-3 py-2 rounded-control text-sm font-bold text-enamel hover:bg-paper">How it works</a>
            <a href="pricing.php" <?= $currentPage === 'pricing.php' ? 'aria-current="page"' : '' ?> class="flex items-center min-h-[44px] px-3 py-2 rounded-control text-sm font-bold text-enamel hover:bg-paper">Pricing</a>
            <div class="pt-3 mt-2 border-t border-coil flex flex-col space-y-2">
                <?php if (is_logged_in()): ?>
                    <a href="<?= is_staff() ? 'staff-dashboard.php' : 'dashboard.php' ?>" class="w-full flex items-center justify-center min-h-[44px] py-2.5 rounded-control text-sm font-bold bg-tote text-tray">Open workspace</a>
                    <a href="logout.php" class="w-full flex items-center justify-center min-h-[44px] py-2 text-sm text-steel">Sign out</a>
                <?php else: ?>
                    <a href="login.php?demo=1" class="w-full flex items-center justify-center min-h-[44px] py-2.5 rounded-control text-sm font-bold text-enamel bg-paper border border-coil">Open live demo</a>
                    <a href="login.php" class="w-full flex items-center justify-center min-h-[44px] py-2.5 rounded-control text-sm font-bold text-enamel border border-coil">Sign in</a>
                    <a href="register.php" class="w-full flex items-center justify-center min-h-[44px] py-2.5 rounded-control text-sm font-bold bg-tote text-tray">Create operator account</a>
                <?php endif; ?>
            </div>
        </div>
    </header>
