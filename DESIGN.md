# VendFlow — Design System (`DESIGN.md`)

## 1. Grounding

- **Subject**: A manual inventory ledger and restock pick-list web application for snack and drink vending machines across multiple locations.
- **Audience**: Independent vending route operators checking par deficits at a warehouse desk before loading totes, or logging restocks one-handed on a phone in front of an open cabinet in a breakroom.
- **Primary job**: Show the operator which machine slots are at or below par and how many units to load onto the hand truck, then log the restock in seconds.

---

## 2. Color Palette (6 Named Hex Values)

Derived from physical vending operations: warehouse manifest paper, painted machine cabinet steel, chrome spiral coils, and route restock totes.

| Role | Name | Hex | Usage |
| :--- | :--- | :--- | :--- |
| **Base ground** | `Manifest Paper` | `#F6F7F4` | Default page ground across public and workspace views |
| **Active surface** | `Tray White` | `#FFFFFF` | Working tables, coil slot cells, form sheets, modal dialogs |
| **Primary ink & cabinet** | `Cabinet Enamel` | `#182026` | Primary text, operator sidebar, footer, and hero cabinet frame (avoids `#0B0B0B` / `#111`) |
| **Secondary ink** | `Stamped Steel` | `#4B5862` | Supporting copy, form hints, and table headers (`7.2:1` on `#FFFFFF`, `6.6:1` on `#F6F7F4`) |
| **Structural rule** | `Coil Wire` | `#D4DCE0` | 1px borders separating distinct machine records, slots, and ledger rows |
| **Primary action** | `Tote Blue` | `#1746A2` | Rare interactive accent reserved exclusively for the single primary conversion CTA per view (`Create operator account` on the landing page, `8.5:1` with `#FFFFFF`). Hero centerpiece coil tray action buttons and machine tabs render in `Cabinet Enamel` (`#182026`) and `Tray White` (`#FFFFFF`) to prevent focal collision and preserve Tote Blue exclusivity. |

### Functional Stock Semantics (Data-Driven Only)

Par thresholds represent target full coil capacities (`par_capacity`). Stock alerts and tote load calculations strictly follow target capacity arithmetic:
- **Empty spiral (`quantity == 0`)**: Stockout alert (`#B42318` ink on `#FEF3F2` ground, `#FECDCA` border). Deficit equals `par_capacity`.
- **Below par (`0 < quantity < par_capacity`)**: Deficit alert (`#9A5B00` ink on `#FFFAEB` ground, `#FEDF89` border). Deficit equals `par_capacity - quantity`.
- **At or above par (`quantity >= par_capacity`)**: Fully stocked / At par (`#166534` ink on `#F0FDF4` ground, `#BBF7D0` border). Deficit equals `0`. Spirals never require overfilling above par to clear alerts.

---

## 3. Typography

Two clearly distinct typefaces chosen specifically for mechanical presence and fast SKU/count disambiguation:

1. **Display & Headings — `Bitter` (`600`, `700`)**
   - Sturdy, low-contrast mechanical slab serif used on `h1`–`h4`, the brand wordmark, and primary numeric totals.
   - `h1`: `clamp(2rem, 3.2vw + 1rem, 3rem)` (`1.625rem` in the compact workspace topbar), `700`, `line-height: 1.12`, `letter-spacing: -0.02em`
   - `h2`: `clamp(1.5rem, 2vw + 0.75rem, 2.125rem)` (`1.25rem` inside workspace ledger sections and dialogs), `700`, `line-height: 1.2`
   - `h3`: `1.25rem`, `600`, `line-height: 1.3`
2. **Body, Tables, Forms & SKUs — `Atkinson Hyperlegible` (`400`, `700`)**
   - Engineered specifically to disambiguate `0`/`O`, `1`/`I`/`l`, and `5`/`S` when scanning SKU codes (`SNK-CHP-01`) and unit counts (`4 / 12`) on mobile screens in dimly lit corridors.
   - `font-variant-numeric: tabular-nums` enforced across all tables, unit counters, and numeric inputs.
   - Prose measure capped at `46ch` (`.prose-measure`) to tune for `Atkinson Hyperlegible`'s wide tabular slashed-zero glyph metrics and preserve ~68-72 rendered characters per line.

### Typographic Prohibitions
- **No ALL-CAPS labels, eyebrows, badges, or table headers** (`uppercase` / `text-transform: uppercase` banned). Sentence case is used throughout.
- **No single-word color/italic/bold accents inside headlines** and no two-tone split wordmark.
- **No decorative monospace labels** used "to look technical."

---

## 4. Layout, Geometry & Anti-Slop Discipline

- **Consistent left alignment**: Headings, prose, horizontal license tiers, FAQs, forms, and ledger tables are deliberately left-aligned.
- **Memorable centerpiece**: Boldness is spent in one place—the interactive **6-slot Vending Machine Coil Tray (`A1–B3`)** on [`index.php`](file:///c:/Users/User/Desktop/KAMLON/KAMLON/index.php), where clicking any spiral loads units to par or records a sale and recalculates the hand-truck tote load live.
- **No SaaS card-grid kits**:
  - KPI summaries use flat, 0-radius architecturally divided strips (`1px solid #D4DCE0`), never 4 identical icon cards.
  - Pricing tiers use horizontal equipment-catalog specification rows, never 3 vertical cards with a scaled/floating "Most Popular" badge.
  - Capabilities use grouped 2-column specification ledgers, never 3x3 pastel-icon card grids.
- **Hierarchy via radius & elevation**:
  - `12px` (`--radius-frame`): Outer machine cabinet trays, form sheets, and modal dialogs
  - `6px` (`--radius-control`): Buttons, inputs, selects, and coil slot cells
  - `4px` (`--radius-tag`): Functional par-status tags
  - `0px`: Ledger rows, summary strips, and section bands
- **No template chrome**: Zero `→` (`&rarr;`) link arrows, zero `·` / `•` middle-dot meta strings, zero `WORD — fragment` labels, zero gradients or blurred glass orbs, and numbered markers (`1`, `2`, `3`) appear only on true chronological sequences.
- **Motion & accessibility**: Purposeful state transitions (`160ms ease-out`) only on interactive coil meters and dialogs; global `@media (prefers-reduced-motion: reduce)` respected; visible `2px solid #1746A2` `:focus-visible` outlines (`#93C5FD` on dark `Cabinet Enamel`).
