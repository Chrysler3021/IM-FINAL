# Design Spec: Public Instant Omnisearch with Live Dropdown

- **Date:** 2026-11-12
- **Topic:** Public landing page search bar UX (`index.php`)
- **Status:** Proposed

---

## 1. Intent & Problem Statement
On the public discovery page (`index.php`), visitors currently experience a broken search interaction:
1. Submitting the search form performs a traditional `GET` request (`?q=...`), reloading the page back at the top hero section while leaving results off-screen in `#nearby-vendos`.
2. Real-time typing filters DOM cards hundreds of pixels below the viewport without giving any immediate feedback in the hero.
3. Visitors cannot quickly tell if a specific craving (e.g., "Coca-Cola", "Doritos") is stocked nearby without manually scrolling and inspecting cards.

The goal is to provide an **Instant Omnisearch Dropdown** (UberEats / Spotlight style) anchored directly beneath the hero search bar, coupled with smooth scrolling to matching machines on submit.

---

## 2. Goals, Non-Goals & Anti-Goals
* **Goals:**
  * Instant, zero-latency feedback as the user types using client-side `VENDO_DATA`.
  * Two-tier live dropdown: **Matching Products** (with thumbnail, price, machine location) and **Matching Machines** (with distance, location, in-stock count).
  * Clicking a product result opens the machine's stock modal directly with the matched item highlighted.
  * Clicking a machine result smoothly scrolls to and subtly highlights that machine's card.
  * Submitting the form (Enter or Search button) prevents page reload and smoothly scrolls to `#nearby-vendos` with live cards filtered.
  * Keyboard navigation (`ArrowDown`, `ArrowUp`, `Enter`, `Escape`) and click-outside dismissal.
* **Non-Goals:**
  * External map geocoding or live GPS tracking (existing relative distance indicators are retained).
  * Server-side AJAX endpoint (all machine and product data is already safely provisioned in `VENDO_DATA` in `index.php`).
* **Anti-Goals:**
  * Disrupting existing quick filter pills ("Potato Chips", "Bottled Water", etc.).
  * Cluttering mobile screens with an unscrollable or overflowing dropdown.

---

## 3. Approaches Considered
1. **Option A: Instant Omnisearch with Live Dropdown (Selected)**
   * *Trade-off:* Requires adding dropdown markup, styling, and JS indexing for products/machines, but delivers the best UX with zero page reloads.
2. **Option B: Smooth Anchor Filter & Results Counter**
   * *Trade-off:* Simpler, but still requires the user to leave the hero context to see individual products.
3. **Option C: Two-Way Intent Switcher ("Search Snacks" vs. "Find Buildings")**
   * *Trade-off:* Adds unnecessary cognitive friction and an extra toggle step for the user.

---

## 4. Detailed Design & Component Behavior

### A. Search Bar Container & Dropdown Layout
* The `.hero-search-box` container becomes a relative anchor (`position: relative`).
* A floating container `#omnisearch-dropdown` is injected immediately below `.search-bar-form`:
  * Hidden by default; displayed when input has focus and query length $\ge 1$.
  * Max height `420px` with vertical overflow scrolling (`overflow-y: auto`).
  * Styled to match VendFlow design tokens (`var(--card-bg)`, `var(--line)`, `var(--shadow-hover)`).

```
+--------------------------------------------------------------------------+
| (Q) Search snacks (e.g. Cola, Chips), buildings, or machine... [Clear] [Search] |
+--------------------------------------------------------------------------+
|  SNACKS & DRINKS FOUND                                                   |
|  [Img] Coca-Cola Original 330ml • In stock at Machine 01 (Metro Tower) $1.75 |
|  [Img] Doritos Nacho Cheese 55g • In stock at Machine 01 (Metro Tower) $2.00 |
|                                                                          |
|  VENDING MACHINES & LOCATIONS                                            |
|  [Vendo] Machine 01 — Metro Tower Main Lobby (50m away) • 5 items        |
|  [Vendo] Machine 02 — Central Transit Concourse (120m away) • 5 items    |
|                                                                          |
|  [View all matching vendos (Enter) ->]                                   |
+--------------------------------------------------------------------------+
```

### B. Two-Tier Search Indexing
* **Product Match Tier (Max 5 results)**:
  * Matches `name` and `category`.
  * Renders: product image thumbnail (fallback to category SVG), product name, machine location badge, price, and stock status.
  * Action: Clicking calls `openVendoStockModal(machineId)`.
* **Machine Match Tier (Max 4 results)**:
  * Matches `machine_name`, `location`, `indoor_placement`, and `identifier`.
  * Renders: machine icon/badge, machine name, location text, and in-stock summary.
  * Action: Clicking scrolls smoothly to `[data-machine-id="..."]` card and adds a brief pulse animation.

### C. Form Submit & Quick Chips Integration
* `public-search-form` `onsubmit`:
  * Calls `e.preventDefault()`.
  * Hides the dropdown.
  * Filters the cards in `#nearby-vendos`.
  * Smoothly scrolls to `#nearby-vendos`.
* Quick filter pills continue to set input value, filter cards, and scroll into view.

---

## 5. Verification Plan
* Manual browser testing:
  * Typing "cola" brings up Coca-Cola items and machines stocking cola.
  * Clicking an item opens the modal with the item displayed.
  * Pressing Enter scrolls down to `#nearby-vendos` showing filtered cards without reloading the page.
  * Pressing `Escape` or clicking outside closes the dropdown.
  * Regression test suite: Run `test_flows.php`, `test_velocity.php`, and `test_staff_flows.php` to verify no regressions.
