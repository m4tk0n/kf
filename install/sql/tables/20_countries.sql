-- install/sql/tables/20_countries.sql
DROP TABLE IF EXISTS `{prefix}countries`;
CREATE TABLE `{prefix}countries` (
  `id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `iso2` CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `iso3` CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `name_cs` VARCHAR(80) NOT NULL,
  `name_en` VARCHAR(80) NOT NULL,
  `phone_prefix` VARCHAR(12) NULL DEFAULT NULL,
  `currency_code` CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_iso2` (`iso2`),
  UNIQUE KEY `uq_iso3` (`iso3`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collation};
