-- Tentatives de connexion échouées, pour le verrouillage anti-force brute.
--
-- Stocké en base et non en session : un attaquant qui supprime son cookie
-- repartirait sinon de zéro à chaque essai.
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`           INT(11)      NOT NULL AUTO_INCREMENT,
    `ip`           VARCHAR(45)  NOT NULL,
    `username`     VARCHAR(100) NOT NULL,
    `attempted_at` DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_ip_time` (`ip`, `attempted_at`),
    KEY `idx_user_time` (`username`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
