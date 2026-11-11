/**
 * VendFlow PostHog WebOS UI Controller
 * Pure Vanilla JavaScript — Multi-Window Desktop Manager
 */

const DESKTOP_WINDOWS = {
    home: {
        modalId: 'main-modal-window',
        btnId: 'btn-icon-home'
    },
    register: {
        modalId: 'register-modal-window',
        btnId: 'btn-icon-register'
    },
    login: {
        modalId: 'login-modal-window',
        btnId: 'btn-icon-login'
    },
    about: {
        modalId: 'about-modal-window',
        btnId: 'btn-icon-about'
    },
    licenses: {
        modalId: 'licenses-modal-window',
        btnId: 'btn-icon-licenses'
    },
    email_sent: {
        modalId: 'email-sent-modal-window',
        btnId: null
    }
};

function openDesktopWindow(windowKey) {
    const reopenPill = document.getElementById('reopen-pill');

    // If 'close', exit/hide all modal windows and reveal reopen pill
    if (windowKey === 'close') {
        Object.keys(DESKTOP_WINDOWS).forEach(k => {
            const win = document.getElementById(DESKTOP_WINDOWS[k].modalId);
            const btn = DESKTOP_WINDOWS[k].btnId ? document.getElementById(DESKTOP_WINDOWS[k].btnId) : null;
            if (win) win.classList.add('hidden');
            if (btn) btn.classList.remove('active');
        });
        if (reopenPill) reopenPill.classList.remove('hidden');
        return;
    }

    if (!windowKey) {
        windowKey = 'home';
    }

    const target = DESKTOP_WINDOWS[windowKey];
    if (!target) return;

    Object.keys(DESKTOP_WINDOWS).forEach(k => {
        const win = document.getElementById(DESKTOP_WINDOWS[k].modalId);
        const btn = DESKTOP_WINDOWS[k].btnId ? document.getElementById(DESKTOP_WINDOWS[k].btnId) : null;
        if (k === windowKey) {
            if (win) win.classList.remove('hidden');
            if (btn) btn.classList.add('active');
        } else {
            if (win) win.classList.add('hidden');
            if (btn) btn.classList.remove('active');
        }
    });

    if (reopenPill) reopenPill.classList.add('hidden');
}

function showEmailSentModal(email) {
    if (email) {
        const emailSpan = document.getElementById('modal-sent-email');
        if (emailSpan) emailSpan.textContent = email;
        const resendLink = document.getElementById('modal-resend-link');
        if (resendLink) resendLink.href = 'resend-verification.php?email=' + encodeURIComponent(email);
    }
    openDesktopWindow('email_sent');
}

function selectLicensePlan(tier) {
    selectRegisterTier(tier || 'solo');
    const nameInput = document.getElementById('reg-first-name') || document.getElementById('reg-name');
    if (nameInput) {
        setTimeout(() => nameInput.focus(), 80);
    }
}

function toggleModal(show) {
    openDesktopWindow(show ? 'home' : 'close');
}

function toggleAboutModal(show) {
    openDesktopWindow(show ? 'about' : 'close');
}

function toggleRegisterModal(show) {
    openDesktopWindow(show ? 'register' : 'close');
}

function toggleLoginModal(show) {
    openDesktopWindow(show ? 'login' : 'close');
}

function toggleLicensesModal(show) {
    openDesktopWindow(show ? 'licenses' : 'close');
}

function switchTab(tabKey) {
    // Buttons
    const buttons = {
        'ask': document.getElementById('tab-btn-ask'),
        'telemetry': document.getElementById('tab-btn-telemetry'),
        'ship': document.getElementById('tab-btn-ship')
    };

    // Panels
    const panels = {
        'ask': document.getElementById('panel-ask'),
        'telemetry': document.getElementById('panel-telemetry'),
        'ship': document.getElementById('panel-ship')
    };

    Object.keys(buttons).forEach(k => {
        if (buttons[k]) buttons[k].classList.remove('active');
        if (panels[k]) panels[k].style.display = 'none';
    });

    if (buttons[tabKey]) buttons[tabKey].classList.add('active');
    if (panels[tabKey]) panels[tabKey].style.display = 'grid';
}

