-- install/sql/tables/90_pending.sql
DROP TABLE IF EXISTS `{prefix}pending`;
CREATE TABLE `{prefix}pending` (
  `id` MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` TINYINT UNSIGNED NOT NULL,
  `created_by` MEDIUMINT UNSIGNED NOT NULL,
  `title` VARCHAR(160) NOT NULL,
  `name_ascii` VARCHAR(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `date_from` DATE NULL DEFAULT NULL,
  `source_url` VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_type` (`created_by`, `type`),
  KEY `idx_source` (`source_url`),
  KEY `idx_dedupe` (`type`, `name_ascii`, `date_from`),
  CONSTRAINT `{prefix}fk_pending_user` FOREIGN KEY (`created_by`) REFERENCES `{prefix}users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collation};
