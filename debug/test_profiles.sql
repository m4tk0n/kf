-- debug/test_profiles.sql
-- Testovací identity. Provider 3 je interní a není dostupný přes OAuth.
-- Soubor se spouští výhradně samostatným tlačítkem v instalátoru po ověření user ID 2.

INSERT INTO `{prefix}users`
    (`provider`, `provider_uid`, `display_name`, `role`, `user_state`, `approved_count`, `created_at`)
VALUES
    (3, 'kf_test_user', 'User', 1, 1, 0, UNIX_TIMESTAMP()),
    (3, 'kf_test_blocked', 'User blokovaný', 1, 2, 0, UNIX_TIMESTAMP()),
    (3, 'kf_test_trusted', 'Důvěryhodný', 2, 1, 0, UNIX_TIMESTAMP()),
    (3, 'kf_test_moderator', 'Moderátor', 3, 1, 0, UNIX_TIMESTAMP()),
    (3, 'kf_test_admin', 'Administrátor', 4, 1, 0, UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE
    `display_name` = VALUES(`display_name`),
    `role` = VALUES(`role`),
    `user_state` = VALUES(`user_state`),
    `approved_count` = VALUES(`approved_count`);
