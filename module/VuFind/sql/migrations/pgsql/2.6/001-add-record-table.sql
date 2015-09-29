--
-- Table structure for table `record`
--

CREATE TABLE record
(
  id serial NOT NULL,
  cache_id varchar(100) NOT NULL,
  record_id varchar(120),
  user_id integer,
  source varchar(50),
  version varchar(20) NOT NULL,
  data text,
  resource_id integer,
  updated timestamp without time zone,
  PRIMARY KEY (id),
  UNIQUE(cache_id)
);

GRANT ALL ON TABLE record TO postgres;
GRANT ALL ON TABLE record TO vufind;
 
GRANT ALL ON TABLE record_id_seq TO postgres;
GRANT ALL ON TABLE record_id_seq TO vufind;
