<?php
require_once __DIR__ . '/../config/init.php';

// Gatekeeper: Only logged-in patients can book
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../auth/login.php");
    exit();
}

// Fetch all 'Active' tests from the database to show in the form
$tests_result = $conn->query("SELECT test_id, test_name, COALESCE(description, '') AS description, price, COALESCE(test_category, 'General') AS test_category, COALESCE(sample_requirement, 'None') AS sample_requirement FROM tests WHERE status = 'Active' ORDER BY FIELD(test_category, 'Laboratory', 'Cardiology', 'Imaging', 'General'), test_name ASC");
$packages_result = $conn->query("SELECT package_id, name, description, final_price FROM packages WHERE status = 'Active' ORDER BY name ASC");
$technicians_result = $conn->query("SELECT technician_id, name, specialization FROM technicians WHERE status = 'Active' ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book an Appointment - DiagnoLab</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=2.0">
</head>
<body>
    <div class="navbar">
        <a href="dashboard.php" class="logo">DiagnoLab</a>
        <div class="nav-buttons">
            <a href="dashboard.php" class="btn-outline">Dashboard</a>
        </div>
    </div>

    <div class="appointment-container">
        <div class="appointment-header">
            <h1>Schedule Your Appointment</h1>
            <p>Select your desired date and tests. Time-slot planning is handled by admin with room management.</p>
        </div>

        <?php
        if (isset($_SESSION['error'])) {
            echo '<div class="alert alert-error">' . htmlspecialchars($_SESSION['error']) . '</div>';
            unset($_SESSION['error']);
        }
        ?>

        <form action="book-process.php" method="POST">
            <div class="booking-layout">
                <!-- Left Column - Form Fields -->
                <div class="form-card">
                    <div class="form-group">
                        <label class="form-label">Select Date</label>
                        <input 
                            type="date" 
                            id="appointment_date" 
                            name="appointment_date" 
                            class="form-input"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label">Time Slot</label>
                        <p style="margin: 0; color: #64748b; font-size: 14px;">Managed by admin according to room allocation and capacity.</p>
                        <small id="slotInfoText" style="display:block; margin-top:8px; color:#64748b;">Pick a date. Exact timing will be finalized by admin.</small>
                        <input type="hidden" id="appointment_time" name="appointment_time" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Select Package (Optional)</label>
                        <select name="package_id" class="form-input" id="packageSelect">
                            <option value="">No package selected</option>
                            <?php if ($packages_result): ?>
                                <?php while($package = $packages_result->fetch_assoc()): ?>
                                    <option value="<?php echo (int)$package['package_id']; ?>">
                                        <?php echo htmlspecialchars($package['name']); ?> (৳<?php echo number_format((float)$package['final_price'], 2); ?>)
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                        <div class="package-info-box" id="packageInfoBox" style="display:none;"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Select Tests</label>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:8px; margin-bottom:10px;">
                            <select id="testTypeFilter" class="form-input">
                                <option value="">All Types</option>
                                <option value="Laboratory">Laboratory</option>
                                <option value="Cardiology">Cardiology</option>
                                <option value="Imaging">Imaging</option>
                                <option value="General">General</option>
                            </select>
                            <select id="testSampleFilter" class="form-input">
                                <option value="">All Samples</option>
                                <option value="Blood">Blood Sample</option>
                                <option value="Urine">Urine Sample</option>
                                <option value="Stool">Stool Sample</option>
                                <option value="Saliva">Saliva Sample</option>
                                <option value="Swab">Swab Sample</option>
                                <option value="None">No Sample Required</option>
                            </select>
                        </div>
                        <input
                            type="text"
                            id="testSearchInput"
                            class="form-input"
                            placeholder="Search tests by name"
                            style="margin-bottom:10px;"
                        >
                        <ul class="test-list">
                            <?php while($test = $tests_result->fetch_assoc()): ?>
                            <li>
                                <label>
                                    <input 
                                        type="checkbox" 
                                        name="test_ids[]" 
                                        value="<?php echo $test['test_id']; ?>"
                                        data-test-name="<?php echo htmlspecialchars($test['test_name']); ?>"
                                        data-test-price="<?php echo $test['price']; ?>"
                                        data-test-category="<?php echo htmlspecialchars(normalize_test_category((string)$test['test_category'])); ?>"
                                        data-test-sample="<?php echo htmlspecialchars(normalize_sample_requirement((string)$test['sample_requirement'])); ?>"
                                        class="test-checkbox"
                                    >
                                    <?php echo htmlspecialchars($test['test_name']); ?>
                                    <span class="test-price">৳<?php echo number_format($test['price'], 2); ?></span>
                                    <small style="display:block; color:#64748b; margin-top:3px;">
                                        <?php echo htmlspecialchars(normalize_test_category((string)$test['test_category'])); ?> | <?php echo htmlspecialchars(sample_requirement_display_label(normalize_sample_requirement((string)$test['sample_requirement']))); ?>
                                    </small>
                                </label>
                            </li>
                            <?php endwhile; ?>
                        </ul>
                        <small id="testSearchEmpty" style="display:none; color:#64748b;">No tests matched your search.</small>
                    </div>

                    <div class="info-text">
                        <strong>Working Hours:</strong> 9:00 AM - 5:00 PM (Monday to Saturday)
                    </div>

                    <div class="form-group" style="margin-top: 18px;">
                        <label class="form-label">Home Sample Collection</label>
                        <label style="display:flex; align-items:center; gap:8px; margin-bottom:10px; color:#334155;">
                            <input type="checkbox" id="isHomeCollection" name="is_home_collection" value="1">
                            Choose home sample collection
                        </label>

                        <div id="homeCollectionFields" style="display:none;">
                            <input type="text" name="collection_address" class="form-input" placeholder="Collection address">
                            <input type="datetime-local" name="collection_time" class="form-input" style="margin-top:10px;">
                            <input type="number" step="0.01" name="collection_charge" id="collectionChargeInput" class="form-input" placeholder="Extra charge (e.g. 100)" value="100" style="margin-top:10px;">
                            <select name="assigned_technician_id" class="form-input" style="margin-top:10px;">
                                <option value="">Preferred technician (auto-matched by test type)</option>
                                <?php if ($technicians_result): ?>
                                    <?php while($tech = $technicians_result->fetch_assoc()): ?>
                                        <option value="<?php echo (int)$tech['technician_id']; ?>">
                                            <?php echo htmlspecialchars($tech['name']); ?><?php echo !empty($tech['specialization']) ? ' - ' . htmlspecialchars($tech['specialization']) : ''; ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 16px;">
                        <label class="form-label">Doctor Referral (Optional)</label>
                        <input type="text" name="doctor_name" class="form-input" placeholder="Doctor name">
                        <input type="text" name="doctor_hospital" class="form-input" placeholder="Hospital/Clinic" style="margin-top:10px;">
                        <input type="text" name="doctor_specialty" class="form-input" placeholder="Specialty" style="margin-top:10px;">
                        <input type="text" name="doctor_contact" class="form-input" placeholder="Contact number" style="margin-top:10px;">
                        <textarea name="referral_notes" class="form-input" placeholder="Referral notes" style="margin-top:10px; min-height:90px;"></textarea>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn-primary" id="submitBtn" disabled>
                            Proceed to Payment
                        </button>
                        <a href="dashboard.php" class="btn-secondary">Cancel</a>
                    </div>
                </div>

                <!-- Right Column - Order Summary -->
                <div class="cart-summary">
                    <div class="cart-header">📋 Order Summary</div>
                    
                    <div id="cartContent">
                        <div class="cart-empty">
                            Select tests to see your order summary
                        </div>
                    </div>

                    <div id="cartItems" style="display: none;">
                        <ul class="cart-items" id="selectedTestsList"></ul>
                        
                        <div class="cart-totals">
                            <div class="cart-row subtotal">
                                <span>Subtotal:</span>
                                <span id="subtotalAmount">৳0.00</span>
                            </div>
                            <div class="cart-row discount" id="discountRow" style="display: none;">
                                <span id="discountLabel">Discount:</span>
                                <span id="discountAmount">৳0.00</span>
                            </div>
                            <div class="cart-row total">
                                <span>Total:</span>
                                <span id="totalAmount">৳0.00</span>
                            </div>
                        </div>

                        <!-- Coupon Section -->
                        <div class="coupon-section">
                            <label class="coupon-label">Have a coupon code?</label>
                            <div class="coupon-input-group">
                                <input 
                                    type="text" 
                                    id="couponCode" 
                                    class="coupon-input" 
                                    placeholder="Enter coupon code"
                                    maxlength="20"
                                >
                                <button type="button" class="btn-apply" id="applyCouponBtn">
                                    Apply
                                </button>
                            </div>
                            <div class="coupon-message" id="couponMessage"></div>
                            <input type="hidden" name="coupon_code" id="appliedCouponCode">
                            <input type="hidden" name="discount_amount" id="appliedDiscountAmount" value="0">
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        const dateInput = document.getElementById('appointment_date');
        const timeInput = document.getElementById('appointment_time');
        const submitBtn = document.getElementById('submitBtn');
        const packageSelect = document.getElementById('packageSelect');
        const packageInfoBox = document.getElementById('packageInfoBox');

        const slotInfoText = document.getElementById('slotInfoText');

        // Set min date to today
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        dateInput.min = `${yyyy}-${mm}-${dd}`;

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        let selectedPackage = null;
        let packageIncludedTestIds = new Set();
        let previousPackageIncludedTestIds = new Set();

        function getManualSelectedTests() {
            return Array.from(document.querySelectorAll('.test-checkbox'))
                .filter(checkbox => checkbox.checked && !checkbox.disabled)
                .map(checkbox => ({
                    id: checkbox.value,
                    name: checkbox.dataset.testName,
                    price: parseFloat(checkbox.dataset.testPrice)
                }));
        }

        function getPackageSubtotal() {
            if (!selectedPackage) return 0;
            return parseFloat(selectedPackage.final_price || 0);
        }

        function calculateSubtotal() {
            const manualSubtotal = getManualSelectedTests().reduce((sum, test) => sum + test.price, 0);
            return getPackageSubtotal() + manualSubtotal;
        }

        function canSubmitBooking() {
            const hasSelection = Boolean(selectedPackage) || getManualSelectedTests().length > 0;
            return hasSelection && Boolean(timeInput.value);
        }

        function renderPackageInfo() {
            if (!selectedPackage) {
                packageInfoBox.style.display = 'none';
                packageInfoBox.innerHTML = '';
                return;
            }

            const includedTests = selectedPackage.tests || [];
            const testItems = includedTests.map(test => `<li><span>${escapeHtml(test.test_name)}</span> <span>৳${Number(test.package_test_price).toFixed(2)}</span></li>`).join('');

            packageInfoBox.innerHTML = `
                <div class="package-info-card">
                    <div class="package-info-header">
                        <strong>${escapeHtml(selectedPackage.name)}</strong>
                        <span>৳${Number(selectedPackage.final_price).toFixed(2)}</span>
                    </div>
                    <p>${escapeHtml(selectedPackage.description || '')}</p>
                    <small>Included tests are locked for this booking.</small>
                    <ul class="package-info-list">${testItems}</ul>
                </div>
            `;
            packageInfoBox.style.display = 'block';
        }

        function syncPackageTestLock() {
            document.querySelectorAll('.test-checkbox').forEach(checkbox => {
                const isPackageTest = packageIncludedTestIds.has(String(checkbox.value));
                const wasLockedByPackage = previousPackageIncludedTestIds.has(String(checkbox.value));
                if (selectedPackage && isPackageTest) {
                    checkbox.checked = true;
                    checkbox.disabled = true;
                    checkbox.closest('li')?.classList.add('package-included-test');
                } else {
                    checkbox.disabled = false;
                    checkbox.closest('li')?.classList.remove('package-included-test');
                    if (wasLockedByPackage) {
                        checkbox.checked = false;
                    } else if (!checkbox.checked) {
                        checkbox.checked = false;
                    }
                }
            });

            previousPackageIncludedTestIds = new Set(packageIncludedTestIds);
        }

        async function loadPackageDetails(packageId) {
            selectedPackage = null;
            packageIncludedTestIds = new Set();

            if (!packageId) {
                renderPackageInfo();
                syncPackageTestLock();
                updateCart();
                return;
            }

            packageInfoBox.style.display = 'block';
            packageInfoBox.innerHTML = '<div class="package-info-card">Loading package details...</div>';

            try {
                const response = await fetch(`../api/get-package-details.php?package_id=${encodeURIComponent(packageId)}`);
                const data = await response.json();

                if (!data.success || !data.package) {
                    packageInfoBox.innerHTML = '<div class="package-info-card">Unable to load package details.</div>';
                    return;
                }

                selectedPackage = data.package;
                packageIncludedTestIds = new Set((data.package.tests || []).map(test => String(test.test_id)));
                renderPackageInfo();
                syncPackageTestLock();
                updateCart();
            } catch (error) {
                packageInfoBox.innerHTML = '<div class="package-info-card">Could not load package details.</div>';
            }
        }

        // Keep patient booking independent from slot management. Admin finalizes exact schedule.
        dateInput.addEventListener('change', function() {
            if (!this.value) {
                timeInput.value = '';
                slotInfoText.textContent = 'Pick a date. Exact timing will be finalized by admin.';
                submitBtn.disabled = !canSubmitBooking();
                return;
            }

            timeInput.value = '09:00';
            slotInfoText.textContent = 'Date selected. Admin will finalize room and exact slot timing.';
            submitBtn.disabled = !canSubmitBooking();
        });

        // Cart functionality
        const testCheckboxes = document.querySelectorAll('.test-checkbox');
        const testSearchInput = document.getElementById('testSearchInput');
        const testTypeFilter = document.getElementById('testTypeFilter');
        const testSampleFilter = document.getElementById('testSampleFilter');
        const testSearchEmpty = document.getElementById('testSearchEmpty');
        const testListItems = document.querySelectorAll('.test-list li');
        const cartContent = document.getElementById('cartContent');
        const cartItems = document.getElementById('cartItems');
        const selectedTestsList = document.getElementById('selectedTestsList');
        const subtotalAmount = document.getElementById('subtotalAmount');
        const discountRow = document.getElementById('discountRow');
        const discountLabel = document.getElementById('discountLabel');
        const discountAmount = document.getElementById('discountAmount');
        const totalAmount = document.getElementById('totalAmount');
        const couponInput = document.getElementById('couponCode');
        const applyCouponBtn = document.getElementById('applyCouponBtn');
        const couponMessage = document.getElementById('couponMessage');
        const appliedCouponCode = document.getElementById('appliedCouponCode');
        const appliedDiscountAmount = document.getElementById('appliedDiscountAmount');
        const isHomeCollection = document.getElementById('isHomeCollection');
        const homeCollectionFields = document.getElementById('homeCollectionFields');
        const collectionChargeInput = document.getElementById('collectionChargeInput');

        let appliedCoupon = null;

        function updateCart() {
            const selectedTests = getManualSelectedTests();
            const packageSubtotal = getPackageSubtotal();

            if (!selectedPackage && selectedTests.length === 0) {
                cartContent.style.display = 'block';
                cartItems.style.display = 'none';
                submitBtn.disabled = true;
                // Reset coupon if no tests selected
                if (appliedCoupon) {
                    removeCoupon();
                }
                return;
            }

            cartContent.style.display = 'none';
            cartItems.style.display = 'block';
            submitBtn.disabled = !canSubmitBooking();

            // Update cart list
            selectedTestsList.innerHTML = '';
            if (selectedPackage) {
                const packageItem = document.createElement('li');
                packageItem.className = 'cart-item package-item';
                packageItem.innerHTML = `
                    <span>${selectedPackage.name} <small>(Package)</small></span>
                    <span>৳${parseFloat(selectedPackage.final_price).toFixed(2)}</span>
                `;
                selectedTestsList.appendChild(packageItem);

                const packageDetail = document.createElement('li');
                packageDetail.className = 'cart-item package-detail';
                packageDetail.innerHTML = `
                    <span>Included: ${(selectedPackage.tests || []).map(test => test.test_name).join(', ')}</span>
                    <span>Locked</span>
                `;
                selectedTestsList.appendChild(packageDetail);
            }

            selectedTests.forEach(test => {
                const li = document.createElement('li');
                li.className = 'cart-item';
                li.innerHTML = `
                    <span>${test.name}</span>
                    <span>৳${test.price.toFixed(2)}</span>
                `;
                selectedTestsList.appendChild(li);
            });

            // Recalculate discount if coupon is applied
            if (appliedCoupon) {
                recalculateCouponDiscount();
            } else {
                calculateTotals();
            }
        }

        function recalculateCouponDiscount() {
            const subtotal = calculateSubtotal();
            let discount = 0;

            if (appliedCoupon.type === 'percentage') {
                discount = (subtotal * appliedCoupon.value) / 100;
            } else {
                discount = appliedCoupon.value;
            }

            // Ensure discount doesn't exceed subtotal
            discount = Math.min(discount, subtotal);
            appliedCoupon.discount = discount;

            calculateTotals();
        }

        function calculateTotals() {
            const subtotal = calculateSubtotal();
            let discount = 0;
            let collectionCharge = 0;

            if (appliedCoupon) {
                discount = appliedCoupon.discount;
                console.log('Calculating totals - Subtotal:', subtotal, 'Discount:', discount, 'Applied Coupon:', appliedCoupon);
            }

            if (isHomeCollection && isHomeCollection.checked) {
                collectionCharge = parseFloat(collectionChargeInput?.value || 0);
            }

            const total = Math.max(0, subtotal - discount + collectionCharge);

            subtotalAmount.textContent = `৳${subtotal.toFixed(2)}`;
            
            if (discount > 0) {
                discountRow.style.display = 'flex';
                discountLabel.textContent = `Discount (${appliedCoupon.description}):`;
                discountAmount.textContent = `-৳${discount.toFixed(2)}`;
                appliedDiscountAmount.value = discount.toFixed(2);
            } else {
                discountRow.style.display = 'none';
                appliedDiscountAmount.value = '0';
            }

            totalAmount.textContent = `৳${total.toFixed(2)}`;
            console.log('Final - Subtotal:', subtotal, 'Discount:', discount, 'Collection:', collectionCharge, 'Total:', total);
        }

        function applyCoupon() {
            console.log('=== applyCoupon() called ===');
            const code = couponInput.value.trim().toUpperCase();
            console.log('Coupon code entered:', code);
            console.log('Selected tests:', getManualSelectedTests());
            
            if (!code) {
                console.log('No code entered');
                showCouponMessage('Please enter a coupon code', 'error');
                return;
            }

            if (!selectedPackage && getManualSelectedTests().length === 0) {
                console.log('No tests selected');
                showCouponMessage('Please select tests first', 'error');
                return;
            }

            const subtotal = calculateSubtotal();
            console.log('Subtotal for validation:', subtotal);

            // Disable button during validation
            applyCouponBtn.disabled = true;
            applyCouponBtn.textContent = 'Validating...';

            console.log('Sending fetch request to ../api/validate-coupon.php');

            // Validate coupon with backend
            fetch('../api/validate-coupon.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `code=${encodeURIComponent(code)}&subtotal=${subtotal}`
            })
            .then(response => {
                console.log('Response received:', response);
                return response.json();
            })
            .then(data => {
                console.log('Coupon validation response:', data);
                if (data.success) {
                    appliedCoupon = {
                        code: code,
                        type: data.type,
                        value: parseFloat(data.value),
                        discount: parseFloat(data.discount),
                        description: data.description
                    };
                    appliedCouponCode.value = code;
                    showCouponMessage(data.message + ' ' + data.description, 'success');
                    couponInput.disabled = true;
                    applyCouponBtn.textContent = 'Remove';
                    applyCouponBtn.disabled = false;
                    console.log('Applied coupon:', appliedCoupon);
                    calculateTotals();
                } else {
                    console.log('Coupon validation failed:', data.message);
                    showCouponMessage(data.message, 'error');
                    applyCouponBtn.disabled = false;
                    applyCouponBtn.textContent = 'Apply';
                }
            })
            .catch(error => {
                console.error('Coupon validation error:', error);
                showCouponMessage('Error validating coupon. Please try again.', 'error');
                applyCouponBtn.disabled = false;
                applyCouponBtn.textContent = 'Apply';
            });
        }

        function removeCoupon() {
            appliedCoupon = null;
            appliedCouponCode.value = '';
            couponInput.value = '';
            couponInput.disabled = false;
            applyCouponBtn.textContent = 'Apply';
            couponMessage.style.display = 'none';
            couponMessage.className = 'coupon-message';
            calculateTotals();
        }

        function showCouponMessage(message, type) {
            couponMessage.textContent = message;
            couponMessage.className = `coupon-message ${type}`;
        }

        function filterTests(query) {
            const normalizedQuery = String(query || '').trim().toLowerCase();
            const selectedType = String(testTypeFilter?.value || '').trim();
            const selectedSample = String(testSampleFilter?.value || '').trim();
            let visibleCount = 0;

            testListItems.forEach(item => {
                const label = item.querySelector('label');
                const nameText = label ? label.textContent.toLowerCase() : '';
                const checkbox = item.querySelector('.test-checkbox');
                const category = checkbox ? String(checkbox.dataset.testCategory || '') : '';
                const sample = checkbox ? String(checkbox.dataset.testSample || '') : '';

                const matchedText = normalizedQuery === '' || nameText.includes(normalizedQuery);
                const matchedType = selectedType === '' || category === selectedType;
                const matchedSample = selectedSample === '' || sample === selectedSample;
                const matched = matchedText && matchedType && matchedSample;
                item.style.display = matched ? '' : 'none';
                if (matched) {
                    visibleCount++;
                }
            });

            testSearchEmpty.style.display = visibleCount === 0 ? 'block' : 'none';
        }

        // Event listeners
        testCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateCart);
        });

        if (testSearchInput) {
            testSearchInput.addEventListener('input', function() {
                filterTests(this.value);
            });
        }

        if (testTypeFilter) {
            testTypeFilter.addEventListener('change', function() {
                filterTests(testSearchInput ? testSearchInput.value : '');
            });
        }

        if (testSampleFilter) {
            testSampleFilter.addEventListener('change', function() {
                filterTests(testSearchInput ? testSearchInput.value : '');
            });
        }

        packageSelect.addEventListener('change', function() {
            loadPackageDetails(this.value);
        });

        if (isHomeCollection) {
            isHomeCollection.addEventListener('change', function() {
                homeCollectionFields.style.display = this.checked ? 'block' : 'none';
                calculateTotals();
            });
        }

        if (collectionChargeInput) {
            collectionChargeInput.addEventListener('input', calculateTotals);
        }

        applyCouponBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Apply button clicked! Button text:', this.textContent);
            if (this.textContent.trim() === 'Apply') {
                console.log('Calling applyCoupon()');
                applyCoupon();
            } else {
                console.log('Calling removeCoupon()');
                removeCoupon();
            }
        });

        couponInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (applyCouponBtn.textContent.trim() === 'Apply') {
                    applyCoupon();
                }
            }
        });

        // Initial cart update
        updateCart();
        filterTests('');

        if (packageSelect.value) {
            loadPackageDetails(packageSelect.value);
        }
        
        console.log('Cart system initialized');
        console.log('Test checkboxes found:', testCheckboxes.length);
        console.log('Apply button:', applyCouponBtn);
    </script>
</body>
</html>



