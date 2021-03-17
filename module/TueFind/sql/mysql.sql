CREATE TABLE IF NOT EXISTS tuefind_redirect (
    url VARCHAR(1000) NOT NULL,
    group_name VARCHAR(1000) DEFAULT NULL,
    timestamp TIMESTAMP DEFAULT NOW() NOT NULL
) DEFAULT CHARSET=utf8;


ALTER TABLE vufind.user ADD tuefind_subscribed_to_newsletter BOOL NOT NULL DEFAULT FALSE;
CREATE INDEX IF NOT EXISTS tuefind_subscribed_to_newsletter_index ON vufind.user (tuefind_subscribed_to_newsletter);


CREATE TABLE IF NOT EXISTS vufind.tuefind_rss_feeds (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    feed_name VARCHAR(200) NOT NULL,
    subsystem_types SET('krimdok', 'ixtheo', 'relbib') NOT NULL,
    feed_url VARCHAR(1000) NOT NULL,
    website_url VARCHAR(1000) NOT NULL,
    title_suppression_regex VARCHAR(200) DEFAULT NULL,
    strptime_format VARCHAR(50) DEFAULT NULL,
    downloader_time_limit INT NOT NULL DEFAULT 30,
    CONSTRAINT id_constraint UNIQUE (id),
    CONSTRAINT feed_name_constraint UNIQUE (feed_name),
    CONSTRAINT feed_url_constraint UNIQUE (feed_url(768)),
    CONSTRAINT website_url_constraint UNIQUE (website_url(768))
) DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;


CREATE TABLE IF NOT EXISTS vufind.tuefind_rss_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    rss_feeds_id INT UNSIGNED NOT NULL,
    item_id VARCHAR(768) NOT NULL,
    item_url VARCHAR(1000) NOT NULL,
    item_title VARCHAR(1000) NOT NULL,
    item_description MEDIUMTEXT NOT NULL,
    pub_date DATETIME NOT NULL,
    insertion_time TIMESTAMP DEFAULT NOW() NOT NULL,
    FOREIGN KEY (rss_feeds_id) REFERENCES vufind.tuefind_rss_feeds(id) ON DELETE CASCADE,
    CONSTRAINT id_constraint UNIQUE (id),
    CONSTRAINT tuefind_rss_items_item_id UNIQUE (item_id),
    INDEX tuefind_rss_items_item_id_index(item_id(768)),
    INDEX tuefind_rss_items_item_url_index(item_url(768)),
    INDEX tuefind_rss_items_pub_date_index(pub_date),
    INDEX tuefind_rss_items_insertion_time_index(insertion_time)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin;


CREATE TABLE IF NOT EXISTS vufind.tuefind_rss_subscriptions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    rss_feeds_id INT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    CONSTRAINT id_constraint UNIQUE (id),
    FOREIGN KEY (rss_feeds_id) REFERENCES tuefind_rss_feeds(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES vufind.user(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin;

ALTER TABLE vufind.user ADD tuefind_rss_feed_send_emails BOOLEAN NOT NULL DEFAULT FALSE;
CREATE INDEX tuefind_rss_feed_send_emails_index ON vufind.user (tuefind_rss_feed_send_emails);
ALTER TABLE vufind.user ADD tuefind_rss_feed_last_notification TIMESTAMP DEFAULT NOW();
