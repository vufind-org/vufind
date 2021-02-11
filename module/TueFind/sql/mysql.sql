CREATE TABLE IF NOT EXISTS tuefind_redirect (
    url VARCHAR(1000) NOT NULL,
    group_name VARCHAR(1000) DEFAULT NULL,
    timestamp TIMESTAMP DEFAULT NOW() NOT NULL
) DEFAULT CHARSET=utf8;


ALTER TABLE vufind.user ADD tuefind_subscribed_to_newsletter BOOL NOT NULL DEFAULT FALSE;
CREATE INDEX IF NOT EXISTS tuefind_subscribed_to_newsletter_index ON vufind.user (tuefind_subscribed_to_newsletter);


CREATE TABLE IF NOT EXISTS tuefind_rss_feeds (
    id INT NOT NULL AUTO_INCREMENT UNIQUE,
    feed_name VARCHAR(100) NOT NULL UNIQUE,
    subsystem_types SET('krimdok', 'ixtheo', 'relbib') NOT NULL,
    feed_url VARCHAR(1000) NOT NULL UNIQUE,
    blog_url VARCHAR(1000) NOT NULL UNIQUE,
    default_downloader_time_limit INT NOT NULL DEFAULT 30
) DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;


CREATE TABLE IF NOT EXISTS tuefind_rss_aggregator (
    rss_feeds_id INT NOT NULL,
    item_id VARCHAR(768) NOT NULL,
    item_url VARCHAR(1000) NOT NULL,
    item_title VARCHAR(1000) NOT NULL,
    item_description MEDIUMTEXT NOT NULL,
    serial_name VARCHAR(1000) NOT NULL,
    feed_url VARCHAR(1000) NOT NULL,
    pub_date DATETIME NOT NULL,
    insertion_time TIMESTAMP DEFAULT NOW() NOT NULL,
    FOREIGN KEY (rss_feeds_id) REFERENCES tuefind_rss_feeds(id) ON DELETE CASCADE,
    CONSTRAINT rss_aggregator_item_id UNIQUE (item_id),
    INDEX rss_aggregator_item_id_index(item_id(768)),
    INDEX rss_aggregator_item_url_index(item_url(768)),
    INDEX rss_aggregator_insertion_time_index(insertion_time)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin;


CREATE TABLE IF NOT EXISTS tuefind_rss_subscriptions (
    rss_feeds_id INT NOT NULL,
    user_id INT NOT NULL,
    FOREIGN KEY (rss_feeds_id) REFERENCES tuefind_rss_feeds(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin;
