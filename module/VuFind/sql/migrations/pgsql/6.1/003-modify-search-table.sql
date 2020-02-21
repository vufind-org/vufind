--
-- Modifications to table `search`
--

ALTER TABLE "search"
  ADD COLUMN notification_frequency int NOT NULL DEFAULT '0',
  ADD COLUMN last_notification_sent timestamp NOT NULL DEFAULT '2000-01-01 00:00:00',
  ADD COLUMN notification_base_url varchar(255) NOT NULL DEFAULT '';

CREATE INDEX notification_frequency_idx ON search (notification_frequency);
CREATE INDEX notification_base_url_idx ON search (notification_base_url);
