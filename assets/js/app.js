/**
 * VendFlow — Operator Workspace & UI Scripts
 */

document.addEventListener('DOMContentLoaded', () => {
    initToasts();
    initModals();
    initInventoryFilters();
    initMobileSidebar();
    initPublicMobileNav();
});

// Toast Notifications
function initToasts() {
    const toasts = document.querySelectorAll('.toast-alert');
    toasts.forEach(toast => {
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 200);
        }, 5000);
    });
}

function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    const colorClass = type === 'success'
        ? 'bg-parGreenBg text-parGreen border-parGreenBorder'
        : type === 'error'
        ? 'bg-stockoutBg text-stockout border-stockoutBorder'
        : 'bg-amberBg text-amberPar border-amberBorder';

    toast.className = `toast-alert flex items-center justify-between p-3.5 text-sm rounded-control shadow-lg border ${colorClass}`;
    toast.innerHTML = `
        <div class="font-bold pr-3">${message}</div>
        <button type="button" aria-label="Dismiss notification" class="text-current opacity-75 hover:opacity-100 cursor-pointer" onclick="this.parentElement.remove()">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    `;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 200);
    }, 5000);
}

// Modal Dialogs
let activeModalTrigger = null;

function initModals() {
    document.querySelectorAll('[data-modal-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.modal-container');
            if (modal) closeModal(modal.id);
        });
    });

    document.querySelectorAll('.modal-container').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal(modal.id);
            }
        });
    });

    document.addEventListener('keydown', (e) => {
        const activeModal = document.querySelector('.modal-container:not(.hidden)');
        if (!activeModal) return;

        if (e.key === 'Escape') {
            closeModal(activeModal.id);
            return;
        }

        if (e.key === 'Tab') {
            const focusables = Array.from(activeModal.querySelectorAll(
                'button:not([disabled]), [href], input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            )).filter(el => el.offsetParent !== null || el.getClientRects().length > 0);

            if (focusables.length === 0) {
                e.preventDefault();
                return;
            }

            const firstEl = focusables[0];
            const lastEl = focusables[focusables.length - 1];

            if (e.shiftKey) {
                if (document.activeElement === firstEl || !activeModal.contains(document.activeElement)) {
                    e.preventDefault();
                    lastEl.focus();
                }
            } else {
                if (document.activeElement === lastEl || !activeModal.contains(document.activeElement)) {
                    e.preventDefault();
                    firstEl.focus();
                }
            }
        }
    });
}

// Public Header Mobile Menu
function initPublicMobileNav() {
    const menuBtn = document.getElementById('mobile-menu-btn');
    const mobileNav = document.getElementById('mobile-nav');
    if (!menuBtn || !mobileNav) return;

    function setOpen(isOpen) {
        mobileNav.classList.toggle('hidden', !isOpen);
        menuBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    menuBtn.addEventListener('click', () => {
        const isCurrentlyHidden = mobileNav.classList.contains('hidden');
        setOpen(isCurrentlyHidden);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !mobileNav.classList.contains('hidden')) {
            setOpen(false);
            menuBtn.focus();
        }
    });
}

function openModal(id, triggerEl = null) {
    const modal = document.getElementById(id);
    if (!modal) return;
    activeModalTrigger = triggerEl || document.activeElement;
    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');

    const firstInput = modal.querySelector('input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])');
    if (firstInput) {
        setTimeout(() => firstInput.focus(), 40);
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');

    if (activeModalTrigger && typeof activeModalTrigger.focus === 'function') {
        const trigger = activeModalTrigger;
        activeModalTrigger = null;
        setTimeout(() => trigger.focus(), 40);
    }
}

// Dashboard Mobile Sidebar
function initMobileSidebar() {
    const toggleBtn = document.getElementById('mobile-sidebar-toggle');
    const closeBtn = document.getElementById('mobile-sidebar-close');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');

    if (toggleBtn && sidebar && overlay) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
        });
    }

    if (closeBtn && sidebar && overlay) {
        closeBtn.addEventListener('click', () => {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
        });
    }

    if (overlay && sidebar) {
        overlay.addEventListener('click', () => {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
        });
    }
}

// Product Modal Triggers
function openAddProductModal(defaultMachineId = '') {
    const form = document.getElementById('product-form');
    if (!form) return;
    form.reset();
    document.getElementById('product-modal-title').textContent = 'Add product to machine';
    document.getElementById('product-id').value = '';
    document.getElementById('product-action').value = 'add_product';

    if (defaultMachineId) {
        const select = document.getElementById('product-machine-id');
        if (select) select.value = defaultMachineId;
    }

    const advisorBox = document.getElementById('modal-par-advisor-box');
    if (advisorBox) advisorBox.style.display = 'none';

    openModal('product-modal');
}

function applyModalParSuggestion() {
    if (window.pendingSuggestedPar) {
        const minThreshInput = document.getElementById('product-min-threshold');
        if (minThreshInput) {
            minThreshInput.value = window.pendingSuggestedPar;
        }
        const advisorBox = document.getElementById('modal-par-advisor-box');
        if (advisorBox) advisorBox.style.display = 'none';
    }
}

