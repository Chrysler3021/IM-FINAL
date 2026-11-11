<?php
/**
 * Global PostHog-Styled Modals for Product, Machine & Stock Management
 */
$modalUserMachines = [];
if (is_logged_in()) {
    $mModalStmt = get_db()->prepare("SELECT id, name, location FROM machines WHERE user_id = :uid ORDER BY name ASC");
    $mModalStmt->execute([':uid' => $_SESSION['user_id']]);
    $modalUserMachines = $mModalStmt->fetchAll();
}
$modalDefaultPar = isset($user['default_threshold']) ? (int)$user['default_threshold'] : 10;
$modalReturnPage = basename($_SERVER['PHP_SELF']);
?>

<!-- =======================================================================
     1. Add / Edit Product Modal (with Image Upload & Live Preview)
     ======================================================================= -->
<div class="modal-overlay" id="product-modal" onclick="handleModalOverlayClick(event, 'product-modal')">
    <div class="modal-container">

        <div class="modal-header">
            <div class="modal-title" id="product-modal-title">Add new product</div>
            <button type="button" class="modal-close-btn" onclick="closeModalById('product-modal')" title="Close">✕</button>
        </div>

        <form id="product-form" action="inventory.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" id="product-action" value="add_product">
            <input type="hidden" name="product_id" id="product-id" value="">
            <input type="hidden" name="return_to" value="<?= e($modalReturnPage) ?>">

            <div class="modal-body">

                <?php if (empty($modalUserMachines)): ?>
                    <div class="dash-flash dash-flash-warning" style="margin-bottom: 0.25rem;">
                        <span style="display:inline-flex;align-items:center;gap:6px;"><span style="width:14px;height:14px;display:inline-flex;color:#d97706;"><?= posthog_icon('warning') ?></span> Register at least one vending machine before adding products.</span>
                        <button type="button" class="btn-dash-outline" onclick="closeModalById('product-modal'); openAddMachineModal();">
                            + Register machine
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Product Name -->
                <div class="form-group-item">
                    <label class="form-label" for="product-name">Product Name *</label>
                    <input
                        type="text"
                        name="name"
                        id="product-name"
                        class="form-input-text"
                        placeholder="e.g. Coca-Cola Original (330ml)"
                        required
                    >
                </div>

                <!-- Image Upload Dropzone with Instant Preview -->
                <div class="form-group-item">
                    <label class="form-label">Product Image</label>
                    <div class="image-upload-zone" id="upload-zone" onclick="triggerProductImageInput()">
                        <input
                            type="file"
                            name="image"
                            id="product-image-file"
                            accept="image/*"
                            style="display: none;"
                            onchange="handleProductImagePreview(event)"
                        >
                        <div class="upload-icon" style="width:24px;height:24px;margin:0 auto;color:#94a3b8;"><?= posthog_icon('camera') ?></div>
                        <div class="upload-text">Click to upload product image</div>
                        <div class="upload-subtext">PNG, JPG, WebP, GIF, or SVG (max 5MB)</div>

                        <div class="image-preview-box" id="image-preview-box">
                            <img src="" alt="Preview" id="preview-img-tag" class="preview-img">
                            <button type="button" class="remove-img-btn" onclick="clearProductImagePreview(event)">✕ Change</button>
                        </div>
                    </div>
                </div>

                <!-- Initial Units & Par Threshold -->
                <div class="form-grid-2">
                    <div class="form-group-item">
                        <label class="form-label" for="product-quantity" id="product-qty-label">Initial Units *</label>
                        <input
                            type="number"
                            name="quantity"
                            id="product-quantity"
                            class="form-input-text"
                            min="0"
                            value="12"
                            required
                        >
                    </div>
                    <div class="form-group-item">
                        <label class="form-label" for="product-min-threshold">Par Threshold *</label>
                        <input
                            type="number"
                            name="min_threshold"
                            id="product-min-threshold"
                            class="form-input-text"
                            min="1"
                            value="<?= $modalDefaultPar ?>"
                            required
                        >
                    </div>
                </div>

                <!-- Smart Par Advisor Modal Callout -->
                <div id="modal-par-advisor-box" class="par-advisor-modal-box" style="display: none; margin-bottom: 1rem;">
                    <div class="par-advisor-modal-text">
                        <span style="font-weight: 700;">⚡ Par Advisor: </span>
                        <span id="modal-par-advisor-text">Suggested par is 15 units based on sales velocity.</span>
                    </div>
                    <button type="button" class="btn-advisor-modal-apply" id="btn-apply-modal-par" onclick="applyModalParSuggestion()">
                        Use suggested par
                    </button>
                </div>

                <!-- Assigned Machine & Category -->
                <div class="form-grid-2">
                    <div class="form-group-item">
                        <label class="form-label" for="product-machine-id">Assigned Machine *</label>
                        <select name="machine_id" id="product-machine-id" class="form-input-text" required>
                            <?php if (empty($modalUserMachines)): ?>
                                <option value="">No machines registered yet</option>
                            <?php else: ?>
                                <?php foreach ($modalUserMachines as $mm): ?>
                                    <option value="<?= (int)$mm['id'] ?>"><?= e($mm['name']) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group-item">
                        <label class="form-label" for="product-category">Category *</label>
                        <select name="category" id="product-category" class="form-input-text" required>
                            <option value="Cold Drinks & Sodas">Cold Drinks &amp; Sodas</option>
                            <option value="Bottled Water & Juices">Bottled Water &amp; Juices</option>
                            <option value="Candy & Chocolate">Candy &amp; Chocolate</option>
                            <option value="Chips & Snacks">Chips &amp; Snacks</option>
                            <option value="Pastries & Biscuits">Pastries &amp; Biscuits</option>
                            <option value="Healthy Snacks">Healthy Snacks</option>
                            <option value="Other">Other products</option>
                        </select>
                    </div>
                </div>

                <!-- SKU, Vend Price & Unit Cost -->
                <div class="form-grid-2">
                    <div class="form-group-item">
                        <label class="form-label" for="product-sku">SKU Code (auto if blank)</label>
                        <input
                            type="text"
                            name="sku"
                            id="product-sku"
                            class="form-input-text"
                            placeholder="e.g. DRK-SDA-01"
                        >
                    </div>
                    <div class="form-group-item">
                        <label class="form-label" for="product-sell-price">Vend Price ($)</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="sell_price"
                            id="product-sell-price"
                            class="form-input-text"
                            value="1.75"
                            placeholder="1.75"
                        >
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group-item">
                        <label class="form-label" for="product-cost-price">Unit Cost ($)</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="cost_price"
                            id="product-cost-price"
                            class="form-input-text"
                            value="0.60"
                            placeholder="0.60"
                        >
                    </div>
                    <div class="form-group-item">
                        <label class="form-label" for="product-notes">Slot / Restock Note</label>
                        <input
                            type="text"
                            name="notes"
                            id="product-notes"
                            class="form-input-text"
                            placeholder="e.g. Spiral B2"
                        >
                    </div>
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" onclick="closeModalById('product-modal')">Cancel</button>
                <button type="submit" class="btn-modal-save" id="product-submit-btn" <?= empty($modalUserMachines) ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : '' ?>>
                    Save product
                </button>
            </div>
        </form>

    </div>
