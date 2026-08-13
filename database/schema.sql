-- =====================================================================
-- Blooming FTTH Dashboard - Database Schema
-- =====================================================================
-- Structure only, no data. For demo data, see seed.sql.
--
-- Usage:
--   mysql -u root -p < schema.sql
--   mysql -u root -p bloowing_db < seed.sql   (optional demo data)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `bloowing_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE `bloowing_db`;

-- ---------------------------------------------------------------------
-- Admin users (dashboard login)
-- ---------------------------------------------------------------------
CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- Technicians (field workers)
-- ---------------------------------------------------------------------
CREATE TABLE `technicians` (
  `technician_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `specialty` varchar(50) DEFAULT NULL,
  `zone` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`technician_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- Zones (service areas, lookup)
-- ---------------------------------------------------------------------
CREATE TABLE `zones` (
  `zone_id` int(11) NOT NULL AUTO_INCREMENT,
  `zone_name` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`zone_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- Drivers (fleet)
-- ---------------------------------------------------------------------
CREATE TABLE `driver` (
  `driver_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `license_number` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`driver_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- Cars (fleet)
-- ---------------------------------------------------------------------
CREATE TABLE `car` (
  `car_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `matricule` varchar(50) NOT NULL,
  `model` varchar(100) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `driver_id` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`car_id`),
  UNIQUE KEY `matricule` (`matricule`),
  KEY `driver_id` (`driver_id`),
  CONSTRAINT `car_ibfk_1` FOREIGN KEY (`driver_id`) REFERENCES `driver` (`driver_id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- Car maintenance history
-- ---------------------------------------------------------------------
CREATE TABLE `car_maintenance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `car_id` int(10) UNSIGNED NOT NULL,
  `date_maintenance` date NOT NULL,
  `description` text DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `car_id` (`car_id`),
  CONSTRAINT `car_maintenance_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `car` (`car_id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- Installations / OT (work orders) - core table
--   etat: 'encoure' (in progress), 'realise' (done),
--         'retard' (late), 'negative' (failed)
-- ---------------------------------------------------------------------
CREATE TABLE `installations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date_intervention` date DEFAULT NULL,
  `nom` varchar(255) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `numero_client` int(11) DEFAULT NULL,
  `port` int(11) DEFAULT NULL,
  `zone` varchar(100) NOT NULL,
  `Gepon` varchar(50) DEFAULT NULL,
  `scan` varchar(255) DEFAULT NULL,
  `pdf_file` varchar(255) DEFAULT NULL,
  `etat` varchar(20) NOT NULL DEFAULT 'encoure',
  `date_realise` date DEFAULT NULL,
  `nature_ot` varchar(100) NOT NULL,
  `technician_id` int(11) DEFAULT NULL,
  `debut` datetime DEFAULT NULL,
  `temp_de_venir` time DEFAULT NULL,
  `commentaire_temp_de_venir` text DEFAULT NULL,
  `temp_de_affectation` datetime DEFAULT NULL,
  `commentaire_temp_de_affectation` text DEFAULT NULL,
  `temp_de_realise` time DEFAULT NULL,
  `commentaire_temp_de_realise` text DEFAULT NULL,
  `temp_de_cloture` time DEFAULT NULL,
  `commentaire_temp_de_cloture` text DEFAULT NULL,
  `due_tempt` datetime DEFAULT NULL,
  `commentaire_due_tempt` text DEFAULT NULL,
  `cloturer_par` varchar(255) DEFAULT NULL,
  `date_de_cloture` date DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `technician_id` (`technician_id`),
  KEY `idx_date_intervention` (`date_intervention`),
  KEY `idx_etat` (`etat`),
  KEY `idx_zone` (`zone`),
  KEY `idx_nature_ot` (`nature_ot`),
  KEY `idx_numero_client` (`numero_client`),
  CONSTRAINT `installations_ibfk_1` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`technician_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- Attendance (technician check-in / check-out)
-- ---------------------------------------------------------------------
CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL AUTO_INCREMENT,
  `technician_id` int(11) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`attendance_id`),
  KEY `technician_id` (`technician_id`),
  KEY `idx_date` (`date`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`technician_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- Materials (stock inventory)
-- ---------------------------------------------------------------------
CREATE TABLE `materials` (
  `material_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  PRIMARY KEY (`material_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- Technician materials (issue / return tracking)
-- ---------------------------------------------------------------------
CREATE TABLE `technician_materials` (
  `usage_id` int(11) NOT NULL AUTO_INCREMENT,
  `technician_id` int(11) DEFAULT NULL,
  `material_id` int(11) DEFAULT NULL,
  `date_given` date DEFAULT NULL,
  `quantity_given` int(11) DEFAULT NULL,
  `quantity_returned` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `car_id` int(10) UNSIGNED DEFAULT NULL,
  `zone` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`usage_id`),
  KEY `technician_id` (`technician_id`),
  KEY `material_id` (`material_id`),
  KEY `fk_car` (`car_id`),
  CONSTRAINT `technician_materials_ibfk_1` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`technician_id`),
  CONSTRAINT `technician_materials_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `materials` (`material_id`),
  CONSTRAINT `fk_car` FOREIGN KEY (`car_id`) REFERENCES `car` (`car_id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- Triggers on installations
-- ---------------------------------------------------------------------
DELIMITER $$

-- Default the status to 'encoure' when missing on insert
CREATE TRIGGER `trg_installations_before_insert`
BEFORE INSERT ON `installations` FOR EACH ROW
BEGIN
  IF NEW.etat IS NULL OR NEW.etat = '' THEN
    SET NEW.etat = 'encoure';
  END IF;
END$$

-- Stamp closure date/time when a work order leaves 'encoure'
CREATE TRIGGER `trg_update_cloture_on_status_change`
BEFORE UPDATE ON `installations` FOR EACH ROW
BEGIN
    IF OLD.etat = 'encoure' AND NEW.etat <> 'encoure' THEN
        IF NEW.etat = 'retard' THEN
            SET NEW.date_de_cloture = NEW.date_realise;
            SET NEW.temp_de_cloture = NEW.temp_de_realise;
        ELSE
            SET NEW.date_de_cloture = CURDATE();
            SET NEW.temp_de_cloture = CURTIME();
        END IF;
    END IF;
END$$

DELIMITER ;
