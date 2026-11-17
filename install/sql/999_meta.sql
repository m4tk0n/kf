-- Vytváří se jako poslední: existence této tabulky značí dokončenou instalaci.
CREATE TABLE IF NOT EXISTS `{p}meta` (
  `k` VARCHAR(32) NOT NULL,
  `v` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO `{p}meta` (`k`, `v`) VALUES ('schema', '1');
