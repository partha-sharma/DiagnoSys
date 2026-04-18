<?php
require_once __DIR__ . '/../config/init.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
error_log("Coupon validation request received");

// Gatekeeper: Only logged-in patients can validate coupons
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    error_log("Unauthorized access - user_id: " . ($_SESSION['user_id'] ?? 'none') . ", role: " . ($_SESSION['role'] ?? 'none'));
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $coupon_code = strtoupper(trim($_POST['code'] ?? ''));
    $subtotal = floatval($_POST['subtotal'] ?? 0);

    error_log("Coupon validation - Code: $coupon_code, Subtotal: $subtotal");

    if (empty($coupon_code)) {
        error_log("Empty coupon code");
        echo json_encode(['success' => false, 'message' => 'Coupon code is required']);
        exit();
    }

    if ($subtotal <= 0) {
        error_log("Invalid subtotal: $subtotal");
        echo json_encode(['success' => false, 'message' => 'Please select tests first']);
        exit();
    }

    // Try to check if coupons table exists
    $table_exists = false;
    $stmt = null;
    $check_table = $conn->query("SHOW TABLES LIKE 'coupons'");
    if ($check_table && $check_table->num_rows > 0) {
        $table_exists = true;
    }

    $coupon = null;

    if ($table_exists) {
        // Check database for coupon
        $stmt = $conn->prepare("
            SELECT * FROM coupons 
            WHERE code = ? 
            AND status = 'Active'
            AND (valid_from IS NULL OR valid_from <= CURDATE())
            AND (valid_until IS NULL OR valid_until >= CURDATE())
            AND (usage_limit IS NULL OR used_count < usage_limit)
        ");
        $stmt->bind_param("s", $coupon_code);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $coupon = $result->fetch_assoc();
        }
    } else {
        error_log("Coupons table doesn't exist, using hardcoded coupons");
        // Fallback to hardcoded coupons if table doesn't exist
        $hardcoded_coupons = [
            'SAVE10' => ['discount_type' => 'percentage', 'discount_value' => 10, 'min_amount' => 0, 'max_discount' => null],
            'SAVE50' => ['discount_type' => 'fixed', 'discount_value' => 50, 'min_amount' => 100, 'max_discount' => null],
            'HEALTH20' => ['discount_type' => 'percentage', 'discount_value' => 20, 'min_amount' => 200, 'max_discount' => null],
            'FIRST100' => ['discount_type' => 'fixed', 'discount_value' => 100, 'min_amount' => 150, 'max_discount' => null],
        ];

        if (isset($hardcoded_coupons[$coupon_code])) {
            $coupon = $hardcoded_coupons[$coupon_code];
        }
    }

    if (!$coupon) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired coupon code']);
        exit();
    }

    // Check minimum amount requirement
    if ($subtotal < $coupon['min_amount']) {
        echo json_encode([
            'success' => false, 
            'message' => sprintf('Minimum order amount of ৳%.2f required', $coupon['min_amount'])
        ]);
        exit();
    }

    // Calculate discount
    $discount = 0;
    if ($coupon['discount_type'] === 'percentage') {
        $discount = ($subtotal * $coupon['discount_value']) / 100;
        // Apply max discount limit if set
        if (isset($coupon['max_discount']) && $coupon['max_discount'] && $discount > $coupon['max_discount']) {
            $discount = $coupon['max_discount'];
        }
        $description = $coupon['discount_value'] . '% off';
    } else {
        $discount = $coupon['discount_value'];
        $description = '৳' . number_format($discount, 2) . ' off';
    }

    // Ensure discount doesn't exceed subtotal
    $discount = min($discount, $subtotal);

    error_log("Coupon '$coupon_code' validated successfully - Discount: $discount, Type: {$coupon['discount_type']}, Value: {$coupon['discount_value']}");

    echo json_encode([
        'success' => true,
        'message' => sprintf('Coupon "%s" applied successfully!', $coupon_code),
        'discount' => round($discount, 2),
        'type' => $coupon['discount_type'],
        'value' => floatval($coupon['discount_value']),
        'description' => $description
    ]);

    // Close statement only if it was created
    if ($stmt) {
        $stmt->close();
    }
} else {
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
