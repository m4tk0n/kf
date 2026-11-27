-- Ověřená CZ/SK punková místa 2024–2027.
-- Jedno fyzické místo je vloženo pouze jednou; změněná adresa by byla samostatným záznamem.
INSERT INTO `{prefix}venues` (`name`, `city_id`, `address`, `created_at`)
SELECT src.name, c.id, src.address, UNIX_TIMESTAMP()
FROM (
  SELECT 'Vagon' AS name, 'Praha' AS city, 'CZ' AS country, 'Národní 25' AS address
  UNION ALL
  SELECT 'Barrák Music Club' AS name, 'Ostrava' AS city, 'CZ' AS country, 'Fügnerova 595/1' AS address
  UNION ALL
  SELECT 'Modrá Vopice' AS name, 'Praha' AS city, 'CZ' AS country, 'Roh ulic Spojovací a K Žižkovu' AS address
  UNION ALL
  SELECT 'Sibiř' AS name, 'Brno' AS city, 'CZ' AS country, 'Dukelská 123/50' AS address
  UNION ALL
  SELECT 'Divadlo Pod čarou' AS name, 'Písek' AS city, 'CZ' AS country, 'Tyršova 28' AS address
  UNION ALL
  SELECT 'Divadlo pod Lampou' AS name, 'Plzeň' AS city, 'CZ' AS country, 'Havířská 11' AS address
  UNION ALL
  SELECT 'MC Fabrika' AS name, 'České Budějovice' AS city, 'CZ' AS country, 'Jeronýmova 6' AS address
  UNION ALL
  SELECT 'Klub Mír' AS name, 'Uherské Hradiště' AS city, 'CZ' AS country, 'Náměstí Míru 765' AS address
  UNION ALL
  SELECT 'Stará Masna' AS name, 'Kroměříž' AS city, 'CZ' AS country, 'Hulínská 2322/28C' AS address
  UNION ALL
  SELECT 'KNAK' AS name, 'Teplice' AS city, 'CZ' AS country, 'Rooseveltovo náměstí 265/3' AS address
  UNION ALL
  SELECT 'První Patro' AS name, 'Brno' AS city, 'CZ' AS country, 'Šilingrovo náměstí 7' AS address
  UNION ALL
  SELECT 'Kabinet MÚZ' AS name, 'Brno' AS city, 'CZ' AS country, 'Sukova 49/4' AS address
  UNION ALL
  SELECT 'Melodka' AS name, 'Brno' AS city, 'CZ' AS country, 'Pisárecká 525/7a' AS address
  UNION ALL
  SELECT 'M-Klub' AS name, 'Vysoké Mýto' AS city, 'CZ' AS country, 'Litomyšlská 71' AS address
  UNION ALL
  SELECT 'Kotelna' AS name, 'Litomyšl' AS city, 'CZ' AS country, 'Kpt. Jaroše 1129' AS address
  UNION ALL
  SELECT 'Baronka' AS name, 'Litoměřice' AS city, 'CZ' AS country, 'Lidická 72/14' AS address
  UNION ALL
  SELECT 'Rock Café' AS name, 'Praha' AS city, 'CZ' AS country, 'Národní 20' AS address
  UNION ALL
  SELECT 'Rampa' AS name, 'Mariánské Lázně' AS city, 'CZ' AS country, 'Kollárova 7' AS address
  UNION ALL
  SELECT 'Futurum Music Bar' AS name, 'Praha' AS city, 'CZ' AS country, 'Zborovská 7' AS address
  UNION ALL
  SELECT 'Zkušebna Art Club' AS name, 'Frýdek-Místek' AS city, 'CZ' AS country, 'Heydukova 85' AS address
  UNION ALL
  SELECT 'Bývalé vlakové nádraží' AS name, 'Horní Jiřetín' AS city, 'CZ' AS country, 'Černická 25/20' AS address
  UNION ALL
  SELECT 'Letní kino Chrudim' AS name, 'Chrudim' AS city, 'CZ' AS country, 'Lázeňská' AS address
  UNION ALL
  SELECT 'Letiště Čápův dvůr' AS name, 'Tábor' AS city, 'CZ' AS country, 'Měšice 477' AS address
  UNION ALL
  SELECT 'Koupaliště Volyně' AS name, 'Volyně' AS city, 'CZ' AS country, 'Nábřeží Dr. Kafky 75' AS address
  UNION ALL
  SELECT 'Lidové divadlo Svitavy' AS name, 'Svitavy' AS city, 'CZ' AS country, 'M. Horákové 466/20' AS address
  UNION ALL
  SELECT 'Včelín Music Bar' AS name, 'Martin' AS city, 'SK' AS country, 'Zámocká 5362/28' AS address
  UNION ALL
  SELECT 'Kocka Music Bar' AS name, 'Martin' AS city, 'SK' AS country, 'Zvolenská 4011/29' AS address
  UNION ALL
  SELECT 'Club 263' AS name, 'Prešov' AS city, 'SK' AS country, 'Hlavná 62/A' AS address
  UNION ALL
  SELECT 'The Doors Pub' AS name, 'Dubnica nad Váhom' AS city, 'SK' AS country, 'Školská 1371' AS address
  UNION ALL
  SELECT 'Klub Lúč' AS name, 'Trenčín' AS city, 'SK' AS country, 'Mierové námestie 17' AS address
  UNION ALL
  SELECT 'Pink Whale' AS name, 'Bratislava' AS city, 'SK' AS country, 'Nábrežie arm. gen. Ludvíka Svobodu, 1868 rkm' AS address
  UNION ALL
  SELECT 'Collosseum Club' AS name, 'Košice' AS city, 'SK' AS country, 'Vrátna 639/70' AS address
  UNION ALL
  SELECT 'Smer Klub 77' AS name, 'Žilina' AS city, 'SK' AS country, 'Romualda Zaymusa 72/8' AS address
  UNION ALL
  SELECT 'Randal Club' AS name, 'Bratislava' AS city, 'SK' AS country, 'Karpatská 3089/2' AS address
  UNION ALL
  SELECT 'Kemping Rybníky Opatovce' AS name, 'Opatovce' AS city, 'SK' AS country, 'Opatovce 173' AS address
  UNION ALL
  SELECT 'RC Mlyn' AS name, 'Vrútky' AS city, 'SK' AS country, 'Matušovičovský rad 5652' AS address
  UNION ALL
  SELECT 'Willax Club' AS name, 'Mladá Boleslav' AS city, 'CZ' AS country, 'Havlíčkova 1307' AS address
) AS src
JOIN `{prefix}countries` AS co ON co.iso2 = src.country
JOIN `{prefix}cities` AS c ON c.country_id = co.id AND c.name = src.city
WHERE NOT EXISTS (
  SELECT 1 FROM `{prefix}venues` AS existing
  WHERE existing.name = src.name AND existing.city_id = c.id AND existing.address = src.address
);