function openEditProductModal(product) {
    const form = document.getElementById('product-form');
    if (!form) return;
    form.reset();
    document.getElementById('product-modal-title').textContent = 'Edit product record';
    document.getElementById('product-id').value = product.id;
    document.getElementById('product-action').value = 'edit_product';

    document.getElementById('product-name').value = product.name || '';
    document.getElementById('product-sku').value = product.sku || '';
    document.getElementById('product-category').value = product.category || 'Chips & Snacks';
    document.getElementById('product-machine-id').value = product.machine_id || '';
    document.getElementById('product-quantity').value = product.quantity !== undefined ? product.quantity : 0;
    document.getElementById('product-min-threshold').value = product.min_threshold || 10;
    document.getElementById('product-cost-price').value = product.cost_price || 0.00;
    document.getElementById('product-sell-price').value = product.sell_price || 0.00;
    document.getElementById('product-notes').value = product.notes || '';

    const advisorBox = document.getElementById('modal-par-advisor-box');
    const advisorText = document.getElementById('modal-par-advisor-text');
    if (advisorBox && advisorText) {
        const curPar = parseInt(product.min_threshold, 10) || 0;
        const recPar = parseInt(product.recommended_par, 10) || 0;
        if (recPar > 0 && recPar !== curPar) {
            window.pendingSuggestedPar = recPar;
            advisorText.textContent = `Recommended par is ${recPar} units (${product.daily_velocity || 0} sales/day, ${product.cadence_label || 'cadence'}).`;
            advisorBox.style.display = 'flex';
        } else {
            window.pendingSuggestedPar = null;
            advisorBox.style.display = 'none';
        }
    }

    openModal('product-modal');
}

function openAdjustStockModal(product) {
    document.getElementById('adjust-product-id').value = product.id;
    document.getElementById('adjust-product-name').textContent = product.name;
    document.getElementById('adjust-machine-name').textContent = product.machine_name || 'Assigned machine';
    document.getElementById('adjust-current-stock').textContent = product.quantity + ' units';
    document.getElementById('adjust-current-qty-val').value = product.quantity;

    const minPar = product.min_threshold ? parseInt(product.min_threshold, 10) : 0;
    const currentQty = parseInt(product.quantity, 10) || 0;
    const defaultLoad = (minPar > currentQty) ? (minPar - currentQty) : 5;

    document.getElementById('adjust-amount').value = defaultLoad;
    document.getElementById('adjust-type').value = 'restock';
    document.getElementById('adjust-notes').value = '';

    calculateAdjustedStock();
    openModal('adjust-stock-modal');
}

function calculateAdjustedStock() {
    const current = parseInt(document.getElementById('adjust-current-qty-val').value, 10) || 0;
    const amount = parseInt(document.getElementById('adjust-amount').value, 10) || 0;
    const type = document.getElementById('adjust-type').value;

    let newQty = current;
    if (type === 'restock') {
        newQty = current + Math.abs(amount);
    } else if (type === 'manual_sale' || type === 'damaged') {
        newQty = Math.max(0, current - Math.abs(amount));
    } else if (type === 'set_exact') {
        newQty = Math.max(0, amount);
    }

    const preview = document.getElementById('adjust-result-preview');
    if (preview) {
        preview.textContent = newQty + ' units';
        if (newQty === 0) {
            preview.className = 'text-base font-display font-bold text-stockout';
        } else if (newQty <= 5) {
            preview.className = 'text-base font-display font-bold text-amberPar';
        } else {
            preview.className = 'text-base font-display font-bold text-tote';
        }
    }
}

function openDeleteModal(type, id, name) {
    const form = document.getElementById('delete-confirm-form');
    if (form) {
        form.action = type === 'machine' ? 'machines.php' : 'inventory.php';
    }
    document.getElementById('delete-type').value = type;
    document.getElementById('delete-id').value = id;
    document.getElementById('delete-item-name').textContent = name;

    const warning = document.getElementById('delete-warning-text');
    if (type === 'machine') {
        warning.textContent = 'Deleting this machine also permanently removes all product records and adjustment logs assigned to it.';
    } else {
        warning.textContent = 'This product record will be removed from the machine catalog. This action cannot be undone.';
    }

    openModal('delete-confirm-modal');
}

function openAddMachineModal() {
    const form = document.getElementById('machine-form');
    if (!form) return;
    form.reset();
    document.getElementById('machine-modal-title').textContent = 'Register vending machine';
    document.getElementById('machine-id').value = '';
    document.getElementById('machine-action').value = 'add_machine';
    document.getElementById('machine-status').value = 'active';
    openModal('machine-modal');
}

