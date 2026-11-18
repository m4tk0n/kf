-- Česká města a obce s doloženým pořádáním punkových koncertů,
-- punkových festivalů nebo klubových akcí s punkovým programem.
-- country_id se dohledává podle ISO2, nikdy se nepředpokládá pevné ID země.
-- INSERT IGNORE + UNIQUE(country_id, slug) umožňuje opakované spuštění.

INSERT IGNORE INTO `{p}cities` (`country_id`, `slug`, `cs`, `en`)
SELECT c.id, v.slug, v.cs, v.en
FROM `{p}countries` AS c
JOIN (
  SELECT 'praha' AS slug, 'Praha' AS cs, 'Prague' AS en
  UNION ALL SELECT 'brno', 'Brno', 'Brno'
  UNION ALL SELECT 'ostrava', 'Ostrava', 'Ostrava'
  UNION ALL SELECT 'plzen', 'Plzeň', 'Pilsen'
  UNION ALL SELECT 'ceske-budejovice', 'České Budějovice', 'České Budějovice'
  UNION ALL SELECT 'liberec', 'Liberec', 'Liberec'
  UNION ALL SELECT 'olomouc', 'Olomouc', 'Olomouc'
  UNION ALL SELECT 'zlin', 'Zlín', 'Zlín'
  UNION ALL SELECT 'pardubice', 'Pardubice', 'Pardubice'
  UNION ALL SELECT 'hradec-kralove', 'Hradec Králové', 'Hradec Králové'
  UNION ALL SELECT 'jihlava', 'Jihlava', 'Jihlava'
  UNION ALL SELECT 'teplice', 'Teplice', 'Teplice'
  UNION ALL SELECT 'usti-nad-labem', 'Ústí nad Labem', 'Ústí nad Labem'
  UNION ALL SELECT 'litomerice', 'Litoměřice', 'Litoměřice'
  UNION ALL SELECT 'louny', 'Louny', 'Louny'
  UNION ALL SELECT 'kladno', 'Kladno', 'Kladno'
  UNION ALL SELECT 'kolin', 'Kolín', 'Kolín'
  UNION ALL SELECT 'znojmo', 'Znojmo', 'Znojmo'
  UNION ALL SELECT 'strakonice', 'Strakonice', 'Strakonice'
  UNION ALL SELECT 'volyne', 'Volyně', 'Volyně'
  UNION ALL SELECT 'pisek', 'Písek', 'Písek'
  UNION ALL SELECT 'chrudim', 'Chrudim', 'Chrudim'
  UNION ALL SELECT 'litomysl', 'Litomyšl', 'Litomyšl'
  UNION ALL SELECT 'svitavy', 'Svitavy', 'Svitavy'
  UNION ALL SELECT 'tabor', 'Tábor', 'Tábor'
  UNION ALL SELECT 'trutnov', 'Trutnov', 'Trutnov'
  UNION ALL SELECT 'jaromer', 'Jaroměř', 'Jaroměř'
  UNION ALL SELECT 'police-nad-metuji', 'Police nad Metují', 'Police nad Metují'
  UNION ALL SELECT 'vrchlabi', 'Vrchlabí', 'Vrchlabí'
  UNION ALL SELECT 'klatovy', 'Klatovy', 'Klatovy'
  UNION ALL SELECT 'karlovy-vary', 'Karlovy Vary', 'Karlovy Vary'
  UNION ALL SELECT 'pribram', 'Příbram', 'Příbram'
  UNION ALL SELECT 'jablonec-nad-nisou', 'Jablonec nad Nisou', 'Jablonec nad Nisou'
  UNION ALL SELECT 'semily', 'Semily', 'Semily'
  UNION ALL SELECT 'valasske-klobouky', 'Valašské Klobouky', 'Valašské Klobouky'
  UNION ALL SELECT 'litvinov', 'Litvínov', 'Litvínov'
  UNION ALL SELECT 'most', 'Most', 'Most'
  UNION ALL SELECT 'frydek-mistek', 'Frýdek-Místek', 'Frýdek-Místek'
  UNION ALL SELECT 'jablunkov', 'Jablunkov', 'Jablunkov'
  UNION ALL SELECT 'uherske-hradiste', 'Uherské Hradiště', 'Uherské Hradiště'
  UNION ALL SELECT 'nymburk', 'Nymburk', 'Nymburk'
  UNION ALL SELECT 'podebrady', 'Poděbrady', 'Poděbrady'
  UNION ALL SELECT 'lovosice', 'Lovosice', 'Lovosice'
  UNION ALL SELECT 'harrachov', 'Harrachov', 'Harrachov'
  UNION ALL SELECT 'trebic', 'Třebíč', 'Třebíč'
  UNION ALL SELECT 'zabreh', 'Zábřeh', 'Zábřeh'
  UNION ALL SELECT 'ricany', 'Říčany', 'Říčany'
  UNION ALL SELECT 'mlada-boleslav', 'Mladá Boleslav', 'Mladá Boleslav'
  UNION ALL SELECT 'benesov', 'Benešov', 'Benešov'
  UNION ALL SELECT 'loket', 'Loket', 'Loket'
  UNION ALL SELECT 'as', 'Aš', 'Aš'
  UNION ALL SELECT 'tremosnice', 'Třemošnice', 'Třemošnice'
  UNION ALL SELECT 'dolni-benesov', 'Dolní Benešov', 'Dolní Benešov'
) AS v ON 1 = 1
WHERE c.iso2 = 'CZ';
