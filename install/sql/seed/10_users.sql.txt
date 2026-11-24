-- install/sql/seed/10_users.sql
INSERT INTO `{prefix}users` (`id`, `provider`, `provider_uid`, `display_name`, `role`, `user_state`, `approved_count`, `created_at`)
VALUES ({ai_user_id}, 0, 'ai', '{ai_name}', 1, 1, 0, UNIX_TIMESTAMP());
