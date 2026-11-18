-- Authentication migration for existing K&F installations.
-- The installer removes users.pass only after both tables have been verified.
CREATE TABLE IF NOT EXISTS `{p}user_identities` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `provider` VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `provider_id` VARCHAR(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `provider_identity` (`provider`, `provider_id`),
  UNIQUE KEY `user_provider` (`user_id`, `provider`),
  CONSTRAINT `fk_{p}identity_user` FOREIGN KEY (`user_id`)
    REFERENCES `{p}users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{p}sessions` (
  `id_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `user_id` INT UNSIGNED NULL,
  `payload` MEDIUMBLOB NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_activity` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME NOT NULL,
  PRIMARY KEY (`id_hash`),
  KEY `user_id` (`user_id`),
  KEY `expires_at` (`expires_at`),
  CONSTRAINT `fk_{p}session_user` FOREIGN KEY (`user_id`)
    REFERENCES `{p}users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