</div>

<!-- =======================================================================
     2. Add / Edit Vending Machine Modal
     ======================================================================= -->
<div class="modal-overlay" id="machine-modal" onclick="handleModalOverlayClick(event, 'machine-modal')">
    <div class="modal-container">

        <div class="modal-header">
            <div class="modal-title" id="machine-modal-title">Register vending machine</div>
            <button type="button" class="modal-close-btn" onclick="closeModalById('machine-modal')" title="Close">✕</button>
        </div>

        <form id="machine-form" action="machines.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" id="machine-action" value="add_machine">
            <input type="hidden" name="machine_id" id="machine-id" value="">
            <input type="hidden" name="return_to" value="<?= e($modalReturnPage) ?>">

            <div class="modal-body">

                <div style="font-size: 0.78rem; color: var(--text-muted); background: #f8fafc; border-left: 3px solid #6366f1; padding: 0.6rem 0.85rem; border-radius: 4px;">
                    <strong>Digital ledger record:</strong> Registers a vending machine for digital inventory tracking.
                </div>

                <div class="form-group-item">
                    <label class="form-label" for="machine-name">Machine Name *</label>
                    <input
                        type="text"
                        name="name"
                        id="machine-name"
                        class="form-input-text"
                        placeholder="e.g. Machine C — Hospital Lobby"
                        required
                    >
                </div>

                <div class="form-grid-2">
                    <div class="form-group-item">
                        <label class="form-label" for="machine-identifier">Asset Code</label>
                        <input
                            type="text"
                            name="identifier"
                            id="machine-identifier"
                            class="form-input-text"
                            placeholder="e.g. VM-HOSP-03"
                        >
                    </div>
                    <div class="form-group-item">
                        <label class="form-label" for="machine-status">Operating Status</label>
                        <select name="status" id="machine-status" class="form-input-text">
                            <option value="active">Active</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="form-group-item">
                    <label class="form-label" for="machine-location">Placement Location / Address *</label>
                    <input
                        type="text"
                        name="location"
                        id="machine-location"
                        class="form-input-text"
                        placeholder="e.g. 1st Floor Waiting Lounge, East Wing"
                        required
                    >
                </div>

                <div class="form-group-item">
                    <label class="form-label" for="machine-notes">Location or Access Notes</label>
                    <textarea
                        name="notes"
                        id="machine-notes"
                        rows="2"
                        class="form-input-text"
                        placeholder="e.g. 2nd floor breakroom; access 8am-5pm"
                    ></textarea>
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" onclick="closeModalById('machine-modal')">Cancel</button>
                <button type="submit" class="btn-modal-save" id="machine-submit-btn">Save machine</button>
            </div>
        </form>

    </div>