function openTab(tabId) {
    openDesktopWindow('home');
    if (tabId === 'tab-telemetry') {
        switchTab('telemetry');
    } else if (tabId === 'tab-ask') {
        switchTab('ask');
    }
}

// Keyboard shortcuts for switching windows
document.addEventListener('keydown', (e) => {
    // Escape key closes/exits active modal window to desktop OS
    if (e.key === 'Escape') {
        openDesktopWindow('close');
    }
    // Shortcuts when not typing in an input/select/textarea
    if (!['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
        if (e.key === 'h' || e.key === 'H') {
            openDesktopWindow('home');
        } else if (e.key === 'a' || e.key === 'A') {
            openDesktopWindow('about');
        } else if (e.key === 'r' || e.key === 'R') {
            selectRegisterTier(tier || 'solo');
        } else if (e.key === 's' || e.key === 'S') {
            openDesktopWindow('login');
        } else if (e.key === 'l' || e.key === 'L') {
            openDesktopWindow('licenses');
        }
    }
});

// AJAX Registration Form Handler
document.addEventListener('DOMContentLoaded', () => {
    const regForm = document.getElementById('register-account-form');
    if (regForm) {
        regForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const submitBtn = document.getElementById('reg-submit-btn');
            const btnText = document.getElementById('reg-btn-text');
            const errBox = document.getElementById('register-ajax-error');

            if (errBox) {
                errBox.style.display = 'none';
                errBox.textContent = '';
            }

            if (submitBtn) submitBtn.disabled = true;
            if (btnText) btnText.innerHTML = '⚡ Creating Account &amp; Sending Email...';

            const formData = new FormData(regForm);
            formData.append('ajax', '1');

            fetch('register.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(res => {
                const contentType = res.headers.get('content-type') || '';
                if (contentType.includes('application/json')) {
                    return res.json();
                }
                if (res.redirected) {
                    window.location.href = res.url;
                    return null;
                }
                return res.text().then(text => {
                    try { return JSON.parse(text); } catch (err) { window.location.reload(); return null; }
                });
            })
            .then(data => {
                if (!data) return;

                if (data.success) {
                    regForm.reset();
                    showEmailSentModal(data.email);
                } else {
                    if (errBox) {
                        errBox.textContent = data.error || 'Could not complete registration. Please try again.';
                        errBox.style.display = 'block';
                    } else {
                        alert(data.error || 'Registration failed');
                    }
                }
            })
            .catch(err => {
                console.error('Registration submit error:', err);
                regForm.submit();
            })
            .finally(() => {
                if (submitBtn) submitBtn.disabled = false;
                const isStaff = document.getElementById('reg-account-type')?.value === 'staff';
                if (btnText) {
                    btnText.innerHTML = isStaff
                        ? 'Verify Key &amp; Join as Staff &rarr;'
                        : 'Buy License &amp; Create Account &rarr;';
                }
            });
        });
    }
});

// License payment mode functions removed; registration is strictly for operator accounts.

// =============================================================================
// Plan Capacity & Dynamic Add-On Calculator (Register Modal)
// =============================================================================
const REG_PLANS = {
    solo:       { label: 'Solo',       basePrice: 19.00,  baseMachines: 4,   baseStaff: 1,  badgeColor: '#2563eb', badgeBg: '#eff6ff' },
    business:   { label: 'Business',   basePrice: 35.00,  baseMachines: 12,  baseStaff: 4,  badgeColor: '#059669', badgeBg: '#ecfdf5' },
    max:        { label: 'Max',        basePrice: 99.00,  baseMachines: 50,  baseStaff: 18, badgeColor: '#7c3aed', badgeBg: '#f3e8ff' },
    enterprise: { label: 'Enterprise', basePrice: 199.00, baseMachines: 100, baseStaff: 30, badgeColor: '#b45309', badgeBg: '#fef3c7' }
};

