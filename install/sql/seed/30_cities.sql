INSERT INTO `{prefix}cities` (`country_id`, `name`, `name_ascii`)
SELECT co.`id`, v.`name`, v.`name_ascii`
FROM (
  SELECT 'CZ' AS iso2, 'Praha' AS name, 'Praha' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Brno' AS name, 'Brno' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Plzeň' AS name, 'Plzen' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Náchod' AS name, 'Nachod' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Hradec Králové' AS name, 'Hradec Kralove' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'České Budějovice' AS name, 'Ceske Budejovice' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Ostrava' AS name, 'Ostrava' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Olomouc' AS name, 'Olomouc' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Břeclav' AS name, 'Breclav' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Pardubice' AS name, 'Pardubice' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Mnichovice' AS name, 'Mnichovice' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Liberec' AS name, 'Liberec' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Libochovice' AS name, 'Libochovice' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Kyjov' AS name, 'Kyjov' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Hodonín' AS name, 'Hodonin' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Chrudim' AS name, 'Chrudim' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Heřmanova Huť' AS name, 'Hermanova Hut' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Strakonice' AS name, 'Strakonice' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Poděbrady' AS name, 'Podebrady' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Žamberk' AS name, 'Zamberk' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Moravská Třebová' AS name, 'Moravska Trebova' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Jaroměř' AS name, 'Jaromer' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Boskovice' AS name, 'Boskovice' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Branice' AS name, 'Branice' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Brněnec' AS name, 'Brnenec' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Ústí nad Orlicí' AS name, 'Usti nad Orlici' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Ostrov' AS name, 'Ostrov' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Hejtmánkovice' AS name, 'Hejtmankovice' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Svojšice' AS name, 'Svojsice' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Strašice' AS name, 'Strasice' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Skrýchov u Opařan' AS name, 'Skrychov u Oparan' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Nové Město nad Metují' AS name, 'Nove Mesto nad Metuji' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Opava' AS name, 'Opava' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Zlín' AS name, 'Zlin' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Blatná' AS name, 'Blatna' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Turnov' AS name, 'Turnov' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Písek' AS name, 'Pisek' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Kojetín' AS name, 'Kojetin' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Frýdek-Místek' AS name, 'Frydek-Mistek' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Slaný' AS name, 'Slany' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Štětí' AS name, 'Steti' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Trutnov' AS name, 'Trutnov' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Volyně' AS name, 'Volyne' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Teplice' AS name, 'Teplice' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Mělník' AS name, 'Melnik' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Ořech' AS name, 'Orech' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Chomutov' AS name, 'Chomutov' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Litvínov' AS name, 'Litvinov' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Rumburk' AS name, 'Rumburk' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Mirotice' AS name, 'Mirotice' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Žatec' AS name, 'Zatec' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Litoměřice' AS name, 'Litomerice' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Děčín' AS name, 'Decin' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Kopřivnice' AS name, 'Koprivnice' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Valašské Meziříčí' AS name, 'Valasske Mezirici' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Třebíč' AS name, 'Trebic' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Nýřany' AS name, 'Nyrany' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Nový Bydžov' AS name, 'Novy Bydzov' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Vysoké Mýto' AS name, 'Vysoke Myto' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Třebechovice pod Orebem' AS name, 'Trebechovice pod Orebem' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Ražice' AS name, 'Razice' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Spálené Poříčí' AS name, 'Spalene Porici' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Chvaleč' AS name, 'Chvalec' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Vyškov' AS name, 'Vyskov' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Tábor' AS name, 'Tabor' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Křižanov' AS name, 'Krizanov' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Orlová' AS name, 'Orlova' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Mokrá-Horákov' AS name, 'Mokra-Horakov' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Kunčice u Letohradu' AS name, 'Kuncice u Letohradu' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Dřeveš' AS name, 'Dreves' AS name_ascii
  UNION ALL
  SELECT 'CZ' AS iso2, 'Poděčely' AS name, 'Podecely' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Bratislava' AS name, 'Bratislava' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Nitra' AS name, 'Nitra' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Podbranč' AS name, 'Podbranc' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Ivanka pri Dunaji' AS name, 'Ivanka pri Dunaji' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Púchov' AS name, 'Puchov' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Banská Bystrica' AS name, 'Banska Bystrica' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Vrbové' AS name, 'Vrbove' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Žemberovce' AS name, 'Zemberovce' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Rajec' AS name, 'Rajec' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Veľké Kostoľany' AS name, 'Velke Kostolany' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Liptovský Mikuláš' AS name, 'Liptovsky Mikulas' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Poprad' AS name, 'Poprad' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Jelenec' AS name, 'Jelenec' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Trebatice' AS name, 'Trebatice' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Myjava' AS name, 'Myjava' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Košice' AS name, 'Kosice' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Prievidza' AS name, 'Prievidza' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Malinovo' AS name, 'Malinovo' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Sereď' AS name, 'Sered' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Trenčín' AS name, 'Trencin' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Bytča' AS name, 'Bytca' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Nitrianske Rudno' AS name, 'Nitrianske Rudno' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Nové Mesto nad Váhom' AS name, 'Nove Mesto nad Vahom' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Stará Turá' AS name, 'Stara Tura' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Senica' AS name, 'Senica' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Častkovce' AS name, 'Castkovce' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Mošovce' AS name, 'Mosovce' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Šamorín' AS name, 'Samorin' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Trnovec nad Váhom' AS name, 'Trnovec nad Vahom' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Zlaté Moravce' AS name, 'Zlate Moravce' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Jaslovské Bohunice' AS name, 'Jaslovske Bohunice' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Šindliar' AS name, 'Sindliar' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Rimavská Sobota' AS name, 'Rimavska Sobota' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Banská Štiavnica' AS name, 'Banska Stiavnica' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Topoľčany' AS name, 'Topolcany' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Vrútky' AS name, 'Vrutky' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Trnava' AS name, 'Trnava' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Nové Sady' AS name, 'Nove Sady' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Bardejov' AS name, 'Bardejov' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Pohronská Polhora' AS name, 'Pohronska Polhora' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Palárikovo' AS name, 'Palarikovo' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Bernolákovo' AS name, 'Bernolakovo' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Brezno' AS name, 'Brezno' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Malacky' AS name, 'Malacky' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Buková' AS name, 'Bukova' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Dolný Kubín' AS name, 'Dolny Kubin' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Prešov' AS name, 'Presov' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Sasinkovo' AS name, 'Sasinkovo' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Sládkovičovo' AS name, 'Sladkovicovo' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Vráble' AS name, 'Vrable' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Lednické Rovne' AS name, 'Lednicke Rovne' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Plavecký Štvrtok' AS name, 'Plavecky Stvrtok' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Modra' AS name, 'Modra' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Zliechov' AS name, 'Zliechov' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Červeník' AS name, 'Cervenik' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Trenčianske Bohuslavice' AS name, 'Trencianske Bohuslavice' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Dobrohošť' AS name, 'Dobrohost' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Lučenec' AS name, 'Lucenec' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Cífer' AS name, 'Cifer' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Hurbanovo' AS name, 'Hurbanovo' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Moravany nad Váhom' AS name, 'Moravany nad Vahom' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Rožňava' AS name, 'Roznava' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Hnúšťa' AS name, 'Hnusta' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Ružiná' AS name, 'Ruzina' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Jatov' AS name, 'Jatov' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Hlohovec' AS name, 'Hlohovec' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Ružomberok' AS name, 'Ruzomberok' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Medzibrod' AS name, 'Medzibrod' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Oravská Poruba' AS name, 'Oravska Poruba' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Stupava' AS name, 'Stupava' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Ihráč' AS name, 'Ihrac' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Spišská Nová Ves' AS name, 'Spisska Nova Ves' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Martin' AS name, 'Martin' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Zvolen' AS name, 'Zvolen' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Žiar nad Hronom' AS name, 'Ziar nad Hronom' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Partizánske' AS name, 'Partizanske' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Turzovka' AS name, 'Turzovka' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Veľké Ripňany' AS name, 'Velke Ripnany' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Jahodná' AS name, 'Jahodna' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Sučany' AS name, 'Sucany' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Šaľa' AS name, 'Sala' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Žilina' AS name, 'Zilina' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Levice' AS name, 'Levice' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Námestovo' AS name, 'Namestovo' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Humenné' AS name, 'Humenne' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Skalica' AS name, 'Skalica' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Považská Bystrica' AS name, 'Povazska Bystrica' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Senec' AS name, 'Senec' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Pezinok' AS name, 'Pezinok' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Svidník' AS name, 'Svidnik' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Michalovce' AS name, 'Michalovce' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Mýtna' AS name, 'Mytna' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Malachov' AS name, 'Malachov' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Opatovce' AS name, 'Opatovce' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Brezová pod Bradlom' AS name, 'Brezova pod Bradlom' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Piešťany' AS name, 'Piestany' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Zemianske Kostoľany' AS name, 'Zemianske Kostolany' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Malé Leváre' AS name, 'Male Levare' AS name_ascii
  UNION ALL
  SELECT 'SK' AS iso2, 'Štrbské Pleso' AS name, 'Strbske Pleso' AS name_ascii
) AS v
INNER JOIN `{prefix}countries` AS co ON co.`iso2` = v.`iso2`
WHERE NOT EXISTS (
  SELECT 1
  FROM `{prefix}cities` AS existing
  WHERE existing.`country_id` = co.`id`
    AND existing.`name_ascii` = v.`name_ascii`
);
