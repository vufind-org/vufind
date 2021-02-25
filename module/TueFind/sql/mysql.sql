CREATE TABLE tuefind_redirect (
    url VARCHAR(1000) NOT NULL,
    group_name VARCHAR(1000) DEFAULT NULL,
    timestamp TIMESTAMP DEFAULT NOW() NOT NULL
) DEFAULT CHARSET=utf8;

ALTER TABLE vufind.user ADD tuefind_subscribed_to_newsletter BOOL NOT NULL DEFAULT FALSE;
CREATE INDEX tuefind_subscribed_to_newsletter_index ON vufind.user (tuefind_subscribed_to_newsletter);

ALTER TABLE vufind.user ADD tuefind_uuid CHAR(36) NOT NULL;
ALTER TABLE vufind.user ADD CONSTRAINT tuefind_user_uuid UNIQUE (tuefind_uuid);

DELIMITER //
CREATE TRIGGER before_user_insert BEFORE INSERT ON vufind.user FOR EACH ROW IF NEW.tuefind_uuid IS NULL THEN SET NEW.tuefind_uuid = UUID(); END IF;//
DELIMITER ;
