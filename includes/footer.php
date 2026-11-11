<?php
/**
 * Public Site Footer
 */
?>
    <footer class="bg-enamel text-enamelMuted border-t border-enamelBorder mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-14">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-8 lg:gap-12">

                <!-- Column 1: Brand & Hardware Disclosure -->
                <div class="md:col-span-6 space-y-4">
                    <div class="flex items-center space-x-2.5">
                        <span class="w-8 h-8 rounded-control bg-enamelSurface border border-enamelBorder text-tray flex items-center justify-center flex-shrink-0" aria-hidden="true">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <rect x="4" y="2.5" width="16" height="19" rx="2" />
                                <line x1="8" y1="7" x2="11" y2="7" />
                                <line x1="13" y1="7" x2="16" y2="7" />
                                <line x1="8" y1="11" x2="11" y2="11" />
                                <line x1="13" y1="11" x2="16" y2="11" />
                                <rect x="7.5" y="15.5" width="9" height="3" rx="0.8" />
                            </svg>
                        </span>
                        <span class="font-display font-bold text-lg text-tray">VendFlow</span>
                    </div>

                    <p class="text-sm text-enamelMuted leading-relaxed prose-measure">
                        Manual inventory ledger and restock pick-list software for snack and drink vending machine operators. Built to track par levels, machine locations, and restock audits from a warehouse desk or breakroom phone.
                    </p>

                    <p class="text-xs text-enamelMuted border-l-2 border-enamelBorder pl-3 leading-relaxed prose-measure">
                        <strong class="text-tray">Hardware boundary:</strong> VendFlow is standalone recordkeeping software. It does not sell physical vending machines, bill validators, or telemetry hardware, and stock counts are updated manually by operators.
                    </p>
                </div>

                <!-- Column 2: Application Links -->
                <div class="md:col-span-3">
                    <h2 class="font-display font-semibold text-sm text-tray mb-3">Software</h2>
                    <ul class="space-y-2 text-sm">
                        <li><a href="index.php" class="text-enamelMuted hover:text-tray">Overview</a></li>
                        <li><a href="features.php" class="text-enamelMuted hover:text-tray">Capabilities &amp; roadmap</a></li>
                        <li><a href="pricing.php" class="text-enamelMuted hover:text-tray">License tiers</a></li>
                        <li><a href="login.php?demo=1" class="text-enamelMuted hover:text-tray">Open live demo</a></li>
                        <li><a href="login.php" class="text-enamelMuted hover:text-tray">Operator sign in</a></li>
                    </ul>
                </div>

                <!-- Column 3: Support & Policies -->
                <div class="md:col-span-3">
                    <h2 class="font-display font-semibold text-sm text-tray mb-3">Support &amp; policies</h2>
                    <ul class="space-y-2 text-sm">
                        <li><a href="mailto:support@vendflow.io" class="text-enamelMuted hover:text-tray underline min-h-[44px] inline-flex items-center">support@vendflow.io</a></li>
                        <li class="text-xs text-enamelMuted">Monday to Friday, 9:00–18:00 EST</li>
                        <li class="pt-1">
                            <button type="button" onclick="openModal('termsModal', this)" class="text-enamelMuted hover:text-tray underline cursor-pointer text-left min-h-[44px] inline-flex items-center">
                                Terms of service
                            </button>
                        </li>
                        <li>
                            <button type="button" onclick="openModal('privacyModal', this)" class="text-enamelMuted hover:text-tray underline cursor-pointer text-left min-h-[44px] inline-flex items-center">
                                Privacy policy
                            </button>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-enamelBorder mt-10 pt-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 text-xs text-enamelMuted">
                <p>&copy; <?= date('Y') ?> VendFlow. Digital vending inventory ledger.</p>
                <p>Multi-tenant operator isolation with CSRF and bcrypt protection.</p>
            </div>
        </div>
    </footer>

    <!-- Terms of Service Dialog -->
    <div id="termsModal" class="modal-container hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-enamel/70" role="dialog" aria-modal="true" aria-labelledby="termsModalTitle">
        <div class="bg-tray rounded-frame max-w-lg w-full p-6 sm:p-7 border border-coil shadow-xl text-left">
            <div class="flex items-start justify-between gap-4 pb-4 border-b border-coil">
                <h3 id="termsModalTitle" class="font-display font-bold text-lg text-enamel">Terms of service</h3>
                <button type="button" data-modal-close class="text-steel hover:text-enamel p-1 rounded-control cursor-pointer" aria-label="Close terms of service dialog">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="mt-4 space-y-3 text-sm text-steel leading-relaxed">
                <p><strong class="text-enamel">Software-only license:</strong> VendFlow grants operators a non-exclusive license to use the digital inventory ledger according to their active plan tier (Starter, Business, or Enterprise).</p>
                <p><strong class="text-enamel">Hardware exclusion:</strong> VendFlow provides software recordkeeping only and does not manufacture, supply, or control physical vending machines, payment validators, or internal telemetry sensors.</p>
                <p><strong class="text-enamel">Operator responsibility:</strong> Operators maintain their own manual restock counts, par thresholds, and login credentials across registered locations.</p>
            </div>
            <div class="mt-6 pt-4 border-t border-coil flex justify-end">
                <button type="button" data-modal-close class="px-4 py-2 rounded-control bg-tote hover:bg-toteHover text-tray text-sm font-bold cursor-pointer">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Privacy Policy Dialog -->
    <div id="privacyModal" class="modal-container hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-enamel/70" role="dialog" aria-modal="true" aria-labelledby="privacyModalTitle">
        <div class="bg-tray rounded-frame max-w-lg w-full p-6 sm:p-7 border border-coil shadow-xl text-left">
            <div class="flex items-start justify-between gap-4 pb-4 border-b border-coil">
                <h3 id="privacyModalTitle" class="font-display font-bold text-lg text-enamel">Privacy policy</h3>
                <button type="button" data-modal-close class="text-steel hover:text-enamel p-1 rounded-control cursor-pointer" aria-label="Close privacy policy dialog">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="mt-4 space-y-3 text-sm text-steel leading-relaxed">
                <p><strong class="text-enamel">Account isolation:</strong> Every vending machine record, SKU, par threshold, and stock adjustment log is scoped strictly to your operator account (`user_id`).</p>
                <p><strong class="text-enamel">Credential protection:</strong> Passwords are stored using bcrypt hashes and form mutations require a valid CSRF session token.</p>
                <p><strong class="text-enamel">Private route records:</strong> Machine locations, wholesale unit costs, and retail prices remain private to your workspace and are never sold or shared.</p>
            </div>
            <div class="mt-6 pt-4 border-t border-coil flex justify-end">
                <button type="button" data-modal-close class="px-4 py-2 rounded-control bg-tote hover:bg-toteHover text-tray text-sm font-bold cursor-pointer">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
