-- install/sql/tables/85_events_tour.sql
-- Společný záznam turné; jednotlivé zastávky jsou samostatné záznamy v events.
DROP TABLE IF EXISTS `{prefix}events_tour`;
CREATE TABLE `{prefix}events_tour` (
  `id` MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(160) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collation};