</div>

<!-- =======================================================================
     3. Log Stock Adjustment Modal (Restock, Sales Tally, Spoilage, Audit)
     ======================================================================= -->
<div class="modal-overlay" id="adjust-stock-modal" onclick="handleModalOverlayClick(event, 'adjust-stock-modal')">
    <div class="modal-container" style="max-width: 460px;">

        <div class="modal-header">
            <div class="modal-title" id="adjust-modal-title">Log stock adjustment</div>
            <button type="button" class="modal-close-btn" onclick="closeModalById('adjust-stock-modal')" title="Close">✕</button>
        </div>

        <form id="adjust-stock-form" action="inventory.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="adjust_stock">
            <input type="hidden" name="product_id" id="adjust-product-id" value="">
            <input type="hidden" name="return_to" value="<?= e($modalReturnPage) ?>">
            <input type="hidden" id="adjust-current-qty-val" value="0">

            <div class="modal-body">

                <div style="background: #f8fafc; border: 1px solid var(--border-light); border-radius: 8px; padding: 0.85rem 1rem;">
                    <div style="font-size: 0.75rem; color: var(--text-muted);" id="adjust-machine-name">Assigned machine</div>
                    <div style="font-size: 1rem; font-weight: 800; color: var(--text-main); margin-top: 0.15rem;" id="adjust-product-name">Product name</div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.8rem; margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px dashed var(--border-light);">
                        <span style="color: var(--text-muted);">Current recorded units:</span>
                        <strong id="adjust-current-stock">0 units</strong>
                    </div>
                </div>

                <div class="form-group-item">
                    <label class="form-label" for="adjust-type">Adjustment Reason *</label>
                    <select name="change_type" id="adjust-type" class="form-input-text" onchange="calculateAdjustedStockPreview()">
                        <option value="restock">Restock load (+ add units)</option>
                        <option value="manual_sale">Sales tally (- deduct units)</option>
                        <option value="damaged">Damaged or expired (- deduct units)</option>
                        <option value="set_exact">Physical count audit (set exact units)</option>
                    </select>
                </div>

                <div class="form-group-item">
                    <label class="form-label" for="adjust-amount">Unit Count *</label>
                    <input
                        type="number"
                        name="amount"
                        id="adjust-amount"
                        min="0"
                        value="5"
                        required
                        class="form-input-text"
                        oninput="calculateAdjustedStockPreview()"
                    >
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.7rem 0.9rem; background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px;">
                    <span style="font-size: 0.82rem; font-weight: 700; color: #9a3412;">Updated recorded stock:</span>
                    <span id="adjust-result-preview" style="font-size: 1.1rem; font-weight: 900; color: var(--accent-orange);">0 units</span>
                </div>

                <div class="form-group-item">
                    <label class="form-label" for="adjust-notes">Operator Note (optional)</label>
                    <input
                        type="text"
                        name="notes"
                        id="adjust-notes"
                        class="form-input-text"
                        placeholder="e.g. Weekly restock count, sales tally, physical audit"
                    >
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" onclick="closeModalById('adjust-stock-modal')">Cancel</button>
                <button type="submit" class="btn-modal-save">Save stock count</button>
            </div>
        </form>

    </div>
