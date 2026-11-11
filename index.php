<?php
/**
 * VendFlow - Public User Discovery Landing Page
 * Open to any guest visitor without logging in.
 * Search nearby vending machines by product, location, or machine name, and inspect live stock.
 */
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'VendFlow • Find Nearby Vending Machines & Live Stock';
$searchQuery = trim((string)($_GET['q'] ?? ''));
$selectedCategory = trim((string)($_GET['category'] ?? ''));

$db = getDBConnection();
$publicMachines = [];
$allMachinesUnfiltered = [];

if ($db !== null) {
    try {
        $allMachinesUnfiltered = get_public_vending_machines($db, '', '', false);
        $publicMachines = ($searchQuery !== '' || $selectedCategory !== '')
            ? get_public_vending_machines($db, $searchQuery, $selectedCategory, false)
            : $allMachinesUnfiltered;
    } catch (Throwable $e) {
        error_log('Public discovery query error: ' . $e->getMessage());
    }
}

// Flatten top in-stock products across active machines for the horizontal product row
$featuredStockItems = [];
foreach ($publicMachines as $vendo) {
    foreach ($vendo['products'] as $prod) {
        if ($prod['quantity'] > 0) {
            $featuredStockItems[] = [
                'product_id'   => $prod['id'],
                'product_name' => $prod['name'],
                'category'     => $prod['category'],
                'sell_price'   => $prod['sell_price'],
                'quantity'     => $prod['quantity'],
                'stock_status' => $prod['stock_status'],
                'image_url'    => $prod['image_url'],
                'matched'      => $prod['matched'],
                'machine_id'   => $vendo['machine_id'],
                'machine_name' => $vendo['machine_name'],
                'location'     => $vendo['location'],
                'distance'     => $vendo['distance'],
            ];
        }
    }
}

