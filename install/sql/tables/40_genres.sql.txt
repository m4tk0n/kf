-- install/sql/tables/40_genres.sql
DROP TABLE IF EXISTS `{prefix}genres`;
CREATE TABLE `{prefix}genres` (
  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(60) NOT NULL,
  `name_ascii` VARCHAR(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_name_ascii` (`name_ascii`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collation};
