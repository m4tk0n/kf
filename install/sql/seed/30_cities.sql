SET @country_cz = (SELECT id FROM `{prefix}countries` WHERE iso2 = 'CZ');
SET @country_sk = (SELECT id FROM `{prefix}countries` WHERE iso2 = 'SK');

INSERT IGNORE INTO `{prefix}cities` (`country_id`, `name`, `name_ascii`) VALUES
(@country_cz, 'Praha', 'Praha'),
(@country_cz, 'Brno', 'Brno'),
(@country_cz, 'Plzeň', 'Plzen'),
(@country_cz, 'Náchod', 'Nachod'),
(@country_cz, 'Hradec Králové', 'Hradec Kralove'),
(@country_cz, 'České Budějovice', 'Ceske Budejovice'),
(@country_cz, 'Ostrava', 'Ostrava'),
(@country_cz, 'Olomouc', 'Olomouc'),
(@country_cz, 'Břeclav', 'Breclav'),
(@country_cz, 'Pardubice', 'Pardubice'),
(@country_cz, 'Mnichovice', 'Mnichovice'),
(@country_cz, 'Liberec', 'Liberec'),
(@country_cz, 'Libochovice', 'Libochovice'),
(@country_cz, 'Kyjov', 'Kyjov'),
(@country_cz, 'Hodonín', 'Hodonin'),
(@country_cz, 'Chrudim', 'Chrudim'),
(@country_cz, 'Heřmanova Huť', 'Hermanova Hut'),
(@country_cz, 'Strakonice', 'Strakonice'),
(@country_cz, 'Poděbrady', 'Podebrady'),
(@country_cz, 'Žamberk', 'Zamberk'),
(@country_cz, 'Moravská Třebová', 'Moravska Trebova'),
(@country_cz, 'Jaroměř', 'Jaromer'),
(@country_cz, 'Boskovice', 'Boskovice'),
(@country_cz, 'Branice', 'Branice'),
(@country_cz, 'Brněnec', 'Brnenec'),
(@country_cz, 'Ústí nad Orlicí', 'Usti nad Orlici'),
(@country_cz, 'Ostrov', 'Ostrov'),
(@country_cz, 'Hejtmánkovice', 'Hejtmankovice'),
(@country_cz, 'Svojšice', 'Svojsice'),
(@country_cz, 'Strašice', 'Strasice'),
(@country_cz, 'Skrýchov u Opařan', 'Skrychov u Oparan'),
(@country_cz, 'Nové Město nad Metují', 'Nove Mesto nad Metuji'),
(@country_cz, 'Opava', 'Opava'),
(@country_cz, 'Zlín', 'Zlin'),
(@country_cz, 'Blatná', 'Blatna'),
(@country_cz, 'Turnov', 'Turnov'),
(@country_cz, 'Písek', 'Pisek'),
(@country_cz, 'Kojetín', 'Kojetin'),
(@country_cz, 'Frýdek-Místek', 'Frydek-Mistek'),
(@country_cz, 'Slaný', 'Slany'),
(@country_cz, 'Štětí', 'Steti'),
(@country_cz, 'Trutnov', 'Trutnov'),
(@country_cz, 'Volyně', 'Volyne'),
(@country_cz, 'Teplice', 'Teplice'),
(@country_cz, 'Mělník', 'Melnik'),
(@country_cz, 'Ořech', 'Orech'),
(@country_cz, 'Chomutov', 'Chomutov'),
(@country_cz, 'Litvínov', 'Litvinov'),
(@country_cz, 'Rumburk', 'Rumburk'),
(@country_cz, 'Mirotice', 'Mirotice'),
(@country_cz, 'Žatec', 'Zatec'),
(@country_cz, 'Litoměřice', 'Litomerice'),
(@country_cz, 'Děčín', 'Decin'),
(@country_cz, 'Kopřivnice', 'Koprivnice'),
(@country_cz, 'Valašské Meziříčí', 'Valasske Mezirici'),
(@country_cz, 'Třebíč', 'Trebic'),
(@country_cz, 'Nýřany', 'Nyrany'),
(@country_cz, 'Nový Bydžov', 'Novy Bydzov'),
(@country_cz, 'Vysoké Mýto', 'Vysoke Myto'),
(@country_cz, 'Třebechovice pod Orebem', 'Trebechovice pod Orebem'),
(@country_cz, 'Ražice', 'Razice'),
(@country_cz, 'Spálené Poříčí', 'Spalene Porici'),
(@country_cz, 'Chvaleč', 'Chvalec'),
(@country_cz, 'Vyškov', 'Vyskov'),
(@country_cz, 'Tábor', 'Tabor'),
(@country_cz, 'Křižanov', 'Krizanov'),
(@country_cz, 'Orlová', 'Orlova'),
(@country_cz, 'Mokrá-Horákov', 'Mokra-Horakov'),
(@country_cz, 'Kunčice u Letohradu', 'Kuncice u Letohradu'),
(@country_cz, 'Dřeveš', 'Dreves'),
(@country_cz, 'Poděčely', 'Podecely'),
(@country_sk, 'Bratislava', 'Bratislava'),
(@country_sk, 'Nitra', 'Nitra'),
(@country_sk, 'Podbranč', 'Podbranc'),
(@country_sk, 'Ivanka pri Dunaji', 'Ivanka pri Dunaji'),
(@country_sk, 'Púchov', 'Puchov'),
(@country_sk, 'Banská Bystrica', 'Banska Bystrica'),
(@country_sk, 'Vrbové', 'Vrbove'),
(@country_sk, 'Žemberovce', 'Zemberovce'),
(@country_sk, 'Rajec', 'Rajec'),
(@country_sk, 'Veľké Kostoľany', 'Velke Kostolany'),
(@country_sk, 'Liptovský Mikuláš', 'Liptovsky Mikulas'),
(@country_sk, 'Poprad', 'Poprad'),
(@country_sk, 'Jelenec', 'Jelenec'),
(@country_sk, 'Trebatice', 'Trebatice'),
(@country_sk, 'Myjava', 'Myjava'),
(@country_sk, 'Košice', 'Kosice'),
(@country_sk, 'Prievidza', 'Prievidza'),
(@country_sk, 'Malinovo', 'Malinovo'),
(@country_sk, 'Sereď', 'Sered'),
(@country_sk, 'Trenčín', 'Trencin'),
(@country_sk, 'Bytča', 'Bytca'),
(@country_sk, 'Nitrianske Rudno', 'Nitrianske Rudno'),
(@country_sk, 'Nové Mesto nad Váhom', 'Nove Mesto nad Vahom'),
(@country_sk, 'Stará Turá', 'Stara Tura'),
(@country_sk, 'Senica', 'Senica'),
(@country_sk, 'Častkovce', 'Castkovce'),
(@country_sk, 'Mošovce', 'Mosovce'),
(@country_sk, 'Šamorín', 'Samorin'),
(@country_sk, 'Trnovec nad Váhom', 'Trnovec nad Vahom'),
(@country_sk, 'Zlaté Moravce', 'Zlate Moravce'),
(@country_sk, 'Jaslovské Bohunice', 'Jaslovske Bohunice'),
(@country_sk, 'Šindliar', 'Sindliar'),
(@country_sk, 'Rimavská Sobota', 'Rimavska Sobota'),
(@country_sk, 'Banská Štiavnica', 'Banska Stiavnica'),
(@country_sk, 'Topoľčany', 'Topolcany'),
(@country_sk, 'Vrútky', 'Vrutky'),
(@country_sk, 'Trnava', 'Trnava'),
(@country_sk, 'Nové Sady', 'Nove Sady'),
(@country_sk, 'Bardejov', 'Bardejov'),
(@country_sk, 'Pohronská Polhora', 'Pohronska Polhora'),
(@country_sk, 'Palárikovo', 'Palarikovo'),
(@country_sk, 'Bernolákovo', 'Bernolakovo'),
(@country_sk, 'Brezno', 'Brezno'),
(@country_sk, 'Malacky', 'Malacky'),
(@country_sk, 'Buková', 'Bukova'),
(@country_sk, 'Dolný Kubín', 'Dolny Kubin'),
(@country_sk, 'Prešov', 'Presov'),
(@country_sk, 'Sasinkovo', 'Sasinkovo'),
(@country_sk, 'Sládkovičovo', 'Sladkovicovo'),
(@country_sk, 'Vráble', 'Vrable'),
(@country_sk, 'Lednické Rovne', 'Lednicke Rovne'),
(@country_sk, 'Plavecký Štvrtok', 'Plavecky Stvrtok'),
(@country_sk, 'Modra', 'Modra'),
(@country_sk, 'Zliechov', 'Zliechov'),
(@country_sk, 'Červeník', 'Cervenik'),
(@country_sk, 'Trenčianske Bohuslavice', 'Trencianske Bohuslavice'),
(@country_sk, 'Dobrohošť', 'Dobrohost'),
(@country_sk, 'Lučenec', 'Lucenec'),
(@country_sk, 'Cífer', 'Cifer'),
(@country_sk, 'Hurbanovo', 'Hurbanovo'),
(@country_sk, 'Moravany nad Váhom', 'Moravany nad Vahom'),
(@country_sk, 'Rožňava', 'Roznava'),
(@country_sk, 'Hnúšťa', 'Hnusta'),
(@country_sk, 'Ružiná', 'Ruzina'),
(@country_sk, 'Jatov', 'Jatov'),
(@country_sk, 'Hlohovec', 'Hlohovec'),
(@country_sk, 'Ružomberok', 'Ruzomberok'),
(@country_sk, 'Medzibrod', 'Medzibrod'),
(@country_sk, 'Oravská Poruba', 'Oravska Poruba'),
(@country_sk, 'Stupava', 'Stupava'),
(@country_sk, 'Ihráč', 'Ihrac'),
(@country_sk, 'Spišská Nová Ves', 'Spisska Nova Ves'),
(@country_sk, 'Martin', 'Martin'),
(@country_sk, 'Zvolen', 'Zvolen'),
(@country_sk, 'Žiar nad Hronom', 'Ziar nad Hronom'),
(@country_sk, 'Partizánske', 'Partizanske'),
(@country_sk, 'Turzovka', 'Turzovka'),
(@country_sk, 'Veľké Ripňany', 'Velke Ripnany'),
(@country_sk, 'Jahodná', 'Jahodna'),
(@country_sk, 'Sučany', 'Sucany'),
(@country_sk, 'Šaľa', 'Sala'),
(@country_sk, 'Žilina', 'Zilina'),
(@country_sk, 'Levice', 'Levice'),
(@country_sk, 'Námestovo', 'Namestovo'),
(@country_sk, 'Humenné', 'Humenne'),
(@country_sk, 'Skalica', 'Skalica'),
(@country_sk, 'Považská Bystrica', 'Povazska Bystrica'),
(@country_sk, 'Senec', 'Senec'),
(@country_sk, 'Pezinok', 'Pezinok'),
(@country_sk, 'Svidník', 'Svidnik'),
(@country_sk, 'Michalovce', 'Michalovce'),
(@country_sk, 'Mýtna', 'Mytna'),
(@country_sk, 'Malachov', 'Malachov'),
(@country_sk, 'Opatovce', 'Opatovce'),
(@country_sk, 'Brezová pod Bradlom', 'Brezova pod Bradlom'),
(@country_sk, 'Piešťany', 'Piestany'),
(@country_sk, 'Zemianske Kostoľany', 'Zemianske Kostolany'),
(@country_sk, 'Malé Leváre', 'Male Levare'),
(@country_sk, 'Štrbské Pleso', 'Strbske Pleso');

