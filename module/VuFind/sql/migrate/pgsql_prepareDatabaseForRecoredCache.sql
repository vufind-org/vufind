--
-- Table structure for table `record`
--

DROP TABLE IF EXISTS "record";

CREATE TABLE record
(
  id serial NOT NULL,
  c_id varchar(100),
  record_id varchar,
  user_id integer,
  session_id varchar,
  source varchar,
  version integer NOT NULL DEFAULT 1,
  data text,
  resource_id integer,
  updated timestamp without time zone,
  expires timestamp without time zone,
  PRIMARY KEY (id),
  UNIQUE(c_id)
);

GRANT ALL ON TABLE record TO postgres;
GRANT ALL ON TABLE record TO vufind;
 
GRANT ALL ON TABLE record_id_seq TO postgres;
GRANT ALL ON TABLE record_id_seq TO vufind;