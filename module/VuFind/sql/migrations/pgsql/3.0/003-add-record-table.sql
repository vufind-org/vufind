--
-- Table structure for table record
--

CREATE TABLE record (
  id serial NOT NULL,
  record_id varchar(255),
  source varchar(50),
  version varchar(20) NOT NULL,
  data text,
  updated timestamp without time zone,
  PRIMARY KEY (id),
  UNIQUE(record_id, source)
);

GRANT ALL ON TABLE record TO postgres;
GRANT ALL ON TABLE record TO vufind;
 
GRANT ALL ON TABLE record_id_seq TO postgres;
GRANT ALL ON TABLE record_id_seq TO vufind;
