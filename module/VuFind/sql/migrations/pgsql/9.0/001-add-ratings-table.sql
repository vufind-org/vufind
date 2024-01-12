-- --------------------------------------------------------

--
-- Table structure for table ratings
--

DROP TABLE IF EXISTS "ratings";

CREATE TABLE ratings (
id SERIAL,
user_id int DEFAULT NULL,
resource_id int DEFAULT NULL,
rating int NOT NULL,
created timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (id)
);
CREATE INDEX ratings_user_id_idx ON ratings (user_id);
CREATE INDEX ratings_resource_id_idx ON ratings (resource_id);

ALTER TABLE ratings
ADD CONSTRAINT ratings_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL,
ADD CONSTRAINT ratings_ibfk_2 FOREIGN KEY (resource_id) REFERENCES resource (id) ON DELETE CASCADE;
