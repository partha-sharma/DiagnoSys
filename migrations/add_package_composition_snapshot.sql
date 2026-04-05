ALTER TABLE `package_tests`
  ADD COLUMN `package_test_price` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `test_id`;

ALTER TABLE `appointments`
  ADD COLUMN `package_id` int(11) DEFAULT NULL AFTER `user_id`,
  ADD COLUMN `package_name_snapshot` varchar(100) DEFAULT NULL AFTER `package_id`,
  ADD COLUMN `package_tests_snapshot` longtext DEFAULT NULL AFTER `package_name_snapshot`,
  ADD COLUMN `package_price_snapshot` decimal(10,2) DEFAULT 0.00 AFTER `package_tests_snapshot`,
  ADD KEY `idx_package_id` (`package_id`);

ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_package` FOREIGN KEY (`package_id`) REFERENCES `packages` (`package_id`) ON DELETE SET NULL;