-- install/sql/tables/15_sessions.sql
DROP TABLE IF EXISTS `{prefix}sessions`;
CREATE TABLE `{prefix}sessions` (
  `token_hash` BINARY(32) NOT NULL,
  `user_id` MEDIUMINT UNSIGNED NOT NULL,
  `ua_hash` BINARY(8) NOT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  `last_seen_at` INT UNSIGNED NOT NULL,
  `expires_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`token_hash`),
  KEY `idx_user` (`user_id`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `{prefix}fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `{prefix}users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collation};
