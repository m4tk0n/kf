-- install/sql/tables/60_bands.sql
DROP TABLE IF EXISTS `{prefix}bands`;
CREATE TABLE `{prefix}bands` (
  `id` MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `city_id` SMALLINT UNSIGNED NOT NULL,
  `genre_id` SMALLINT UNSIGNED NULL DEFAULT NULL,
  `logo` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_by` MEDIUMINT UNSIGNED NULL DEFAULT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_city` (`city_id`),
  KEY `idx_genre` (`genre_id`),
  KEY `idx_name` (`name`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `{prefix}fk_bands_city` FOREIGN KEY (`city_id`) REFERENCES `{prefix}cities` (`id`),
  CONSTRAINT `{prefix}fk_bands_genre` FOREIGN KEY (`genre_id`) REFERENCES `{prefix}genres` (`id`) ON DELETE SET NULL,
  CONSTRAINT `{prefix}fk_bands_user` FOREIGN KEY (`created_by`) REFERENCES `{prefix}users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collation};
