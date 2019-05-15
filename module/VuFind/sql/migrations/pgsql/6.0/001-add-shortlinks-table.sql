--
-- Table structure for table shortlinks
--

CREATE TABLE shortlinks (
id SERIAL,
path text,
created timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (id)
);