function openAddStaffModal() {
    const modal = document.getElementById('add-staff-modal');
    if (!modal) return;
    const nameInput = document.getElementById('modal-staff-name') || document.getElementById('staff-name');
    const uInput = document.getElementById('modal-staff-username') || document.getElementById('staff-username');
    const pInput = document.getElementById('modal-staff-password') || document.getElementById('staff-password');
    if (nameInput) nameInput.value = '';
    if (uInput) uInput.value = '';
    if (pInput) pInput.value = '';
    const allRadio = modal.querySelector('input[name="access_scope"][value="all"]');
    if (allRadio) allRadio.checked = true;
    const list = document.getElementById('modal-staff-machines-list') || document.getElementById('add-machines-checklist');
    if (list) list.classList.add('hidden');
    openModal('add-staff-modal');
}

function openEditMachineModal(machine) {
    const form = document.getElementById('machine-form');
    if (!form) return;
    form.reset();
    document.getElementById('machine-modal-title').textContent = 'Edit machine record';
    document.getElementById('machine-id').value = machine.id;
    document.getElementById('machine-action').value = 'edit_machine';

    document.getElementById('machine-name').value = machine.name || '';
    document.getElementById('machine-identifier').value = machine.identifier || '';
    document.getElementById('machine-location').value = machine.location || '';
    document.getElementById('machine-status').value = machine.status || 'active';
    document.getElementById('machine-notes').value = machine.notes || '';

    openModal('machine-modal');
}

// Live Search, Filter, and Sort on Inventory Ledger
function initInventoryFilters() {
    const searchInput = document.getElementById('inventory-search');
    const machineFilter = document.getElementById('filter-machine');
    const categoryFilter = document.getElementById('filter-category');
    const statusFilter = document.getElementById('filter-status');
    const sortSelect = document.getElementById('sort-inventory');
    const clearBtn = document.getElementById('clear-filters-btn');

    if (!searchInput && !machineFilter && !categoryFilter && !statusFilter) return;

    function applyFilters() {
        const query = (searchInput?.value || '').toLowerCase().trim();
        const selectedMachine = machineFilter?.value || 'all';
        const selectedCategory = categoryFilter?.value || 'all';
        const selectedStatus = statusFilter?.value || 'all';

        const rows = document.querySelectorAll('.inventory-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const name = (row.dataset.name || '').toLowerCase();
            const sku = (row.dataset.sku || '').toLowerCase();
            const machine = row.dataset.machineId || '';
            const category = row.dataset.category || '';
            const qty = parseInt(row.dataset.quantity, 10) || 0;
            const min = parseInt(row.dataset.minThreshold, 10) || 0;

            const matchesSearch = !query || name.includes(query) || sku.includes(query);
            const matchesMachine = selectedMachine === 'all' || machine === selectedMachine;
            const matchesCategory = selectedCategory === 'all' || category === selectedCategory;

            let matchesStatus = true;
            if (selectedStatus === 'low') {
                matchesStatus = (qty <= min && qty > 0);
            } else if (selectedStatus === 'out') {
                matchesStatus = (qty === 0);
            } else if (selectedStatus === 'healthy') {
                matchesStatus = (qty > min);
            }

            if (matchesSearch && matchesMachine && matchesCategory && matchesStatus) {
                row.classList.remove('hidden');
                visibleCount++;
            } else {
                row.classList.add('hidden');
            }
        });

        const emptyState = document.getElementById('inventory-empty-state');
        if (emptyState) {
            emptyState.classList.toggle('hidden', visibleCount !== 0);
        }

        const counter = document.getElementById('visible-products-count');
        if (counter) {
            counter.textContent = visibleCount;
        }
    }

    if (searchInput) searchInput.addEventListener('input', applyFilters);
    if (machineFilter) machineFilter.addEventListener('change', applyFilters);
    if (categoryFilter) categoryFilter.addEventListener('change', applyFilters);
    if (statusFilter) statusFilter.addEventListener('change', applyFilters);

    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (machineFilter) machineFilter.value = 'all';
            if (categoryFilter) categoryFilter.value = 'all';
            if (statusFilter) statusFilter.value = 'all';
            applyFilters();
        });
    }

    if (sortSelect) {
        sortSelect.addEventListener('change', () => {
            const tableBody = document.getElementById('inventory-table-body');
            if (!tableBody) return;
            const rows = Array.from(tableBody.querySelectorAll('.inventory-row'));
            const criteria = sortSelect.value;

            rows.sort((a, b) => {
                if (criteria === 'name-asc') {
                    return (a.dataset.name || '').localeCompare(b.dataset.name || '');
                } else if (criteria === 'name-desc') {
                    return (b.dataset.name || '').localeCompare(a.dataset.name || '');
                } else if (criteria === 'qty-asc') {
                    return (parseInt(a.dataset.quantity, 10) || 0) - (parseInt(b.dataset.quantity, 10) || 0);
                } else if (criteria === 'qty-desc') {
                    return (parseInt(b.dataset.quantity, 10) || 0) - (parseInt(a.dataset.quantity, 10) || 0);
                } else if (criteria === 'updated-desc') {
                    return (b.dataset.updated || '').localeCompare(a.dataset.updated || '');
                }
                return 0;
            });

            rows.forEach(r => tableBody.appendChild(r));
        });
    }

    // Apply initial GET filter presets on page load
    applyFilters();
}
