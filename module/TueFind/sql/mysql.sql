CREATE TABLE tuefind_redirect (
    url VARCHAR(1000) NOT NULL,
    group_name VARCHAR(1000) DEFAULT NULL,
    timestamp TIMESTAMP DEFAULT NOW() NOT NULL
) DEFAULT CHARSET=utf8;

ALTER TABLE vufind.user ADD tuefind_subscribed_to_newsletter BOOL NOT NULL DEFAULT FALSE;
CREATE INDEX tuefind_subscribed_to_newsletter_index ON vufind.user (tuefind_subscribed_to_newsletter);
