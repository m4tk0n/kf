-- 10 schválených testovacích klubů. Přímý zápis do veřejné tabulky venues.
INSERT INTO `{prefix}venues` (`slug`,`name`,`name_ascii`,`city_id`,`created_by`,`created_at`)
SELECT 'klub-test-1','klub-test-1','klub-test-1',c.`id`,NULL,UNIX_TIMESTAMP() FROM `{prefix}cities` c
WHERE c.`name_ascii`='brno' AND NOT EXISTS (SELECT 1 FROM `{prefix}venues` x WHERE x.`slug`='klub-test-1');
INSERT INTO `{prefix}venues` (`slug`,`name`,`name_ascii`,`city_id`,`created_by`,`created_at`)
SELECT 'klub-test-2','klub-test-2','klub-test-2',c.`id`,NULL,UNIX_TIMESTAMP() FROM `{prefix}cities` c
WHERE c.`name_ascii`='praha' AND NOT EXISTS (SELECT 1 FROM `{prefix}venues` x WHERE x.`slug`='klub-test-2');
INSERT INTO `{prefix}venues` (`slug`,`name`,`name_ascii`,`city_id`,`created_by`,`created_at`)
SELECT 'klub-test-3','klub-test-3','klub-test-3',c.`id`,NULL,UNIX_TIMESTAMP() FROM `{prefix}cities` c
WHERE c.`name_ascii`='ostrava' AND NOT EXISTS (SELECT 1 FROM `{prefix}venues` x WHERE x.`slug`='klub-test-3');
INSERT INTO `{prefix}venues` (`slug`,`name`,`name_ascii`,`city_id`,`created_by`,`created_at`)
SELECT 'klub-test-4','klub-test-4','klub-test-4',c.`id`,NULL,UNIX_TIMESTAMP() FROM `{prefix}cities` c
WHERE c.`name_ascii`='plzen' AND NOT EXISTS (SELECT 1 FROM `{prefix}venues` x WHERE x.`slug`='klub-test-4');
INSERT INTO `{prefix}venues` (`slug`,`name`,`name_ascii`,`city_id`,`created_by`,`created_at`)
SELECT 'klub-test-5','klub-test-5','klub-test-5',c.`id`,NULL,UNIX_TIMESTAMP() FROM `{prefix}cities` c
WHERE c.`name_ascii`='olomouc' AND NOT EXISTS (SELECT 1 FROM `{prefix}venues` x WHERE x.`slug`='klub-test-5');
INSERT INTO `{prefix}venues` (`slug`,`name`,`name_ascii`,`city_id`,`created_by`,`created_at`)
SELECT 'klub-test-6','klub-test-6','klub-test-6',c.`id`,NULL,UNIX_TIMESTAMP() FROM `{prefix}cities` c
WHERE c.`name_ascii`='liberec' AND NOT EXISTS (SELECT 1 FROM `{prefix}venues` x WHERE x.`slug`='klub-test-6');
INSERT INTO `{prefix}venues` (`slug`,`name`,`name_ascii`,`city_id`,`created_by`,`created_at`)
SELECT 'klub-test-7','klub-test-7','klub-test-7',c.`id`,NULL,UNIX_TIMESTAMP() FROM `{prefix}cities` c
WHERE c.`name_ascii`='ceske-budejovice' AND NOT EXISTS (SELECT 1 FROM `{prefix}venues` x WHERE x.`slug`='klub-test-7');
INSERT INTO `{prefix}venues` (`slug`,`name`,`name_ascii`,`city_id`,`created_by`,`created_at`)
SELECT 'klub-test-8','klub-test-8','klub-test-8',c.`id`,NULL,UNIX_TIMESTAMP() FROM `{prefix}cities` c
WHERE c.`name_ascii`='zlin' AND NOT EXISTS (SELECT 1 FROM `{prefix}venues` x WHERE x.`slug`='klub-test-8');
INSERT INTO `{prefix}venues` (`slug`,`name`,`name_ascii`,`city_id`,`created_by`,`created_at`)
SELECT 'klub-test-9','klub-test-9','klub-test-9',c.`id`,NULL,UNIX_TIMESTAMP() FROM `{prefix}cities` c
WHERE c.`name_ascii`='pardubice' AND NOT EXISTS (SELECT 1 FROM `{prefix}venues` x WHERE x.`slug`='klub-test-9');
INSERT INTO `{prefix}venues` (`slug`,`name`,`name_ascii`,`city_id`,`created_by`,`created_at`)
SELECT 'klub-test-10','klub-test-10','klub-test-10',c.`id`,NULL,UNIX_TIMESTAMP() FROM `{prefix}cities` c
WHERE c.`name_ascii`='hradec-kralove' AND NOT EXISTS (SELECT 1 FROM `{prefix}venues` x WHERE x.`slug`='klub-test-10');
