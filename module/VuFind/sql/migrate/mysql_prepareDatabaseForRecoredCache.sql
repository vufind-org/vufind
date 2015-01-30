--
-- Table structure for table `record`
--

DROP TABLE IF EXISTS `record`;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `record` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `c_id` varchar(100) NOT NULL,
  `record_id` varchar(200) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `source` varchar(50) DEFAULT NULL,
  `data` text DEFAULT NULL,
  `resource_id` int(11) NOT NULL DEFAULT '0',
  `updated` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `expires` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `record_id` (`c_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;