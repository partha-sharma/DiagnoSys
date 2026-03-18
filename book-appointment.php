<?php
require_once 'config/init.php';

// Gatekeeper: Only logged-in patients can book
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

// Fetch all 'Active' tests from the database to show in the form
$tests_result = $conn->query("SELECT * FROM tests WHERE status = 'Active' ORDER BY test_name ASC");
$packages_result = $conn->query("SELECT package_id, name, description, final_price FROM packages WHERE status = 'Active' ORDER BY name ASC");
$technicians_result = $conn->query("SELECT technician_id, name, specialization FROM technicians WHERE status = 'Active' ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book an Appointment - DiagnoLab</title>
    <link rel="stylesheet" href="assets/css/style.css?v=2.0">
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
            <p>Select your desired date, time, and the tests you need.</p>
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
                        <label class="form-label">Select Time</label>
                        <div class="time-slots" id="timeSlotsContainer">
                            <p style="grid-column: 1/-1; text-align: center; color: #94a3b8; padding: 20px 0;">
                                Select a date first
                            </p>
                        </div>
                        <small id="slotInfoText" style="display:block; margin-top:8px; color:#64748b;"></small>
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
                    </div>

                    <div class="form-group">
                        <label class="form-label">Select Tests</label>
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
                                        class="test-checkbox"
                                    >
                                    <?php echo htmlspecialchars($test['test_name']); ?>
                                    <span class="test-price">৳<?php echo number_format($test['price'], 2); ?></span>
                                </label>
                            </li>
                            <?php endwhile; ?>
                        </ul>
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
                                <option value="">Assign technician (optional)</option>
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
        const timeSlotsContainer = document.getElementById('timeSlotsContainer');
        const submitBtn = document.getElementById('submitBtn');

        const slotInfoText = document.getElementById('slotInfoText');

        // Set min date to today
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        dateInput.min = `${yyyy}-${mm}-${dd}`;

        // Format time to 12-hour
        function formatTime(time) {
            const [h, m] = time.split(':');
            const hour = parseInt(h);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const hr = hour % 12 || 12;
            return `${hr}:${m} ${ampm}`;
        }

        // Load time slots when date changes
        dateInput.addEventListener('change', function() {
            if (!this.value) return;

            timeSlotsContainer.innerHTML = '';
            timeInput.value = '';
            submitBtn.disabled = true;
            const selectedDate = new Date(this.value);
            const isToday = selectedDate.toDateString() === today.toDateString();

            fetch(`get-available-slots.php?date=${encodeURIComponent(this.value)}`)
            .then(response => response.json())
            .then(data => {
                if (!data.success || !Array.isArray(data.slots) || data.slots.length === 0) {
                    slotInfoText.textContent = 'No configured slots found for this date. Contact admin.';
                    return;
                }

                data.slots.forEach(slotRow => {
                    const slot = slotRow.time;
                    const available = Number(slotRow.available || 0);
                    const status = String(slotRow.status || 'Available');

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = formatTime(slot);
                btn.className = 'time-btn';
                btn.dataset.time = slot;

                // Disable past slots
                if (isToday) {
                    const [h, m] = slot.split(':');
                    const slotTime = new Date(today.getFullYear(), today.getMonth(), today.getDate(), h, m);
                    if (slotTime < today) {
                        btn.disabled = true;
                    }
                }

                if (status !== 'Available' || available <= 0) {
                    btn.disabled = true;
                    btn.title = 'Slot unavailable';
                } else {
                    btn.title = `${available} seats left`;
                }

                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (!this.disabled) {
                        document.querySelectorAll('.time-btn').forEach(b => b.classList.remove('selected'));
                        this.classList.add('selected');
                        timeInput.value = this.dataset.time;
                        submitBtn.disabled = selectedTests.length === 0;
                        slotInfoText.textContent = this.title;
                    }
                });

                timeSlotsContainer.appendChild(btn);
                });
            })
            .catch(() => {
                slotInfoText.textContent = 'Could not fetch live slot availability.';
            });
        });

        // Cart functionality
        const testCheckboxes = document.querySelectorAll('.test-checkbox');
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

        let selectedTests = [];
        let appliedCoupon = null;

        function updateCart() {
            selectedTests = [];
            
            testCheckboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    selectedTests.push({
                        id: checkbox.value,
                        name: checkbox.dataset.testName,
                        price: parseFloat(checkbox.dataset.testPrice)
                    });
                }
            });

            if (selectedTests.length === 0) {
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
            submitBtn.disabled = !timeInput.value;

            // Update cart list
            selectedTestsList.innerHTML = '';
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
            const subtotal = selectedTests.reduce((sum, test) => sum + test.price, 0);
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
            const subtotal = selectedTests.reduce((sum, test) => sum + test.price, 0);
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
            console.log('Selected tests:', selectedTests);
            
            if (!code) {
                console.log('No code entered');
                showCouponMessage('Please enter a coupon code', 'error');
                return;
            }

            if (selectedTests.length === 0) {
                console.log('No tests selected');
                showCouponMessage('Please select tests first', 'error');
                return;
            }

            const subtotal = selectedTests.reduce((sum, test) => sum + test.price, 0);
            console.log('Subtotal for validation:', subtotal);

            // Disable button during validation
            applyCouponBtn.disabled = true;
            applyCouponBtn.textContent = 'Validating...';

            console.log('Sending fetch request to validate-coupon.php');

            // Validate coupon with backend
            fetch('validate-coupon.php', {
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

        // Event listeners
        testCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateCart);
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
        
        console.log('Cart system initialized');
        console.log('Test checkboxes found:', testCheckboxes.length);
        console.log('Apply button:', applyCouponBtn);
    </script>
</body>
</html>