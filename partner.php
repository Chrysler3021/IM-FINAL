<?php
/**
 * VendFlow - Partner & Operator Platform Landing Page
 * Pure Vanilla PHP + MySQL PDO + 1-Click Instant Demo Workspace Entry
 * Redesigned with the Black & Gold Elegance Design System
 */
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'VendFlow • The Open Platform for Vending Operations';
$isSignedIn = is_logged_in();
$isRootAdmin = $isSignedIn && is_superadmin() && !isset($_SESSION['impersonator_id']);
$workspaceEntryUrl = $isSignedIn ? ($isRootAdmin ? 'superadmin.php' : 'dashboard.php') : 'dashboard.php?demo=1';
$activeWindow = $initialWindow ?? ($_GET['window'] ?? 'home');
if (!in_array($activeWindow, ['home', 'about', 'register', 'login', 'licenses', 'email_sent'], true)) {
    $activeWindow = 'home';
}
$emailSentParam = trim($_GET['email'] ?? '');
$loginError = $loginError ?? '';
$registerError = $registerError ?? '';
$signedInUser = $isSignedIn ? current_user() : null;

// Fetch live database context safely
$db = getDBConnection();
$dbConnected = ($db !== null);

$sampleMachines = [];
$sampleProducts = [];

if ($dbConnected) {
    try {
        $stmtM = $db->query("SELECT name, location, status FROM machines ORDER BY id ASC LIMIT 3");
        $sampleMachines = $stmtM->fetchAll();

        $stmtP = $db->query("SELECT name, category, quantity, sell_price FROM products ORDER BY id DESC LIMIT 4");
        $sampleProducts = $stmtP->fetchAll();
    } catch (PDOException $e) {
        error_log("Landing DB Query notice: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Black and Gold Elegance Palette (#FFFFFF, #E5E5E5, #FCA311, #14213D, #000000) */
            --bg: #FFFFFF;
            --primary: #FFFFFF;
            --platinum: #E5E5E5;
            --secondary: #FCA311;
            --secondary-hover: #E5920A;
            --secondary-tint: #FFF8EB;
            --tertiary: #14213D;
            --tertiary-hover: #0E182D;
            --tertiary-tint: #F3F5F9;
            --tertiary-soft: #E6EAF2;
            --black: #000000;

            /* Typography & Neutrals */
            --ink: #000000;
            --mute: #54627B;
            --line: #E5E5E5;
            --line-subtle: #EEEEEE;
            --card-bg: #FFFFFF;

            /* Status Accents */
            --status-success: #15803D;
            --status-success-bg: #DCFCE7;
            --status-warning: #B45309;
            --status-warning-bg: #FEF3C7;
            --status-danger: #B91C1C;
            --status-danger-bg: #FEE2E2;

            /* Radii & Motion */
            --radius-card: 26px;
            --radius-pill: 999px;
            --shadow-soft: 0 12px 30px -10px rgba(20, 33, 61, 0.08);
            --shadow-hover: 0 20px 40px -12px rgba(20, 33, 61, 0.16);
            --ease: cubic-bezier(.16, 1, .3, 1);

            box-sizing: border-box;
            color-scheme: light;
        }

        html {
            scroll-behavior: smooth;
        }

        *, *::before, *::after {
            box-sizing: inherit;
            margin: 0;
            padding: 0;
        }

        body {
            background: var(--bg);
            color: var(--ink);
            font-family: 'Outfit', system-ui, -apple-system, sans-serif;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button {
            font: inherit;
            cursor: pointer;
        }

        ::selection {
            background: var(--secondary);
            color: var(--black);
        }

        :focus-visible {
            outline: 2px solid var(--secondary);
            outline-offset: 3px;
            border-radius: 8px;
        }

        .wrap {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
            width: 100%;
        }

        h1, h2, h3, h4 {
            margin: 0;
            letter-spacing: -0.025em;
            line-height: 1.1;
            color: var(--tertiary);
        }

        h2 {
            font-size: clamp(1.75rem, 3.2vw, 2.3rem);
            font-weight: 700;
        }

        .sub {
            color: var(--mute);
            margin: 0.5rem 0 0;
            font-size: 1rem;
            max-width: 52ch;
            font-weight: 400;
        }

        .sec {
            padding: 88px 0 0;
        }

        .head {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 24px;
            margin-bottom: 34px;
        }

        .more {
            font-size: 0.94rem;
            font-weight: 600;
            color: var(--secondary);
            display: inline-flex;
            gap: 6px;
            align-items: center;
            white-space: nowrap;
            transition: color 0.2s ease, transform 0.2s ease;
        }

        .more:hover {
            color: var(--secondary-hover);
            transform: translateX(2px);
        }

        /* ===================================================================
           Top Navigation Bar (Identical to index.php)
           =================================================================== */
        .public-navbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: var(--primary);
            border-bottom: 1px solid var(--line);
            padding: 0.9rem 1.5rem;
        }

        .navbar-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .brand-logo {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            text-decoration: none;
            color: var(--tertiary);
            font-weight: 800;
            font-size: 1.35rem;
            letter-spacing: -0.02em;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 1.75rem;
            list-style: none;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--tertiary);
            font-weight: 600;
            font-size: 0.92rem;
            transition: color 0.16s ease;
            position: relative;
            padding: 4px 0;
        }

        .nav-links a::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 2px;
            background: var(--secondary);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s var(--ease);
        }

        .nav-links a:hover {
            color: var(--secondary);
        }

        .nav-links a:hover::after {
            transform: scaleX(1);
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .nav-signin-link {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--tertiary);
            padding: 0.5rem 0.8rem;
            transition: color 0.18s ease;
        }

        .nav-signin-link:hover {
            color: var(--secondary);
        }

        .nav-partner-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            background: var(--tertiary);
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.86rem;
            padding: 0.6rem 1.25rem;
            border-radius: var(--radius-pill);
            transition: background 0.18s ease, color 0.18s ease, transform 0.18s ease;
        }

        .nav-partner-btn:hover {
            background: var(--secondary);
            color: var(--black);
            transform: translateY(-1px);
        }

        /* ===================================================================
           Desktop Hero Section (Cinder & Bean Architecture)
           =================================================================== */
        .hero {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            align-items: center;
            gap: 48px;
            min-height: min(78vh, 660px);
            padding: 48px 0 36px;
        }

        .hero-label {
            font-size: 0.92rem;
            font-weight: 600;
            color: var(--tertiary);
            margin-bottom: 0.75rem;
            letter-spacing: 0.01em;
            display: inline-block;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--tertiary);
            background: var(--secondary-tint);
            border-radius: var(--radius-pill);
            padding: 7px 16px;
            border: 1px solid rgba(252, 163, 17, 0.35);
        }

        .chip i {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--secondary);
            animation: pulse 2.4s ease-in-out infinite;
        }

        .hero h1 {
            font-size: clamp(2.5rem, 4.8vw, 4rem);
            font-weight: 800;
            margin: 20px 0 0;
            color: var(--black);
            letter-spacing: -0.035em;
            line-height: 1.05;
        }

        .hero h1 span {
            display: block;
            font-weight: 300;
            color: var(--mute);
        }

        .hero h1 em {
            font-style: normal;
            color: var(--secondary);
        }

        .hero p {
            max-width: 50ch;
            color: var(--mute);
            margin: 18px 0 28px;
            font-size: 1.08rem;
            line-height: 1.55;
            font-weight: 400;
        }

        .cta {
            display: flex;
            gap: 14px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 14px;
        }

        .btn {
            background: var(--tertiary);
            color: var(--primary);
            border: 0;
            border-radius: var(--radius-pill);
            padding: 14px 28px;
            font-weight: 700;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: transform 0.25s var(--ease), background 0.2s ease, color 0.2s ease;
        }

        .btn:hover {
            background: var(--secondary);
            color: var(--black);
            transform: translateY(-2px);
        }

        .btn-gold {
            background: var(--secondary);
            color: var(--black);
        }

        .btn-gold:hover {
            background: var(--secondary-hover);
            color: var(--black);
        }

        .ghost {
            font-weight: 600;
            padding: 14px 8px;
            color: var(--tertiary);
            border-bottom: 2px solid var(--line);
            transition: border-color 0.2s ease, color 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .ghost:hover {
            border-color: var(--secondary);
            color: var(--secondary);
        }

        .trust-metrics-strip {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            flex-wrap: wrap;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--line);
            font-size: 0.84rem;
            font-weight: 600;
            color: var(--mute);
        }

        .trust-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .trust-item-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 14px;
            height: 14px;
            color: var(--secondary);
            flex-shrink: 0;
        }

        .btn-icon-wrap {
            width: 15px;
            height: 15px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            vertical-align: -2px;
            margin-right: 6px;
        }

        .inline-ico {
            width: 15px;
            height: 15px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            vertical-align: -2px;
            margin-right: 4px;
            flex-shrink: 0;
        }

        /* Hero Right-Column Art Plate */
        .hero-art-w {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
        }

        .hero-showcase-card {
            position: relative;
            width: 100%;
            max-width: 460px;
            aspect-ratio: 1 / 1.05;
            background: var(--platinum);
            border-radius: 36px;
            padding: 22px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            border: 1px solid var(--line);
            box-shadow: var(--shadow-soft);
        }

        .hero-showcase-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 24px;
            display: block;
        }

        /* ===================================================================
           Section 1: Operator Access & Onboarding Hub (#portal)
           Segmented Tabs for Registration, Sign In, and Email Sent
           =================================================================== */
        .portal-segment-nav {
            display: inline-flex;
            background: var(--platinum);
            padding: 6px;
            border-radius: var(--radius-pill);
            border: 1px solid var(--line);
            gap: 6px;
            margin-bottom: 2rem;
            max-width: 100%;
            overflow-x: auto;
        }

        .portal-segment-btn {
            background: transparent;
            color: var(--tertiary);
            border: none;
            border-radius: var(--radius-pill);
            padding: 10px 22px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .portal-segment-btn.active {
            background: var(--tertiary);
            color: var(--primary);
            box-shadow: 0 4px 12px rgba(20, 33, 61, 0.15);
        }

        .portal-segment-btn:hover:not(.active) {
            color: var(--secondary);
        }

        .portal-tab-panel {
            display: none;
        }

        .portal-tab-panel.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        .card-frame {
            background: var(--card-bg);
            border: 1px solid var(--line);
            border-radius: var(--radius-card);
            padding: 2.25rem;
            box-shadow: var(--shadow-soft);
        }

        /* Forms Styling */
        .public-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .public-grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .public-form-group {
            margin-bottom: 1rem;
        }

        .public-label {
            display: block;
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--tertiary);
            margin-bottom: 0.4rem;
        }

        .public-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--line);
            border-radius: 12px;
            font-family: inherit;
            font-size: 0.92rem;
            color: var(--black);
            background: var(--primary);
            outline: none;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .public-input:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(252, 163, 17, 0.15);
        }

        /* Fleet Calculator Box */
        .calc-box {
            background: var(--secondary-tint);
            border: 1.5px solid rgba(252, 163, 17, 0.4);
            border-radius: 18px;
            padding: 1.4rem;
            margin-bottom: 1.25rem;
        }

        .calc-stepper-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            background: var(--primary);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 1rem;
            margin: 0.85rem 0;
        }

        .stepper-btn {
            width: 36px;
            height: 36px;
            border: 1px solid var(--line);
            background: var(--platinum);
            border-radius: 8px;
            font-weight: 800;
            cursor: pointer;
            color: var(--tertiary);
            font-size: 1.1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s ease;
        }

        .stepper-btn:hover {
            background: var(--secondary);
            color: var(--black);
        }

        .stepper-input {
            height: 36px;
            text-align: center;
            font-weight: 800;
            border-radius: 8px;
        }

        /* Alerts */
        .public-alert {
            padding: 0.85rem 1.15rem;
            border-radius: 12px;
            font-size: 0.88rem;
            line-height: 1.5;
            margin-bottom: 1.25rem;
        }

        .public-alert-info {
            background: var(--tertiary-tint);
            border: 1px solid var(--tertiary-soft);
            color: var(--tertiary);
        }

        .public-alert-error {
            background: var(--status-danger-bg);
            border: 1px solid #FECACA;
            color: var(--status-danger);
            font-weight: 600;
        }

        /* ===================================================================
           Section 2: Pricing & Software Licenses (#pricing)
           =================================================================== */
        .licenses-metric-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .metric-card {
            background: var(--card-bg);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 1.4rem;
            box-shadow: var(--shadow-soft);
            transition: transform 0.25s ease;
        }

        .metric-card:hover {
            transform: translateY(-3px);
            border-color: var(--secondary);
        }

        .metric-card .label {
            font-size: 0.75rem;
            font-weight: 800;
            color: var(--mute);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .metric-card .val {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--tertiary);
            margin: 0.25rem 0;
        }

        .metric-card .desc {
            font-size: 0.78rem;
            color: var(--status-success);
            font-weight: 700;
        }

        .pricing-layout-grid {
            display: grid;
            grid-template-columns: 1fr 1.35fr;
            gap: 24px;
        }

        .pricing-featured-box {
            background: var(--card-bg);
            border: 2px solid var(--secondary);
            border-radius: var(--radius-card);
            padding: 2.25rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 16px 36px rgba(252, 163, 17, 0.12);
        }

        .pricing-check-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin: 1.4rem 0;
            font-size: 0.92rem;
            color: var(--ink);
        }

        .pricing-check-list li {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pricing-check-list li span:first-child {
            color: var(--status-success);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 16px;
            height: 16px;
            flex-shrink: 0;
        }

        /* Comparison Table */
        .table-wrap {
            overflow-x: auto;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--primary);
        }

        .comp-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
        }

        .comp-table th {
            padding: 0.85rem 1.15rem;
            text-align: left;
            font-weight: 800;
            background: var(--platinum);
            color: var(--tertiary);
            border-bottom: 1px solid var(--line);
        }

        .comp-table td {
            padding: 0.85rem 1.15rem;
            border-bottom: 1px solid var(--line-subtle);
        }

        /* Steps Row */
        .steps-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 1.25rem;
        }

        .step-tile {
            background: var(--tertiary-tint);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 1.15rem;
        }

        .step-num {
            font-size: 0.8rem;
            font-weight: 800;
            color: var(--secondary);
            margin-bottom: 0.3rem;
            text-transform: uppercase;
        }

        .step-text {
            font-size: 0.85rem;
            color: var(--mute);
            line-height: 1.45;
        }

        /* ===================================================================
           Section 3: About Us & The Anti-SaaS Manifesto (#about)
           =================================================================== */
        .bento-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }

        .bento-card {
            background: var(--card-bg);
            border: 1px solid var(--line);
            border-radius: var(--radius-card);
            padding: 2rem;
            box-shadow: var(--shadow-soft);
            transition: transform 0.25s ease, border-color 0.2s ease;
        }

        .bento-card:hover {
            transform: translateY(-4px);
            border-color: var(--secondary);
        }

        .bento-card.col-span-2 {
            grid-column: span 2;
        }

        .bento-card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: var(--secondary-tint);
            color: var(--secondary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
            margin-bottom: 1.25rem;
            border: 1px solid rgba(252, 163, 17, 0.3);
        }

        .stat-pills-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1.25rem;
        }

        .stat-pill {
            background: var(--platinum);
            color: var(--tertiary);
            padding: 0.35rem 0.85rem;
            border-radius: var(--radius-pill);
            font-size: 0.78rem;
            font-weight: 700;
            border: 1px solid var(--line);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .stat-pill-ico {
            width: 14px;
            height: 14px;
            display: inline-flex;
            color: var(--secondary);
            flex-shrink: 0;
        }

        /* ===================================================================
           Footer (Identical to index.php)
           =================================================================== */
        footer {
            margin-top: 100px;
            background: var(--tertiary);
            color: var(--primary);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .fgrid {
            display: grid;
            grid-template-columns: 1.4fr repeat(4, 1fr);
            gap: 40px;
            padding: 64px 0 48px;
        }

        .fgrid p {
            color: rgba(255, 255, 255, 0.75);
            font-size: 0.92rem;
            max-width: 30ch;
            margin: 14px 0 0;
            line-height: 1.5;
        }

        .fgrid h4 {
            margin: 0 0 16px;
            font-size: 0.98rem;
            font-weight: 700;
            color: var(--primary);
        }

        .fgrid ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 10px;
            font-size: 0.92rem;
            color: rgba(255, 255, 255, 0.75);
        }

        .fgrid a {
            transition: color 0.16s ease;
        }

        .fgrid a:hover {
            color: var(--secondary);
        }

        .legal {
            display: flex;
            flex-wrap: wrap;
            gap: 12px 28px;
            justify-content: space-between;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            padding: 24px 0 32px;
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.65);
        }

        .legal div {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
        }

        .legal a:hover {
            color: var(--primary);
        }

        /* Animations */
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.45; transform: scale(0.7); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive Breakpoints */
        @media (max-width: 960px) {
            .nav-links {
                display: none;
            }
            .hero {
                grid-template-columns: 1fr;
                min-height: 0;
                gap: 36px;
                padding-top: 24px;
            }
            .hero-art-w {
                order: -1;
                max-width: 440px;
                margin: 0 auto;
            }
            .pricing-layout-grid,
            .bento-grid {
                grid-template-columns: 1fr;
            }
            .bento-card.col-span-2 {
                grid-column: span 1;
            }
            .licenses-metric-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .fgrid {
                grid-template-columns: 1fr 1fr;
                gap: 32px;
            }
            .fgrid > div:first-child {
                grid-column: 1 / -1;
            }
            .public-grid-3,
            .public-grid-2 {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 540px) {
            .wrap {
                padding: 0 18px;
            }
            .licenses-metric-grid,
            .steps-grid,
            .calc-stepper-grid {
                grid-template-columns: 1fr;
            }
            .fgrid {
                grid-template-columns: 1fr;
            }
            .cta {
                flex-direction: column;
                align-items: stretch;
            }
            .btn {
                justify-content: center;
            }
        }
    </style>
</head>
<body>

    <!-- SVG Icon Sprite Definitions -->
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
        <defs>
            <symbol id="arr" viewBox="0 0 16 16">
                <path d="M3 8h10m-4-4 4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            </symbol>
        </defs>
    </svg>

    <!-- ===================================================================
         Top Navigation Bar (Consistent with index.php)
         =================================================================== -->
    <header class="public-navbar">
        <div class="navbar-container">
            <a href="partner.php" class="brand-logo">VendFlow</a>

            <ul class="nav-links">
                <li><a href="index.php">Public Vendos</a></li>
                <li><a href="#about">Why VendFlow</a></li>
                <li><a href="#pricing">Fleet Pricing</a></li>
                <li><a href="#portal">Operator Portal</a></li>
                <li><a href="contact.php">Contact</a></li>
            </ul>

            <div class="nav-actions">
                <?php if ($isSignedIn): ?>
                    <a href="<?= e($workspaceEntryUrl) ?>" class="nav-partner-btn">
                        <span><?= $isRootAdmin ? 'Superadmin Console' : 'Open Workspace' ?></span>
                        <svg width="14" height="14"><use href="#arr"/></svg>
                    </a>
                    <a href="logout.php" class="nav-signin-link" title="Sign Out">Sign Out</a>
                <?php else: ?>
                    <a href="javascript:void(0)" onclick="openDesktopWindow('login')" class="nav-signin-link">Sign In</a>
                    <a href="dashboard.php?demo=1" class="nav-partner-btn">
                        <span>1-Click Demo</span>
                        <svg width="14" height="14"><use href="#arr"/></svg>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main>
        <!-- ===================================================================
             Hero Section (2-Column Desktop Grid Architecture)
             =================================================================== -->
        <section class="wrap hero">
            <div>
                <div class="hero-label">Operator Platform &bull; Digital Fleet Ledger</div>
                <h1><span>Take control of your</span><em>vending fleet,</em> without monthly SaaS taxes</h1>
                <p>The open digital inventory platform for route operators. Replace clipboards and $40/mo per-machine hardware tolls with instant par monitoring, visual stock cards, and 100% database ownership.</p>

                <div class="cta">
                    <a class="btn btn-gold" href="dashboard.php?demo=1">1-Click Demo Workspace <svg width="14" height="14"><use href="#arr"/></svg></a>
                    <button type="button" class="btn" onclick="openDesktopWindow('register')">Create Operator Account <svg width="14" height="14"><use href="#arr"/></svg></button>
                    <button type="button" class="ghost" onclick="openDesktopWindow('login')">Sign In &rarr;</button>
                </div>

                <div class="trust-metrics-strip">
                    <div class="trust-item"><span class="trust-item-icon"><?= posthog_icon('check') ?></span><span>$0 Per-Machine Recurring Tolls</span></div>
                    <div class="trust-item"><span class="trust-item-icon"><?= posthog_icon('check') ?></span><span>No Proprietary Telemetry Dongles</span></div>
                    <div class="trust-item"><span class="trust-item-icon"><?= posthog_icon('check') ?></span><span>100% MySQL Data Ownership</span></div>
                </div>
            </div>

            <!-- Balanced Desktop Art Showcase Plate -->
            <div class="hero-art-w">
                <div class="hero-showcase-card">
                    <img
                        src="assets/images/vendos/vendo-candies.jpg"
                        alt="VendFlow Operator Fleet Network"
                        class="hero-showcase-img"
                    >
                </div>
            </div>
        </section>

        <!-- ===================================================================
             Section 1: Operator Access & Onboarding Hub (#portal)
             =================================================================== -->
        <section class="wrap sec" id="portal">
            <div class="head">
                <div>
                    <h2>Operator portal &amp; onboarding</h2>
                    <p class="sub">Configure your fleet software license or launch the instant evaluator sandbox.</p>
                </div>
                <div class="portal-segment-nav" role="tablist">
                    <button
                        type="button"
                        class="portal-segment-btn <?= in_array($activeWindow, ['home', 'register'], true) ? 'active' : '' ?>"
                        id="tab-btn-register"
                        onclick="switchPortalTab('register')"
                    >
                        <span class="btn-icon-wrap"><?= posthog_icon('people') ?></span> Create Account &amp; License
                    </button>
                    <button
                        type="button"
                        class="portal-segment-btn <?= $activeWindow === 'login' ? 'active' : '' ?>"
                        id="tab-btn-login"
                        onclick="switchPortalTab('login')"
                    >
                        <span class="btn-icon-wrap"><?= posthog_icon('user') ?></span> Sign In &amp; 1-Click Demo
                    </button>
                    <button
                        type="button"
                        class="portal-segment-btn <?= $activeWindow === 'email_sent' ? 'active' : '' ?>"
                        id="tab-btn-email_sent"
                        style="<?= $activeWindow === 'email_sent' ? '' : 'display:none;' ?>"
                        onclick="switchPortalTab('email_sent')"
                    >
                        <span class="btn-icon-wrap"><?= posthog_icon('mail') ?></span> Verification Sent
                    </button>
                </div>
            </div>

            <!-- TAB 1: Register Account & Configure License -->
            <div class="portal-tab-panel <?= in_array($activeWindow, ['home', 'register'], true) ? 'active' : '' ?>" id="register-modal-window">
                <div class="card-frame">
                    <div style="margin-bottom: 1.5rem;">
                        <h3 style="font-size: 1.45rem; font-weight: 800; color: var(--tertiary); margin-bottom: 0.35rem;">
                            Create Operator Account &amp; Configure Fleet License
                        </h3>
                        <p style="font-size: 0.95rem; color: var(--mute);">
                            Select a base tier and scale machine and route restocker limits on demand. License keys and simulated receipts are auto-generated.
                        </p>
                    </div>

                    <?php displayFlashMessage(); ?>

                    <?php if ($isSignedIn): ?>
                        <div class="public-alert public-alert-info" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                            <div>
                                <strong>Currently signed in as <?= e($signedInUser['name'] ?? 'User') ?> (<?= e($signedInUser['email'] ?? '') ?>)</strong><br>
                                <span style="font-size: 0.84rem;">Registering a new account will sign you out of your current session and dispatch an email verification link.</span>
                            </div>
                            <a href="logout.php" class="btn btn-gold" style="padding: 6px 14px; font-size: 0.8rem;">Sign out first</a>
                        </div>
                    <?php endif; ?>

                    <?php if ($registerError !== ''): ?>
                        <div class="public-alert public-alert-error"><?= e($registerError) ?></div>
                    <?php endif; ?>

                    <div id="register-ajax-error" class="public-alert public-alert-error" style="display:none;"></div>

                    <form action="register.php" method="POST" id="register-account-form">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="account_type" id="reg-account-type" value="operator">

                        <div class="public-grid-3">
                            <div class="public-form-group">
                                <label class="public-label" for="reg-first-name">First Name *</label>
                                <input type="text" id="reg-first-name" name="first_name" required placeholder="Alex" value="<?= e($_POST['first_name'] ?? '') ?>" class="public-input">
                            </div>
                            <div class="public-form-group">
                                <label class="public-label" for="reg-middle-name">Middle Name <span style="font-weight: 400; color: var(--mute);">(Optional)</span></label>
                                <input type="text" id="reg-middle-name" name="middle_name" placeholder="James" value="<?= e($_POST['middle_name'] ?? '') ?>" class="public-input">
                            </div>
                            <div class="public-form-group">
                                <label class="public-label" for="reg-last-name">Last Name *</label>
                                <input type="text" id="reg-last-name" name="last_name" required placeholder="Morgan" value="<?= e($_POST['last_name'] ?? '') ?>" class="public-input">
                            </div>
                        </div>

                        <div class="public-grid-2">
                            <div class="public-form-group">
                                <label class="public-label" for="reg-email">Email Address *</label>
                                <input type="email" id="reg-email" name="email" required placeholder="alex@apexvending.com" value="<?= e($_POST['email'] ?? '') ?>" class="public-input">
                            </div>
                            <div class="public-form-group">
                                <label class="public-label" for="reg-business">Business / Company Name</label>
                                <input type="text" id="reg-business" name="business_name" placeholder="Apex Vending Co." value="<?= e($_POST['business_name'] ?? '') ?>" class="public-input">
                            </div>
                        </div>

                        <div class="public-grid-2">
                            <div class="public-form-group">
                                <label class="public-label" for="reg-password">Password *</label>
                                <input type="password" id="reg-password" name="password" required minlength="6" placeholder="Min 6 characters" class="public-input">
                            </div>
                            <div class="public-form-group">
                                <label class="public-label" for="reg-confirm">Confirm Password *</label>
                                <input type="password" id="reg-confirm" name="confirm_password" required minlength="6" placeholder="Repeat password" class="public-input">
                            </div>
                        </div>

                        <!-- Fleet Capacity & Dynamic License Calculator -->
                        <div class="calc-box">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem;">
                                <div>
                                    <div style="font-size: 0.98rem; font-weight: 800; color: var(--tertiary); display: inline-flex; align-items: center; gap: 6px;">
                                        <span class="inline-ico" style="color: var(--secondary);"><?= posthog_icon('receipt') ?></span>
                                        <span>License Plan &amp; Fleet Calculator</span>
                                    </div>
                                    <div style="font-size: 0.78rem; color: var(--mute);">
                                        Choose your base plan, then adjust machine fleet or route staff limits as needed.
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div id="reg-calc-total-display" style="font-size: 1.4rem; font-weight: 800; color: var(--tertiary); line-height: 1;">
                                        $19.00<span style="font-size: 0.8rem; font-weight: 600; color: var(--mute);">/mo</span>
                                    </div>
                                    <span id="reg-calc-tier-badge" style="font-size: 0.7rem; font-weight: 800; padding: 0.2rem 0.6rem; border-radius: var(--radius-pill); background: var(--secondary); color: var(--black); display: inline-block; margin-top: 3px;">
                                        SOLO TIER
                                    </span>
                                </div>
                            </div>

                            <div style="margin-bottom: 0.85rem;">
                                <label class="public-label" for="reg-plan-tier">Base Software License Tier</label>
                                <select id="reg-plan-tier" name="plan_tier" class="public-input" style="font-weight: 700;" onchange="onRegisterPlanChanged(this.value)">
                                    <option value="solo" selected>Solo ($19.00/mo — standard 4 machines, 1 staff)</option>
                                    <option value="business">Business ($35.00/mo — standard 12 machines, 4 staff)</option>
                                    <option value="max">Max ($99.00/mo — standard 50 machines, 18 staff)</option>
                                    <option value="enterprise">Enterprise ($199.00/mo — fleet scale 100 machines, 30 staff)</option>
                                </select>
                            </div>

                            <div class="calc-stepper-grid">
                                <div>
                                    <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.35rem;">
                                        <label style="font-size: 0.8rem; font-weight: 700; color: var(--tertiary);">Machine Fleet</label>
                                        <span id="reg-machines-floor-tag" style="font-size: 0.72rem; color: var(--secondary-hover); font-weight: 800;">Base 4</span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 0.35rem;">
                                        <button type="button" class="stepper-btn" onclick="adjustRegStepper('machines', -1)">−</button>
                                        <input type="number" id="reg-custom-machines" name="custom_machine_limit" value="4" min="4" max="10000" class="public-input stepper-input" oninput="onRegCapacityInputChanged('machines')">
                                        <button type="button" class="stepper-btn" onclick="adjustRegStepper('machines', 1)">+</button>
                                    </div>
                                    <div id="reg-machines-addon-note" style="font-size: 0.72rem; color: var(--mute); margin-top: 0.35rem;">
                                        Included: 4 mach &bull; +$1.00/extra
                                    </div>
                                </div>

                                <div>
                                    <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.35rem;">
                                        <label style="font-size: 0.8rem; font-weight: 700; color: var(--tertiary);">Route Staff</label>
                                        <span id="reg-staff-floor-tag" style="font-size: 0.72rem; color: var(--secondary-hover); font-weight: 800;">Base 1</span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 0.35rem;">
                                        <button type="button" class="stepper-btn" onclick="adjustRegStepper('staff', -1)">−</button>
                                        <input type="number" id="reg-custom-staff" name="custom_staff_limit" value="1" min="1" max="10000" class="public-input stepper-input" oninput="onRegCapacityInputChanged('staff')">
                                        <button type="button" class="stepper-btn" onclick="adjustRegStepper('staff', 1)">+</button>
                                    </div>
                                    <div id="reg-staff-addon-note" style="font-size: 0.72rem; color: var(--mute); margin-top: 0.35rem;">
                                        Included: 1 staff &bull; +$2.00/extra
                                    </div>
                                </div>
                            </div>

                            <div id="reg-tier-nudge-box" style="display: none; background: #FEFCE8; border: 1px solid #FEF08A; border-radius: 10px; padding: 0.75rem 1rem; margin-bottom: 0.85rem; font-size: 0.8rem; color: #854D0E;">
                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; flex-wrap: wrap;">
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <span class="inline-ico" style="color: #854D0E;"><?= posthog_icon('lightbulb') ?></span>
                                        <span id="reg-nudge-text">Upgrading to Business ($35/mo) includes 12 machines &amp; 4 staff and saves money!</span>
                                    </div>
                                    <button type="button" id="reg-nudge-btn" onclick="applyNudgeUpgrade()" class="btn btn-gold" style="padding: 4px 12px; font-size: 0.75rem;">
                                        Switch to Business &rarr;
                                    </button>
                                </div>
                            </div>

                            <input type="hidden" id="reg-custom-price" name="custom_price" value="19.00">

                            <div>
                                <label class="public-label" for="reg-payment">Simulated Payment Method ($0.00 sandbox charge)</label>
                                <select id="reg-payment" name="payment_method" class="public-input" style="font-weight: 600;">
                                    <option value="Visa •••• 4242 (Simulated)">Visa •••• 4242 (Simulated Sandbox Card)</option>
                                    <option value="Mastercard •••• 8888 (Simulated)">Mastercard •••• 8888 (Simulated Sandbox Card)</option>
                                    <option value="GCash / Digital Wallet (Sandbox)">GCash / Digital Wallet (Sandbox)</option>
                                    <option value="Corporate Invoice / Bank Transfer (Sandbox)">Corporate Invoice / Bank Transfer (Sandbox)</option>
                                </select>
                            </div>
                        </div>

                        <div class="public-form-group" style="margin-bottom: 1.25rem;">
                            <label style="display: flex; align-items: flex-start; gap: 0.65rem; font-size: 0.86rem; color: var(--ink); cursor: pointer; background: var(--tertiary-tint); padding: 0.75rem 1rem; border: 1px solid var(--line); border-radius: 12px;">
                                <input type="checkbox" name="seed_demo" value="1" style="margin-top: 3px; accent-color: var(--secondary);">
                                <span><strong>Optional:</strong> Pre-load Machine A &amp; Machine B with 9 sample SKUs (leave unchecked to start with a clean empty fleet)</span>
                            </label>
                        </div>

                        <button type="submit" id="reg-submit-btn" class="btn btn-gold" style="width: 100%; justify-content: center; font-size: 1rem; padding: 15px;">
                            <span id="reg-btn-text">Buy License &amp; Create Account &rarr;</span>
                        </button>
                    </form>

                    <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--line); font-size: 0.88rem; color: var(--mute); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                        <span>Already registered an account?</span>
                        <button type="button" onclick="switchPortalTab('login')" class="more">
                            Sign in to existing account <svg width="14" height="14"><use href="#arr"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- TAB 2: Sign In & Instant Evaluator Sandbox -->
            <div class="portal-tab-panel <?= $activeWindow === 'login' ? 'active' : '' ?>" id="login-modal-window">
                <div style="display: grid; grid-template-columns: 1.15fr 0.85fr; gap: 24px;">
                    <!-- Left: Sign-in Form -->
                    <div class="card-frame">
                        <div style="margin-bottom: 1.5rem;">
                            <span class="chip" style="margin-bottom: 0.65rem;"><i></i>Secure Session Auth</span>
                            <h3 style="font-size: 1.5rem; font-weight: 800; color: var(--tertiary); margin-bottom: 0.35rem;">
                                Sign in to your workspace
                            </h3>
                            <p style="font-size: 0.95rem; color: var(--mute);">
                                Access your vending fleet, visual SKU progress bars, and restock audit ledger.
                            </p>
                        </div>

                        <?php displayFlashMessage(); ?>

                        <?php if ($loginError !== ''): ?>
                            <div class="public-alert public-alert-error"><?= $loginError ?></div>
                        <?php endif; ?>

                        <form action="login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                            <div class="public-form-group">
                                <label class="public-label" for="login-email">Email Address or Staff Username</label>
                                <input
                                    type="text"
                                    id="login-email"
                                    name="email"
                                    required
                                    class="public-input"
                                    placeholder="name@company.com or staff @username"
                                    value="<?= e($_POST['email'] ?? '') ?>"
                                    autocomplete="username"
                                >
                            </div>

                            <div class="public-form-group">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                                    <label class="public-label" for="login-password" style="margin-bottom: 0;">Password</label>
                                    <a href="forgot-password.php" style="font-size: 0.82rem; font-weight: 700; color: var(--secondary);">Forgot password?</a>
                                </div>
                                <input
                                    type="password"
                                    id="login-password"
                                    name="password"
                                    required
                                    class="public-input"
                                    placeholder="Enter your password"
                                    value=""
                                >
                            </div>

                            <button type="submit" class="btn" style="width: 100%; justify-content: center; margin-top: 0.5rem; padding: 14px;">
                                Sign In With Credentials &rarr;
                            </button>
                        </form>

                        <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--line); font-size: 0.88rem; color: var(--mute); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                            <span>Need a new fleet account?</span>
                            <button type="button" onclick="switchPortalTab('register')" class="more">
                                Create an account <svg width="14" height="14"><use href="#arr"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Right: Instant Evaluator Sandbox -->
                    <div class="card-frame" style="background: var(--tertiary-tint); display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <span class="chip" style="margin-bottom: 0.65rem; background: var(--secondary-tint); border-color: rgba(252, 163, 17, 0.4);">
                                <i></i>Instant Evaluator Sandbox
                            </span>
                            <h4 style="font-size: 1.3rem; font-weight: 800; color: var(--tertiary); margin-bottom: 0.4rem;">
                                Test without typing credentials
                            </h4>
                            <p style="font-size: 0.88rem; color: var(--mute); line-height: 1.5; margin-bottom: 1.25rem;">
                                Enter our guarded evaluator workspace pre-populated with Section 9 fleet data:
                            </p>

                            <div style="background: var(--primary); border: 1px solid var(--line); border-radius: 14px; padding: 0.85rem 1rem; margin-bottom: 0.75rem;">
                                <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 0.88rem; color: var(--tertiary);">
                                    <span style="display: inline-flex; align-items: center; gap: 6px;"><span class="inline-ico" style="color: var(--secondary);"><?= posthog_icon('building') ?></span> Machine A: Tech Towers</span>
                                    <span style="color: var(--status-danger); font-size: 0.75rem; background: var(--status-danger-bg); padding: 2px 8px; border-radius: var(--radius-pill);">2 Low Stock</span>
                                </div>
                                <div style="font-size: 0.78rem; color: var(--mute); margin-top: 2px;">2nd Fl Breakroom &bull; 5 registered SKUs &bull; Dave (Staff)</div>
                            </div>

                            <div style="background: var(--primary); border: 1px solid var(--line); border-radius: 14px; padding: 0.85rem 1rem; margin-bottom: 1rem;">
                                <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 0.88rem; color: var(--tertiary);">
                                    <span style="display: inline-flex; align-items: center; gap: 6px;"><span class="inline-ico" style="color: var(--secondary);"><?= posthog_icon('building') ?></span> Machine B: Student Union</span>
                                    <span style="color: var(--status-warning); font-size: 0.75rem; background: var(--status-warning-bg); padding: 2px 8px; border-radius: var(--radius-pill);">1 Low Stock</span>
                                </div>
                                <div style="font-size: 0.78rem; color: var(--mute); margin-top: 2px;">Building C &bull; 4 registered SKUs &bull; Operator oversight</div>
                            </div>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 0.65rem; margin-top: 1rem;">
                            <a href="login.php?demo=1" class="btn btn-gold" style="width: 100%; justify-content: center;">
                                <span class="inline-ico"><?= posthog_icon('bolt') ?></span> 1-Click Operator Demo Login &rarr;
                            </a>
                            <a href="login.php?demo_staff=1" class="btn" style="width: 100%; justify-content: center; background: var(--primary); color: var(--tertiary); border: 1px solid var(--line);">
                                <span class="inline-ico"><?= posthog_icon('user') ?></span> 1-Click Restocker Demo (Dave) &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: Email Sent Confirmation State -->
            <div class="portal-tab-panel <?= $activeWindow === 'email_sent' ? 'active' : '' ?>" id="email-sent-modal-window">
                <div class="card-frame" style="text-align: center; max-width: 680px; margin: 0 auto; padding: 3rem 2rem;">
                    <div style="width: 72px; height: 72px; background: var(--secondary-tint); border: 2px solid var(--secondary); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: var(--secondary); margin-bottom: 1.25rem; padding: 18px;">
                        <span style="width: 36px; height: 36px; display: inline-flex;"><?= posthog_icon('mail') ?></span>
                    </div>
                    <h3 style="font-size: 1.6rem; font-weight: 800; color: var(--tertiary); margin-bottom: 0.5rem;">
                        Verification email dispatched
                    </h3>
                    <p style="font-size: 0.98rem; color: var(--mute); max-width: 480px; margin: 0 auto 1.5rem; line-height: 1.55;">
                        We sent an activation link to <strong id="modal-sent-email" style="color: var(--tertiary);"><?= e($emailSentParam ?: 'your email address') ?></strong>. Click the link to complete account setup.
                    </p>

                    <div style="background: var(--tertiary-tint); border: 1px solid var(--line); border-radius: 16px; padding: 1.25rem 1.5rem; text-align: left; margin-bottom: 1.75rem;">
                        <div style="font-size: 0.78rem; font-weight: 800; color: var(--secondary); text-transform: uppercase; margin-bottom: 0.75rem;">
                            Next steps to enter your workspace
                        </div>
                        <ol style="margin-left: 1.2rem; font-size: 0.88rem; color: var(--ink); display: flex; flex-direction: column; gap: 0.5rem;">
                            <li>Check your inbox for <strong>"Verify your VendFlow operator account"</strong>.</li>
                            <li>Click the <strong>"Verify Email Address"</strong> button in the email.</li>
                            <li>Return to sign in to access your newly provisioned fleet ledger.</li>
                        </ol>
                    </div>

                    <button type="button" onclick="switchPortalTab('login')" class="btn btn-gold" style="width: 100%; justify-content: center;">
                        Go to Operator Sign In &rarr;
                    </button>
                    <div style="margin-top: 1rem; font-size: 0.84rem; color: var(--mute);">
                        Didn't see it? <a href="resend-verification.php?email=<?= urlencode($emailSentParam) ?>" id="modal-resend-link" style="color: var(--secondary); font-weight: 700; text-decoration: underline;">Resend verification email &rarr;</a>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===================================================================
             Section 2: Pricing & Software Licenses (#pricing)
             =================================================================== -->
        <section class="wrap sec" id="pricing">
            <div class="head">
                <div>
                    <h2>Fleet pricing &amp; licenses</h2>
                    <p class="sub">Flat software licenses with zero per-machine fees and zero hardware lock-ins.</p>
                </div>
                <a class="more" href="#portal" onclick="switchPortalTab('register')">
                    Configure license <svg width="14" height="14"><use href="#arr"/></svg>
                </a>
            </div>

            <!-- 4 Metric Badges -->
            <div class="licenses-metric-grid">
                <div class="metric-card">
                    <div class="label">Registered Fleet</div>
                    <div class="val">Unlimited</div>
                    <div class="desc">$0 per-machine fees forever</div>
                </div>
                <div class="metric-card">
                    <div class="label">Cataloged SKUs</div>
                    <div class="val">Unlimited</div>
                    <div class="desc">Visual JPG, PNG &amp; WebP uploads</div>
                </div>
                <div class="metric-card">
                    <div class="label">Route Staff Passes</div>
                    <div class="val">Included Free</div>
                    <div class="desc">Connect team via operator key</div>
                </div>
                <div class="metric-card">
                    <div class="label">Audit Ledger History</div>
                    <div class="val">Lifetime</div>
                    <div class="desc">Permanent immutable logs</div>
                </div>
            </div>

            <!-- Pricing Layout: Featured Card + Comparison Table -->
            <div class="pricing-layout-grid">
                <!-- Featured Plan Card -->
                <div class="pricing-featured-box">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <h3 style="font-size: 1.5rem; font-weight: 800; color: var(--tertiary);">VendFlow Unlimited</h3>
                            <span style="background: var(--secondary-tint); color: var(--black); font-size: 0.72rem; font-weight: 800; padding: 3px 10px; border-radius: var(--radius-pill); border: 1px solid var(--secondary);">
                                ALL-INCLUSIVE
                            </span>
                        </div>

                        <div style="font-size: 2.6rem; font-weight: 800; color: var(--tertiary); margin: 0.75rem 0;">
                            $49 <span style="font-size: 1rem; font-weight: 600; color: var(--mute);">/ month &bull; flat fleet rate</span>
                        </div>

                        <p style="font-size: 0.92rem; color: var(--mute); line-height: 1.5; margin-bottom: 1.25rem;">
                            Complete digital vending inventory software with unlimited machines and automated license generation.
                        </p>

                        <ul class="pricing-check-list">
                            <li><span><?= posthog_icon('check') ?></span> <strong>Registered machines:</strong> Unlimited cabinets</li>
                            <li><span><?= posthog_icon('check') ?></span> <strong>Cataloged SKUs:</strong> Unlimited snacks &amp; drinks</li>
                            <li><span><?= posthog_icon('check') ?></span> <strong>Visual images:</strong> Unlimited uploads</li>
                            <li><span><?= posthog_icon('check') ?></span> <strong>Route staff members:</strong> Free passes via operator key</li>
                            <li><span><?= posthog_icon('check') ?></span> <strong>Low-stock par alerts:</strong> Automatic real-time detection</li>
                            <li><span><?= posthog_icon('check') ?></span> <strong>Audit trail retention:</strong> Lifetime immutable ledger</li>
                            <li><span><?= posthog_icon('check') ?></span> <strong>Per-machine monthly fees:</strong> $0.00 forever</li>
                            <li><span><?= posthog_icon('check') ?></span> <strong>Data export:</strong> Direct MySQL PDO schema access</li>
                        </ul>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-top: 1rem;">
                        <button type="button" class="btn btn-gold" onclick="openDesktopWindow('register'); selectRegisterTier('business');" style="width: 100%; justify-content: center;">
                            Buy License &amp; Create Account &rarr;
                        </button>
                        <a href="login.php?demo=1" class="btn" style="width: 100%; justify-content: center; background: var(--platinum); color: var(--tertiary);">
                            <span class="inline-ico"><?= posthog_icon('bolt') ?></span> Try 1-Click Operator Demo &rarr;
                        </a>
                    </div>
                </div>

                <!-- Comparison Table & Activation Steps -->
                <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                    <!-- Comparison Table -->
                    <div class="card-frame" style="padding: 1.75rem;">
                        <div style="margin-bottom: 1rem;">
                            <span class="chip" style="margin-bottom: 0.5rem;"><i></i>Radical Transparency</span>
                            <h4 style="font-size: 1.25rem; font-weight: 800; color: var(--tertiary);">
                                VendFlow vs. Traditional Vending SaaS
                            </h4>
                        </div>

                        <div class="table-wrap">
                            <table class="comp-table">
                                <thead>
                                    <tr>
                                        <th>Policy / Feature</th>
                                        <th style="color: var(--status-danger);">Legacy SaaS</th>
                                        <th style="color: var(--status-success);">VendFlow Unlimited</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Pricing model</strong></td>
                                        <td style="color: var(--status-danger);">$20–$45/mo per machine</td>
                                        <td style="color: var(--status-success); font-weight: 700;">Flat $49/mo for entire fleet</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Hardware required</strong></td>
                                        <td style="color: var(--status-danger);">$300 IoT dongles per machine</td>
                                        <td style="color: var(--status-success); font-weight: 700;">No hardware required</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Staff passes</strong></td>
                                        <td style="color: var(--status-danger);">$15–$30/mo extra per user</td>
                                        <td style="color: var(--status-success); font-weight: 700;">Free via Operator Key</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Data ownership</strong></td>
                                        <td style="color: var(--status-danger);">Proprietary cloud lock-in</td>
                                        <td style="color: var(--status-success); font-weight: 700;">100% yours in MySQL PDO</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Audit retention</strong></td>
                                        <td style="color: var(--status-danger);">30–90 days max</td>
                                        <td style="color: var(--status-success); font-weight: 700;">Lifetime permanent ledger</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- 4 Quick Activation Steps -->
                        <div class="steps-grid">
                            <div class="step-tile">
                                <div class="step-num">Step 1: Register</div>
                                <div class="step-text">Operators choose simulated sandbox card ($0 real charge) or staff connect via operator key.</div>
                            </div>
                            <div class="step-tile">
                                <div class="step-num">Step 2: Provision</div>
                                <div class="step-text">MySQL generates a unique <code>VF-UNL-...</code> key and instant paid receipt atomically.</div>
                            </div>
                            <div class="step-tile">
                                <div class="step-num">Step 3: Manage</div>
                                <div class="step-text">Catalog snacks, upload photos, set SKU par thresholds, and log restocks immediately.</div>
                            </div>
                            <div class="step-tile">
                                <div class="step-num">Step 4: Scale</div>
                                <div class="step-text">Add more cabinets or route staff anytime without paying extra per-machine fees.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===================================================================
             Section 3: About Us & The Anti-SaaS Manifesto (#about)
             =================================================================== -->
        <section class="wrap sec" id="about">
            <div class="head">
                <div>
                    <h2>About VendFlow</h2>
                    <p class="sub">Built from scratch for route operators who want pure software control.</p>
                </div>
                <a class="more" href="sql/schema.sql" download>
                    Download schema.sql <svg width="14" height="14"><use href="#arr"/></svg>
                </a>
            </div>

            <div class="bento-grid">
                <!-- Origin Story Card (spans 2 cols) -->
                <div class="bento-card col-span-2">
                    <div class="bento-card-icon"><?= posthog_icon('book') ?></div>
                    <h3 style="font-size: 1.4rem; font-weight: 800; color: var(--tertiary); margin-bottom: 0.75rem;">
                        The Origin Story: Breaking the Vending Software Racket
                    </h3>
                    <p style="color: var(--mute); line-height: 1.6; margin-bottom: 1rem;">
                        Legacy vending software charges operators <strong>$20 to $45 per machine every month</strong>, forcing businesses into proprietary telemetry dongles and holding data hostage behind closed APIs. An operator managing 15 machines across corporate offices and campus halls ends up spending thousands of dollars a year just to track cold drinks and potato chips.
                    </p>
                    <p style="color: var(--mute); line-height: 1.6; margin-bottom: 1.25rem;">
                        <strong>VendFlow was built to eliminate that tax.</strong> A pure PHP + MySQL digital ledger where you own the database, you set your own par thresholds, and you never pay an arbitrary fee just to add another machine to your route.
                    </p>
                    <div class="stat-pills-row">
                        <span class="stat-pill"><span class="stat-pill-ico"><?= posthog_icon('bolt') ?></span> Pure Vanilla PHP 8.2</span>
                        <span class="stat-pill"><span class="stat-pill-ico"><?= posthog_icon('database') ?></span> MySQL PDO Prepared</span>
                        <span class="stat-pill"><span class="stat-pill-ico"><?= posthog_icon('box') ?></span> Zero NPM Bloat</span>
                        <span class="stat-pill"><span class="stat-pill-ico"><?= posthog_icon('clock') ?></span> &lt; 20ms Local Response</span>
                    </div>
                </div>

                <!-- Industrial Zero Tolls Sign -->
                <div class="bento-card" style="background: var(--tertiary); color: var(--primary); display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 800; color: var(--secondary); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
                            OPERATOR PLEDGE
                        </div>
                        <h4 style="font-size: 1.35rem; font-weight: 800; color: var(--primary); margin-bottom: 0.5rem;">
                            It has been $000 charged in per-cabinet fees
                        </h4>
                        <p style="font-size: 0.88rem; color: rgba(255, 255, 255, 0.75); line-height: 1.5;">
                            Zero proprietary dongle lock-in. Zero forced telemetry retrofits. Built for manual and route-based tracking.
                        </p>
                    </div>

                    <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid rgba(255, 255, 255, 0.12);">
                        <a href="dashboard.php?demo=1" class="btn btn-gold" style="width: 100%; justify-content: center; font-size: 0.85rem;">
                            Enter Live Demo &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- ===================================================================
         Footer (Identical to index.php)
         =================================================================== -->
    <footer id="contact">
        <div class="wrap">
            <div class="fgrid">
                <div>
                    <a class="brand-logo" href="index.php" style="color: var(--primary);">VendFlow</a>
                    <p>Real-time digital inventory records and live public discovery for smart vending machine routes.</p>
                </div>

                <div>
                    <h4>Products</h4>
                    <ul>
                        <li><a href="index.php">Browse Public Vendos</a></li>
                        <li><a href="index.php#in-stock-now">In-Stock Snacks</a></li>
                        <li><a href="index.php#top-categories">Beverage Directory</a></li>
                        <li><a href="dashboard.php?demo=1">Demo Workspace</a></li>
                    </ul>
                </div>

                <div>
                    <h4>Category</h4>
                    <ul>
                        <li><a href="index.php#top-categories">Cold Beverages</a></li>
                        <li><a href="index.php#top-categories">Crispy Snacks</a></li>
                        <li><a href="index.php#top-categories">Sweets &amp; Biscuits</a></li>
                        <li><a href="#pricing">Unlimited Fleet</a></li>
                    </ul>
                </div>

                <div>
                    <h4>Company info</h4>
                    <ul>
                        <li><a href="#about">About us</a></li>
                        <li><a href="partner.php">Partner with us</a></li>
                        <li><a href="#pricing">Payment options</a></li>
                        <li><a href="login.php">Operator login</a></li>
                        <li><a href="dashboard.php?demo=1">Live demo</a></li>
                    </ul>
                </div>

                <div>
                    <h4>Follow us</h4>
                    <ul>
                        <li><a href="partner.php">Instagram</a></li>
                        <li><a href="partner.php">Facebook</a></li>
                        <li><a href="partner.php">YouTube</a></li>
                        <li><a href="partner.php?window=register">Fleet Portal</a></li>
                    </ul>
                </div>
            </div>

            <div class="legal">
                <div>
                    <a href="partner.php">Data settings</a>
                    <a href="partner.php">Cookie settings</a>
                    <a href="partner.php">Privacy Policy</a>
                    <a href="#pricing">Terms and Conditions</a>
                    <a href="login.php">Imprint</a>
                </div>
                <span>&copy; <?= date('Y') ?> VendFlow Network &bull; All rights reserved</span>
            </div>
        </div>
    </footer>

    <!-- Include main.js for dynamic calculator, AJAX registration, and stepper calculations -->
    <script src="assets/js/main.js?v=<?= time() ?>"></script>
    <script>
        // Smooth Tab Switcher for Operator Access Hub
        function switchPortalTab(tabKey) {
            const tabs = {
                register: { btn: 'tab-btn-register', panel: 'register-modal-window' },
                login: { btn: 'tab-btn-login', panel: 'login-modal-window' },
                email_sent: { btn: 'tab-btn-email_sent', panel: 'email-sent-modal-window' }
            };

            Object.keys(tabs).forEach(k => {
                const b = document.getElementById(tabs[k].btn);
                const p = document.getElementById(tabs[k].panel);
                if (k === tabKey) {
                    if (b) {
                        b.classList.add('active');
                        b.style.display = 'inline-block';
                    }
                    if (p) p.classList.add('active');
                } else {
                    if (b) b.classList.remove('active');
                    if (p) p.classList.remove('active');
                }
            });
        }

        // Overwrite openDesktopWindow for seamless page navigation
        window.openDesktopWindow = function(windowKey) {
            if (windowKey === 'about') {
                const target = document.getElementById('about');
                if (target) target.scrollIntoView({ behavior: 'smooth' });
                return;
            }
            if (windowKey === 'licenses') {
                const target = document.getElementById('pricing');
                if (target) target.scrollIntoView({ behavior: 'smooth' });
                return;
            }
            if (windowKey === 'login') {
                switchPortalTab('login');
                const target = document.getElementById('portal');
                if (target) target.scrollIntoView({ behavior: 'smooth' });
                return;
            }
            if (windowKey === 'register' || windowKey === 'home') {
                switchPortalTab('register');
                const target = document.getElementById('portal');
                if (target) target.scrollIntoView({ behavior: 'smooth' });
                return;
            }
            if (windowKey === 'email_sent') {
                switchPortalTab('email_sent');
                const target = document.getElementById('portal');
                if (target) target.scrollIntoView({ behavior: 'smooth' });
                return;
            }
        };

        // If URL specified a window parameter on load, navigate smoothly to it
        document.addEventListener('DOMContentLoaded', () => {
            const params = new URLSearchParams(window.location.search);
            const win = params.get('window');
            if (win === 'login') {
                switchPortalTab('login');
            } else if (win === 'about') {
                document.getElementById('about')?.scrollIntoView({ behavior: 'smooth' });
            } else if (win === 'licenses') {
                document.getElementById('pricing')?.scrollIntoView({ behavior: 'smooth' });
            } else if (win === 'email_sent') {
                switchPortalTab('email_sent');
            }
        });
    </script>
</body>
</html>
