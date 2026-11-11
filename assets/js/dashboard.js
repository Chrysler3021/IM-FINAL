/**
 * VendFlow Workspace Client Controller (Pure Vanilla JavaScript)
 * Handles PostHog modals, live image upload previews, stock preview math,
 * grid/table catalog switching, and client-side search/category filters.
 */

(function () {
    'use strict';

    // -------------------------------------------------------------------------
    // Generic Modal Helpers
    // -------------------------------------------------------------------------
    window.openModalById = function (modalId) {
        var el = document.getElementById(modalId);
        if (!el) return;
        el.classList.add('open');
        document.body.style.overflow = 'hidden';
    };

    window.closeModalById = function (modalId) {
        var el = document.getElementById(modalId);
        if (!el) return;
        el.classList.remove('open');
        if (!document.querySelector('.modal-overlay.open')) {
            document.body.style.overflow = '';
        }
    };

    window.handleModalOverlayClick = function (event, modalId) {
        if (event.target && event.target.id === modalId) {
            window.closeModalById(modalId);
        }
    };

    // Close open modals on Escape, quick focus search or open Add Product on Ctrl+K
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.open').forEach(function (modal) {
                modal.classList.remove('open');
            });
            document.body.style.overflow = '';
        }

        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            var searchInput = document.getElementById('catalog-search');
            if (searchInput) {
                e.preventDefault();
                searchInput.focus();
                searchInput.select();
            }
        }
    });

    // -------------------------------------------------------------------------
    // 1. Product Add / Edit Modal & Live Image Upload Preview
    // -------------------------------------------------------------------------
    window.triggerProductImageInput = function () {
        var fileInput = document.getElementById('product-image-file');
        if (fileInput) {
            fileInput.click();
        }
    };

    window.handleProductImagePreview = function (event) {
        var input = event.target;
        if (!input || !input.files || !input.files[0]) return;

        var file = input.files[0];
        if (file.size > 5 * 1024 * 1024) {
            alert('Image exceeds the 5MB upload limit. Please select a smaller file.');
            input.value = '';
            return;
        }

        var reader = new FileReader();
        reader.onload = function (evt) {
            var imgTag = document.getElementById('preview-img-tag');
            var previewBox = document.getElementById('image-preview-box');
            if (imgTag && previewBox) {
                imgTag.src = evt.target.result;
                previewBox.style.display = 'flex';
            }
        };
        reader.readAsDataURL(file);
    };

    window.clearProductImagePreview = function (event) {
        if (event) {
            event.stopPropagation();
        }
        var fileInput = document.getElementById('product-image-file');
        var imgTag = document.getElementById('preview-img-tag');
        var previewBox = document.getElementById('image-preview-box');

        if (fileInput) fileInput.value = '';
        if (imgTag) imgTag.src = '';
        if (previewBox) previewBox.style.display = 'none';
    };

    window.openAddProductModal = function (defaultMachineId) {
        var form = document.getElementById('product-form');
        if (form) form.reset();

        window.clearProductImagePreview();

        var title = document.getElementById('product-modal-title');
        var action = document.getElementById('product-action');
        var idField = document.getElementById('product-id');
        var qtyLabel = document.getElementById('product-qty-label');
        var submitBtn = document.getElementById('product-submit-btn');
        var machineSelect = document.getElementById('product-machine-id');

        if (title) title.textContent = 'Add new product';
        if (action) action.value = 'add_product';
        if (idField) idField.value = '';
        if (qtyLabel) qtyLabel.textContent = 'Initial Units *';
        if (submitBtn) submitBtn.textContent = '+ Add product';

        if (defaultMachineId && machineSelect) {
            machineSelect.value = String(defaultMachineId);
        }

        window.openModalById('product-modal');
        setTimeout(function () {
            var nameInput = document.getElementById('product-name');
            if (nameInput) nameInput.focus();
        }, 60);
    };

    window.openEditProductModal = function (product) {
        if (!product) return;

        var form = document.getElementById('product-form');
        if (form) form.reset();
        window.clearProductImagePreview();

        document.getElementById('product-modal-title').textContent = 'Edit product record';
        document.getElementById('product-action').value = 'edit_product';
        document.getElementById('product-id').value = product.id || '';
        document.getElementById('product-qty-label').textContent = 'Recorded Units *';
        document.getElementById('product-submit-btn').textContent = 'Save changes';

        document.getElementById('product-name').value = product.name || '';
        document.getElementById('product-quantity').value = (product.quantity !== undefined) ? product.quantity : 0;
        document.getElementById('product-min-threshold').value = product.min_threshold || 10;

        var machineSelect = document.getElementById('product-machine-id');
        if (machineSelect && product.machine_id) {
            machineSelect.value = String(product.machine_id);
        }

        var catSelect = document.getElementById('product-category');
        if (catSelect && product.category) {
            var matched = false;
            for (var i = 0; i < catSelect.options.length; i++) {
                if (catSelect.options[i].value === product.category) {
                    catSelect.selectedIndex = i;
                    matched = true;
                    break;
                }
            }
            if (!matched) {
                catSelect.value = 'Other';
            }
        }

        document.getElementById('product-sku').value = product.sku || '';
        document.getElementById('product-sell-price').value = product.sell_price || '0.00';
        document.getElementById('product-cost-price').value = product.cost_price || '0.00';
        document.getElementById('product-notes').value = product.notes || '';

        if (product.image_url) {
            var imgTag = document.getElementById('preview-img-tag');
            var previewBox = document.getElementById('image-preview-box');
            if (imgTag && previewBox) {
                imgTag.src = product.image_url;
                previewBox.style.display = 'flex';
            }
        }

        window.openModalById('product-modal');
    };

    // -------------------------------------------------------------------------
    // 2. Machine Add / Edit Modal
    // -------------------------------------------------------------------------
    window.openAddMachineModal = function () {
        var form = document.getElementById('machine-form');
        if (form) form.reset();

        document.getElementById('machine-modal-title').textContent = 'Register vending machine';
        document.getElementById('machine-action').value = 'add_machine';
        document.getElementById('machine-id').value = '';
        document.getElementById('machine-status').value = 'active';
        document.getElementById('machine-submit-btn').textContent = 'Save machine';

        window.openModalById('machine-modal');
        setTimeout(function () {
            var nameInput = document.getElementById('machine-name');
            if (nameInput) nameInput.focus();
        }, 60);
    };

    window.openEditMachineModal = function (machine) {
        if (!machine) return;

        var form = document.getElementById('machine-form');
        if (form) form.reset();

        document.getElementById('machine-modal-title').textContent = 'Edit vending machine';
        document.getElementById('machine-action').value = 'edit_machine';
        document.getElementById('machine-id').value = machine.id || '';
        document.getElementById('machine-name').value = machine.name || '';
        document.getElementById('machine-identifier').value = machine.identifier || '';
        document.getElementById('machine-status').value = machine.status || 'active';
        document.getElementById('machine-location').value = machine.location || '';
        document.getElementById('machine-notes').value = machine.notes || '';
        document.getElementById('machine-submit-btn').textContent = 'Save changes';

        window.openModalById('machine-modal');
    };

    // -------------------------------------------------------------------------
    // 3. Stock Adjustment Modal & Live Math Preview
    // -------------------------------------------------------------------------
    window.openAdjustStockModal = function (product, defaultReason) {
        if (!product) return;

        var currentQty = parseInt(product.quantity, 10) || 0;
        var parQty = parseInt(product.min_threshold, 10) || 10;
        var reason = defaultReason || 'restock';

        document.getElementById('adjust-product-id').value = product.id;
        document.getElementById('adjust-current-qty-val').value = currentQty;
        document.getElementById('adjust-product-name').textContent = product.name || 'Product';
        document.getElementById('adjust-machine-name').textContent = product.machine_name || 'Assigned cabinet';
        document.getElementById('adjust-current-stock').textContent = currentQty + (currentQty === 1 ? ' unit' : ' units');
        document.getElementById('adjust-type').value = reason;
        document.getElementById('adjust-notes').value = '';

        var suggestedAmount = 5;
        if (reason === 'restock' && currentQty <= parQty) {
            suggestedAmount = Math.max(1, parQty - currentQty);
        } else if (reason === 'manual_sale' || reason === 'damaged') {
            suggestedAmount = 1;
        } else if (reason === 'set_exact') {
            suggestedAmount = currentQty;
        }
        document.getElementById('adjust-amount').value = suggestedAmount;

        window.calculateAdjustedStockPreview();
        window.openModalById('adjust-stock-modal');

        setTimeout(function () {
            var amtInput = document.getElementById('adjust-amount');
            if (amtInput) {
                amtInput.focus();
                amtInput.select();
            }
        }, 60);
    };

    // Shared canonical stock calculation helper
    window.calculateNewStockQuantity = function (currentQty, type, amount) {
        var current = parseInt(currentQty, 10) || 0;
        var amt = Math.max(0, parseInt(amount, 10) || 0);
        if (type === 'restock') {
            return current + amt;
        } else if (type === 'manual_sale' || type === 'damaged') {
            return Math.max(0, current - amt);
        } else if (type === 'set_exact') {
            return amt;
        }
        return current;
    };

    window.calculateAdjustedStockPreview = function () {
        var currentValEl = document.getElementById('adjust-current-qty-val');
        var typeEl = document.getElementById('adjust-type');
        var amountEl = document.getElementById('adjust-amount');
        var previewEl = document.getElementById('adjust-result-preview');

        if (!currentValEl || !typeEl || !amountEl || !previewEl) return;

        var currentQty = parseInt(currentValEl.value, 10) || 0;
        var type = typeEl.value;
        var amount = Math.max(0, parseInt(amountEl.value, 10) || 0);
        var updatedQty = window.calculateNewStockQuantity(currentQty, type, amount);

        previewEl.textContent = updatedQty + (updatedQty === 1 ? ' unit' : ' units');
    };

    // -------------------------------------------------------------------------
    // 4. Delete Confirmation Modal
    // -------------------------------------------------------------------------
    window.openDeleteModal = function (type, id, itemName) {
        var form = document.getElementById('delete-confirm-form');
        var titleEl = document.getElementById('delete-modal-title');
        var typeEl = document.getElementById('delete-type');
        var idEl = document.getElementById('delete-id');
        var nameEl = document.getElementById('delete-item-name');
        var warnEl = document.getElementById('delete-warning-text');

        if (type === 'machine') {
            if (form) form.action = 'machines.php';
            if (titleEl) titleEl.textContent = 'Delete vending machine';
            if (warnEl) {
                warnEl.textContent = 'Deleting this machine will also permanently remove all SKU stock records and audit logs assigned to it.';
            }
        } else {
            if (form) form.action = 'inventory.php';
            if (titleEl) titleEl.textContent = 'Delete product SKU';
            if (warnEl) {
                warnEl.textContent = 'Deleting this SKU will permanently remove its current stock record and adjustment history.';
            }
        }

        if (typeEl) typeEl.value = type;
        if (idEl) idEl.value = id;
        if (nameEl) nameEl.textContent = itemName || 'this record';

        window.openModalById('delete-confirm-modal');
    };

    // -------------------------------------------------------------------------
    // 5. Inventory Catalog View Switcher & Live Search/Category Filters
    // -------------------------------------------------------------------------
    var activeCategoryFilter = 'all';

    window.switchView = function (view) {
        var gridView = document.getElementById('product-grid-view');
        var tableView = document.getElementById('product-table-view');
        var btnGrid = document.getElementById('btn-view-grid');
        var btnTable = document.getElementById('btn-view-table');

        if (!gridView || !tableView) return;

        if (view === 'table') {
            gridView.style.display = 'none';
            tableView.style.display = 'block';
            if (btnGrid) btnGrid.classList.remove('active');
            if (btnTable) btnTable.classList.add('active');
            try { localStorage.setItem('vendflow_catalog_view', 'table'); } catch (e) {}
        } else {
            gridView.style.display = 'grid';
            tableView.style.display = 'none';
            if (btnGrid) btnGrid.classList.add('active');
            if (btnTable) btnTable.classList.remove('active');
            try { localStorage.setItem('vendflow_catalog_view', 'grid'); } catch (e) {}
        }
    };

    window.setCategoryFilter = function (categoryKey, btnEl) {
        activeCategoryFilter = categoryKey || 'all';
        document.querySelectorAll('.filter-chip').forEach(function (chip) {
            chip.classList.remove('active');
        });
        if (btnEl) {
            btnEl.classList.add('active');
        }
        window.filterProducts();
    };

    window.filterProducts = function () {
        var searchInput = document.getElementById('catalog-search');
        var machineFilter = document.getElementById('catalog-machine-filter');
        var query = searchInput ? (searchInput.value || '').toLowerCase().trim() : '';
        var selectedMachine = machineFilter ? (machineFilter.value || '') : '';

        var cards = document.querySelectorAll('.product-item-card');
        var rows = document.querySelectorAll('.table-row-item');
        var noMatchBox = document.getElementById('filter-no-results');

        var visibleCount = 0;

        function matchesItem(el) {
            var searchBlob = el.getAttribute('data-search') || '';
            var catKey = el.getAttribute('data-category') || '';
            var machineId = el.getAttribute('data-machine') || '';
            var isLow = el.getAttribute('data-low') === '1';

            var matchQuery = !query || searchBlob.indexOf(query) !== -1;
            var matchMachine = !selectedMachine || machineId === selectedMachine;
            var matchCat = false;

            if (activeCategoryFilter === 'all') {
                matchCat = true;
            } else if (activeCategoryFilter === 'low-stock') {
                matchCat = isLow;
            } else {
                matchCat = (catKey === activeCategoryFilter);
            }

            return matchQuery && matchMachine && matchCat;
        }

        cards.forEach(function (card) {
            var show = matchesItem(card);
            card.style.display = show ? 'flex' : 'none';
            if (show) visibleCount++;
        });

        rows.forEach(function (row) {
            var show = matchesItem(row);
            row.style.display = show ? '' : 'none';
        });

        if (noMatchBox) {
            noMatchBox.style.display = (cards.length > 0 && visibleCount === 0) ? 'flex' : 'none';
        }
    };

    // Restore saved catalog view preference & auto-dismiss success flashes
    document.addEventListener('DOMContentLoaded', function () {
        var gridView = document.getElementById('product-grid-view');
        if (gridView) {
            try {
                var saved = localStorage.getItem('vendflow_catalog_view');
                if (saved === 'table') {
                    window.switchView('table');
                }
            } catch (e) {}

            // Apply initial filter if machine dropdown is preselected via URL
            var machineFilter = document.getElementById('catalog-machine-filter');
            if (machineFilter && machineFilter.value) {
                window.filterProducts();
            }
        }

        setTimeout(function () {
            document.querySelectorAll('.dash-flash-success').forEach(function (flash) {
                flash.style.transition = 'opacity 0.35s ease';
                flash.style.opacity = '0';
                setTimeout(function () { flash.remove(); }, 350);
            });
        }, 5000);
    });
})();