-- Města doplněná pro ověřený CZ/SK punkový seed.
INSERT INTO `{prefix}cities` (`country_id`, `name`, `name_ascii`)
SELECT @country_cz, 'Příbram', 'Pribram' WHERE NOT EXISTS (SELECT 1 FROM `{prefix}cities` WHERE `country_id` = @country_cz AND `name` = 'Příbram')
UNION ALL
SELECT @country_cz, 'Zábřeh na Moravě', 'Zabreh na Morave' WHERE NOT EXISTS (SELECT 1 FROM `{prefix}cities` WHERE `country_id` = @country_cz AND `name` = 'Zábřeh na Moravě')
UNION ALL
SELECT @country_cz, 'Ústí nad Labem', 'Usti nad Labem' WHERE NOT EXISTS (SELECT 1 FROM `{prefix}cities` WHERE `country_id` = @country_cz AND `name` = 'Ústí nad Labem')
UNION ALL
SELECT @country_sk, 'Moldava nad Bodvou', 'Moldava nad Bodvou' WHERE NOT EXISTS (SELECT 1 FROM `{prefix}cities` WHERE `country_id` = @country_sk AND `name` = 'Moldava nad Bodvou')
UNION ALL
SELECT @country_cz, 'Police nad Metují', 'Police nad Metuji' WHERE NOT EXISTS (SELECT 1 FROM `{prefix}cities` WHERE `country_id` = @country_cz AND `name` = 'Police nad Metují')
UNION ALL
SELECT @country_cz, 'Jindřichův Hradec', 'Jindrichuv Hradec' WHERE NOT EXISTS (SELECT 1 FROM `{prefix}cities` WHERE `country_id` = @country_cz AND `name` = 'Jindřichův Hradec')
UNION ALL
SELECT @country_cz, 'Kladno', 'Kladno' WHERE NOT EXISTS (SELECT 1 FROM `{prefix}cities` WHERE `country_id` = @country_cz AND `name` = 'Kladno')
UNION ALL
SELECT @country_cz, 'Velké Meziříčí', 'Velke Mezirici' WHERE NOT EXISTS (SELECT 1 FROM `{prefix}cities` WHERE `country_id` = @country_cz AND `name` = 'Velké Meziříčí')
UNION ALL
SELECT @country_cz, 'Benešov', 'Benesov' WHERE NOT EXISTS (SELECT 1 FROM `{prefix}cities` WHERE `country_id` = @country_cz AND `name` = 'Benešov')
UNION ALL
SELECT @country_cz, 'Odolena Voda', 'Odolena Voda' WHERE NOT EXISTS (SELECT 1 FROM `{prefix}cities` WHERE `country_id` = @country_cz AND `name` = 'Odolena Voda')
UNION ALL
SELECT @country_cz, 'Kardašova Řečice', 'Kardasova Recice' WHERE NOT EXISTS (SELECT 1 FROM `{prefix}cities` WHERE `country_id` = @country_cz AND `name` = 'Kardašova Řečice')
UNION ALL
SELECT @country_cz, 'Roudnice nad Labem', 'Roudnice nad Labem' WHERE NOT EXISTS (SELECT 1 FROM `{prefix}cities` WHERE `country_id` = @country_cz AND `name` = 'Roudnice nad Labem')
UNION ALL
SELECT @country_cz, 'Vsetín', 'Vsetin' WHERE NOT EXISTS (SELECT 1 FROM `{prefix}cities` WHERE `country_id` = @country_cz AND `name` = 'Vsetín')
UNION ALL
SELECT @country_cz, 'Horní Jiřetín', 'Horni Jiretin' WHERE NOT EXISTS (SELECT 1 FROM `{prefix}cities` WHERE `country_id` = @country_cz AND `name` = 'Horní Jiřetín')
UNION ALL
SELECT @country_cz, 'Svitavy', 'Svitavy' WHERE NOT EXISTS (SELECT 1 FROM `{prefix}cities` WHERE `country_id` = @country_cz AND `name` = 'Svitavy')
UNION ALL
SELECT @country_cz, 'Uherské Hradiště', 'Uherske Hradiste' WHERE NOT EXISTS (SELECT 1 FROM `{prefix}cities` WHERE `country_id` = @country_cz AND `name` = 'Uherské Hradiště')
UNION ALL
SELECT @country_cz, 'Kroměříž', 'Kromeriz' WHERE NOT EXISTS (SELECT 1 FROM `{prefix}cities` WHERE `country_id` = @country_cz AND `name` = 'Kroměříž')
UNION ALL
SELECT @country_cz, 'Litomyšl', 'Litomysl' WHERE NOT EXISTS (SELECT 1 FROM `{prefix}cities` WHERE `country_id` = @country_cz AND `name` = 'Litomyšl')
UNION ALL
SELECT @country_cz, 'Mariánské Lázně', 'Marianske Lazne' WHERE NOT EXISTS (SELECT 1 FROM `{prefix}cities` WHERE `country_id` = @country_cz AND `name` = 'Mariánské Lázně')
UNION ALL
SELECT @country_sk, 'Dubnica nad Váhom', 'Dubnica nad Vahom' WHERE NOT EXISTS (SELECT 1 FROM `{prefix}cities` WHERE `country_id` = @country_sk AND `name` = 'Dubnica nad Váhom')
UNION ALL
SELECT @country_cz, 'Mladá Boleslav', 'Mlada Boleslav' WHERE NOT EXISTS (SELECT 1 FROM `{prefix}cities` WHERE `country_id` = @country_cz AND `name` = 'Mladá Boleslav');
