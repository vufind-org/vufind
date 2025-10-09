CREATE TABLE IF NOT EXISTS `api_key` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `revoked` tinyint(1) NOT NULL DEFAULT 0,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `api_key_user_id_idx` (`user_id`),
  KEY `api_key_token_idx` (`token`),
  KEY `api_key_created_idx` (`created`),
  KEY `api_key_revoked_idx` (`revoked`),
  KEY `api_key_last_used_idx` (`last_used`),
  CONSTRAINT `api_key_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;
