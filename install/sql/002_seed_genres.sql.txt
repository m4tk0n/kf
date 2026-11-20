-- Základní hudební žánry a podžánry pro mezinárodní katalog K&F.
-- Slug je stabilní identifikátor; INSERT IGNORE umožňuje bezpečné opakované spuštění.

INSERT IGNORE INTO `{p}genres` (`name`, `slug`) VALUES
('Rock','rock'),('Pop','pop'),('Alternative','alternative'),('Indie','indie'),
('Punk','punk'),('Hardcore','hardcore'),('Hard Rock','hard-rock'),
('Progressive Rock','progressive-rock'),('Psychedelic Rock','psychedelic-rock'),('Post-rock','post-rock'),
('Metal','metal'),('Heavy Metal','heavy-metal'),('Thrash Metal','thrash-metal'),
('Death Metal','death-metal'),('Black Metal','black-metal'),('Doom Metal','doom-metal'),('Metalcore','metalcore'),
('Folk','folk'),('Country','country'),('Blues','blues'),('Jazz','jazz'),
('Funk','funk'),('Soul','soul'),('R&B','r-b'),('Hip-hop','hip-hop'),('Rap','rap'),
('Electronic','electronic'),('Techno','techno'),('House','house'),('Trance','trance'),
('Drum and Bass','drum-and-bass'),('Dubstep','dubstep'),('Ambient','ambient'),
('Synthpop','synthpop'),('Industrial','industrial'),('Gothic','gothic'),
('Reggae','reggae'),('Ska','ska'),('Disco','disco'),('Classical','classical'),
('Latin','latin'),('World music','world-music'),('Singer-songwriter','singer-songwriter'),('Schlager','schlager'),

-- Rock, alternative a indie
('Classic Rock','classic-rock'),('Soft Rock','soft-rock'),('Southern Rock','southern-rock'),
('Blues Rock','blues-rock'),('Garage Rock','garage-rock'),('Glam Rock','glam-rock'),
('Art Rock','art-rock'),('Krautrock','krautrock'),('Space Rock','space-rock'),
('Stoner Rock','stoner-rock'),('Noise Rock','noise-rock'),('Math Rock','math-rock'),
('Grunge','grunge'),('Britpop','britpop'),('Shoegaze','shoegaze'),('Dream Pop','dream-pop'),
('Indie Rock','indie-rock'),('Indie Pop','indie-pop'),('Post-punk','post-punk'),
('New Wave','new-wave'),('Post-hardcore','post-hardcore'),('Emo','emo'),
('Industrial Rock','industrial-rock'),('Gothic Rock','gothic-rock'),('Darkwave','darkwave'),

-- Punk a hardcore
('Hardcore Punk','hardcore-punk'),('Pop Punk','pop-punk'),('Skate Punk','skate-punk'),
('Street Punk','street-punk'),('Crust Punk','crust-punk'),('Anarcho-punk','anarcho-punk'),
('Ska Punk','ska-punk'),('Post-punk Revival','post-punk-revival'),

-- Metal
('Power Metal','power-metal'),('Progressive Metal','progressive-metal'),
('Symphonic Metal','symphonic-metal'),('Folk Metal','folk-metal'),('Gothic Metal','gothic-metal'),
('Nu Metal','nu-metal'),('Groove Metal','groove-metal'),('Sludge Metal','sludge-metal'),
('Stoner Metal','stoner-metal'),('Speed Metal','speed-metal'),('Glam Metal','glam-metal'),
('Industrial Metal','industrial-metal'),('Djent','djent'),('Deathcore','deathcore'),
('Melodic Death Metal','melodic-death-metal'),('Technical Death Metal','technical-death-metal'),
('Blackgaze','blackgaze'),('Metallic Hardcore','metallic-hardcore'),('Mathcore','mathcore'),

-- Elektronika a taneční hudba
('Electronica','electronica'),('EDM','edm'),('Minimal Techno','minimal-techno'),
('Acid Techno','acid-techno'),('Hard Techno','hard-techno'),('Melodic Techno','melodic-techno'),
('Deep House','deep-house'),('Progressive House','progressive-house'),('Tech House','tech-house'),
('Acid House','acid-house'),('Electro House','electro-house'),('Electro','electro'),
('Breakbeat','breakbeat'),('Big Beat','big-beat'),('Jungle','jungle'),
('Liquid Drum and Bass','liquid-drum-and-bass'),('Neurofunk','neurofunk'),
('UK Garage','uk-garage'),('2-step','2-step'),('Bassline','bassline'),
('Trip-hop','trip-hop'),('Downtempo','downtempo'),('Chillout','chillout'),
('IDM','idm'),('Hardstyle','hardstyle'),('Hardcore Techno','hardcore-techno'),
('Eurodance','eurodance'),('Synthwave','synthwave'),('Vaporwave','vaporwave'),
('EBM','ebm'),('Industrial Dance','industrial-dance'),

-- Hip-hop, soul a příbuzné styly
('Trap','trap'),('Drill','drill'),('Grime','grime'),('Boom Bap','boom-bap'),
('Conscious Hip-hop','conscious-hip-hop'),('Alternative Hip-hop','alternative-hip-hop'),
('Cloud Rap','cloud-rap'),('Phonk','phonk'),('New Jack Swing','new-jack-swing'),
('Contemporary R&B','contemporary-r-b'),('Neo Soul','neo-soul'),('Gospel','gospel'),

-- Jazz, blues a příbuzné styly
('Bebop','bebop'),('Swing','swing'),('Cool Jazz','cool-jazz'),
('Free Jazz','free-jazz'),('Jazz Fusion','jazz-fusion'),('Smooth Jazz','smooth-jazz'),
('Latin Jazz','latin-jazz'),('Gypsy Jazz','gypsy-jazz'),('Delta Blues','delta-blues'),
('Chicago Blues','chicago-blues'),

-- Folk, country, reggae a world music
('Bluegrass','bluegrass'),('Americana','americana'),('Alt-country','alt-country'),
('Celtic Folk','celtic-folk'),('Traditional Folk','traditional-folk'),('Indie Folk','indie-folk'),
('Neofolk','neofolk'),('Cajun','cajun'),('Zydeco','zydeco'),
('Afrobeat','afrobeat'),('Afrobeats','afrobeats'),('Highlife','highlife'),
('Bossa Nova','bossa-nova'),('Samba','samba'),('Salsa','salsa'),
('Merengue','merengue'),('Cumbia','cumbia'),('Reggaeton','reggaeton'),
('Dancehall','dancehall'),('Dub','dub'),('Rocksteady','rocksteady'),
('Roots Reggae','roots-reggae'),('K-pop','k-pop'),('J-pop','j-pop'),

-- Klasika a další koncertní formy
('Opera','opera'),('Operetta','operetta'),('Chamber Music','chamber-music'),
('Orchestral','orchestral'),('Contemporary Classical','contemporary-classical'),
('Musical Theatre','musical-theatre'),('Soundtrack','soundtrack'),
('Experimental','experimental'),('Acoustic','acoustic'),('A cappella','a-cappella');
