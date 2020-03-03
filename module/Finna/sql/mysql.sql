/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Additional columns for comments
--
ALTER TABLE comments ADD COLUMN `finna_visible` tinyint(1) DEFAULT '1';
ALTER TABLE comments ADD COLUMN `finna_rating` float DEFAULT NULL;
ALTER TABLE comments ADD COLUMN `finna_type` tinyint(1) DEFAULT '0' NOT NULL;
ALTER TABLE comments ADD COLUMN `finna_updated` datetime DEFAULT NULL;
ALTER TABLE comments ADD INDEX `finna_visible` (`finna_visible`);
ALTER TABLE comments ADD INDEX `finna_rating` (`finna_rating`);
--
-- Additional columns for user
--
ALTER TABLE `user` ADD COLUMN `finna_due_date_reminder` int(11) NOT NULL DEFAULT 0;
ALTER TABLE `user` ADD COLUMN `finna_last_expiration_reminder` datetime NOT NULL DEFAULT '2000-01-01 00:00:00';
ALTER TABLE `user` ADD COLUMN `finna_nickname` varchar(255) DEFAULT NULL UNIQUE;
CREATE INDEX `finna_user_due_date_reminder_key` ON user (`finna_due_date_reminder`);
CREATE INDEX `finna_user_email` ON user (`email`);

--
-- Additional columns for user_card
--
ALTER TABLE `user_card` ADD COLUMN `finna_due_date_reminder` int(11) NOT NULL DEFAULT 0;
-- To initialize: UPDATE user_card SET finna_due_date_reminder=(SELECT finna_due_date_reminder FROM user WHERE user.id=user_card.user_id);

--
-- Additional columns for user_list
--
ALTER TABLE user_list ADD COLUMN `finna_updated` datetime DEFAULT NULL;

--
-- Additional columns for user_resource
--
ALTER TABLE `user_resource` ADD COLUMN `finna_custom_order_index` int DEFAULT NULL;

--
-- Additional tables
--
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `finna_comments_record` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `record_id` varchar(255) NOT NULL,
  `comment_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `comment_id` (`comment_id`),
  KEY `key_record_id` (`record_id`),
  CONSTRAINT `comments_record_ibfk_1` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `finna_comments_inappropriate` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `comment_id` int(11) NOT NULL,
  `created` datetime NOT NULL DEFAULT '2000-01-01 00:00:00',
  `reason` varchar(1000) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `comment_id` (`comment_id`),
  CONSTRAINT `finna_comments_inappropriate_ibfk_1` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

CREATE TABLE `finna_due_date_reminder` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `loan_id` varchar(255) NOT NULL,
  `due_date` datetime NOT NULL,
  `notification_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_loan` (`user_id`,`loan_id`),
  CONSTRAINT `due_date_reminder_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `finna_transaction` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `driver` varchar(255) NOT NULL,
  `amount` int(11) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'EUR',
  `transaction_fee` int(11) NOT NULL,
  `created` datetime NOT NULL DEFAULT '2000-01-01 00:00:00',
  `paid` datetime NOT NULL DEFAULT '2000-01-01 00:00:00',
  `registered` datetime NOT NULL DEFAULT '2000-01-01 00:00:00',
  `complete` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(255) DEFAULT '',
  `cat_username` varchar(50) NOT NULL,
  `reported` datetime NOT NULL DEFAULT '2000-01-01 00:00:00',
  PRIMARY KEY (`id`),
  KEY `transaction_id` (`transaction_id`),
  KEY `complete_cat_username_created` (`complete`,`cat_username`, `created`),
  KEY `paid_reported` (`paid`,`reported`),
  KEY `driver` (`driver`),
  CONSTRAINT `finna_transactions_ibfk1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `finna_fee` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT '',
  `type` varchar(255) NOT NULL DEFAULT '',
  `amount` float NOT NULL DEFAULT '0',
  `currency` varchar(3) NOT NULL DEFAULT 'EUR',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `finna_fee_ibfk1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
  CONSTRAINT `finna_fee_ibfk2` FOREIGN KEY (`transaction_id`) REFERENCES `finna_transaction` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `finna_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `resource_id` varchar(255) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `mtime` int(11) NOT NULL,
  `data` longblob,
  PRIMARY KEY (`id`),
  UNIQUE KEY `resource_id` (`resource_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `finna_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT null,
  `ui_url` varchar(255) NOT NULL,
  `form` varchar(255) NOT NULL,
  `message_json` json DEFAULT '',
  `message` longtext DEFAULT '',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` varchar(255) NOT NULL DEFAULT 'open',
  `modifier_id` int(11) DEFAULT NULL,
  `modification_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `url_status` (`ui_url`, `status`),
  KEY `form` (`form`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
