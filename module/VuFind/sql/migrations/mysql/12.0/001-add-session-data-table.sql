CREATE TABLE `session_data` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `session_id` varchar(128) NOT NULL,
  `type` varchar(50) NOT NULL,
  `data` JSON NOT NULL,
  PRIMARY KEY (`id`),
  KEY `session_id_idx` (`session_id`),
  UNIQUE KEY `session_data_session_id_type_index` (`session_id`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;
