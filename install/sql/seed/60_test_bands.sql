-- 10 schválených testovacích kapel. Přímý zápis do veřejné tabulky bands.
INSERT INTO `{prefix}bands` (`slug`,`name`,`name_ascii`,`city_id`,`genre_id`,`created_by`,`created_at`)
SELECT 'kapela-test-1','kapela-test-1','kapela-test-1',c.`id`,g.`id`,NULL,UNIX_TIMESTAMP() FROM `{prefix}cities` c JOIN `{prefix}genres` g ON g.`name_ascii`='rock'
WHERE c.`name_ascii`='brno' AND NOT EXISTS (SELECT 1 FROM `{prefix}bands` x WHERE x.`slug`='kapela-test-1');
INSERT INTO `{prefix}bands` (`slug`,`name`,`name_ascii`,`city_id`,`genre_id`,`created_by`,`created_at`)
SELECT 'kapela-test-2','kapela-test-2','kapela-test-2',c.`id`,g.`id`,NULL,UNIX_TIMESTAMP() FROM `{prefix}cities` c JOIN `{prefix}genres` g ON g.`name_ascii`='punk'
WHERE c.`name_ascii`='praha' AND NOT EXISTS (SELECT 1 FROM `{prefix}bands` x WHERE x.`slug`='kapela-test-2');
INSERT INTO `{prefix}bands` (`slug`,`name`,`name_ascii`,`city_id`,`genre_id`,`created_by`,`created_at`)
SELECT 'kapela-test-3','kapela-test-3','kapela-test-3',c.`id`,g.`id`,NULL,UNIX_TIMESTAMP() FROM `{prefix}cities` c JOIN `{prefix}genres` g ON g.`name_ascii`='metal'
WHERE c.`name_ascii`='ostrava' AND NOT EXISTS (SELECT 1 FROM `{prefix}bands` x WHERE x.`slug`='kapela-test-3');
INSERT INTO `{prefix}bands` (`slug`,`name`,`name_ascii`,`city_id`,`genre_id`,`created_by`,`created_at`)
SELECT 'kapela-test-4','kapela-test-4','kapela-test-4',c.`id`,g.`id`,NULL,UNIX_TIMESTAMP() FROM `{prefix}cities` c JOIN `{prefix}genres` g ON g.`name_ascii`='alternative'
WHERE c.`name_ascii`='plzen' AND NOT EXISTS (SELECT 1 FROM `{prefix}bands` x WHERE x.`slug`='kapela-test-4');
INSERT INTO `{prefix}bands` (`slug`,`name`,`name_ascii`,`city_id`,`genre_id`,`created_by`,`created_at`)
SELECT 'kapela-test-5','kapela-test-5','kapela-test-5',c.`id`,g.`id`,NULL,UNIX_TIMESTAMP() FROM `{prefix}cities` c JOIN `{prefix}genres` g ON g.`name_ascii`='indie'
WHERE c.`name_ascii`='olomouc' AND NOT EXISTS (SELECT 1 FROM `{prefix}bands` x WHERE x.`slug`='kapela-test-5');
INSERT INTO `{prefix}bands` (`slug`,`name`,`name_ascii`,`city_id`,`genre_id`,`created_by`,`created_at`)
SELECT 'kapela-test-6','kapela-test-6','kapela-test-6',c.`id`,g.`id`,NULL,UNIX_TIMESTAMP() FROM `{prefix}cities` c JOIN `{prefix}genres` g ON g.`name_ascii`='pop'
WHERE c.`name_ascii`='liberec' AND NOT EXISTS (SELECT 1 FROM `{prefix}bands` x WHERE x.`slug`='kapela-test-6');
INSERT INTO `{prefix}bands` (`slug`,`name`,`name_ascii`,`city_id`,`genre_id`,`created_by`,`created_at`)
SELECT 'kapela-test-7','kapela-test-7','kapela-test-7',c.`id`,g.`id`,NULL,UNIX_TIMESTAMP() FROM `{prefix}cities` c JOIN `{prefix}genres` g ON g.`name_ascii`='electronic'
WHERE c.`name_ascii`='ceske-budejovice' AND NOT EXISTS (SELECT 1 FROM `{prefix}bands` x WHERE x.`slug`='kapela-test-7');
INSERT INTO `{prefix}bands` (`slug`,`name`,`name_ascii`,`city_id`,`genre_id`,`created_by`,`created_at`)
SELECT 'kapela-test-8','kapela-test-8','kapela-test-8',c.`id`,g.`id`,NULL,UNIX_TIMESTAMP() FROM `{prefix}cities` c JOIN `{prefix}genres` g ON g.`name_ascii`='hardcore'
WHERE c.`name_ascii`='zlin' AND NOT EXISTS (SELECT 1 FROM `{prefix}bands` x WHERE x.`slug`='kapela-test-8');
INSERT INTO `{prefix}bands` (`slug`,`name`,`name_ascii`,`city_id`,`genre_id`,`created_by`,`created_at`)
SELECT 'kapela-test-9','kapela-test-9','kapela-test-9',c.`id`,g.`id`,NULL,UNIX_TIMESTAMP() FROM `{prefix}cities` c JOIN `{prefix}genres` g ON g.`name_ascii`='folk'
WHERE c.`name_ascii`='pardubice' AND NOT EXISTS (SELECT 1 FROM `{prefix}bands` x WHERE x.`slug`='kapela-test-9');
INSERT INTO `{prefix}bands` (`slug`,`name`,`name_ascii`,`city_id`,`genre_id`,`created_by`,`created_at`)
SELECT 'kapela-test-10','kapela-test-10','kapela-test-10',c.`id`,g.`id`,NULL,UNIX_TIMESTAMP() FROM `{prefix}cities` c JOIN `{prefix}genres` g ON g.`name_ascii`='jazz'
WHERE c.`name_ascii`='hradec-kralove' AND NOT EXISTS (SELECT 1 FROM `{prefix}bands` x WHERE x.`slug`='kapela-test-10');
