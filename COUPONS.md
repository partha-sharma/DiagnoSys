# DiagnoSys Coupon Codes

This document lists all available coupon codes that can be used when booking appointments.

## Available Coupons

### SAVE10
- **Type:** Percentage Discount
- **Discount:** 10% off
- **Minimum Order:** No minimum
- **Description:** Get 10% discount on all tests

### SAVE50
- **Type:** Fixed Amount
- **Discount:** ৳50.00 off
- **Minimum Order:** ৳100.00
- **Description:** Flat ৳50 off on orders above ৳100

### HEALTH20
- **Type:** Percentage Discount
- **Discount:** 20% off
- **Minimum Order:** ৳200.00
- **Description:** 20% discount for health checkup packages

### FIRST100
- **Type:** Fixed Amount
- **Discount:** ৳100.00 off
- **Minimum Order:** ৳150.00
- **Description:** First time customer special - ৳100 off on orders above ৳150

---

## How to Use Coupons

1. Select your desired tests on the appointment booking page
2. Enter the coupon code in the "Have a coupon code?" field
3. Click the "Apply" button
4. The discount will be automatically calculated and applied to your total
5. Proceed to payment with the discounted amount

## Notes

- Only one coupon can be applied per appointment
- Coupons are case-insensitive (you can type them in lowercase or uppercase)
- The discount cannot exceed the total order amount
- Coupons are validated in real-time with the server

## For Administrators

To add or modify coupons, update the `coupons` table in the database or modify the hardcoded coupons array in `validate-coupon.php`.
