-- install/sql/tables/93_fav_venues.sql
DROP TABLE IF EXISTS `{prefix}fav_venues`;
CREATE TABLE `{prefix}fav_venues` (
  `user_id` MEDIUMINT UNSIGNED NOT NULL,
  `id` SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (`user_id`, `id`),
  KEY `idx_id` (`id`),
  CONSTRAINT `{prefix}fk_fav_venues_user` FOREIGN KEY (`user_id`) REFERENCES `{prefix}users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `{prefix}fk_fav_venues_venue` FOREIGN KEY (`id`) REFERENCES `{prefix}venues` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collation};
