-- Schéma K&F. {p} se při instalaci nahradí hodnotou TABLE_PREFIX.

CREATE TABLE IF NOT EXISTS `{p}countries` (
  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `iso2` CHAR(2) NOT NULL,
  `iso3` CHAR(3) NOT NULL,
  `cs` VARCHAR(80) NOT NULL,
  `en` VARCHAR(80) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `iso2` (`iso2`),
  UNIQUE KEY `iso3` (`iso3`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{p}cities` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `country_id` SMALLINT UNSIGNED NOT NULL,
  `slug` VARCHAR(80) NOT NULL,
  `cs` VARCHAR(80) NOT NULL,
  `en` VARCHAR(80) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `country_slug` (`country_id`, `slug`),
  KEY `cs` (`cs`),
  KEY `en` (`en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{p}genres` (
  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(60) NOT NULL,
  `slug` VARCHAR(60) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{p}venues` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `city_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `city_id` (`city_id`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{p}bands` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `country_id` SMALLINT UNSIGNED NOT NULL,
  `slug` VARCHAR(80) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `img` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `name` (`name`),
  KEY `country_id` (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{p}band_genres` (
  `band_id` INT UNSIGNED NOT NULL,
  `genre_id` SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (`band_id`, `genre_id`),
  KEY `genre_band` (`genre_id`, `band_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{p}events` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `venue_id` INT UNSIGNED NOT NULL,
  `type` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `slug` VARCHAR(140) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `starts_at` DATETIME NOT NULL,
  `ends_at` DATETIME NULL,
  `img` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `starts_at` (`starts_at`),
  KEY `venue_starts` (`venue_id`, `starts_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{p}event_bands` (
  `event_id` INT UNSIGNED NOT NULL,
  `band_id` INT UNSIGNED NOT NULL,
  `pos` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`event_id`, `band_id`),
  KEY `band_event` (`band_id`, `event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{p}users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(190) NOT NULL,
  `display_name` VARCHAR(80) NULL,
  `role` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `state` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `confirmed` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `prefs` JSON NULL,
  `deletion_scheduled_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `{p}pending` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` TINYINT UNSIGNED NOT NULL,
  `dupkey` VARCHAR(160) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(120) NULL,
  `country_id` SMALLINT UNSIGNED NULL,
  `city` VARCHAR(100) NULL,
  `payload` JSON NOT NULL,
  PRIMARY KEY (`id`),
  KEY `type_key` (`type`, `dupkey`),
  KEY `user_id` (`user_id`),
  KEY `country_city` (`country_id`, `city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
