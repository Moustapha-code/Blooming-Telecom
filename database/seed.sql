-- =====================================================================
-- Blooming FTTH Dashboard - Demo Seed Data
-- =====================================================================
-- Fictional data for local development / demo only.
-- Login: admin / password
--
-- Usage (after schema.sql):
--   mysql -u root -p bloowing_db < seed.sql
-- =====================================================================

USE `bloowing_db`;

-- Admin user (password = "password", bcrypt)
INSERT INTO `admin_users` (`username`, `password`) VALUES
('admin', '$2y$10$ZSS5sXC3DRdRycYLA1QbK.dqVf.sUi0iXJPBpCnbsSmaCcpr0ti/e');

-- Zones
INSERT INTO `zones` (`zone_name`) VALUES
('TZ2Z03'), ('TZ2Z04'), ('TZ2Z06'), ('TZ2Z11'),
('TZ3Z03'), ('MN1Z01'), ('MN1Z02'), ('MN1Z03');

-- Technicians (fictional)
INSERT INTO `technicians` (`name`, `phone`, `email`, `specialty`, `zone`) VALUES
('Tech Demo 1', '20000001', 'tech1@example.com', 'Fiber Splicing', 'TZ'),
('Tech Demo 2', '20000002', 'tech2@example.com', 'Technical Support', 'MIN'),
('Tech Demo 3', '20000003', 'tech3@example.com', '', 'Tunis Sud');

-- Drivers (fictional)
INSERT INTO `driver` (`name`, `phone`, `license_number`, `notes`) VALUES
('Driver Demo 1', '30000001', NULL, NULL),
('Driver Demo 2', '30000002', NULL, NULL);

-- Cars (fictional plates)
INSERT INTO `car` (`matricule`, `model`, `brand`, `driver_id`, `notes`) VALUES
('0000 AA 01', 'Kangoo', 'Renault', 1, NULL),
('0000 AA 02', 'Partner', 'Peugeot', 2, NULL);

-- Car maintenance history
INSERT INTO `car_maintenance` (`car_id`, `date_maintenance`, `description`, `cost`) VALUES
(1, '2026-01-10', 'Vidange et filtres', 1500.00),
(2, '2026-01-15', 'Pneus avant', 3200.00);

-- Materials
INSERT INTO `materials` (`name`, `description`, `unit`, `stock_quantity`) VALUES
('Cable Drop 50',  'Drop cable 50m',  'pieces', 60),
('Cable Drop 100', 'Drop cable 100m', 'pieces', 120),
('ONT Router',     'Client ONT unit', 'pieces', 8),
('Connecteur SC',  'SC/APC fast connector', 'pieces', 200);

-- Installations / OT (fictional client numbers and GEPON ids)
INSERT INTO `installations`
(`date_intervention`, `numero_client`, `zone`, `Gepon`, `etat`, `date_realise`, `nature_ot`, `technician_id`, `temp_de_venir`, `temp_de_realise`) VALUES
('2026-01-05', 10000001, 'TZ2Z06', '11111', 'realise', '2026-01-05', 'DRG', 1, '08:35:00', '10:00:00'),
('2026-01-05', 10000002, 'TZ2Z03', '22222', 'realise', '2026-01-06', 'CPL', 2, '09:10:00', '11:30:00'),
('2026-01-06', 10000003, 'MN1Z01', '33333', 'retard',  '2026-01-08', 'DRG', 1, '09:25:00', '14:00:00'),
('2026-01-06', 10000004, 'TZ3Z03', '44444', 'realise', '2026-01-06', 'CST', 3, '10:37:00', '12:15:00'),
('2026-01-07', 10000005, 'TZ2Z11', '55555', 'encoure', NULL,         'CPL', NULL, NULL, NULL),
('2026-01-07', 10000006, 'MN1Z02', '66666', 'negative', NULL,        'DRG', 2, '11:42:00', NULL),
('2026-01-08', 10000007, 'TZ2Z04', '77777', 'encoure', NULL,         'TRL', NULL, NULL, NULL),
('2026-01-08', 10000008, 'MN1Z03', '88888', 'realise', '2026-01-08', 'CPL', 3, '12:28:00', '15:40:00');

-- Attendance
INSERT INTO `attendance` (`technician_id`, `date`, `check_in_time`, `check_out_time`, `status`, `notes`) VALUES
(1, '2026-01-05', '08:00:00', '17:00:00', 'Present', NULL),
(2, '2026-01-05', '08:15:00', '17:05:00', 'Present', NULL),
(3, '2026-01-05', NULL, NULL, 'Absent', 'Sick leave'),
(1, '2026-01-06', '08:05:00', '17:00:00', 'Present', NULL),
(2, '2026-01-06', '08:00:00', '16:45:00', 'Present', NULL);

-- Technician materials (issue / return)
INSERT INTO `technician_materials`
(`technician_id`, `material_id`, `date_given`, `quantity_given`, `quantity_returned`, `notes`, `car_id`, `zone`) VALUES
(1, 1, '2026-01-05', 2, 1, '', 1, 'TZ'),
(1, 3, '2026-01-05', 1, 0, '', 1, 'TZ'),
(2, 2, '2026-01-06', 2, 2, '', 2, 'MIN'),
(3, 4, '2026-01-06', 10, 4, '', NULL, 'Tunis Sud');
