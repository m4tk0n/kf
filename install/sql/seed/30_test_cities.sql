-- Testovací města pro seed kapel, klubů a akcí.
INSERT INTO `{prefix}cities` (`country_id`, `name`, `name_ascii`)
SELECT c.`id`, 'Brno', 'brno' FROM `{prefix}countries` c
WHERE c.`iso2`='CZ' AND NOT EXISTS (SELECT 1 FROM `{prefix}cities` x WHERE x.`country_id`=c.`id` AND x.`name_ascii`='brno');
INSERT INTO `{prefix}cities` (`country_id`, `name`, `name_ascii`)
SELECT c.`id`, 'Praha', 'praha' FROM `{prefix}countries` c
WHERE c.`iso2`='CZ' AND NOT EXISTS (SELECT 1 FROM `{prefix}cities` x WHERE x.`country_id`=c.`id` AND x.`name_ascii`='praha');
INSERT INTO `{prefix}cities` (`country_id`, `name`, `name_ascii`)
SELECT c.`id`, 'Ostrava', 'ostrava' FROM `{prefix}countries` c
WHERE c.`iso2`='CZ' AND NOT EXISTS (SELECT 1 FROM `{prefix}cities` x WHERE x.`country_id`=c.`id` AND x.`name_ascii`='ostrava');
INSERT INTO `{prefix}cities` (`country_id`, `name`, `name_ascii`)
SELECT c.`id`, 'Plzeň', 'plzen' FROM `{prefix}countries` c
WHERE c.`iso2`='CZ' AND NOT EXISTS (SELECT 1 FROM `{prefix}cities` x WHERE x.`country_id`=c.`id` AND x.`name_ascii`='plzen');
INSERT INTO `{prefix}cities` (`country_id`, `name`, `name_ascii`)
SELECT c.`id`, 'Olomouc', 'olomouc' FROM `{prefix}countries` c
WHERE c.`iso2`='CZ' AND NOT EXISTS (SELECT 1 FROM `{prefix}cities` x WHERE x.`country_id`=c.`id` AND x.`name_ascii`='olomouc');
INSERT INTO `{prefix}cities` (`country_id`, `name`, `name_ascii`)
SELECT c.`id`, 'Liberec', 'liberec' FROM `{prefix}countries` c
WHERE c.`iso2`='CZ' AND NOT EXISTS (SELECT 1 FROM `{prefix}cities` x WHERE x.`country_id`=c.`id` AND x.`name_ascii`='liberec');
INSERT INTO `{prefix}cities` (`country_id`, `name`, `name_ascii`)
SELECT c.`id`, 'České Budějovice', 'ceske budejovice' FROM `{prefix}countries` c
WHERE c.`iso2`='CZ' AND NOT EXISTS (SELECT 1 FROM `{prefix}cities` x WHERE x.`country_id`=c.`id` AND x.`name_ascii`='ceske budejovice');
INSERT INTO `{prefix}cities` (`country_id`, `name`, `name_ascii`)
SELECT c.`id`, 'Zlín', 'zlin' FROM `{prefix}countries` c
WHERE c.`iso2`='CZ' AND NOT EXISTS (SELECT 1 FROM `{prefix}cities` x WHERE x.`country_id`=c.`id` AND x.`name_ascii`='zlin');
INSERT INTO `{prefix}cities` (`country_id`, `name`, `name_ascii`)
SELECT c.`id`, 'Pardubice', 'pardubice' FROM `{prefix}countries` c
WHERE c.`iso2`='CZ' AND NOT EXISTS (SELECT 1 FROM `{prefix}cities` x WHERE x.`country_id`=c.`id` AND x.`name_ascii`='pardubice');
INSERT INTO `{prefix}cities` (`country_id`, `name`, `name_ascii`)
SELECT c.`id`, 'Hradec Králové', 'hradec kralove' FROM `{prefix}countries` c
WHERE c.`iso2`='CZ' AND NOT EXISTS (SELECT 1 FROM `{prefix}cities` x WHERE x.`country_id`=c.`id` AND x.`name_ascii`='hradec kralove');