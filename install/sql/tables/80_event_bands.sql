-- install/sql/tables/80_event_bands.sql
DROP TABLE IF EXISTS `{prefix}event_bands`;
CREATE TABLE `{prefix}event_bands` (
  `event_id` MEDIUMINT UNSIGNED NOT NULL,
  `band_id` MEDIUMINT UNSIGNED NOT NULL,
  `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `time_play` TIME NULL DEFAULT NULL,
  PRIMARY KEY (`event_id`, `band_id`),
  KEY `idx_band` (`band_id`),
  CONSTRAINT `{prefix}fk_eb_event` FOREIGN KEY (`event_id`) REFERENCES `{prefix}events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `{prefix}fk_eb_band` FOREIGN KEY (`band_id`) REFERENCES `{prefix}bands` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collation};
