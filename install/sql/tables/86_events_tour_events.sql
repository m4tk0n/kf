-- install/sql/tables/86_events_tour_events.sql
-- Přiřazení samostatných akcí (zastávek) k turné.
-- Pořadí zastávek se určuje podle events.date_from; stop_order se proto neukládá.
DROP TABLE IF EXISTS `{prefix}events_tour_events`;
CREATE TABLE `{prefix}events_tour_events` (
  `tour_id` MEDIUMINT UNSIGNED NOT NULL,
  `event_id` MEDIUMINT UNSIGNED NOT NULL,
  PRIMARY KEY (`tour_id`, `event_id`),
  KEY `idx_event` (`event_id`),
  CONSTRAINT `{prefix}fk_ete_tour` FOREIGN KEY (`tour_id`) REFERENCES `{prefix}events_tour` (`id`) ON DELETE CASCADE,
  CONSTRAINT `{prefix}fk_ete_event` FOREIGN KEY (`event_id`) REFERENCES `{prefix}events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET={charset} COLLATE={collation};