</div>

<!-- =======================================================================
     4. Delete Confirmation Modal
     ======================================================================= -->
<div class="modal-overlay" id="delete-confirm-modal" onclick="handleModalOverlayClick(event, 'delete-confirm-modal')">
    <div class="modal-container" style="max-width: 430px;">

        <div class="modal-header">
            <div class="modal-title" id="delete-modal-title">Delete ledger record</div>
            <button type="button" class="modal-close-btn" onclick="closeModalById('delete-confirm-modal')" title="Close">✕</button>
        </div>

        <form id="delete-confirm-form" action="inventory.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="delete_item">
            <input type="hidden" name="type" id="delete-type" value="product">
            <input type="hidden" name="id" id="delete-id" value="">
            <input type="hidden" name="return_to" value="<?= e($modalReturnPage) ?>">

            <div class="modal-body">
                <p style="font-size: 0.9rem; color: var(--text-sub); line-height: 1.5;">
                    Are you sure you want to remove <strong style="color: var(--text-main);" id="delete-item-name">this record</strong> from your workspace?
                </p>
                <div style="font-size: 0.78rem; font-weight: 700; color: #991b1b; background: #fef2f2; border-left: 3px solid #dc2626; padding: 0.6rem 0.85rem; border-radius: 4px;" id="delete-warning-text">
                    This permanent deletion cannot be undone.
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" onclick="closeModalById('delete-confirm-modal')">Cancel</button>
                <button type="submit" class="btn-modal-danger">Delete record</button>
            </div>
        </form>

    </div>
</div>

<!-- =======================================================================
     5. Add Staff Member Modal
     ======================================================================= -->
