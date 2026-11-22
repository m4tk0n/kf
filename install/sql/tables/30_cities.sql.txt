-- install/sql/tables/30_cities.sql
DROP TABLE IF EXISTS `{prefix}cities`;
CREATE TABLE `{prefix}cities` (
  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `country_id` TINYINT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `name_ascii` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_country` (`country_id`),
  KEY `idx_name_ascii` (`name_ascii`),
  CONSTRAINT `{prefix}fk_cities_country` FOREIGN KEY (`country_id`) REFERENCES `{prefix}countries` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collation};
