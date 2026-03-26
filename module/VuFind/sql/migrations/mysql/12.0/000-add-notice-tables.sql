CREATE TABLE `notice` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `priority` int(11) NOT NULL DEFAULT 0,
  `position` varchar(50) DEFAULT NULL,
  `style` varchar(50) DEFAULT NULL,
  `content_type` varchar(50) NOT NULL DEFAULT 'text',
  `conditions` JSON DEFAULT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE `notice_translation` (
  `notice_id` int(11) NOT NULL,
  `language` varchar(50) NOT NULL,
  `content` text DEFAULT NULL,
  PRIMARY KEY (`notice_id`, `language`),
  KEY `notice_translation_notice_id_idx` (`notice_id`),
  CONSTRAINT `notice_translation_ibfk_1` FOREIGN KEY (`notice_id`) REFERENCES `notice` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;
