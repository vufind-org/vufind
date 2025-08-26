CREATE TABLE `audit_event` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `type` varchar(50) NOT NULL,
  `subtype` varchar(50) NOT NULL,
  `user_id` int NULL,
  `session_id` varchar(128) NULL,
  `username` varchar(255) NULL,
  `client_ip` varchar(255) NULL,
  `server_ip` varchar(255) NULL,
  `server_name` varchar(255) NULL,
  `message`  varchar(255) NULL,
  `data` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_event_user_id_idx` (`user_id`),
  CONSTRAINT `audit_event_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
