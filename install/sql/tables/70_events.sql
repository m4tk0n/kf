-- install/sql/tables/70_events.sql
DROP TABLE IF EXISTS `{prefix}events`;
CREATE TABLE `{prefix}events` (
  `id` MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(160) NOT NULL,
  `type` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `date_from` DATE NOT NULL,
  `date_to` DATE NOT NULL,
  `time_start` TIME NULL DEFAULT NULL,
  `city_id` SMALLINT UNSIGNED NOT NULL,
  `venue_id` SMALLINT UNSIGNED NULL DEFAULT NULL,
  `flyer` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_by` MEDIUMINT UNSIGNED NULL DEFAULT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_dates` (`date_from`, `date_to`),
  KEY `idx_date_to` (`date_to`),
  KEY `idx_city` (`city_id`),
  KEY `idx_venue` (`venue_id`, `date_from`),
  KEY `idx_name` (`name`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `{prefix}fk_events_city` FOREIGN KEY (`city_id`) REFERENCES `{prefix}cities` (`id`),
  CONSTRAINT `{prefix}fk_events_venue` FOREIGN KEY (`venue_id`) REFERENCES `{prefix}venues` (`id`) ON DELETE SET NULL,
  CONSTRAINT `{prefix}fk_events_user` FOREIGN KEY (`created_by`) REFERENCES `{prefix}users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collation};