$placeholderVendoImages = [
    'assets/images/vendos/vendo-candies.jpg',
    'assets/images/vendos/vendo-modern.jpg',
];
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
            --secondary-text: #B45309; /* Deep amber meeting WCAG AA >= 4.5:1 on #FFFFFF */
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

        h1, h2, h3 {
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
            color: var(--secondary-text);
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
           Top Navigation Bar (Kept intact with Black and Gold styling)
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
           Desktop-First Hero Section (Cinder & Bean Architecture)
           =================================================================== */
        .hero {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            align-items: center;
            gap: 48px;
            min-height: min(78vh, 660px);
            padding: 44px 0 32px;
        }

        .hero h1 {
            font-size: clamp(2.6rem, 5.2vw, 4.2rem);
            font-weight: 800;
            margin: 0;
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
            color: var(--secondary-text);
        }

        .hero p {
            max-width: 48ch;
            color: var(--mute);
            margin: 18px 0 28px;
            font-size: 1.08rem;
            line-height: 1.55;
            font-weight: 400;
        }

        /* Docked Hero Search Form */
        .hero-search-box {
            margin-bottom: 24px;
        }

        .search-bar-form {
            display: flex;
            align-items: center;
            background: var(--primary);
            border: 2px solid var(--tertiary);
            border-radius: var(--radius-pill);
            padding: 0.38rem 0.45rem 0.38rem 1.25rem;
            box-shadow: var(--shadow-soft);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .search-bar-form:focus-within {
            border-color: var(--secondary);
            box-shadow: 0 12px 32px rgba(252, 163, 17, 0.2);
        }

        .search-icon-svg {
            width: 20px;
            height: 20px;
            color: var(--secondary);
            flex-shrink: 0;
            margin-right: 0.75rem;
        }

        .search-input {
            flex: 1;
            border: none;
            outline: none;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--black);
            background: transparent;
            min-width: 0;
        }

        .search-input::placeholder {
            color: var(--mute);
            opacity: 0.8;
        }

        .search-clear-btn {
            background: transparent;
            border: none;
            color: var(--mute);
            font-family: inherit;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            padding: 0.35rem 0.65rem;
            display: none;
        }

        .search-clear-btn.visible {
            display: inline-block;
        }

        .search-submit-btn {
            background: var(--secondary);
            color: var(--black);
            border: none;
            border-radius: var(--radius-pill);
            padding: 0.75rem 1.6rem;
            font-family: inherit;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.18s ease;
            flex-shrink: 0;
        }

        .search-submit-btn:hover {
            background: var(--secondary-hover);
        }

        .quick-filter-tags {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            flex-wrap: wrap;
            margin-top: 0.85rem;
        }

        .filter-chip {
            background: var(--platinum);
            color: var(--tertiary);
            border: 1px solid var(--line);
            border-radius: var(--radius-pill);
            padding: 0.34rem 0.9rem;
            font-family: inherit;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.16s ease, color 0.16s ease, border-color 0.16s ease;
        }

        .filter-chip:hover,
        .filter-chip.active {
            background: var(--secondary);
            color: var(--black);
            border-color: var(--secondary);
        }

        .cta {
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 28px;
        }

        .btn {
            background: var(--tertiary);
            color: var(--primary);
            border: 0;
            border-radius: var(--radius-pill);
            padding: 14px 28px;
            font-weight: 600;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: transform 0.3s var(--ease), background 0.2s ease, color 0.2s ease;
        }

        .btn:hover {
            background: var(--secondary);
            color: var(--black);
            transform: translateY(-2px);
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

        /* Hero Right-Column Art Plate (Black and Gold Showcase) */
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
            max-width: 480px;
            aspect-ratio: 1 / 1.05;
            background: var(--platinum);
            border-radius: 36px;
            padding: 24px;
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

        .floating-badge {
            position: absolute;
            background: var(--primary);
            border-radius: var(--radius-pill);
            padding: 10px 18px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 14px 30px rgba(20, 33, 61, 0.14);
            border: 1px solid var(--line);
            font-size: 0.84rem;
            font-weight: 700;
            color: var(--tertiary);
            z-index: 5;
            animation: float 7s ease-in-out infinite;
        }

        .floating-badge.top-badge {
            top: 36px;
            left: -12px;
        }

        .floating-badge.bottom-badge {
            bottom: 36px;
            right: -12px;
            animation-delay: -3.5s;
        }

        .floating-badge-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--secondary-tint);
            color: var(--secondary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 5px;
            flex-shrink: 0;
        }

        /* ===================================================================
           Section 1: Top Categories (Cinder & Bean Asymmetric 1.4fr 1fr Grid)
           =================================================================== */
        .cats {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            grid-template-rows: repeat(2, minmax(210px, 1fr));
            gap: 22px;
        }

        .cat {
            position: relative;
            border-radius: 28px;
            background: var(--tertiary);
            overflow: hidden;
            padding: 32px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            isolation: isolate;
            cursor: pointer;
            border: 1px solid var(--line);
            transition: transform 0.4s var(--ease), box-shadow 0.4s var(--ease), border-color 0.2s ease;
        }

        .cat:first-child {
            grid-row: span 2;
            padding: 40px;
        }

        .cat:hover,
        .cat.active-cat {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
            border-color: var(--secondary);
        }

        .cat-bg-img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: -2;
            transition: transform 0.7s var(--ease);
        }

        .cat:hover .cat-bg-img {
            transform: scale(1.05);
        }

        .cat-overlay {
            position: absolute;
            inset: 0;
            background: rgba(20, 33, 61, 0.62);
            z-index: -1;
            transition: background 0.3s ease;
        }

        .cat:hover .cat-overlay {
            background: rgba(20, 33, 61, 0.48);
        }

        .cat h3 {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0;
        }

        .cat:first-child h3 {
            font-size: 2rem;
        }

        .cat span {
            font-size: 0.92rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
            display: inline-flex;
            gap: 6px;
            margin-top: 8px;
            align-items: center;
            transition: color 0.2s ease;
        }

        .cat:hover span {
            color: var(--secondary);
        }

        /* ===================================================================
           Section 2: Popular Items Rail (Horizontally Scrollable Snap Row)
           =================================================================== */
        .rail {
            display: flex;
            gap: 20px;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            padding: 6px 24px 28px;
            margin: 0 -24px;
            scrollbar-width: thin;
            scrollbar-color: var(--line) transparent;
        }

        .rail::-webkit-scrollbar {
            height: 6px;
        }

        .rail::-webkit-scrollbar-track {
            background: transparent;
        }

        .rail::-webkit-scrollbar-thumb {
            background: var(--line);
            border-radius: var(--radius-pill);
        }

        .popular-card,
        .shake {
            flex: 0 0 252px;
            scroll-snap-align: start;
            background: var(--card-bg);
            border: 1px solid var(--line);
            border-radius: 28px;
            padding: 20px 20px 22px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: var(--shadow-soft);
            transition: transform 0.4s var(--ease), box-shadow 0.4s var(--ease), border-color 0.2s ease;
        }

        .popular-card:hover,
        .shake:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-hover);
            border-color: var(--secondary);
        }

        .likes {
            display: inline-flex;
            gap: 6px;
            align-items: center;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--tertiary);
            background: var(--platinum);
            border-radius: var(--radius-pill);
            padding: 5px 12px;
            align-self: flex-start;
        }

        .likes svg {
            width: 14px;
            height: 14px;
            color: var(--secondary-text);
        }

        .product-thumb,
        .glass {
            display: block;
            height: 140px;
            width: auto;
            max-width: 100%;
            margin: 16px auto 14px;
            object-fit: contain;
            transition: transform 0.4s var(--ease);
        }

        .popular-card:hover .product-thumb,
        .shake:hover .glass {
            transform: scale(1.08);
        }

        .popular-card h3,
        .shake h3 {
            font-size: 1.08rem;
            font-weight: 700;
            color: var(--black);
            margin: 0 0 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .popular-machine-sub,
        .shake .shake-machine-sub {
            font-size: 0.78rem;
            color: var(--mute);
            margin-bottom: 12px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .popular-machine-sub svg,
        .shake .shake-machine-sub svg {
            width: 13px;
            height: 13px;
            color: var(--secondary-text);
            flex-shrink: 0;
        }

        .buy {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
            padding-top: 10px;
            border-top: 1px solid var(--line-subtle);
        }

        .price {
            font-weight: 700;
            font-size: 1.15rem;
            color: var(--tertiary);
        }

        .add {
            background: var(--tertiary);
            color: var(--primary);
            border: 0;
            border-radius: var(--radius-pill);
            padding: 8px 18px;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: transform 0.2s var(--ease), background 0.2s ease, color 0.2s ease;
        }

        .add:hover {
            background: var(--secondary);
            color: var(--black);
            transform: scale(1.04);
        }

        /* ===================================================================
           Section 3: Nearby Vending Machines (Cinder & Bean Blog Grid)
           =================================================================== */
        .vendos-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 26px;
        }

        .vendo-card {
            background: var(--card-bg);
            border: 1px solid var(--line);
            border-radius: 28px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: var(--shadow-soft);
            transition: transform 0.4s var(--ease), box-shadow 0.4s var(--ease), border-color 0.2s ease;
        }

        .vendo-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-hover);
            border-color: var(--secondary);
        }

        .tile {
            border-radius: 20px;
            aspect-ratio: 16 / 10;
            position: relative;
            overflow: hidden;
            display: block;
            margin-bottom: 16px;
            background: var(--platinum);
        }

        .tile img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.5s var(--ease);
        }

        .vendo-card:hover .tile img {
            transform: scale(1.06);
        }

        .meta {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--secondary-text);
            letter-spacing: 0.02em;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .vendo-card h3 {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: -0.015em;
            margin: 0 0 4px;
            color: var(--tertiary);
        }

        .vendo-indoor-spot {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--mute);
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin: 0 0 10px;
        }

        .vendo-indoor-spot svg {
            width: 14px;
            height: 14px;
            color: var(--secondary-text);
            flex-shrink: 0;
        }

        .vendo-card p {
            margin: 0 0 14px;
            color: var(--mute);
            font-size: 0.88rem;
            line-height: 1.5;
        }

        .vendo-mini-items {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin-bottom: 14px;
        }

        .mini-item-tag {
            font-size: 0.72rem;
            font-weight: 600;
            padding: 0.25rem 0.65rem;
            border-radius: var(--radius-pill);
            background: var(--platinum);
            color: var(--tertiary);
            border: 1px solid var(--line);
            display: inline-flex;
            align-items: center;
        }

        .mini-item-tag.matched-item {
            background: var(--secondary);
            color: var(--black);
            border-color: var(--secondary);
        }

        .mini-item-tag.out-item {
            opacity: 0.45;
            text-decoration: line-through;
        }

        .mini-item-more {
            cursor: pointer;
            background: var(--tertiary-tint);
            color: var(--tertiary);
            border-color: var(--tertiary-soft);
            transition: background 0.18s ease, color 0.18s ease;
        }

        .mini-item-more:hover {
            background: var(--secondary);
            color: var(--black);
            border-color: var(--secondary);
        }

        .vendo-card-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: auto;
            padding-top: 14px;
            border-top: 1px solid var(--line-subtle);
            gap: 8px;
            flex-wrap: wrap;
        }

        .vendo-price-summary {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--tertiary);
        }

        .vendo-price-summary small {
            display: block;
            font-size: 0.72rem;
            color: var(--mute);
            font-weight: 500;
        }

        .vendo-card-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-directions {
            background: var(--platinum);
            color: var(--tertiary);
            border: 1px solid var(--line);
            border-radius: var(--radius-pill);
            padding: 8px 14px;
            font-size: 0.78rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            transition: background 0.18s ease, color 0.18s ease, border-color 0.18s ease;
        }

        .btn-directions:hover {
            background: var(--tertiary);
            color: var(--primary);
            border-color: var(--tertiary);
        }

        .btn-directions svg {
            width: 12px;
            height: 12px;
        }

        .btn-view-stock {
            background: var(--tertiary);
            color: var(--primary);
            border: 0;
            border-radius: var(--radius-pill);
            padding: 8px 18px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease;
        }

        .btn-view-stock:hover {
            background: var(--secondary);
            color: var(--black);
            transform: scale(1.03);
        }

        .vendo-pay-strip {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed var(--line-subtle);
            font-size: 0.78rem;
            color: var(--mute);
            font-weight: 600;
            flex-wrap: wrap;
        }

        .pay-badge-pill {
            background: var(--tertiary-tint);
            color: var(--tertiary);
            padding: 3px 9px;
            border-radius: var(--radius-pill);
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* ===================================================================
           Empty Search State
           =================================================================== */
        .empty-search-box {
            background: var(--platinum);
            border: 2px dashed var(--line);
            border-radius: var(--radius-card);
            padding: 3rem 1.5rem;
            text-align: center;
            max-width: 580px;
            margin: 2rem auto 0;
        }

        .empty-search-box h3 {
            color: var(--tertiary);
            font-size: 1.25rem;
            margin-bottom: 0.4rem;
        }

        .empty-search-box p {
            color: var(--mute);
            font-size: 0.92rem;
            margin-bottom: 1.25rem;
        }

        /* ===================================================================
           Live Vendo Stock Modal (Black and Gold Elegance)
           =================================================================== */
        .vendo-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(20, 33, 61, 0.65);
            backdrop-filter: blur(4px);
            z-index: 200;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
        }

        .vendo-modal-backdrop.open {
            display: flex;
        }

        .vendo-modal-dialog {
            background: var(--primary);
            border-radius: 28px;
            max-width: 680px;
            width: 100%;
            max-height: 88vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
            border: 2px solid var(--tertiary);
        }

        .vendo-modal-header {
            background: var(--tertiary);
            color: var(--primary);
            padding: 1.35rem 1.6rem;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
        }

        .vendo-modal-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.35rem;
            color: var(--primary);
        }

        .vendo-modal-meta {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.85);
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.65rem;
        }

        .modal-indoor-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(255, 255, 255, 0.16);
            color: var(--primary);
            padding: 3px 10px;
            border-radius: var(--radius-pill);
            font-size: 0.76rem;
            font-weight: 600;
        }

        .modal-header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .modal-directions-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--secondary);
            color: var(--black);
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: var(--radius-pill);
            transition: background 0.18s ease;
        }

        .modal-directions-btn:hover {
            background: var(--secondary-hover);
        }

        .modal-directions-btn svg {
            width: 12px;
            height: 12px;
        }

        .vendo-modal-close {
            background: rgba(255, 255, 255, 0.15);
            color: var(--primary);
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            font-size: 1.2rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.18s ease, color 0.18s ease;
        }

        .vendo-modal-close:hover {
            background: var(--secondary);
            color: var(--black);
        }

        .vendo-modal-body {
            padding: 1.5rem;
            overflow-y: auto;
        }

        .modal-shelf-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(185px, 1fr));
            gap: 1rem;
        }

        .modal-product-item {
            background: var(--platinum);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .modal-product-item img {
            width: 58px;
            height: 58px;
            object-fit: contain;
            margin-bottom: 0.6rem;
        }

        .modal-prod-name {
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--tertiary);
        }

        .modal-prod-price {
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--secondary-text);
            margin: 0.25rem 0 0.5rem;
        }

        .modal-stock-chip {
            font-size: 0.74rem;
            font-weight: 600;
            padding: 0.2rem 0.65rem;
            border-radius: var(--radius-pill);
        }

        .chip-instock {
            background: var(--status-success-bg);
            color: var(--status-success);
        }

        .chip-lowstock {
            background: var(--status-warning-bg);
            color: var(--status-warning);
        }

        .chip-outstock {
            background: var(--status-danger-bg);
            color: var(--status-danger);
        }

        .vendo-modal-footer {
            background: var(--tertiary-tint);
            border-top: 1px solid var(--line);
            padding: 0.9rem 1.6rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .modal-pay-tags {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .modal-pay-tags .pay-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--primary);
            border: 1px solid var(--line);
            padding: 4px 10px;
            border-radius: var(--radius-pill);
            font-size: 0.74rem;
            font-weight: 600;
            color: var(--tertiary);
        }

        .modal-pay-tags .pay-tag svg {
            width: 12px;
            height: 12px;
            color: var(--secondary-text);
        }

        .btn-modal-done {
            background: var(--tertiary);
            color: var(--primary);
            border: none;
            border-radius: var(--radius-pill);
            padding: 7px 18px;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.18s ease, color 0.18s ease;
        }

        .btn-modal-done:hover {
            background: var(--secondary);
            color: var(--black);
        }

        /* ===================================================================
           Footer (Black and Gold Elegance Architecture)
           =================================================================== */
        footer {
            margin-top: 100px;
            background: var(--tertiary); /* #14213D */
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

        /* Animations & Keyframes */
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        @media (prefers-reduced-motion: reduce) {
            * {
                animation: none !important;
                transition: none !important;
            }
        }

        /* ===================================================================
           Responsive Layout Breakpoints
           =================================================================== */
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
            .cats {
                grid-template-columns: 1fr;
                grid-template-rows: none;
            }
            .cat, .cat:first-child {
                grid-row: auto;
                min-height: 220px;
                padding: 28px;
            }
            .vendos-grid {
                grid-template-columns: 1fr;
            }
            .fgrid {
                grid-template-columns: 1fr 1fr;
                gap: 32px;
            }
            .fgrid > div:first-child {
                grid-column: 1 / -1;
            }
            .sec {
                padding-top: 64px;
            }
            .head {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
        }

        @media (max-width: 540px) {
            .wrap {
                padding: 0 18px;
            }
            .rail {
                padding: 4px 18px 24px;
                margin: 0 -18px;
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
            <symbol id="thumbs-up" viewBox="0 0 16 16">
                <path d="M2 7h3v7H2zM6 7l3-5c1.5 0 2 1 1.6 2.2L10 6h3.5c.9 0 1.5.9 1.2 1.7l-1.6 5.3c-.2.6-.7 1-1.3 1H6z" fill="currentColor"/>
            </symbol>
        </defs>
    </svg>

    <!-- ===================================================================
         Top Navigation Bar
         =================================================================== -->
    <header class="public-navbar">
        <div class="navbar-container">
            <a href="index.php" class="brand-logo">VendFlow</a>

            <ul class="nav-links">
                <li><a href="#nearby-vendos">Nearby Vendos</a></li>
                <li><a href="#in-stock-now">In Stock Now</a></li>
                <li><a href="#top-categories">Top Categories</a></li>
                <li><a href="partner.php">Partner With Us</a></li>
            </ul>

            <a href="partner.php" class="nav-partner-btn">
                <span>Partner with us</span>
                <svg width="14" height="14" style="margin-left: 4px;"><use href="#arr"/></svg>
            </a>
        </div>
    </header>

    <main>
        <!-- ===================================================================
             Hero Section (Cinder & Bean 2-Column Responsive Architecture)
             =================================================================== -->
        <section class="wrap hero">
            <div>
                <h1><span>Find nearby</span><em>vending machines,</em> live in real-time</h1>
                <p>Search snacks, cold beverages, and building locations across active machines. Check what's in stock before you walk over.</p>

                <!-- Search Bar Form -->
                <div class="hero-search-box">
                    <form action="index.php" method="GET" class="search-bar-form" id="public-search-form">
                        <svg class="search-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input
                            type="search"
                            name="q"
                            id="public-search-input"
                            class="search-input"
                            placeholder="Search snacks (e.g. Cola, Chips), buildings, or machine..."
                            value="<?= e($searchQuery) ?>"
                            autocomplete="off"
                        >
                        <button type="button" class="search-clear-btn <?= $searchQuery !== '' ? 'visible' : '' ?>" id="clear-search-btn" onclick="clearPublicSearch()">Clear</button>
                        <button type="submit" class="search-submit-btn">Search</button>
                    </form>

                    <div class="quick-filter-tags">
                        <button type="button" class="filter-chip <?= ($searchQuery === '' && $selectedCategory === '') ? 'active' : '' ?>" onclick="applyQuickSearch('')">All Vendos</button>
                        <button type="button" class="filter-chip" onclick="applyQuickSearch('Potato Chips')">Potato Chips</button>
                        <button type="button" class="filter-chip" onclick="applyQuickSearch('Bottled Water')">Bottled Water</button>
                        <button type="button" class="filter-chip" onclick="applyQuickSearch('Cola')">Cola</button>
                        <button type="button" class="filter-chip" onclick="applyQuickSearch('Fruit Juice')">Fruit Juice</button>
                        <button type="button" class="filter-chip" onclick="applyQuickSearch('Tech Towers')">Tech Towers</button>
                        <button type="button" class="filter-chip" onclick="applyQuickSearch('Student Union')">Student Union</button>
                    </div>
                </div>

                <div class="cta">
                    <a class="btn" href="#nearby-vendos">Explore Nearby Machines <svg width="14" height="14"><use href="#arr"/></svg></a>
                    <a class="ghost" href="partner.php">Partner with us <svg width="14" height="14"><use href="#arr"/></svg></a>
                </div>
            </div>

            <!-- Balanced Desktop Art Showcase Plate -->
            <div class="hero-art-w">
                <div class="hero-showcase-card">
                    <img
                        src="assets/images/vendos/vendo-candies.jpg"
                        alt="VendFlow Machine Network Showcase"
                        class="hero-showcase-img"
                    >
                </div>
            </div>
        </section>

        <!-- ===================================================================
             Section 1: Nearby Vending Machines (The Primary Spatial Directory)
             =================================================================== -->
        <section class="wrap sec" id="nearby-vendos">
            <div class="head">
                <div>
                    <h2>Nearby vending machines</h2>
                    <p class="sub" id="vendo-results-subtitle">Explore active vendos, building locations &amp; what's in stock near you.</p>
                </div>
                <a class="more" href="partner.php">Partner with us <svg width="14" height="14"><use href="#arr"/></svg></a>
            </div>

            <div class="vendos-grid" id="vendos-card-grid">
                <?php foreach ($publicMachines as $idx => $vendo): ?>
                    <?php
                    $vendoPhoto = $placeholderVendoImages[$idx % count($placeholderVendoImages)];
                    $prices = array_map(fn($p) => $p['sell_price'], $vendo['products']);
                    $minPrice = !empty($prices) ? min($prices) : 0.00;
                    $searchTokens = strtolower(
                        $vendo['machine_name'] . ' ' .
                        $vendo['location'] . ' ' .
                        ($vendo['indoor_placement'] ?? '') . ' ' .
                        $vendo['identifier'] . ' ' .
                        implode(' ', array_map(fn($p) => $p['name'] . ' ' . $p['category'], $vendo['products']))
                    );
                    ?>
                    <article
                        class="vendo-card"
                        data-search-haystack="<?= e($searchTokens) ?>"
                        data-machine-id="<?= (int)$vendo['machine_id'] ?>"
                    >
                        <div>
                            <div class="tile">
                                <img
                                    src="<?= e($vendoPhoto) ?>"
                                    alt="<?= e($vendo['machine_name']) ?>"
                                    data-vendo-image="<?= (int)$vendo['machine_id'] ?>"
                                >
                            </div>

                            <div class="meta">
                                <span><?= e($vendo['distance']) ?> &bull; Code: <?= e($vendo['identifier']) ?></span>
                                <span><?= (int)$vendo['in_stock_count'] ?>/<?= (int)$vendo['total_products'] ?> in stock</span>
                            </div>

                            <h3><?= e($vendo['machine_name']) ?></h3>
                            <div class="vendo-indoor-spot">
                                <?= posthog_icon('mappin') ?>
                                <span><?= e($vendo['indoor_placement'] ?? 'Ground Floor • Main Lobby') ?> &bull; <?= e($vendo['location']) ?></span>
                            </div>
                            <p>Currently stocking <?= (int)$vendo['in_stock_count'] ?> unique snacks and cold drinks with <?= (int)$vendo['total_units'] ?> units ready for pickup.</p>

                            <div class="vendo-mini-items">
                                <?php if (empty($vendo['products'])): ?>
                                    <span class="mini-item-tag">Restocking soon</span>
                                <?php else: ?>
                                    <?php
                                    $visibleProds = array_slice($vendo['products'], 0, 4);
                                    $overflowCount = count($vendo['products']) - count($visibleProds);
                                    ?>
                                    <?php foreach ($visibleProds as $prod): ?>
                                        <span
                                            class="mini-item-tag <?= !empty($prod['matched']) ? 'matched-item' : '' ?> <?= $prod['quantity'] <= 0 ? 'out-item' : '' ?>"
                                            data-prod-name="<?= e(strtolower($prod['name'] . ' ' . $prod['category'])) ?>"
                                        >
                                            <?= e($prod['name']) ?> (<?= (int)$prod['quantity'] ?>)
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if ($overflowCount > 0): ?>
                                        <button
                                            type="button"
                                            class="mini-item-tag mini-item-more"
                                            onclick="openVendoStockModal(<?= (int)$vendo['machine_id'] ?>)"
                                            title="View all <?= count($vendo['products']) ?> products"
                                        >
                                            +<?= $overflowCount ?> more &rarr;
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="vendo-card-bottom">
                            <div class="vendo-price-summary">
                                <?= $minPrice > 0 ? 'From $' . number_format($minPrice, 2) : 'Live Stock' ?>
                                <small><?= (int)$vendo['total_units'] ?> units total</small>
                            </div>
                            <div class="vendo-card-actions">
                                <a
                                    href="<?= e($vendo['maps_url'] ?? ('https://maps.google.com/search?q=' . urlencode($vendo['location'] . ' Vending'))) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="btn-directions"
                                    title="Open walking directions in Google Maps"
                                >
                                    <?= posthog_icon('external') ?>
                                    <span>Directions</span>
                                </a>
                                <button
                                    type="button"
                                    class="btn-view-stock"
                                    onclick="openVendoStockModal(<?= (int)$vendo['machine_id'] ?>)"
                                >
                                    View Stock
                                </button>
                            </div>
                        </div>

                        <div class="vendo-pay-strip">
                            <span>Pay via:</span>
                            <span class="pay-badge-pill">Apple Pay / NFC</span>
                            <span class="pay-badge-pill">Cards</span>
                            <span class="pay-badge-pill">Cash</span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="empty-search-box" id="empty-vendo-state" style="<?= empty($publicMachines) ? 'display:block;' : 'display:none;' ?>">
                <h3>No matching vending machines found</h3>
                <p>Try searching for another snack (like "Potato Chips" or "Cola") or a location (like "Tech Towers" or "Student Union").</p>
                <button type="button" class="btn" style="margin: 0 auto;" onclick="clearPublicSearch()">Show All Vending Machines</button>
            </div>
        </section>

        <!-- ===================================================================
             Section 2: Popular Items Rail (Horizontally Scrollable Snap Row)
             =================================================================== -->
        <section class="wrap sec" id="in-stock-now">
            <div class="head">
                <div>
                    <h2>Popular snacks &amp; drinks</h2>
                    <p class="sub">Fresh, cold, and ready for instant pickup. Scroll to explore live stock.</p>
                </div>
                <a class="more" href="#nearby-vendos">All machines <svg width="14" height="14"><use href="#arr"/></svg></a>
            </div>

            <div class="rail" id="rail" tabindex="0" aria-label="Popular Snacks and Drinks">
                <?php foreach (array_slice($featuredStockItems, 0, 10) as $item): ?>
                    <?php
                    $itemSearchKey = strtolower($item['product_name'] . ' ' . $item['category'] . ' ' . $item['machine_name'] . ' ' . $item['location']);
                    ?>
                    <article class="popular-card shake" data-spotlight-haystack="<?= e($itemSearchKey) ?>">
                        <span class="likes">
                            <?= posthog_icon('bolt') ?>
                            <?= (int)$item['quantity'] ?> in stock
                        </span>

                        <img
                            src="<?= e($item['image_url']) ?>"
                            alt="<?= e($item['product_name']) ?>"
                            class="product-thumb glass"
                            onerror="this.src='assets/images/vendos/vendo-candies.jpg'"
                        >

                        <div>
                            <h3><?= e($item['product_name']) ?></h3>
                            <div class="popular-machine-sub shake-machine-sub" title="<?= e($item['location']) ?>">
                                <?= posthog_icon('mappin') ?>
                                <span><?= e($item['machine_name']) ?> &bull; <?= e($item['distance']) ?></span>
                            </div>
                        </div>

                        <div class="buy">
                            <span class="price">$<?= number_format((float)$item['sell_price'], 2) ?></span>
                            <button
                                type="button"
                                class="add"
                                onclick="openVendoStockModal(<?= (int)$item['machine_id'] ?>)"
                                title="Inspect live inventory for <?= e($item['machine_name']) ?>"
                            >
                                View stock
                            </button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ===================================================================
             Section 3: Top Categories (Cinder & Bean Asymmetric 1.4fr 1fr Grid)
             =================================================================== -->
        <section class="wrap sec" id="top-categories">
            <div class="head">
                <div>
                    <h2>Top categories</h2>
                    <p class="sub">The snacks and drinks people ordered most this week.</p>
                </div>
                <a class="more" href="#nearby-vendos" onclick="applyQuickSearch('')">
                    Full catalog <svg width="14" height="14"><use href="#arr"/></svg>
                </a>
            </div>

            <div class="cats">
                <!-- Large Featured Category (spans 2 rows) -->
                <div class="cat" onclick="filterByCategory('Drink', this)">
                    <img
                        src="assets/images/vendos/vendo-modern.jpg"
                        alt="Cold Beverages"
                        class="cat-bg-img"
                    >
                    <div class="cat-overlay"></div>
                    <h3>Cold Beverages</h3>
                    <span>View drinks <svg width="14" height="14"><use href="#arr"/></svg></span>
                </div>

                <!-- Top Right Category -->
                <div class="cat" onclick="filterByCategory('Snack', this)">
                    <img
                        src="assets/images/vendos/vendo-candies.jpg"
                        alt="Crispy Snacks"
                        class="cat-bg-img"
                    >
                    <div class="cat-overlay"></div>
                    <h3>Crispy Snacks</h3>
                    <span>View snacks <svg width="14" height="14"><use href="#arr"/></svg></span>
                </div>

                <!-- Bottom Right Category -->
                <div class="cat" onclick="filterByCategory('Chocolate', this)">
                    <img
                        src="assets/images/vendos/vendo-candies.jpg"
                        alt="Sweets & Biscuits"
                        class="cat-bg-img"
                        style="object-position: center 70%;"
                    >
                    <div class="cat-overlay"></div>
                    <h3>Sweets &amp; Biscuits</h3>
                    <span>View sweets <svg width="14" height="14"><use href="#arr"/></svg></span>
                </div>
            </div>
        </section>
    </main>

    <!-- ===================================================================
         Interactive Vendo Stock Modal
         =================================================================== -->
    <div class="vendo-modal-backdrop" id="vendo-stock-modal" onclick="if(event.target === this) closeVendoStockModal()">
        <div class="vendo-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="modal-vendo-name">
            <div class="vendo-modal-header">
                <div>
                    <h3 class="vendo-modal-title" id="modal-vendo-name">Vending Machine</h3>
                    <div class="vendo-modal-meta">
                        <span id="modal-vendo-indoor" class="modal-indoor-badge">Level 1 &bull; Main Lobby</span>
                        <span id="modal-vendo-location">Location</span>
                        <span>&bull;</span>
                        <span id="modal-vendo-distance">Distance</span>
                    </div>
                </div>
                <div class="modal-header-actions">
                    <a
                        id="modal-vendo-directions"
                        href="#"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="modal-directions-btn"
                        title="Open walking directions in Google Maps"
                    >
                        <?= posthog_icon('external') ?>
                        <span>Directions</span>
                    </a>
                    <button type="button" class="vendo-modal-close" onclick="closeVendoStockModal()" aria-label="Close dialog">&times;</button>
                </div>
            </div>
            <div class="vendo-modal-body">
                <div class="modal-shelf-grid" id="modal-vendo-products"></div>
            </div>
            <div class="vendo-modal-footer">
                <div class="modal-pay-tags">
                    <span class="pay-tag"><?= posthog_icon('shield') ?> Apple Pay / NFC</span>
                    <span class="pay-tag"><?= posthog_icon('receipt') ?> Visa / Mastercard</span>
                    <span class="pay-tag"><?= posthog_icon('box') ?> Bills &amp; Coins</span>
                </div>
                <button type="button" class="btn-modal-done" onclick="closeVendoStockModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- ===================================================================
         Footer (Black and Gold Elegance Architecture)
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
                        <li><a href="javascript:void(0)" onclick="applyQuickSearch('Potato Chips')">Potato Chips</a></li>
                        <li><a href="javascript:void(0)" onclick="applyQuickSearch('Bottled Water')">Bottled Water</a></li>
                        <li><a href="javascript:void(0)" onclick="applyQuickSearch('Cola')">Cold Cola</a></li>
                        <li><a href="javascript:void(0)" onclick="applyQuickSearch('Chocolate')">Chocolate Bars</a></li>
                    </ul>
                </div>

                <div>
                    <h4>Category</h4>
                    <ul>
                        <li><a href="#top-categories" onclick="applyQuickSearch('Drink')">Cold Beverages</a></li>
                        <li><a href="#top-categories" onclick="applyQuickSearch('Snack')">Crispy Snacks</a></li>
                        <li><a href="#top-categories" onclick="applyQuickSearch('Biscuits')">Sweets &amp; Biscuits</a></li>
                        <li><a href="#nearby-vendos" onclick="applyQuickSearch('')">Weekly Pick</a></li>
                    </ul>
                </div>

                <div>
                    <h4>Company info</h4>
                    <ul>
                        <li><a href="partner.php?window=about">About us</a></li>
                        <li><a href="partner.php">Partner with us</a></li>
                        <li><a href="partner.php?window=licenses">Payment options</a></li>
                        <li><a href="#nearby-vendos">Track stock</a></li>
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
                    <a href="partner.php?window=licenses">Terms and Conditions</a>
                    <a href="login.php">Imprint</a>
                </div>
                <span>&copy; <?= date('Y') ?> VendFlow Network &bull; All rights reserved</span>
            </div>
        </div>
    </footer>

    <script>
        const VENDO_DATA = <?= json_encode($allMachinesUnfiltered, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        const searchInput = document.getElementById('public-search-input');
        const clearBtn = document.getElementById('clear-search-btn');
        const vendoCards = Array.from(document.querySelectorAll('.vendo-card'));
        const spotlightCards = Array.from(document.querySelectorAll('.shake'));
        const emptyStateBox = document.getElementById('empty-vendo-state');
        const resultsSubtitle = document.getElementById('vendo-results-subtitle');

        function filterPublicDirectory(rawQuery) {
            const q = (rawQuery || '').trim().toLowerCase();
            if (clearBtn) {
                clearBtn.classList.toggle('visible', q.length > 0);
            }

            let visibleVendoCount = 0;

            vendoCards.forEach(card => {
                const haystack = (card.getAttribute('data-search-haystack') || '').toLowerCase();
                const matches = q === '' || haystack.includes(q);
                card.style.display = matches ? 'flex' : 'none';
                if (matches) {
                    visibleVendoCount++;
                }

                // Highlight matching product tags inside the card
                const itemTags = card.querySelectorAll('.mini-item-tag[data-prod-name]');
                itemTags.forEach(tag => {
                    const prodText = tag.getAttribute('data-prod-name') || '';
                    if (q !== '' && prodText.includes(q)) {
                        tag.classList.add('matched-item');
                    } else {
                        tag.classList.remove('matched-item');
                    }
                });
            });

            spotlightCards.forEach(card => {
                const haystack = (card.getAttribute('data-spotlight-haystack') || '').toLowerCase();
                card.style.display = (q === '' || haystack.includes(q)) ? 'flex' : 'none';
            });

            if (emptyStateBox) {
                emptyStateBox.style.display = visibleVendoCount === 0 ? 'block' : 'none';
            }

            if (resultsSubtitle) {
                if (q === '') {
                    resultsSubtitle.textContent = "Explore active vendos, building locations & what's in stock near you.";
                } else {
                    resultsSubtitle.textContent = `Showing ${visibleVendoCount} vending machine(s) matching "${rawQuery.trim()}"`;
                }
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                filterPublicDirectory(e.target.value);
            });
        }

        function applyQuickSearch(term) {
            if (searchInput) {
                searchInput.value = term;
            }
            document.querySelectorAll('.filter-chip').forEach(chip => {
                chip.classList.toggle('active', chip.textContent.trim().toLowerCase() === (term === '' ? 'all vendos' : term.toLowerCase()));
            });
            document.querySelectorAll('.cat').forEach(c => c.classList.remove('active-cat'));
            filterPublicDirectory(term);
            const target = document.getElementById('nearby-vendos');
            if (target && term !== '') {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        function filterByCategory(catKeyword, cardEl) {
            document.querySelectorAll('.cat').forEach(c => c.classList.remove('active-cat'));
            if (cardEl) {
                cardEl.classList.add('active-cat');
            }
            if (searchInput) {
                searchInput.value = catKeyword;
            }
            filterPublicDirectory(catKeyword);
            const target = document.getElementById('nearby-vendos');
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        function clearPublicSearch() {
            applyQuickSearch('');
        }

        let lastFocusedElement = null;

        function openVendoStockModal(machineId) {
            const vendo = VENDO_DATA.find(m => Number(m.machine_id) === Number(machineId));
            if (!vendo) return;

            lastFocusedElement = document.activeElement;

            document.getElementById('modal-vendo-name').textContent = vendo.machine_name;
            document.getElementById('modal-vendo-location').textContent = vendo.location;
            document.getElementById('modal-vendo-distance').textContent = vendo.distance;

            const indoorEl = document.getElementById('modal-vendo-indoor');
            if (indoorEl) {
                indoorEl.textContent = vendo.indoor_placement || 'Ground Floor • Main Lobby';
            }

            const directionsBtn = document.getElementById('modal-vendo-directions');
            if (directionsBtn) {
                directionsBtn.href = vendo.maps_url || ('https://maps.google.com/search?q=' + encodeURIComponent(vendo.location + ' Vending'));
            }

            const container = document.getElementById('modal-vendo-products');
            container.innerHTML = '';

            if (!vendo.products || vendo.products.length === 0) {
                container.innerHTML = '<p style="grid-column: 1/-1; text-align:center; color:var(--mute); padding: 1.5rem;">No products currently listed for this machine.</p>';
            } else {
                vendo.products.forEach(p => {
                    let badgeClass = 'chip-instock';
                    let badgeLabel = `${p.quantity} in stock`;
                    if (p.quantity <= 0) {
                        badgeClass = 'chip-outstock';
                        badgeLabel = 'Out of stock';
                    } else if (p.stock_status === 'low_stock') {
                        badgeClass = 'chip-lowstock';
                        badgeLabel = `Low stock (${p.quantity} left)`;
                    }

                    const itemEl = document.createElement('div');
                    itemEl.className = 'modal-product-item';
                    itemEl.innerHTML = `
                        <img src="${escapeHtml(p.image_url)}" alt="${escapeHtml(p.name)}" onerror="this.src='assets/images/vendos/vendo-candies.jpg'">
                        <div class="modal-prod-name">${escapeHtml(p.name)}</div>
                        <div class="modal-prod-price">$${Number(p.sell_price).toFixed(2)}</div>
                        <span class="modal-stock-chip ${badgeClass}">${escapeHtml(badgeLabel)}</span>
                    `;
                    container.appendChild(itemEl);
                });
            }

            const modal = document.getElementById('vendo-stock-modal');
            modal.classList.add('open');

            const closeBtn = modal.querySelector('.vendo-modal-close');
            if (closeBtn) {
                closeBtn.focus();
            }
        }

        function closeVendoStockModal() {
            const modal = document.getElementById('vendo-stock-modal');
            modal.classList.remove('open');
            if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
                lastFocusedElement.focus();
            }
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str == null ? '' : String(str);
            return div.innerHTML;
        }

        document.addEventListener('keydown', (e) => {
            const modal = document.getElementById('vendo-stock-modal');
            if (!modal || !modal.classList.contains('open')) return;

            if (e.key === 'Escape') {
                closeVendoStockModal();
                return;
            }

            // Keyboard Focus Trap within modal
            if (e.key === 'Tab') {
                const focusables = Array.from(modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'));
                if (focusables.length === 0) return;

                const firstEl = focusables[0];
                const lastEl = focusables[focusables.length - 1];

                if (e.shiftKey) {
                    if (document.activeElement === firstEl) {
                        e.preventDefault();
                        lastEl.focus();
                    }
                } else {
                    if (document.activeElement === lastEl) {
                        e.preventDefault();
                        firstEl.focus();
                    }
                }
            }
        });
    </script>
</body>
</html>
