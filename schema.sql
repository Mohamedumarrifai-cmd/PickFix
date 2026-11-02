-- ==============================================================================
-- PickFix Expanded Database Schema
-- Sri Lanka's On-Demand Local Services, Garage Mechanics & 3D CAD Marketplace
-- ==============================================================================

CREATE DATABASE IF NOT EXISTS `pickfix_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `pickfix_db`;

-- Drop existing tables if re-initializing (in reverse order of foreign keys)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `transactions`;
DROP TABLE IF EXISTS `reviews`;
DROP TABLE IF EXISTS `messages`;
DROP TABLE IF EXISTS `bookings`;
DROP TABLE IF EXISTS `providers`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------------------------
-- 1. Users Table
-- ------------------------------------------------------------------------------
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `phone` VARCHAR(20) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('customer', 'provider') NOT NULL DEFAULT 'customer',
    `avatar_url` VARCHAR(255) NULL DEFAULT 'assets/images/default-avatar.png',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_email` (`email`),
    INDEX `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 2. Service Providers / Garage Mechanics / 3D Designers Profile Table
-- ------------------------------------------------------------------------------
CREATE TABLE `providers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `workshop_name` VARCHAR(150) NULL COMMENT 'Registered garage, workshop, or studio name',
    `category` VARCHAR(50) NOT NULL COMMENT 'plumber, electrician, mechanic, ac_repair, 3d_design, emergency',
    `sub_category` VARCHAR(120) NULL COMMENT 'e.g. Hybrid Diagnostics, Engine Repair, Bodywork, Emergency Towing',
    `engine_types` VARCHAR(120) NULL DEFAULT 'Petrol, Diesel, Hybrid/EV' COMMENT 'Supported engine types for mechanics',
    `specialties` VARCHAR(255) NULL COMMENT 'Comma-separated keywords for instant search',
    `towing_available` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Flatbed/wheel-lift towing truck available',
    `nic_number` VARCHAR(20) NOT NULL COMMENT 'Sri Lankan NIC format (e.g. 199012345678 or 901234567V)',
    `is_verified` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = Verified with Sri Lankan NIC & Business Registration, 0 = Pending',
    `is_online` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = Online / Available for Job Radar, 0 = Offline',
    `current_lat` DECIMAL(10, 8) NOT NULL DEFAULT 6.92710000 COMMENT 'Current latitude (Default Colombo)',
    `current_lng` DECIMAL(11, 8) NOT NULL DEFAULT 79.86120000 COMMENT 'Current longitude (Default Colombo)',
    `city` VARCHAR(100) NOT NULL DEFAULT 'Colombo',
    `rating` DECIMAL(3, 2) NOT NULL DEFAULT 4.80,
    `total_jobs` INT NOT NULL DEFAULT 0,
    `hourly_rate` DECIMAL(10, 2) NOT NULL DEFAULT 2500.00 COMMENT 'Base service or hourly rate in LKR',
    `bio` TEXT NULL,
    `portfolio_url` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_providers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_providers_category` (`category`),
    INDEX `idx_providers_online` (`is_online`),
    INDEX `idx_providers_verified` (`is_verified`),
    INDEX `idx_providers_towing` (`towing_available`),
    INDEX `idx_providers_location` (`current_lat`, `current_lng`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 3. Bookings & 3D Project Deliverables Table
-- ------------------------------------------------------------------------------
CREATE TABLE `bookings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `booking_code` VARCHAR(30) NOT NULL UNIQUE COMMENT 'Human-friendly reference e.g. PF-20261102-1234',
    `customer_id` INT NOT NULL,
    `provider_id` INT NULL COMMENT 'Assigned provider or NULL if pending broadcast',
    `service_type` VARCHAR(50) NOT NULL COMMENT 'plumber, electrician, mechanic, ac_repair, 3d_design, emergency',
    `sub_category` VARCHAR(120) NULL,
    `project_title` VARCHAR(200) NULL,
    `project_notes` TEXT NULL,
    `reference_file_path` VARCHAR(255) NULL COMMENT 'Customer-uploaded reference spec/sketch/PDF',
    `delivered_file_path` VARCHAR(255) NULL COMMENT 'Designer-uploaded deliverable render/STL/ZIP',
    `delivery_notes` TEXT NULL COMMENT 'Provider notes regarding completed work',
    `status` ENUM('pending', 'accepted', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    `is_emergency` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = High priority SOS dispatch',
    `customer_lat` DECIMAL(10, 8) NULL,
    `customer_lng` DECIMAL(11, 8) NULL,
    `customer_address` VARCHAR(255) NULL,
    `budget` DECIMAL(10, 2) NULL COMMENT 'Agreed price or estimated budget in LKR',
    `payment_status` ENUM('unpaid', 'pending', 'paid') NOT NULL DEFAULT 'unpaid',
    `paid_amount` DECIMAL(10, 2) NULL,
    `payment_method` VARCHAR(50) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_bookings_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bookings_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE SET NULL,
    INDEX `idx_bookings_status` (`status`),
    INDEX `idx_bookings_service` (`service_type`),
    INDEX `idx_bookings_emergency` (`is_emergency`),
    INDEX `idx_bookings_payment` (`payment_status`),
    INDEX `idx_bookings_customer` (`customer_id`),
    INDEX `idx_bookings_provider` (`provider_id`),
    INDEX `idx_bookings_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 4. Real-Time Customer-Provider Messages Table (Chat)
-- ------------------------------------------------------------------------------
CREATE TABLE `messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `booking_id` INT NOT NULL,
    `sender_id` INT NOT NULL,
    `receiver_id` INT NOT NULL,
    `message_text` TEXT NOT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_messages_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_messages_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_messages_booking` (`booking_id`),
    INDEX `idx_messages_sender` (`sender_id`),
    INDEX `idx_messages_receiver` (`receiver_id`),
    INDEX `idx_messages_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 5. Customer Reviews & 5-Star Ratings Table
-- ------------------------------------------------------------------------------
CREATE TABLE `reviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `booking_id` INT NOT NULL UNIQUE,
    `customer_id` INT NOT NULL,
    `provider_id` INT NOT NULL,
    `rating` TINYINT NOT NULL COMMENT '1 to 5 stars',
    `comment` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_reviews_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reviews_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reviews_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE,
    INDEX `idx_reviews_provider` (`provider_id`),
    INDEX `idx_reviews_rating` (`rating`),
    INDEX `idx_reviews_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 6. Transactions & Platform Monetization Table (PayHere / Genie / Cash)
-- ------------------------------------------------------------------------------
CREATE TABLE `transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `booking_id` INT NOT NULL,
    `customer_id` INT NOT NULL,
    `provider_id` INT NOT NULL,
    `amount_lkr` DECIMAL(10, 2) NOT NULL COMMENT 'Total amount paid by client in LKR',
    `platform_fee_lkr` DECIMAL(10, 2) NOT NULL COMMENT '10% Platform commission retained by PickFix',
    `provider_payout_lkr` DECIMAL(10, 2) NOT NULL COMMENT '90% Net revenue credited to provider',
    `payment_gateway` ENUM('payhere', 'genie', 'cash') NOT NULL DEFAULT 'payhere',
    `status` ENUM('success', 'pending', 'failed') NOT NULL DEFAULT 'success',
    `transaction_ref` VARCHAR(60) NOT NULL UNIQUE,
    `payment_details` VARCHAR(255) NULL COMMENT 'Masked card, FriMi/Genie mobile number, or Cash receipt note',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_transactions_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_transactions_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_transactions_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE,
    INDEX `idx_transactions_booking` (`booking_id`),
    INDEX `idx_transactions_customer` (`customer_id`),
    INDEX `idx_transactions_provider` (`provider_id`),
    INDEX `idx_transactions_status` (`status`),
    INDEX `idx_transactions_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Sample Demonstration Seed Data (Password for all test users: password123)
-- Hash: $2y$10$bg7xw/n0.CnZkOBaNrTSweUX3Swzx/4WqrAKCjsA8SKxOQwDF/XGG
-- ------------------------------------------------------------------------------

-- Seed Users
INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password_hash`, `role`) VALUES
(1, 'Kavinda Perera', 'customer@pickfix.lk', '+94 77 123 4567', '$2y$10$bg7xw/n0.CnZkOBaNrTSweUX3Swzx/4WqrAKCjsA8SKxOQwDF/XGG', 'customer'),
(2, 'Kasun Jayasuriya', 'kasun.plumber@pickfix.lk', '+94 71 987 6543', '$2y$10$bg7xw/n0.CnZkOBaNrTSweUX3Swzx/4WqrAKCjsA8SKxOQwDF/XGG', 'provider'),
(3, 'Nimal Fernando', 'nimal.electrician@pickfix.lk', '+94 76 345 6789', '$2y$10$bg7xw/n0.CnZkOBaNrTSweUX3Swzx/4WqrAKCjsA8SKxOQwDF/XGG', 'provider'),
(4, 'Roshan Silva', 'roshan.3d@pickfix.lk', '+94 75 678 9012', '$2y$10$bg7xw/n0.CnZkOBaNrTSweUX3Swzx/4WqrAKCjsA8SKxOQwDF/XGG', 'provider'),
(5, 'Saman Kumara', 'saman.mechanic@pickfix.lk', '+94 78 456 1230', '$2y$10$bg7xw/n0.CnZkOBaNrTSweUX3Swzx/4WqrAKCjsA8SKxOQwDF/XGG', 'provider'),
(6, 'Dinesh Weerasinghe', 'dinesh.ac@pickfix.lk', '+94 72 112 2334', '$2y$10$bg7xw/n0.CnZkOBaNrTSweUX3Swzx/4WqrAKCjsA8SKxOQwDF/XGG', 'provider'),
(7, 'Priyantha Jayakody', 'priyantha.hybrid@pickfix.lk', '+94 77 889 9001', '$2y$10$bg7xw/n0.CnZkOBaNrTSweUX3Swzx/4WqrAKCjsA8SKxOQwDF/XGG', 'provider'),
(8, 'Sunil Wickramasinghe', 'sunil.towing@pickfix.lk', '+94 71 223 3445', '$2y$10$bg7xw/n0.CnZkOBaNrTSweUX3Swzx/4WqrAKCjsA8SKxOQwDF/XGG', 'provider'),
(9, 'Mohamed Rauf', 'rauf.tuning@pickfix.lk', '+94 76 554 4332', '$2y$10$bg7xw/n0.CnZkOBaNrTSweUX3Swzx/4WqrAKCjsA8SKxOQwDF/XGG', 'provider');

-- Seed Provider Profiles (Expanded with Garage Mechanics & Specialized Workshops)
INSERT INTO `providers` (`id`, `user_id`, `workshop_name`, `category`, `sub_category`, `engine_types`, `specialties`, `towing_available`, `nic_number`, `is_verified`, `is_online`, `current_lat`, `current_lng`, `city`, `rating`, `total_jobs`, `hourly_rate`, `bio`) VALUES
(1, 2, 'Lanka Quick Flow Plumbing', 'plumber', 'Domestic & Commercial High-Pressure Plumbing', 'N/A', 'Leak detection, Water pump, Burst pipes, Bathroom fitting', 0, '198912345678', 1, 1, 6.92710000, 79.86120000, 'Colombo 01 - Fort', 4.90, 48, 2500.00, 'Licensed master plumber with 10+ years experience in Colombo and suburbs. Specialist in leak detection, water pump installations, and emergency burst pipes.'),
(2, 3, 'ElectroTech Power Solutions', 'electrician', 'Industrial Wiring & Solar Inverter Systems', 'N/A', 'Solar Inverter, Short Circuit, CEB Meter, 3-Phase Industrial', 0, '198523456789', 1, 1, 6.87220000, 79.88280000, 'Nugegoda', 4.80, 62, 3000.00, 'Certified electrical technician (CEB compliant). Solar panel maintenance, main distribution panels, surge protection, and smart home lighting.'),
(3, 4, 'Colombo 3D Prototyping & CAD Lab', '3d_design', 'Architectural BIM, Photorealistic CGI & SolidWorks Prototyping', 'N/A', 'SolidWorks, Blender 4K, Watertight STL, 3D Print Prototyping, Revit BIM', 0, '199234567890', 1, 1, 6.90340000, 79.86460000, 'Colombo 07 - Cinnamon Gardens', 5.00, 89, 5500.00, 'Senior CAD engineer & 3D visualization specialist. Proficient in 3ds Max, Blender, Rhino, SolidWorks, and AutoCAD. Rapid 3D printing STL generation and architectural renders for overseas and local developers.'),
(4, 5, 'Saman Mobile Auto Rescue', 'mechanic', 'Roadside Vehicle Breakdown & Battery Jump', 'Petrol, Diesel, Hybrid/EV', 'Battery jump start, Alternator fix, Tire puncture, On-site repair', 0, '198745678901', 1, 1, 6.85110000, 79.86590000, 'Dehiwala', 4.75, 74, 3500.00, 'Mobile mechanic with equipped service van. Battery jump-starts, roadside alternator replacement, computerized ECU scanning, and emergency tire changes.'),
(5, 6, 'CoolBreeze Inverter AC Care', 'ac_repair', 'Inverter AC Chemical Wash & Gas Refill', 'N/A', 'Inverter PCB, R32 Gas, R410A Refrigerant, Chemical Wash', 0, '199056789012', 1, 1, 6.91470000, 79.87780000, 'Borella', 4.85, 39, 2800.00, 'Specialized in LG, Panasonic, and Daikin Inverter split units. PCB troubleshooting, antibacterial chemical cleaning, and R32/R410A refrigerant top-up.'),
(6, 7, 'Apex Hybrid Auto Clinic & EV Tech', 'mechanic', 'Hybrid Battery Diagnostics & Inverter Overhaul', 'Hybrid/EV, Petrol', 'Hybrid Battery Cell Balancing, Inverter Water Pump, ABS Actuator, Toyota/Honda Diagnostics', 1, '198811223344', 1, 1, 6.89050000, 79.87350000, 'Colombo 05 - Havelock Town', 4.95, 112, 4500.00, 'Premier Hybrid & Electric vehicle diagnostics center in Colombo. OEM scanners for Toyota Prius, Aqua, Vezel, Nissan Leaf. High-voltage battery pack reconditioning, cell replacement, and electronic inverter diagnostics.'),
(7, 8, 'Lanka Heavy Recovery & 24/7 Towing', 'mechanic', 'Emergency Towing, Bodywork & Heavy Recovery', 'Diesel, Petrol, Hybrid/EV', 'Flatbed Towing Truck, Highway Recovery, Wheel-Lift, Accident Bodywork, Frame Straightening', 1, '198422334455', 1, 1, 6.95400000, 79.91200000, 'Kelaniya / Expressway Junction', 4.90, 94, 5000.00, 'Fleet of hydraulic flatbed recovery trucks. 24/7 islandwide emergency vehicle transport, Southern & Central expressway recovery, accident insurance estimates, and professional denting/painting.'),
(8, 9, 'Precision Motor Works & ECU Tuning', 'mechanic', 'Engine Repair, Common Rail Diesel & ECU Remap', 'Diesel, Petrol', 'Common Rail Diesel, Turbo Overhaul, ECU Tuning, Gearbox Rebuild, Smoke Test', 0, '199133445566', 1, 1, 6.86500000, 79.86800000, 'Mount Lavinia / Dehiwala', 4.80, 68, 3800.00, 'Specialist in European and Japanese engine overhauls, high-pressure common rail diesel injectors (CRDI), turbocharger servicing, and computerized OBD2 diagnostic dyno mapping.');

-- Seed Initial Bookings
INSERT INTO `bookings` (`id`, `booking_code`, `customer_id`, `provider_id`, `service_type`, `sub_category`, `project_title`, `project_notes`, `reference_file_path`, `delivered_file_path`, `delivery_notes`, `status`, `is_emergency`, `customer_lat`, `customer_lng`, `customer_address`, `budget`, `payment_status`, `paid_amount`, `payment_method`, `created_at`) VALUES
-- Emergency SOS Plumber Job (In Progress)
(1, 'PF-SOS-20261102-101', 1, 1, 'plumber', 'Burst Main Water Pipe', 'URGENT: Main pipe burst in kitchen', 'High pressure water flooding under ground floor tiles. Turn off valve is stuck. Need emergency plumber with heavy-duty tools immediately!', NULL, NULL, NULL, 'in_progress', 1, 6.91250000, 79.85800000, 'No. 42, Galle Road, Colombo 03', 4500.00, 'unpaid', NULL, NULL, DATE_SUB(NOW(), INTERVAL 25 MINUTE)),

-- Completed 3D Design & CAD Modeling Job (Paid via PayHere with Deliverable Ready)
(2, 'PF-CAD-20261102-102', 1, 3, '3d_design', 'Interior Rendering & 3D Prototyping', 'Modern Living Room Photorealistic 4K Render & 3D Printable Decor', 'Client requested a contemporary Colombo penthouse interior featuring Teak accents, ambient recessed LED strips, and a customized 3D printable parametric geometric vase.', 'uploads/references/penthouse_floorplan_sketch.jpg', 'uploads/deliverables/penthouse_living_render_final.jpg', 'Completed high-resolution 4K photorealistic render using Blender Cycles. Included color-balanced textures and light bounce calculation. STL 3D printable decor mesh also bundled.', 'completed', 0, 6.90500000, 79.86000000, 'Kollupitiya Luxury Residencies, Colombo 03', 15000.00, 'paid', 15000.00, 'payhere', DATE_SUB(NOW(), INTERVAL 2 DAY)),

-- Completed Hybrid Garage Job (Paid via Genie)
(3, 'PF-MEC-20261102-103', 1, 6, 'mechanic', 'Hybrid Battery Diagnostics & Inverter Overhaul', 'Toyota Aqua Check Hybrid System Warning Light', 'Vehicle was stalling with master warning light. Apex Hybrid conducted full computerized cell health check and balanced modules 4 and 7.', NULL, NULL, 'Replaced degraded module 4, cleaned high-voltage fan ducting, and cleared DTC code P0A80. Hybrid battery efficiency restored to 94%.', 'completed', 0, 6.89000000, 79.87000000, 'Thimbirigasyaya Road, Colombo 05', 8500.00, 'paid', 8500.00, 'genie', DATE_SUB(NOW(), INTERVAL 1 DAY)),

-- Pending 3D Prototyping Request on Radar (Waiting for Designer Acceptance)
(4, 'PF-CAD-20261102-104', 1, NULL, '3d_design', 'Product Prototyping (STL/3D Print)', 'Aerodynamic Drone Propeller Guard & Gimbal Frame', 'Need engineering design for a carbon-reinforced drone cage for agricultural spraying in Kurunegala. Must export watertight STL file ready for BambuLab X1-Carbon print.', 'uploads/references/drone_cage_dimensions.pdf', NULL, NULL, 'pending', 0, 6.92000000, 79.87000000, 'Tech Startup Hub, Colombo 08', 22000.00, 'unpaid', NULL, NULL, DATE_SUB(NOW(), INTERVAL 15 MINUTE));

-- ------------------------------------------------------------------------------
-- Seed Messages (Real-Time Customer-Provider Chat Samples)
-- ------------------------------------------------------------------------------
INSERT INTO `messages` (`id`, `booking_id`, `sender_id`, `receiver_id`, `message_text`, `is_read`, `created_at`) VALUES
(1, 1, 1, 2, 'Water is rising fast under the kitchen tiles, please hurry! Main stopcock is seized.', 1, DATE_SUB(NOW(), INTERVAL 20 MINUTE)),
(2, 1, 2, 1, 'Kasun here! I am in the PickFix rapid response van at Kollupitiya junction. ETA is 5 minutes. Please shut off the master electricity breaker if water reaches walls.', 1, DATE_SUB(NOW(), INTERVAL 18 MINUTE)),
(3, 1, 1, 2, 'Done! Power breaker is down. There is open parking right in front of the house gate.', 0, DATE_SUB(NOW(), INTERVAL 15 MINUTE)),
(4, 3, 1, 7, 'Priyantha, is it safe to drive the car slowly to your workshop in Havelock Town?', 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(5, 3, 7, 1, 'Yes, keep it in ECO mode below 40 km/h. Our bay 2 is cleared and ready for high-voltage diagnostic scanning.', 1, DATE_SUB(NOW(), INTERVAL 1 DAY));

-- ------------------------------------------------------------------------------
-- Seed Customer Reviews & 5-Star Ratings
-- ------------------------------------------------------------------------------
INSERT INTO `reviews` (`id`, `booking_id`, `customer_id`, `provider_id`, `rating`, `comment`, `created_at`) VALUES
(1, 2, 1, 3, 5, 'Exceptional 3D render fidelity! Roshan perfectly visualized our Colombo penthouse and delivered watertight STL 3D printable files that sliced flawlessly on our printer. Worth every rupee.', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 3, 1, 6, 5, 'Apex Hybrid diagnosed my Toyota Aqua within 30 minutes. Clear explanation of battery cell voltages and very honest pricing. Saved me from buying a whole new battery pack!', DATE_SUB(NOW(), INTERVAL 20 HOUR));

-- ------------------------------------------------------------------------------
-- Seed Monetization Transactions (PayHere / Genie / Cash with 10% Platform Fee)
-- ------------------------------------------------------------------------------
INSERT INTO `transactions` (`id`, `booking_id`, `customer_id`, `provider_id`, `amount_lkr`, `platform_fee_lkr`, `provider_payout_lkr`, `payment_gateway`, `status`, `transaction_ref`, `payment_details`, `created_at`) VALUES
(1, 2, 1, 3, 15000.00, 1500.00, 13500.00, 'payhere', 'success', 'TXN-PH-20261101-78912', 'VISA Credit Card (ending 4242)', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 3, 1, 6, 8500.00, 850.00, 7650.00, 'genie', 'success', 'TXN-GEN-20261102-45123', 'Dialog Genie Wallet (077****567)', DATE_SUB(NOW(), INTERVAL 1 DAY));
