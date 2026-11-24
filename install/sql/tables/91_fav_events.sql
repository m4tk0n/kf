-- install/sql/tables/91_fav_events.sql
DROP TABLE IF EXISTS `{prefix}fav_events`;
CREATE TABLE `{prefix}fav_events` (
  `user_id` MEDIUMINT UNSIGNED NOT NULL,
  `id` MEDIUMINT UNSIGNED NOT NULL,
  PRIMARY KEY (`user_id`, `id`),
  KEY `idx_id` (`id`),
  CONSTRAINT `{prefix}fk_fav_events_user` FOREIGN KEY (`user_id`) REFERENCES `{prefix}users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `{prefix}fk_fav_events_event` FOREIGN KEY (`id`) REFERENCES `{prefix}events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collation};
