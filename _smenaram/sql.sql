CREATE TABLE IF NOT EXISTS smenaram (
    datum DATE PRIMARY KEY,
    osoba1 VARCHAR(10) DEFAULT NULL,
    osoba2 VARCHAR(10) DEFAULT NULL,
    osoba3 VARCHAR(10) DEFAULT NULL
);

-- Pokud tabulka uû existuje, spusù tento ¯·dek:
ALTER TABLE smenaram ADD COLUMN osoba3 VARCHAR(10) DEFAULT NULL AFTER osoba2;
