ALTER TABLE `oai_resumption`
  ADD COLUMN `token` varchar(255) DEFAULT NULL;

ALTER TABLE `oai_resumption` ADD UNIQUE KEY (`token`);
