-- Ověřené české a slovenské punkové kapely 2024–2027.
-- Kapela je vložena pouze jednou podle názvu, i když se opakuje na více akcích.
INSERT INTO `{prefix}bands` (`name`, `city_id`, `genre_id`, `created_at`)
SELECT src.name, c.id, g.id, UNIX_TIMESTAMP()
FROM (
  SELECT 'E!E' AS name, 'Příbram' AS city, 'CZ' AS country, 'Punk' AS genre
  UNION ALL
  SELECT 'Vision Days' AS name, 'Brněnec' AS city, 'CZ' AS country, 'Ska punk' AS genre
  UNION ALL
  SELECT 'Punk Floid' AS name, 'Zábřeh na Moravě' AS city, 'CZ' AS country, 'Punk rock' AS genre
  UNION ALL
  SELECT 'Houba' AS name, 'Ústí nad Labem' AS city, 'CZ' AS country, 'Punk rock' AS genre
  UNION ALL
  SELECT 'Nežfaleš' AS name, 'Praha' AS city, 'CZ' AS country, 'Punk rock' AS genre
  UNION ALL
  SELECT 'The Fialky' AS name, 'Praha' AS city, 'CZ' AS country, 'Punk rock' AS genre
  UNION ALL
  SELECT 'Totální nasazení' AS name, 'Slaný' AS city, 'CZ' AS country, 'Punk rock' AS genre
  UNION ALL
  SELECT 'SPS' AS name, 'Praha' AS city, 'CZ' AS country, 'Punk' AS genre
  UNION ALL
  SELECT 'Znouzectnost' AS name, 'Plzeň' AS city, 'CZ' AS country, 'Punk rock' AS genre
  UNION ALL
  SELECT 'Odpad' AS name, 'Martin' AS city, 'SK' AS country, 'Punk' AS genre
  UNION ALL
  SELECT '!V.V.Ú.' AS name, 'Pardubice' AS city, 'CZ' AS country, 'Punk' AS genre
  UNION ALL
  SELECT 'Zputnik' AS name, 'Praha' AS city, 'CZ' AS country, 'Industrial punk' AS genre
  UNION ALL
  SELECT 'Volant' AS name, 'Pardubice' AS city, 'CZ' AS country, 'Ska punk' AS genre
  UNION ALL
  SELECT 'Kohout plaší smrt' AS name, 'Liberec' AS city, 'CZ' AS country, 'Punk' AS genre
  UNION ALL
  SELECT 'Dukla Vozovna' AS name, 'Pardubice' AS city, 'CZ' AS country, 'Punk rock' AS genre
  UNION ALL
  SELECT 'Punkreas' AS name, 'Moldava nad Bodvou' AS city, 'SK' AS country, 'Punk' AS genre
  UNION ALL
  SELECT 'Sistem Erorr' AS name, 'Prešov' AS city, 'SK' AS country, 'Punk' AS genre
  UNION ALL
  SELECT 'Uhol Dopadu' AS name, 'Trenčín' AS city, 'SK' AS country, 'Punk' AS genre
  UNION ALL
  SELECT 'P.U.M.' AS name, 'Praha' AS city, 'CZ' AS country, 'Punk rock' AS genre
  UNION ALL
  SELECT 'Supertesla' AS name, 'Brno' AS city, 'CZ' AS country, 'Punk rock' AS genre
  UNION ALL
  SELECT 'Jet8' AS name, 'Praha' AS city, 'CZ' AS country, 'Hardcore punk' AS genre
  UNION ALL
  SELECT 'Bez Šance' AS name, 'Police nad Metují' AS city, 'CZ' AS country, 'Punk rock' AS genre
  UNION ALL
  SELECT 'Tragedis' AS name, 'Jindřichův Hradec' AS city, 'CZ' AS country, 'Folk punk' AS genre
  UNION ALL
  SELECT 'Rumble Ansamble' AS name, 'Kladno' AS city, 'CZ' AS country, 'Punk rock' AS genre
  UNION ALL
  SELECT 'Bermudskej Kvádr' AS name, 'Velké Meziříčí' AS city, 'CZ' AS country, 'Punk' AS genre
  UNION ALL
  SELECT 'RV-4' AS name, 'Benešov' AS city, 'CZ' AS country, 'Hardcore punk' AS genre
  UNION ALL
  SELECT 'SPOTS' AS name, 'Odolena Voda' AS city, 'CZ' AS country, 'Hardcore punk' AS genre
  UNION ALL
  SELECT 'Takhle Vole' AS name, 'Kardašova Řečice' AS city, 'CZ' AS country, 'Punk' AS genre
  UNION ALL
  SELECT 'Přísun Povolen' AS name, 'Praha' AS city, 'CZ' AS country, 'Punk rock' AS genre
  UNION ALL
  SELECT 'Zeměžluč' AS name, 'Brno' AS city, 'CZ' AS country, 'Hardcore punk' AS genre
  UNION ALL
  SELECT 'Louty' AS name, 'Roudnice nad Labem' AS city, 'CZ' AS country, 'Punk rock' AS genre
  UNION ALL
  SELECT 'Pirates of the Pubs' AS name, 'České Budějovice' AS city, 'CZ' AS country, 'Celtic punk' AS genre
  UNION ALL
  SELECT 'Cheers!' AS name, 'Plzeň' AS city, 'CZ' AS country, 'Celtic punk' AS genre
  UNION ALL
  SELECT 'Vysoké Napětí' AS name, 'Vsetín' AS city, 'CZ' AS country, 'Ska punk' AS genre
) AS src
JOIN `{prefix}countries` AS co ON co.iso2 = src.country
JOIN `{prefix}cities` AS c ON c.country_id = co.id AND c.name = src.city
JOIN `{prefix}genres` AS g ON g.name_ascii = CASE src.genre
  WHEN 'Punk' THEN 'punk'
  WHEN 'Punk rock' THEN 'punk-rock'
  WHEN 'Ska punk' THEN 'ska-punk'
  WHEN 'Hardcore punk' THEN 'hardcore-punk'
  WHEN 'Celtic punk' THEN 'celtic-punk'
  WHEN 'Industrial punk' THEN 'industrial-punk'
  WHEN 'Folk punk' THEN 'folk-punk'
END
WHERE NOT EXISTS (
  SELECT 1 FROM `{prefix}bands` AS existing WHERE existing.name = src.name
);
