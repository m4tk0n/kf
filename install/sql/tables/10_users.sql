-- install/sql/tables/10_users.sql
DROP TABLE IF EXISTS `{prefix}users`;
CREATE TABLE `{prefix}users` (
  `id` MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider` TINYINT UNSIGNED NOT NULL,
  `provider_uid` VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `display_name` VARCHAR(80) NOT NULL,
  `role` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `user_state` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `approved_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` INT UNSIGNED NOT NULL,
  `last_login_at` INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_provider_uid` (`provider`, `provider_uid`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collation};
