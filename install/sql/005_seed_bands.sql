-- První ověřená kapela katalogu K&F: The Fialky.
-- Země se dohledává podle ISO2; ID nejsou natvrdo zadaná.
-- Opakované spuštění je bezpečné díky INSERT IGNORE a stabilním slugům.

INSERT IGNORE INTO `{p}bands` (`country_id`, `slug`, `name`)
SELECT c.id, 'the-fialky', 'The Fialky'
FROM `{p}countries` AS c
WHERE c.iso2 = 'CZ';

INSERT IGNORE INTO `{p}band_genres` (`band_id`, `genre_id`)
SELECT b.id, g.id
FROM `{p}bands` AS b
JOIN `{p}genres` AS g ON g.slug = 'punk'
WHERE b.slug = 'the-fialky';
