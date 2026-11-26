-- install/sql/tables/50_venues.sql
DROP TABLE IF EXISTS `{prefix}venues`;
CREATE TABLE `{prefix}venues` (
  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `city_id` SMALLINT UNSIGNED NOT NULL,
  `address` VARCHAR(255) NULL DEFAULT NULL,
  `img` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_by` MEDIUMINT UNSIGNED NULL DEFAULT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_city` (`city_id`),
  KEY `idx_name` (`name`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `{prefix}fk_venues_city` FOREIGN KEY (`city_id`) REFERENCES `{prefix}cities` (`id`),
  CONSTRAINT `{prefix}fk_venues_user` FOREIGN KEY (`created_by`) REFERENCES `{prefix}users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collation};
