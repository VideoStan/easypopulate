CREATE TABLE easypopulate_feeds (
  id int(3) NOT NULL AUTO_INCREMENT,
  name varchar(64),
  handler varchar(64),
  config text,
  last_run_data text,
  created datetime,
  modified datetime,
  UNIQUE KEY (name),
  PRIMARY KEY (id)
);