const ADDON_RATES = {
    machineRate: 1.00,
    staffRate: 2.00
};

let currentRegTier = 'solo';
let recommendedNudgeTier = null;

function calculateRegCapacity(tier, machines, staff) {
    const plan = REG_PLANS[tier] || REG_PLANS.solo;
    const rawMach  = parseInt(machines, 10);
    const rawStaff = parseInt(staff, 10);

    const finalMach  = (!isNaN(rawMach) && rawMach > 0) ? Math.max(plan.baseMachines, rawMach) : plan.baseMachines;
    const finalStaff = (!isNaN(rawStaff) && rawStaff > 0) ? Math.max(plan.baseStaff, rawStaff) : plan.baseStaff;

    const extraMach  = Math.max(0, finalMach - plan.baseMachines);
    const extraStaff = Math.max(0, finalStaff - plan.baseStaff);

    const extraMachCost  = extraMach * ADDON_RATES.machineRate;
    const extraStaffCost = extraStaff * ADDON_RATES.staffRate;
    const totalPrice     = plan.basePrice + extraMachCost + extraStaffCost;

    let nudgeTier = null;
    let nudgeText = null;

    if (tier === 'solo') {
        if (totalPrice >= 35.00 || finalMach >= 12 || finalStaff >= 4) {
            nudgeTier = 'business';
            nudgeText = 'Upgrading to Business ($35.00/mo) includes 12 machines & 4 staff by default and will save you money as your fleet expands.';
        }
    } else if (tier === 'business') {
        if (totalPrice >= 99.00 || finalMach >= 50 || finalStaff >= 18) {
            nudgeTier = 'max';
            nudgeText = 'Upgrading to Max ($99.00/mo) includes 50 machines & 18 staff by default and provides better economy of scale.';
        }
    } else if (tier === 'max') {
        if (totalPrice >= 199.00 || finalMach >= 100 || finalStaff >= 30) {
            nudgeTier = 'enterprise';
            nudgeText = 'Upgrading to Enterprise ($199.00/mo) includes 100 machines & 30 staff by default with priority fleet support.';
        }
    }

    return {
        plan,
        finalMach,
        finalStaff,
        extraMach,
        extraStaff,
        extraMachCost,
        extraStaffCost,
        totalPrice,
        nudgeTier,
        nudgeText
    };
}

