<?php
require_once 'config/init.php';

// Gatekeeper: Only logged-in patients can book
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

// Fetch all 'Active' tests from the database to show in the form
$tests_result = $conn->query("SELECT * FROM tests WHERE status = 'Active' ORDER BY test_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book an Appointment - DiagnoLab</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .appointment-container {
            padding: 40px 20px;
            max-width: 600px;
            margin: 0 auto;
        }

        .appointment-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .appointment-header h1 {
            font-size: 28px;
            color: #0f172a;
            margin-bottom: 10px;
        }

        .appointment-header p {
            color: #64748b;
            font-size: 15px;
        }

        .form-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .form-input {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            color: #1a1a1a;
        }

        .form-input:focus {
            outline: none;
            border-color: #0ea5e9;
            box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.1);
        }

        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(75px, 1fr));
            gap: 8px;
        }

        .time-btn {
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: #f8fafc;
            color: #64748b;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: 0.2s;
        }

        .time-btn:hover:not(:disabled) {
            border-color: #0ea5e9;
            background: #f0f8ff;
            color: #0ea5e9;
        }

        .time-btn.selected {
            background: #0ea5e9;
            color: white;
            border-color: #0ea5e9;
        }

        .time-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .btn-primary {
            flex: 1;
            padding: 12px;
            background: #0ea5e9;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-primary:hover:not(:disabled) {
            background: #0284c7;
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-secondary {
            flex: 1;
            padding: 12px;
            background: white;
            color: #0ea5e9;
            border: 1px solid #0ea5e9;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
        }

        .btn-secondary:hover {
            background: #0ea5e9;
            color: white;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 3px solid #ef4444;
        }

        .test-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .test-list li {
            margin-bottom: 10px;
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            transition: 0.2s;
        }

        .test-list li:hover {
            border-color: #0ea5e9;
            background: #f0f8ff;
        }

        .test-list label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-size: 14px;
            color: #334155;
        }

        .test-list input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #0ea5e9;
        }

        .test-price {
            margin-left: auto;
            font-weight: 600;
            color: #0ea5e9;
        }

        .info-text {
            background: #f0f8ff;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            color: #0c4a6e;
            margin-top: 15px;
            border-left: 3px solid #0ea5e9;
        }

        @media (max-width: 640px) {
            .appointment-container {
                padding: 20px;
            }

            .form-card {
                padding: 20px;
            }

            .time-slots {
                grid-template-columns: repeat(4, 1fr);
            }

            .coupon-input-group {
                flex-direction: column;
            }

            .btn-apply {
                width: 100%;
            }
        }
    </style>
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

        <div class="form-card">
            <?php
            if (isset($_SESSION['error'])) {
                echo '<div class="alert alert-error">' . htmlspecialchars($_SESSION['error']) . '</div>';
                unset($_SESSION['error']);
            }
            ?>

            <form action="book-process.php" method="POST">
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
                    <input type="hidden" id="appointment_time" name="appointment_time" required>
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

                <!-- Cart Summary -->
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

                <div class="info-text">
                    <strong>Working Hours:</strong> 9:00 AM - 5:00 PM (Monday to Saturday)
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-primary" id="submitBtn" disabled>
                        Proceed to Payment
                    </button>
                    <a href="dashboard.php" class="btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        const dateInput = document.getElementById('appointment_date');
        const timeInput = document.getElementById('appointment_time');
        const timeSlotsContainer = document.getElementById('timeSlotsContainer');
        const submitBtn = document.getElementById('submitBtn');

        // Time slots
        const timeSlots = ['09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00', '12:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', '17:00'];

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
            const selectedDate = new Date(this.value);
            const isToday = selectedDate.toDateString() === today.toDateString();

            timeSlots.forEach(slot => {
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

                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (!this.disabled) {
                        document.querySelectorAll('.time-btn').forEach(b => b.classList.remove('selected'));
                        this.classList.add('selected');
                        timeInput.value = this.dataset.time;
                        submitBtn.disabled = false;
                    }
                });

                timeSlotsContainer.appendChild(btn);
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
                // Reset coupon if no tests selected
                if (appliedCoupon) {
                    removeCoupon();
                }
                return;
            }

            cartContent.style.display = 'none';
            cartItems.style.display = 'block';

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

            if (appliedCoupon) {
                discount = appliedCoupon.discount;
                console.log('Calculating totals - Subtotal:', subtotal, 'Discount:', discount, 'Applied Coupon:', appliedCoupon);
            }

            const total = Math.max(0, subtotal - discount);

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
            console.log('Final - Subtotal:', subtotal, 'Discount:', discount, 'Total:', total);
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