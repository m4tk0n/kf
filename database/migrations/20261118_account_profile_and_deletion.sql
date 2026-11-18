-- Run once on the K&F database before deploying the account profile routes.
-- Uses the table prefix configured in .env (replace __PREFIX__ with that value).
ALTER TABLE `__PREFIX__users`
  ADD COLUMN `display_name` VARCHAR(80) NULL AFTER `email`,
  ADD COLUMN `deletion_scheduled_at` DATETIME NULL DEFAULT NULL;
