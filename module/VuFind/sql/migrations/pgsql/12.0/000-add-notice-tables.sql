CREATE TABLE notice (
  id SERIAL,
  enabled boolean NOT NULL DEFAULT '1',
  display_order int NOT NULL DEFAULT 0,
  position varchar(50) DEFAULT NULL,
  style varchar(50) DEFAULT NULL,
  content_type varchar(50) NOT NULL DEFAULT 'text',
  conditions JSON DEFAULT NULL,
  created timestamp NOT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE notice_translation (
  notice_id int NOT NULL,
  language varchar(50) NOT NULL,
  content text DEFAULT NULL,
  PRIMARY KEY (notice_id, language)
);
CREATE INDEX notice_translation_notice_id_idx ON notice_translation (notice_id);

ALTER TABLE notice_translation
ADD CONSTRAINT notice_translation_ibfk_1 FOREIGN KEY (notice_id) REFERENCES "notice" (id) ON DELETE CASCADE;

