ALTER TABLE `access_token` ADD COLUMN `expires` tinyint(1) NOT NULL DEFAULT '1';
ALTER TABLE `access_token` ADD KEY `access_token_expires_idx` (`expires`);
