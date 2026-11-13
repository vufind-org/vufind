CREATE TABLE `content_page` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hrid` varchar(50) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `content_type` varchar(50) NOT NULL DEFAULT 'text',
  `conditions` JSON DEFAULT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `content_page_hrid_idx` (`hrid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE `content_page_translation` (
  `content_page_id` int(11) NOT NULL,
  `language` varchar(50) NOT NULL,
  `headline` varchar(255) DEFAULT NULL,
  `content` text DEFAULT NULL,
  PRIMARY KEY (`content_page_id`, `language`),
  KEY `content_page_translation_content_page_id_idx` (`content_page_id`),
  CONSTRAINT `content_page_translation_ibfk_1` FOREIGN KEY (`content_page_id`) REFERENCES `content_page` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE `custom_link` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `display_order` int(11) NOT NULL DEFAULT 0,
  `position` varchar(50) DEFAULT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'url',
  `page` varchar(50) DEFAULT NULL,
  `conditions` JSON DEFAULT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE `custom_link_translation` (
  `custom_link_id` int(11) NOT NULL,
  `language` varchar(50) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `url` text DEFAULT NULL,
  PRIMARY KEY (`custom_link_id`, `language`),
  KEY `custom_link_translation_custom_link_id_idx` (`custom_link_id`),
  CONSTRAINT `custom_link_translation_ibfk_1` FOREIGN KEY (`custom_link_id`) REFERENCES `custom_link` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;
