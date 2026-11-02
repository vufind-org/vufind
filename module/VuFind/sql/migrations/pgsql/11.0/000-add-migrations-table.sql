CREATE TABLE migrations (
  id SERIAL,
  name varchar(255),
  status varchar(50) NOT NULL,
  target_version varchar(50) NOT NULL,
  date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
);
