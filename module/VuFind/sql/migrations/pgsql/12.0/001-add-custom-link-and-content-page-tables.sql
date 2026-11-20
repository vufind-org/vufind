CREATE TABLE content_page (
  id SERIAL,
  hrid varchar(50) NOT NULL,
  enabled boolean NOT NULL DEFAULT '1',
  content_type varchar(50) NOT NULL DEFAULT 'text',
  conditions JSON DEFAULT NULL,
  created timestamp NOT NULL,
  PRIMARY KEY (id)
);
CREATE UNIQUE INDEX content_page_hrid_idx ON content_page (hrid);

CREATE TABLE content_page_translation (
  content_page_id int NOT NULL,
  language varchar(50) NOT NULL,
  headline varchar(255) DEFAULT NULL,
  content text DEFAULT NULL,
  PRIMARY KEY (content_page_id, language)
);
CREATE INDEX content_page_translation_content_page_id_idx ON content_page_translation (content_page_id);

CREATE TABLE custom_link (
  id SERIAL,
  enabled boolean NOT NULL DEFAULT '1',
  display_order int NOT NULL DEFAULT 0,
  position varchar(50) DEFAULT NULL,
  type varchar(50) NOT NULL DEFAULT 'url',
  page varchar(50) DEFAULT NULL,
  conditions JSON DEFAULT NULL,
  created timestamp NOT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE custom_link_translation (
  custom_link_id int NOT NULL,
  language varchar(50) NOT NULL,
  title varchar(255) DEFAULT NULL,
  url text DEFAULT NULL,
  PRIMARY KEY (custom_link_id, language)
);
CREATE INDEX custom_link_translation_custom_link_id_idx ON custom_link_translation (custom_link_id);

ALTER TABLE content_page_translation
ADD CONSTRAINT content_page_translation_ibfk_1 FOREIGN KEY (content_page_id) REFERENCES "content_page" (id) ON DELETE CASCADE;

ALTER TABLE custom_link_translation
ADD CONSTRAINT custom_link_translation_ibfk_1 FOREIGN KEY (custom_link_id) REFERENCES "custom_link" (id) ON DELETE CASCADE;
