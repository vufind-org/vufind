ALTER TABLE `resource`
  ADD COLUMN `display_title` VARCHAR(255) DEFAULT NULL AFTER `title`;
ALTER TABLE `resource`
  ADD COLUMN `updated` datetime NOT NULL DEFAULT '2000-01-01 00:00:00';

ALTER TABLE `resource` ADD KEY `resource_updated_idx` (`updated`);