function updateRegisterCalculatorUI() {
    const machInput  = document.getElementById('reg-custom-machines');
    const staffInput = document.getElementById('reg-custom-staff');
    if (!machInput || !staffInput) return;

    const calc = calculateRegCapacity(currentRegTier, machInput.value, staffInput.value);

    machInput.value = calc.finalMach;
    machInput.min   = calc.plan.baseMachines;
    staffInput.value = calc.finalStaff;
    staffInput.min   = calc.plan.baseStaff;

    const machFloorTag = document.getElementById('reg-machines-floor-tag');
    if (machFloorTag) machFloorTag.textContent = 'Base ' + calc.plan.baseMachines;

    const staffFloorTag = document.getElementById('reg-staff-floor-tag');
    if (staffFloorTag) staffFloorTag.textContent = 'Base ' + calc.plan.baseStaff;

    const machNote = document.getElementById('reg-machines-addon-note');
    if (machNote) {
        machNote.textContent = 'Included: ' + calc.plan.baseMachines + ' mach' + 
            (calc.extraMach > 0 ? ' · Extra: +' + calc.extraMach + ' (+$' + calc.extraMachCost.toFixed(2) + '/mo)' : ' · +$1.00/extra');
    }

    const staffNote = document.getElementById('reg-staff-addon-note');
    if (staffNote) {
        staffNote.textContent = 'Included: ' + calc.plan.baseStaff + ' staff' + 
            (calc.extraStaff > 0 ? ' · Extra: +' + calc.extraStaff + ' (+$' + calc.extraStaffCost.toFixed(2) + '/mo)' : ' · +$2.00/extra');
    }

    const totalDisplay = document.getElementById('reg-calc-total-display');
    if (totalDisplay) {
        totalDisplay.innerHTML = '$' + calc.totalPrice.toFixed(2) + '<span style="font-size: 0.75rem; font-weight: 600; color: #6b7280;">/mo</span>';
    }

    const tierBadge = document.getElementById('reg-calc-tier-badge');
    if (tierBadge) {
        tierBadge.textContent = calc.plan.label.toUpperCase() + ' TIER';
        tierBadge.style.color = calc.plan.badgeColor;
        tierBadge.style.background = calc.plan.badgeBg;
    }

    const hiddenPrice = document.getElementById('reg-custom-price');
    if (hiddenPrice) hiddenPrice.value = calc.totalPrice.toFixed(2);

    const btnText = document.getElementById('reg-btn-text');
    if (btnText) {
        btnText.innerHTML = 'Activate License &amp; Create Account ($' + calc.totalPrice.toFixed(2) + '/mo) &rarr;';
    }

    recommendedNudgeTier = calc.nudgeTier;
    const nudgeBox = document.getElementById('reg-tier-nudge-box');
    const nudgeTextEl = document.getElementById('reg-nudge-text');
    const nudgeBtn = document.getElementById('reg-nudge-btn');

    if (calc.nudgeTier && calc.nudgeText) {
        if (nudgeBox) nudgeBox.style.display = 'block';
        if (nudgeTextEl) nudgeTextEl.textContent = calc.nudgeText;
        if (nudgeBtn) {
            const nextLabel = REG_PLANS[calc.nudgeTier] ? REG_PLANS[calc.nudgeTier].label : 'Next Tier';
            nudgeBtn.innerHTML = 'Switch to ' + nextLabel + ' &rarr;';
        }
    } else {
        if (nudgeBox) nudgeBox.style.display = 'none';
    }
}

function onRegisterPlanChanged(tier) {
    if (!REG_PLANS[tier]) tier = 'solo';
    currentRegTier = tier;
    const plan = REG_PLANS[tier];

    const machInput  = document.getElementById('reg-custom-machines');
    const staffInput = document.getElementById('reg-custom-staff');

    if (machInput) machInput.value = plan.baseMachines;
    if (staffInput) staffInput.value = plan.baseStaff;

    updateRegisterCalculatorUI();
}

function adjustRegStepper(type, delta) {
    const el = document.getElementById(type === 'machines' ? 'reg-custom-machines' : 'reg-custom-staff');
    if (!el) return;

    const plan = REG_PLANS[currentRegTier] || REG_PLANS.solo;
    const floor = (type === 'machines') ? plan.baseMachines : plan.baseStaff;

    let cur = parseInt(el.value, 10);
    if (isNaN(cur)) cur = floor;

    const next = Math.max(floor, cur + delta);
    el.value = next;
    updateRegisterCalculatorUI();
}

function onRegCapacityInputChanged(type) {
    const el = document.getElementById(type === 'machines' ? 'reg-custom-machines' : 'reg-custom-staff');
    if (!el) return;
    updateRegisterCalculatorUI();
}

function applyNudgeUpgrade() {
    if (!recommendedNudgeTier || !REG_PLANS[recommendedNudgeTier]) return;
    const select = document.getElementById('reg-plan-tier');
    if (select) {
        select.value = recommendedNudgeTier;
    }
    onRegisterPlanChanged(recommendedNudgeTier);
}

function selectRegisterTier(tier) {
    selectRegisterTier(tier || 'solo');
    const select = document.getElementById('reg-plan-tier');
    if (select && REG_PLANS[tier]) {
        select.value = tier;
        onRegisterPlanChanged(tier);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    updateRegisterCalculatorUI();
});

function fillSuperadminCredentials() {
    const emailInput = document.getElementById('login-email');
    const passInput = document.getElementById('login-password');
    if (emailInput) {
        emailInput.value = 'admin@vendflow.io';
        emailInput.focus();
    }
    if (passInput) {
        passInput.value = 'adminpassword123';
    }
}