<div class="modal-overlay" id="add-staff-modal" onclick="handleModalOverlayClick(event, 'add-staff-modal')">
    <div class="modal-container" style="max-width: 480px;">

        <div class="modal-header">
            <div class="modal-title" id="add-staff-modal-title">Create staff account</div>
            <button type="button" class="modal-close-btn" onclick="closeModalById('add-staff-modal')" title="Close">✕</button>
        </div>

        <form id="global-add-staff-form" action="staff.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="add_staff">
            <input type="hidden" name="redirect" value="<?= e($modalReturnPage) ?>">

            <div class="modal-body">
                <?php if (function_exists('is_demo_user') && is_demo_user($modalUser ?? current_user())): ?>
                    <div style="background: #fff7ed; border: 1.5px solid #fed7aa; border-radius: 8px; padding: 0.65rem 0.85rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; color: #9a3412; font-size: 0.82rem; font-weight: 700;">
                        <span style="width:14px;height:14px;display:inline-flex;color:#d97706;"><?= posthog_icon('warning') ?></span>
                        <span>Demo limit: Please register an operator account</span>
                    </div>
                <?php endif; ?>
                <div class="form-group-item">
                    <label class="form-label" for="modal-staff-name">Staff Full Name *</label>
                    <input type="text" name="name" id="modal-staff-name" required placeholder="e.g. Dave Miller" class="form-input-text">
                </div>

                <div class="form-group-item">
                    <label class="form-label" for="modal-staff-username">Username *</label>
                    <div style="position: relative; display: flex; align-items: center;">
                        <span style="position: absolute; left: 10px; color: var(--text-muted); font-weight: 700;">@</span>
                        <input type="text" name="username" id="modal-staff-username" required pattern="[a-zA-Z0-9_\-\.]{3,30}" placeholder="dave_route1" oninput="this.value = this.value.toLowerCase().replace(/[^a-z0-9_\-\.]/g, '')" class="form-input-text" style="padding-left: 28px; font-family: monospace;">
                    </div>
                    <span style="font-size: 0.72rem; color: var(--text-muted); margin-top: 3px; display: block;">Staff will use this username to sign in. Must be unique.</span>
                </div>

                <div class="form-group-item">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <label class="form-label" for="modal-staff-password" style="margin-bottom: 0;">Password *</label>
                        <button type="button" onclick="generateRandomStaffPassword()" style="font-size: 0.75rem; color: #2563eb; background: none; border: none; font-weight: 700; cursor: pointer;">Generate random</button>
                    </div>
                    <input type="text" name="password" id="modal-staff-password" required minlength="6" placeholder="At least 6 characters" class="form-input-text" style="font-family: monospace;">
                </div>

                <div class="form-group-item" style="border-top: 1px solid #e5e7eb; padding-top: 10px; margin-top: 6px;">
                    <label class="form-label">Machine Access Permissions</label>
                    <div style="display: flex; flex-direction: column; gap: 6px; font-size: 0.85rem;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="access_scope" value="all" checked onchange="toggleModalStaffMachines(false)">
                            <span style="font-weight: 600;">All machines in fleet (recommended)</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="access_scope" value="selected" onchange="toggleModalStaffMachines(true)">
                            <span style="font-weight: 600;">Specific machines only</span>
                        </label>
                    </div>

                    <div id="modal-staff-machines-list" style="display: none; margin-top: 8px; max-height: 140px; overflow-y: auto; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px;">
                        <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">Select accessible machines:</span>
                        <?php foreach ($modalUserMachines as $m): ?>
                            <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; padding: 3px 0; cursor: pointer;">
                                <input type="checkbox" name="machine_ids[]" value="<?= (int)$m['id'] ?>">
                                <span><strong><?= e($m['name']) ?></strong> <span style="color: var(--text-muted);">(<?= e($m['location']) ?>)</span></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" onclick="closeModalById('add-staff-modal')">Cancel</button>
                <?php $isDemoModal = function_exists("is_demo_user") && is_demo_user($modalUser ?? current_user()); ?><button type="submit" class="btn-modal-save" <?= $isDemoModal ? 'onclick="alert(\'Demo limit: Please register an operator account\'); return false;"' : '' ?>>Create staff account</button>
            </div>
        </form>

    </div>
</div>

<script>
function openAddStaffModal() {
    const nameInput = document.getElementById('modal-staff-name');
    const uInput = document.getElementById('modal-staff-username');
    const pInput = document.getElementById('modal-staff-password');
    if (nameInput) nameInput.value = '';
    if (uInput) uInput.value = '';
    if (pInput) pInput.value = '';
    const allRadio = document.querySelector('#add-staff-modal input[name="access_scope"][value="all"]');
    if (allRadio) allRadio.checked = true;
    const list = document.getElementById('modal-staff-machines-list');
    if (list) list.style.display = 'none';
    openModalById('add-staff-modal');
}

function generateRandomStaffPassword() {
    const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!#%';
    let pass = '';
    for (let i = 0; i < 10; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    const pInput = document.getElementById('modal-staff-password');
    if (pInput) pInput.value = pass;
}

function toggleModalStaffMachines(show) {
    const list = document.getElementById('modal-staff-machines-list');
    if (list) {
        list.style.display = show ? 'block' : 'none';
    }
}
</script>
