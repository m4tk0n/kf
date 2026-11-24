-- install/sql/tables/92_fav_bands.sql
DROP TABLE IF EXISTS `{prefix}fav_bands`;
CREATE TABLE `{prefix}fav_bands` (
  `user_id` MEDIUMINT UNSIGNED NOT NULL,
  `id` MEDIUMINT UNSIGNED NOT NULL,
  PRIMARY KEY (`user_id`, `id`),
  KEY `idx_id` (`id`),
  CONSTRAINT `{prefix}fk_fav_bands_user` FOREIGN KEY (`user_id`) REFERENCES `{prefix}users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `{prefix}fk_fav_bands_band` FOREIGN KEY (`id`) REFERENCES `{prefix}bands` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collation};
